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
 * Non JS moving and hiding dashboard links.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../../config.php');
require_once(dirname(__FILE__) . '/../../../lib.php');
require_once(dirname(__FILE__) . '/locallib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = required_param('action', PARAM_INT);
$name = optional_param('name', null, PARAM_RAW);
$showhide = optional_param('showhide', 0, PARAM_INT);
$copy = optional_param('copy', null, PARAM_RAW);
$moveto = optional_param('moveto', null, PARAM_RAW);
$cancelcopy = optional_param('cancelcopy', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$usercanedit = has_capability('moodle/course:update', context_course::instance($courseid));

if (!$usercanedit) {
    print_error('noeditcoursesettings', 'format_pgrid');
}

if ($action == SHOWHIDE) {
    if ($name) {
        format_pgrid_quicklink_visibility($courseid, $name, $showhide);
        redirect(course_get_url($course));
    } else {
        print_error('noname', 'format_pgrid');
    }
} else if ($action == MOVE) {
    if (!empty($moveto) && !empty($name) && confirm_sesskey()) {
        if ($name == 'quicklinksequence') {
            $linkcopy = $USER->quicklinkcopy;

            if (!ismovingquicklink($courseid)) {
                print_error('needcopy', '', "view.php?id=$courseid");
            }

            $updated = format_pgrid_dashlink_move($courseid, $name, $linkcopy, $moveto);
            unset($USER->quicklinkcopy);
            unset($USER->quicklinkcopycourse);        
        } else if ($name == 'activitylinksequence') {
            $linkcopy = $USER->activitylinkcopy;

            if (!ismovingactivitylink($courseid)) {
                print_error('needcopy', '', "view.php?id=$courseid");
            }

            $updated = format_pgrid_dashlink_move($courseid, $name, $linkcopy, $moveto);
            unset($USER->activitylinkcopy);
            unset($USER->activitylinkcopycourse);
        }

        if (!$updated) {
            print_error('courseformatmissing');
        }

        redirect(course_get_url($course));

    } else if (!empty($copy) && !empty($name) && confirm_sesskey()) {
        if ($name == 'quicklinksequence') {
            $USER->quicklinkcopy = $copy;
            $USER->quicklinkcopycourse = $courseid;
            unset($USER->activitylinkcopy);
            unset($USER->activitylinkcopycourse);
        } else if ($name == 'activitylinksequence') {
            $USER->activitylinkcopy = $copy;
            $USER->activitylinkcopycourse = $courseid;
            unset($USER->quicklinkcopy);
            unset($USER->quicklinkcopycourse);
        }

        redirect(course_get_url($course));

    } else if (!empty($cancelcopy) && !empty($name) and confirm_sesskey()) {
        if ($name == 'quicklinksequence') {
            unset($USER->quicklinkcopy);
            unset($USER->quicklinkcopycourse);
        } else if ($name == 'activitylinksequence') {
            unset($USER->activitylinkcopy);
            unset($USER->activitylinkcopycourse);
        }

        redirect(course_get_url($course));
    } else {
        print_error('unknowaction');
    }
} else {
    print_error('unknowaction');
}

