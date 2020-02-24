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
 * format_pgrid unit tests for upgradelib
 *
 * @package    format_pgrid
 * @copyright  2020 CAPDM Ltd (https://www.capdm.com)
 * @copyright  based on work by 2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/pgrid/db/upgradelib.php');

/**
 * format_pgrid unit tests for upgradelib
 *
 * @package    format_pgrid
 * @copyright  2020 CAPDM Ltd (https://www.capdm.com)
 * @copyright  based on work by 2017 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_pgrid_upgrade_testcase extends advanced_testcase {

    /**
     * Test upgrade step to remove orphaned sections.
     */
    public function test_numsections_no_actions() {
        global $DB;

        $this->resetAfterTest(true);

        $params = array('format' => 'pgrid', 'numsections' => 5, 'startdate' => 1445644800);
        $course = $this->getDataGenerator()->create_course($params);
        // This test is executed after 'numsections' option was already removed, add it manually.
        $DB->insert_record('course_format_options', ['courseid' => $course->id, 'format' => 'pgrid',
            'sectionid' => 0, 'name' => 'numsections', 'value' => '5']);

        // There are 6 sections in the course (0-section and sections 1, ... 5).
        $this->assertEquals(6, $DB->count_records('course_sections', ['course' => $course->id]));

        format_pgrid_upgrade_remove_numsections();

        // There are still 6 sections in the course.
        $this->assertEquals(6, $DB->count_records('course_sections', ['course' => $course->id]));

    }

    /**
     * Test upgrade step to remove orphaned sections.
     */
    public function test_numsections_delete_empty() {
        global $DB;

        $this->resetAfterTest(true);

        // Set default number of sections to 10.
        set_config('numsections', 10, 'moodlecourse');

        $params1 = array('format' => 'pgrid', 'numsections' => 5, 'startdate' => 1445644800);
        $course1 = $this->getDataGenerator()->create_course($params1);
        $params2 = array('format' => 'pgrid', 'numsections' => 20, 'startdate' => 1445644800);
        $course2 = $this->getDataGenerator()->create_course($params2);
        // This test is executed after 'numsections' option was already removed, add it manually and
        // set it to be 2 less than actual number of sections.
        $DB->insert_record('course_format_options', ['courseid' => $course1->id, 'format' => 'pgrid',
            'sectionid' => 0, 'name' => 'numsections', 'value' => '3']);

        // There are 6 sections in the first course (0-section and sections 1, ... 5).
        $this->assertEquals(6, $DB->count_records('course_sections', ['course' => $course1->id]));
        // There are 21 sections in the second course.
        $this->assertEquals(21, $DB->count_records('course_sections', ['course' => $course2->id]));

        format_pgrid_upgrade_remove_numsections();

        // Two sections were deleted in the first course.
        $this->assertEquals(4, $DB->count_records('course_sections', ['course' => $course1->id]));
        // The second course was reset to 11 sections (default plus 0-section).
        $this->assertEquals(11, $DB->count_records('course_sections', ['course' => $course2->id]));

    }

    /**
     * Test upgrade step to remove orphaned sections.
     */
    public function test_numsections_hide_non_empty() {
        global $DB;

        $this->resetAfterTest(true);

        $params = array('format' => 'pgrid', 'numsections' => 5, 'startdate' => 1445644800);
        $course = $this->getDataGenerator()->create_course($params);

        // Add a module to the second last section.
        $cm = $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'section' => 4]);

        // This test is executed after 'numsections' option was already removed, add it manually and
        // set it to be 2 less than actual number of sections.
        $DB->insert_record('course_format_options', ['courseid' => $course->id, 'format' => 'pgrid',
            'sectionid' => 0, 'name' => 'numsections', 'value' => '3']);

        // There are 6 sections.
        $this->assertEquals(6, $DB->count_records('course_sections', ['course' => $course->id]));

        format_pgrid_upgrade_remove_numsections();

        // One section was deleted and one hidden.
        $this->assertEquals(5, $DB->count_records('course_sections', ['course' => $course->id]));
        $this->assertEquals(0, $DB->get_field('course_sections', 'visible', ['course' => $course->id, 'section' => 4]));
        // The module is still visible.
        $this->assertEquals(1, $DB->get_field('course_modules', 'visible', ['id' => $cm->cmid]));
    }

    /**
     * Test upgrade step to add empty sections.
     */
    public function test_numsections_add_empty_sections() {
        global $DB;

        $this->resetAfterTest(true);

        $params = array('format' => 'pgrid', 'numsections' => 16, 'startdate' => 1445644800);
        $course = $this->getDataGenerator()->create_course($params);

        // This test is executed after 'numsections' option was already removed.
        // Set the 'numsections' course format value to 18, simulating the scenario in which there are fewer real sections.
        $DB->insert_record('course_format_options', ['courseid' => $course->id, 'format' => 'pgrid',
            'sectionid' => 0, 'name' => 'numsections', 'value' => '18']);

        // There are 16 sections.
        $this->assertEquals(17, $DB->count_records('course_sections', ['course' => $course->id]));

        format_pgrid_upgrade_remove_numsections();

        // Confirm that the upgrade method added the missing empty sections.
        $this->assertEquals(19, $DB->count_records('course_sections', ['course' => $course->id]));
    }
}
