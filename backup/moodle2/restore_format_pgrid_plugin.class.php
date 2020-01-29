<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Specialised restore for format_pgrid
 *
 * @package   format_pgrid
 * @category  backup
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Specialised restore for format_pgrid
 *
 * Processes 'numsections' from the old backup files and hides sections that used to be "orphaned".
 * Processes 'layoutstructure' from the old backup files and sets the equivilent 'baseclass'.
 *
 * @package   format_pgrid
 * @category  backup
 * @copyright 2017 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_pgrid_plugin extends restore_format_plugin {

    /** @var int */
    protected $originalnumsections = 0;

    /**
     * Checks if backup file was made on Moodle before 3.3 and we should respect the 'numsections'
     * and potential "orphaned" sections in the end of the course.
     *
     * @return bool
     */
    protected function need_restore_numsections() {
        $backupinfo = $this->step->get_task()->get_info();
        $backuprelease = $backupinfo->backup_release;
        return version_compare($backuprelease, '3.3', 'lt');
    }

    /**
     * Creates a dummy path element in order to be able to execute code after restore
     *
     * @return restore_path_element[]
     */
    public function define_course_plugin_structure() {
        global $DB;

        // Since this method is executed before the restore we can do some pre-checks here.
        // In case of merging backup into existing course find the current number of sections.
        $target = $this->step->get_task()->get_target();
        if (($target == backup::TARGET_CURRENT_ADDING || $target == backup::TARGET_EXISTING_ADDING) &&
                $this->need_restore_numsections()) {
            $maxsection = $DB->get_field_sql(
                'SELECT max(section) FROM {course_sections} WHERE course = ?',
                [$this->step->get_task()->get_courseid()]);
            $this->originalnumsections = (int)$maxsection;
        }

        // Dummy path element is needed in order for after_restore_course() to be called.
        return [new restore_path_element('dummy_course', $this->get_pathfor('/dummycourse'))];
    }

    /**
     * Dummy process method
     */
    public function process_dummy_course() {

    }

    /**
     * Handles setting the automatic end date for a restored course.
     *
     * @param int $enddate The end date in the backup file.
     */
    protected function update_automatic_end_date($enddate) {
        global $DB;

        // At this stage the 'course_format_options' table will already have a value set for this option as it is
        // part of the course format and the default will have been set.
        // Get the current course format option.
        $params = array(
            'courseid' => $this->step->get_task()->get_courseid(),
            'format' => 'pgrid',
            'sectionid' => 0,
            'name' => 'automaticenddate'
        );
        $cfoid = $DB->get_field('course_format_options', 'id', $params);

        $update = new stdClass();
        $update->id = $cfoid;

        if (empty($enddate)) {
            $update->value = 1;
            $DB->update_record('course_format_options', $update);

            // Now, let's update the course end date.
            format_pgrid::format_weeks_update_end_date($this->step->get_task()->get_courseid());
        } else {
            $update->value = 0;
            $DB->update_record('course_format_options', $update);

            // The end date may have been changed by observers during restore, ensure we set it back to what was in the backup.
            $DB->set_field('course', 'enddate', $enddate, array('id' => $this->step->get_task()->get_courseid()));
        }
    }    

    /**
     * Executed after course restore is complete
     *
     * This method is only executed if course configuration was overridden
     */
    public function after_restore_course() {
        global $DB;

        if (!$this->need_restore_numsections()) {
            // Backup file was made in Moodle 3.3 or later, we don't need to process 'numsecitons'.
            return;
        }

        $data = $this->connectionpoint->get_data();
        $backupinfo = $this->step->get_task()->get_info();

        if ($backupinfo->original_course_format !== 'topics' || !isset($data['tags']['numsections'])) {
            // Backup from another course format or backup file does not even have 'numsections'.
            return;
        }

        $numsections = (int)$data['tags']['numsections'];

        foreach ($backupinfo->sections as $key => $section) {
            // For each section from the backup file check if it was restored and if was "orphaned" in the original
            // course and mark it as hidden. This will leave all activities in it visible and available just as it was
            // in the original course.
            // Exception is when we restore with merging and the course already had a section with this section number,
            // in this case we don't modify the visibility.
            if ($this->step->get_task()->get_setting_value($key . '_included')) {
                $sectionnum = (int)$section->title;
                if ($sectionnum > $numsections && $sectionnum > $this->originalnumsections) {
                    $DB->execute("UPDATE {course_sections} SET visible = 0 WHERE course = ? AND section = ?",
                        [$this->step->get_task()->get_courseid(), $sectionnum]);
                }
            }
        }

        // Backup may not include the end date, so set it to 0.
        $enddate = isset($data['tags']['enddate']) ? $data['tags']['enddate'] : 0;

        // Set the relevant baseclass if this is an old backup with the setting 'layoutstructure'.
        // Set the automatic end date if applicable.
        if ($backupinfo->original_course_format === 'pgrid' && isset($data['tags']['layoutstructure'])) {
            if ($data['tags']['layoutstructure'] == 2) {
                // Set the automatic end date setting and the course end date (if applicable).
                $this->update_automatic_end_date($enddate);

            } else if ($data['tags']['layoutstructure'] == 3 || $data['tags']['layoutstructure'] == 5) {
                $DB->execute("UPDATE {course_format_options} SET value = 2 WHERE courseid = ? AND format = 'pgrid' AND name = 'baseclass'",
                        [$this->step->get_task()->get_courseid()]);

                // Set the automatic end date setting and the course end date (if applicable).
                $this->update_automatic_end_date($enddate);

            } else if ($data['tags']['layoutstructure'] == 4) {
                $DB->execute("UPDATE {course_format_options} SET value = 1 WHERE courseid = ? AND format = 'pgrid' AND name = 'baseclass'",
                        [$this->step->get_task()->get_courseid()]);
            } 
        }

        // Set the automatic end date if this is a 3.4 backup and applicable.
        if ($backupinfo->original_course_format === 'pgrid' && isset($data['tags']['baseclass'])) {
            if ($data['tags']['baseclass'] == 2) {
                // Set the automatic end date setting and the course end date (if applicable).
                $this->update_automatic_end_date($enddate);
            }
        }
    }
}


            

            