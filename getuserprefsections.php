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
 * Gets the saved toggled states of sections.
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

$courseid = required_param('courseid', PARAM_INT);
$name = 'format_pgrid_expanded' . $courseid;
$userpref = get_user_preferences($name);

if(!$userpref) {
	$userpref = json_encode([], true);
}

// The value has been saved as a json string.
echo $userpref;
