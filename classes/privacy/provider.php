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
 * Privacy Subsystem implementation for format_pgrid.
 *
 * @package    format_pgrid
 * @copyright  2018 Amanda Doughty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_pgrid\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\writer;

/**
 * Privacy Subsystem for format_pgrid implementing null_provider.
 *
 * @copyright  2018 Amanda Doughty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements 
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin has some sitewide user preferences to export.
    \core_privacy\local\request\user_preference_provider
{
	
    /**
     * Returns meta data about this system.
     *
     * @param collection $itemcollection The initialised item collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items) : collection {
        // There are several user preferences in the format
        // format_pgrid_expanded{$course-id)}. I've used same
        // methods as tool_usertours.
        $items->add_user_preference('format_pgrid_expanded', 'privacy:metadata:preference:format_pgrid_expanded');
        $items->add_user_preference('format_pgrid_toggledash', 'privacy:metadata:preference:format_pgrid_toggledash');

        return $items;
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        global $DB;

        $preferences = get_user_preferences();

        foreach ($preferences as $name => $value) {
            $descriptionidentifier = null;
            $courseid = null;

            if (strpos($name, 'format_pgrid_expanded') === 0) {
                $descriptionidentifier = 'privacy:request:preference:format_pgrid_expanded';
                $courseid = substr($name, strlen('format_pgrid_expanded'));
            }

            if ($descriptionidentifier !== null) {
                $decoded = json_decode($value);
                $sectionstates = '';
                $modinfo = get_fast_modinfo($courseid);
                $course = $modinfo->get_course();

                if ($course) {
                    $sections = $modinfo->get_section_info_all();

                    foreach ($sections as $number => $section) {
                        if (isset($decoded->{$section->id}) && $decoded->{$section->id}) {
                            $sectionstate = get_string('expanded', 'format_pgrid');
                        } else {
                            $sectionstate = get_string('collapsed', 'format_pgrid');
                        }

                        if ($section->name) {
                            $sectionname = $section->name;
                        } else {
                            $sectionname = get_string('sectionname', 'format_pgrid');
                            $sectionname .= " $number";
                        }

                        $sectionstates .= "$sectionname: $sectionstate, ";
                    }                    

                    writer::export_user_preference(
                        'format_pgrid',
                        $name,
                        $value,
                        get_string($descriptionidentifier, 'format_pgrid', (object) [
                            'course' => $course->fullname,
                            'sectionstates' => $sectionstates,
                        ])
                    );
                }
            }

            if (strpos($name, 'format_pgrid_toggledash') === 0) {
                $descriptionidentifier = 'privacy:request:preference:format_pgrid_toggledash';
                $courseid = substr($name, strlen('format_pgrid_toggledash'));
            }

            if ($descriptionidentifier !== null) {
                $modinfo = get_fast_modinfo($courseid);
                $course = $modinfo->get_course();
                $dashboardstate = $value ? 'open' : 'closed';

                if ($course) {
                    writer::export_user_preference(
                        'format_pgrid',
                        $name,
                        $value,
                        get_string($descriptionidentifier, 'format_pgrid', (object) [
                            'course' => $course->fullname,
                            'dashboardstate' => $sectionstates,
                        ])
                    );
                }
            }
        }
    }
}
