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
 * Index page for lti activities.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../config.php");
require_once($CFG->dirroot.'/course/format/pgrid/dashboard/locallib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');

$id = required_param('id', PARAM_INT);   // Course id.
$typeid = required_param('typeid', PARAM_INT);   // Type id.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$type = lti_get_type($typeid);

if(!$type) {
    $type = new stdClass();
    $type->id = 0;
}

require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_lti\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$PAGE->set_url('/course/format/pgrid/dashboard/ltiindex.php', array('id' => $course->id, 'typeid' => $typeid));

if($type->id) {
    $pagetitle = strip_tags($course->shortname . ': ' . $type->name . ' ' . get_string('modulenamepluralformatted', "lti"));
} else {
    $pagetitle = strip_tags($course->shortname . ': ' . get_string('modulenamepluralformatted', "lti"));
}

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Print the main part of the page.
if($type->id) {
    echo $OUTPUT->heading($type->name . ' ' . get_string('modulenamepluralformatted', 'lti'));
} else {
    echo $OUTPUT->heading(get_string('modulenamepluralformatted', 'lti'));
}

// Get all the appropriate data.
if (!$ltis = format_pgrid_get_lti_instances($course, $type)) {
    notice(get_string('noltis', 'lti'), "../../course/view.php?id=$course->id");
    die;
}

// Print the list of instances (your module will probably extend this).
$timenow = time();
$strname = get_string("name");
$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array ($strsectionname, $strname);
    $table->align = array ("center", "left");
} else {
    $table->head  = array ($strname);
}

foreach ($ltis as $lti) {
    if (!$lti->visible) {
        // Show dimmed if the mod is hidden.
        $link = "<a class=\"dimmed\" href=\"/mod/lti/view.php?id=$lti->coursemodule\">$lti->name</a>";
    } else {
        // Show normal if the mod is visible.
        $link = "<a href=\"/mod/lti/view.php?id=$lti->coursemodule\">$lti->name</a>";
    }

    if ($usesections) {
        $table->data[] = array (get_section_name($course, $lti->section), $link);
    } else {
        $table->data[] = array ($link);
    }
}

echo "<br />";

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
