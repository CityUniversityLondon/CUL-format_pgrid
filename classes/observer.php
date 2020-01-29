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
 * Observer for format_pgrid (based on format_weeks).
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers supported by this format.
 */
class format_pgrid_observer {

    /**
     * Observer for the event course_content_deleted.
     *
     * @param \core\event\course_content_deleted $event
     */
    public static function course_content_deleted(\core\event\course_content_deleted $event) {
        global $DB;

        $DB->delete_records('user_preferences', array('name' => 'format_pgrid_expanded' . $event->objectid)); // This is the $courseid.
    }

    /**
     * Triggered via \core\event\course_updated event.
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event) {
        if (class_exists('format_pgrid', false)) {
            // If class format_pgrid was never loaded, this is definitely not a course in 'format_pgrid' format.
            // Course may still be in another format but format_pgrid::format_weeks_update_end_date() will check it.
            format_pgrid::format_weeks_update_end_date($event->courseid);
        }
    }

    /**
     * Triggered via \core\event\course_section_created event.
     *
     * @param \core\event\course_section_created $event
     */
    public static function course_section_created(\core\event\course_section_created $event) {
        if (class_exists('format_pgrid', false)) {
            // If class format_pgrid was never loaded, this is definitely not a course in 'format_pgrid' format.
            // Course may still be in another format but format_pgrid::format_weeks_update_end_date() will check it.
            format_pgrid::format_weeks_update_end_date($event->courseid);
        }
    }

    /**
     * Triggered via \core\event\course_section_deleted event.
     *
     * @param \core\event\course_section_deleted $event
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event) {
        if (class_exists('format_pgrid', false)) {
            // If class format_pgrid was never loaded, this is definitely not a course in 'format_pgrid' format.
            // Course may still be in another format but format_pgrid::format_weeks_update_end_date() will check it.
            format_pgrid::format_weeks_update_end_date($event->courseid);
        }
    }    
}
