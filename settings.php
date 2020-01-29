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
 * Settings for format_pgrid.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/* Base course format.
*/
$name = 'format_pgrid/defaultbaseclass';
$title = get_string('defaultbaseclass', 'format_pgrid');
$description = get_string('defaultbaseclass_desc', 'format_pgrid');
$default = 1;
$choices = [
    1 => new lang_string('pluginname', 'format_topics'),
    2 => new lang_string('pluginname', 'format_weeks')

        // 1 => new lang_string('setlayoutstructuretopic', 'format_pgrid'), // Topic.
        // 2 => new lang_string('setlayoutstructureweek', 'format_pgrid'), // Week.
        // 3 => new lang_string('setlayoutstructurelatweekfirst', 'format_pgrid'), // Latest Week First.
        // 4 => new lang_string('setlayoutstructurecurrenttopicfirst', 'format_pgrid'), // Current Topic First.
        // 5 => new lang_string('setlayoutstructureday', 'format_pgrid')                // Day.
];
$settings->add(new admin_setting_configselect($name, $title, $description, $default, $choices));

// Show the section summary when collapsed.
// 1 => No.
// 2 => Yes.
$name = 'format_pgrid/defaultshowsectionsummary';
$title = get_string('defaultshowsectionsummary', 'format_pgrid');
$description = get_string('defaultshowsectionsummary_desc', 'format_pgrid');
$default = 1;
$choices = [
    1 => new lang_string('no'),
    2 => new lang_string('yes')
];
$settings->add(new admin_setting_configselect($name, $title, $description, $default, $choices));

/* Default blocks.
*/
$name = 'format_pgrid/defaultblocks_pgrid';
$title = get_string('defaultblocks', 'format_pgrid');
$description = get_string('defaultblocks_desc', 'format_pgrid');
$default = 'settings,culactivity_stream,culupcoming_events,culschool_html,quickmail';
$settings->add(new admin_setting_configtextarea($name, $title, $description, $default));

/* Quicklinks.
*/
$elements = [
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

foreach ($elements as $element) {
    $name = 'format_pgrid/defaultshow' . $element;
    $title = get_string('defaultshow' . $element, 'format_pgrid');
    $description = get_string('defaultshow' . $element . '_desc', 'format_pgrid');
    $default = 2;
    $choices = [
        1 => new lang_string('no'),
        2 => new lang_string('yes')
    ];
    $settings->add(new admin_setting_configselect($name, $title, $description, $default, $choices));
}

// Connection timeout in s.
$name = 'format_pgrid/connect_timeout';
$title = get_string('connect_timeout', 'format_pgrid');
$description = get_string('connect_timeout_desc', 'format_pgrid');
$default = 4;
$type = PARAM_INT;
$settings->add(new admin_setting_configtext($name, $title, $description, $default, $type));

// Transfer timeout in s.
$name = 'format_pgrid/transfer_timeout';
$title = get_string('transfer_timeout', 'format_pgrid');
$description = get_string('transfer_timeout_desc', 'format_pgrid');
$default = 8;
$type = PARAM_INT;
$settings->add(new admin_setting_configtext($name, $title, $description, $default, $type));

// Reading List API url.
$name = 'format_pgrid/aspireAPI';
$title = get_string('aspireAPI', 'format_pgrid');
$description = get_string('aspireAPI_desc', 'format_pgrid');
$default = 'http://readinglists.city.ac.uk';
$type = PARAM_RAW;
$settings->add(new admin_setting_configtext($name, $title, $description, $default, $type));

// Libguides url.
$name = 'format_pgrid/libAppsAPI';
$title = get_string('libAppsAPI', 'format_pgrid');
$description = get_string('libAppsAPI_desc', 'format_pgrid');
$default = 'http://lgapi-eu.libapps.com/1.1/guides/';
$type = PARAM_RAW;
$settings->add(new admin_setting_configtext($name, $title, $description, $default, $type));

// Libguides default url.
$name = 'format_pgrid/libAppsDefaultURL';
$title = get_string('libAppsDefaultURL', 'format_pgrid');
$description = get_string('libAppsDefaultURL_desc', 'format_pgrid');
$default = 'http://libguides.city.ac.uk/home';
$type = PARAM_RAW;
$settings->add(new admin_setting_configtext($name, $title, $description, $default, $type));

// Libguides site ID.
$name = 'format_pgrid/libAppsSiteId';
$title = get_string('libAppsSiteId', 'format_pgrid');
$description = get_string('libAppsSiteId_desc', 'format_pgrid');
$default = '426';
$type = PARAM_RAW;
$settings->add(new admin_setting_configtext($name, $title, $description, $default, $type));

// Libguides API key.
$name = 'format_pgrid/libAppsKey';
$title = get_string('libAppsKey', 'format_pgrid');
$description = get_string('libAppsKey_desc', 'format_pgrid');
$default = 'e4706d90b346c209c37b32a6a94781d7';
$type = PARAM_RAW;
$settings->add(new admin_setting_configtext($name, $title, $description, $default, $type));