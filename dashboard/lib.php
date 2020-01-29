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
 * lib.php for format_pgrid.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class format_pgrid_dashboard {

    private $elements;
    private $modfullnames;
    private $ltitypes;

    public function __construct() {
        global $COURSE;

        $this->elements = [
            'readinglists',
            'libguides', 
            'timetable', 
            'graderreport', 
            'calendar', 
            'students',
            'lecturers',
            'courseofficers',
            'media'
        ];

        $this->modfullnames = self::format_pgrid_get_modfullnames($COURSE);
        $this->ltitypes = self::format_pgrid_get_ltitypes($COURSE);
    }

    public function set_dashboard_options(&$courseformatoptions) {
        global $DB, $COURSE;

        $dashboardoptions = [];

        foreach ($this->elements as $element) {
            $dashboardoptions['show' . $element] = [
                'default' => get_config('format_pgrid', 'defaultshow' . $element),
                'type' => PARAM_INT,
            ];
        }

        foreach ($this->modfullnames as $mod => $modplural) {
            $dashboardoptions['show' . $mod] = [
                'default' => 2,
                'type' => PARAM_INT,
            ];
        }

        foreach ($this->ltitypes as $typeid => $name) {
            $dashboardoptions['showltitype' . $typeid] = [
                'default' => 2,
                'type' => PARAM_INT,
            ];
        }

        $dashboardoptions['quicklinksequence'] = [
            'default' => join(',', $this->elements),
            'type' => PARAM_RAW,
        ];

        $dashboardoptions['activitylinksequence'] = [
            'default' => '',
            'type' => PARAM_RAW,
        ];

        $dashboardoptions['selectmoduleleaders'] = [
            'default' => null,
            'type' => PARAM_RAW,
        ];        

        $courseformatoptions = $courseformatoptions + $dashboardoptions;
    }

    public function set_dashboard_edit_options(&$courseformatoptionsedit) {
        global $DB, $COURSE;

        $dashboardoptionsedit = [];
        $coursecontext = context_course::instance($COURSE->id);
        
        foreach ($this->elements as $element) {
            $courseformatoptionsedit['show' . $element] = [
                'label' => new lang_string('setshow' . $element, 'format_pgrid'),
                'help' => 'setshow' . $element,
                'help_component' => 'format_pgrid',
                'element_type' => 'select',
                'element_attributes' => [
                    [
                        1 => new lang_string('no'),
                        2 => new lang_string('yes')
                    ]
                ]
            ];
        }

        foreach ($this->modfullnames as $mod => $modplural) {
            $courseformatoptionsedit['show' . $mod] = [
                'label' => new lang_string('setshowmodname', 'format_pgrid', $modplural),
                'help' => 'setshowmod',
                'help_component' => 'format_pgrid',
                'element_type' => 'select',
                'element_attributes' => [
                    [
                        1 => new lang_string('no'),
                        2 => new lang_string('yes')
                    ]
                ]
            ];
        }

        foreach ($this->ltitypes as $typeid => $name) {
            $courseformatoptionsedit['showltitype' . $typeid] = [
                'label' => new lang_string('setshowmodname', 'format_pgrid', $name),
                'help' => 'setshowmod',
                'help_component' => 'format_pgrid',
                'element_type' => 'select',
                'element_attributes' => [
                    [
                        1 => new lang_string('no'),
                        2 => new lang_string('yes')
                    ]
                ]
            ];
        }

        // Get all the lecturers.
        $lecturers = [];
        $lecturerrole = $DB->get_record('role', ['shortname' => 'lecturer']);

        if ($lecturerrole) {
            $lecturers = get_role_users($lecturerrole->id, $coursecontext);
        }

        // Create a multi select box?
        $selectbox = [];

        if (count($lecturers)) {
            foreach ($lecturers as $lecturer) {
                $selectbox[$lecturer->id] = fullname($lecturer);
            }
        } else {
            $selectbox[0] = get_string('nolecturers', 'format_pgrid');
        }

        $dashboardoptionsedit['quicklinksequence'] = [
            'label' => '',
            'element_type' => 'hidden'
        ];

        $dashboardoptionsedit['activitylinksequence'] = [
            'label' => '',
            'element_type' => 'hidden'
        ];

        $dashboardoptionsedit['selectmoduleleaders'] = [
            'label' => new lang_string('setselectmoduleleaders', 'format_pgrid'),
            'help' => 'setselectmoduleleaders',
            'help_component' => 'format_pgrid',
            'element_type' => 'select',
            'element_attributes' => [
                $selectbox,
                ['multiple' => 'multiple', 'size' => 6]
            ]
        ];

        $courseformatoptionsedit = $courseformatoptionsedit + $dashboardoptionsedit;
    }

   /**
     * Updates format options for a course
     *
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_dashboard_options($data, $oldcourse = null) {
        // Convert the form array to a string to enable saving to course_format_options table.
        // Without this, an error is thrown:
        // Warning: mysqli::real_escape_string() expects parameter 1 to be string, array given
        if (isset($data->selectmoduleleaders) && is_array($data->selectmoduleleaders)) {
            $data->selectmoduleleaders = join(',', $data->selectmoduleleaders);
        }

        return $data;
    }    

    /**
     * TODO
     */
    static function format_pgrid_get_modfullnames($course) {
        $modinfo = get_array_of_activities($course->id);
        $plurals = get_module_types_names(true);

        $modfullnames = [];
        $archetypes   = [];

        foreach($modinfo as $cm) {
            if ($cm->mod == 'lti') {
                continue;
            }

            if (array_key_exists($cm->mod, $modfullnames)) {
                continue;
            }

            if (!array_key_exists($cm->mod, $archetypes)) {
                $archetypes[$cm->mod] = plugin_supports('mod', $cm->mod, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
            }

            if ($archetypes[$cm->mod] == MOD_ARCHETYPE_RESOURCE) {
                if (!array_key_exists('resources', $modfullnames)) {
                    $modfullnames['resources'] = get_string('resources');
                }

            } else {
                if (isset($plurals[$cm->mod])) {
                    $modfullnames[$cm->mod] = $plurals[$cm->mod];
                } else {
                    $modfullnames[$cm->mod] = ucfirst($cm->mod);
                }
            }
        }

        return $modfullnames;
    }

    static function format_pgrid_get_ltitypes($course) {
        global $DB;

        $plurals = get_module_types_names(true);

        $sql = "SELECT DISTINCT l.typeid, m.name
                FROM {course_modules} cm
                JOIN {modules} m
                ON cm.module = m.id
                JOIN {lti} l
                ON cm.instance = l.id
                WHERE cm.course = :courseid
                AND m.name = 'lti'";

        $params = ['courseid' => $course->id];

        $records = $DB->get_recordset_sql($sql, $params);
        $ltitypes = [];

        foreach($records as $record) {
            if (!$record->typeid) {
                $ltitypes[$record->typeid] = $plurals[$record->name];
            } else {
                $type = lti_get_type($record->typeid);

                if ($type) {
                    if (array_key_exists($record->typeid, $ltitypes)) {
                        continue;
                    }

                    $ltitypes[$type->id] = $type->name;
                }
            }      
        }       

        return $ltitypes;
    }
}