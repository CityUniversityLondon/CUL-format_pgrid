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
 * Outputs the course page.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();
$ajaxurl = '/course/format/pgrid/dashboard/dashlink_edit_ajax.php';

if (($marker >=0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_pgrid');

if (!empty($displaysection && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null);
}

user_preference_allow_ajax_update('format_pgrid_expanded' . $course->id, PARAM_INT);
user_preference_allow_ajax_update('format_pgrid_toggledash' . $course->id, PARAM_INT);

// Include course format js module
$PAGE->requires->js('/course/format/pgrid/format.js');
$PAGE->requires->js_call_amd('format_pgrid/sectiontoggle', 'init', ['courseid' => $course->id]);

if ($PAGE->user_is_editing()) {
    $PAGE->requires->string_for_js('moveactivitylink', 'format_pgrid');
    $PAGE->requires->string_for_js('movequicklink', 'format_pgrid');
    $PAGE->requires->yui_module('moodle-format_pgrid-dragdrop', 'M.format_pgrid.init_quicklinkdd',
                [[
                    'courseid' => $course->id,
                    'ajaxurl' => $ajaxurl,
                    'config' => 0,
                ]], null, true);

    $PAGE->requires->yui_module('moodle-format_pgrid-dragdrop', 'M.format_pgrid.init_activitylinkdd',
                [[
                    'courseid' => $course->id,
                    'ajaxurl' => $ajaxurl,
                    'config' => 0,
                ]], null, true);
}
