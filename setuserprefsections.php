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
 * Ajax script for saving toggle state of sections.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

/** Include config */
require_once(__DIR__ . '/../../../config.php');

if (!empty($CFG->forcelogin)) {
    require_login();
}

// Get the name of the preference to update, and check that it is allowed.
$courseid = required_param('courseid', PARAM_INT);
$sectionid = required_param('sectionid', PARAM_INT);
$name = 'format_pgrid_expanded' . $courseid;

if (!isset($USER->ajax_updatable_user_prefs[$name])) {
    print_error('notallowedtoupdateprefremotely');
}

$value = required_param('value', PARAM_INT);
$sectiontoggles = [];

if($userpref = get_user_preferences($name)) {
	$sectiontoggles = json_decode($userpref, true);
}

$sectiontoggles[$sectionid] = $value;
$newsectiontoggles = json_encode($sectiontoggles, true);

// Update.
if (isset($value)) {
    if (!set_user_preference($name, $newsectiontoggles)) {
        print_error('errorsettinguserpref');
    }
    echo json_encode('OK');

} else {
    header('HTTP/1.1 406 Not Acceptable');
    echo 'Not Acceptable';
}
