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
 * Photoboard renderable.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_pgrid\output;

use renderer_base;
use renderable;
use templatable;
use stdClass;

defined('MOODLE_INTERNAL') || die();

// require_once($CFG->dirroot . '/course/format/pgrid/dashboard/locallib.php');

class photoboard implements templatable, renderable {

    /**
     * @var $course - The course instance.
     */
    public $course = null;

    /**
     * @var $users - The users.
     */
    public $users = [];

    /**
     * @var $mode - The template to show.
     */
    public $mode = [];

    /**
     * @var $unifiedfilter - HTML.
     */
    public $unifiedfilter = [];

    /**
     * @var $pagingbar - HTML.
     */
    public $pagingbar = [];

    /**
     * @var $initialbar - HTML.
     */
    public $initialbar = [];

    /**
     * @var $baseurl - The base url with all required params.
     */
    public $baseurl = [];       

    /**
     * Constructor method.
     *
     * @param stdClass $course
     * @param array $users
     * @param int $mode
     * @param string $unifiedfilter
     * @param string $initialbar
     * @param string $pagingbar
     * @param moodle_url $baseurl
     * 
     * @param string $target one of rendering target constants
     */
    public function __construct(
        $course, 
        $users = [], 
        $mode = 1, 
        $unifiedfilter = null, 
        $initialbar = null,
        $pagingbar = null,
        $baseurl
        ) 
    {
        global $PAGE;

        $this->course = $course;
        $this->users = $users;
        $this->mode = $mode;
        $this->unifiedfilter = $unifiedfilter;
        $this->initialbar = $initialbar;
        $this->pagingbar = $pagingbar;
        $this->baseurl = $baseurl;
    }

    public function export_for_template(renderer_base $output) {
        $export = new stdClass();
        $export->course = $this->course;
        $export->users = $this->get_users();
        $export->mode = $this->mode;
        $export->modes = $this->get_modes();
        $export->unifiedfilter = $this->unifiedfilter;
        $export->initialbar = $this->initialbar;
        $export->pagingbar = $this->pagingbar;        

        return $export;
    }

    public function get_users() {
        global $CFG, $USER, $OUTPUT, $DB;

        $course = $this->course;
        $finalusers = [];
        $draftusers = [];
        $users = [];
        $usersprinted = [];
        $userids = [];
        $context = \context_course::instance($course->id);

        // Get the hidden field list.
        if (has_capability('moodle/course:viewhiddenuserfields', $context)) {
            $hiddenfields = [];
        } else {
            $hiddenfields = explode(',', $CFG->hiddenuserfields);
            $hiddenfields[] = 'mobile';
            $hiddenfields[] = 'forumposts';
            $hiddenfields[] = 'sendmessage';
            $hiddenfields[] = 'allusergroups';
        }

        foreach ($this->users as $user) {
            $userids[] = $user->id;
            $xuser = new stdClass();

            // Create a copy of the user with hidden fields removed if current USER does not have capability moodle/course:viewhiddenuserfields.
            foreach ($user as $key => $value) {
                if (!in_array($value, $hiddenfields)) {
                    $xuser->$key = $value;
                }
            }
            
            $users[$user->id] = $user;
            $draftusers[$user->id] = $xuser;
        }

        // user_get_participants does not return all of the expected fields.
        // I think this is a bug in user/lib.php #1273:
        // $userfields = get_extra_user_fields($context, array('username', 'lang', 'timezone', 'maildisplay'));
        // $userfieldssql = user_picture::fields('u', $userfields);
        $usermaildisplay = $DB->get_records_list('user', 'id', $userids, '', 'id, maildisplay');
        $courseformat = course_get_format($this->course)->get_format_options();

        if($courseformat['selectmoduleleaders']) {
            $moduleleaders = explode(',', $courseformat['selectmoduleleaders']);
        } else {
            $moduleleaders = [];
        }

        // Check to see if groups are being used in this course
        // and if so, set $currentgroup to reflect the current group.
        $groupmode = groups_get_course_groupmode($this->course);   // Groups are being used.
        $currentgroup = groups_get_course_group($this->course, true);

        if (!$currentgroup) {      // To make some other functions work better later.
            $currentgroup  = null;
        }

        foreach ($users as $user) {
            if (in_array($user->id, $usersprinted)) {
                continue;
            }

            // Get the copy of the user with hidden fields removed if current
            // user does not have capability moodle/course:viewhiddenuserfields.
            $xuser = $draftusers[$user->id];
            $usersprinted[] = $user->id; // Add new user to the array of users printed.
            $xuser->imghtml = $OUTPUT->user_picture(
                $user,
                [
                    'class' => 'card-img-top',
                    'size' => 100,
                    'courseid' => $course->id
                ]
            );
            $moduleleaderstr = '';

            if (in_array($user->id, $moduleleaders)) {
                $moduleleaderstr = get_string('moduleleader', 'format_pgrid');
            }

            $fullname = fullname($user, has_capability('moodle/site:viewfullnames', $context)) . $moduleleaderstr;

            if (has_capability('moodle/course:viewhiddenuserfields', $context)) {
                $userlink = new \moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id));
                $xuser->userlink = $userlink;
            }

            $xuser->fullname = $fullname;

            // $xuser->userlink = $userlink;

            // Added temp sql to get maildisplay above.
            if (
                $usermaildisplay[$user->id]->maildisplay == 1 
                || (
                    $usermaildisplay[$user->id]->maildisplay == 2 
                    && ($course->id != SITEID) 
                    && !isguestuser()
                    ) 
                || has_capability('moodle/course:viewhiddenuserfields', $context)
                || ($user->id == $USER->id)
            ) {
                $xuser->email = $user->email;                
            } else {
                $xuser->email = false;
            }

            if (has_capability('moodle/course:viewhiddenuserfields', $context, $user)) {
                $xuser->staff = true;

                $sql = 'SELECT shortname, data
                        FROM {user_info_data} uid
                        JOIN {user_info_field} uif
                        ON uid.fieldid = uif.id
                        WHERE uid.userid = :userid';

                if ($result = $DB->get_records_sql($sql, array('userid' => $user->id))){                        
                    $xuser->stafftelephone = isset($result['stafftelephone']->data) ? $result['stafftelephone']->data : '';
                    $xuser->staffofficehrs = isset($result['staffofficehrs']) ? $result['staffofficehrs']->data : '';
                    $xuser->stafflocation = isset($result['stafflocation']->data) ? $result['stafflocation']->data : '';
                } else {
                    $xuser->stafftelephone = '';
                    $xuser->staffofficehrs = '';
                    $xuser->stafflocation = '';
                }

                $xuser->course = $this->course->id;
            }

            $showgroups = !in_array('allusergroups', $hiddenfields) || (in_array('allusergroups', $hiddenfields) && has_capability('moodle/course:viewhiddenuserfields', $context, $user));

            if ($showgroups) {
                if ($currentgroup) {
                    $group = groups_get_group($currentgroup);
                    $xuser->group = $group->name;
                } else {
                    // show all groups user belongs to:
                    $groups = groups_get_all_groups($this->course->id, $user->id);
                    $groupnames = array();

                    foreach ($groups as $group) {
                        $groupnames[] = $group->name;
                    }

                    if (count($groups)) {
                        $xuser->group = implode(', ', $groupnames);
                    }
                }
            }

            if (!in_array('lastaccess', $hiddenfields)) {
                if ($user->lastaccess) {
                    $timesince = format_time(time() - $user->lastaccess);
                    $xuser->lastaccess = userdate($user->lastaccess);
                    $xuser->lastaccess .= " ($timesince)";
                } else {
                    $xuser->lastaccess = get_string('never');
                }
            } else {
                $xuser->lastaccess = '';
            }

            $links = [];
            $link = [];

            if (!in_array('forumposts', $hiddenfields)) {
                $link['url'] = new \moodle_url('/mod/forum/user.php', ['id' => $user->id, 'course' => $course->id]);
                $link['title'] = get_string('forumposts', 'mod_forum');
                $links[] = $link;
            }

            if ($USER->id != $user->id && !\core\session\manager::is_loggedinas() && has_capability('moodle/user:loginas', $context) && !is_siteadmin($user->id)) {
                $link['url'] = new \moodle_url('/course/loginas.php', ['id' => $course->id, 'user' => $user->id, 'sesskey' => sesskey()]);
                $link['title'] = get_string('loginas');
                $links[] = $link;
            }

            if (!in_array('sendmessage', $hiddenfields)) {
                $link['url'] = new \moodle_url('/message/index.php', ['id' => $user->id, 'viewing' => 'course_' . $course->id]);
                $link['title'] = get_string('sendmessage', 'format_pgrid');
                $links[] = $link;
            }

            $xuser->links = $links;
            $finalusers[] = $xuser;
        }

        return $finalusers;
    }

    public function get_modes() {
        $modes[MODE_BRIEF]['title'] = get_string('photogrid', 'format_pgrid');
        $modes[MODE_USERDETAILS]['title'] = get_string('detailedlist', 'format_pgrid');
        $modeurl = clone($this->baseurl);
        $modeurl->param('mode', 0);
        $modes[MODE_BRIEF]['link'] = $modeurl->out(false);
        $modeurl = clone($this->baseurl);
        $modeurl->param('mode', 1);
        $modes[MODE_USERDETAILS]['link'] = $modeurl->out(false);

        if ($this->mode == MODE_BRIEF) {
            $modes[MODE_BRIEF]['active'] = 1;
            // $modes[MODE_BRIEF]['link'] = '#brief';
        } else {
            $modes[MODE_BRIEF]['active'] = 0;
            // $modes[MODE_BRIEF]['link'] = '#brief';
        }

        if ($this->mode == MODE_USERDETAILS) {
            $modes[MODE_USERDETAILS]['active'] = 1;
            // $modes[MODE_USERDETAILS]['link'] = '#detailed';
        } else {
            $modes[MODE_USERDETAILS]['active'] = 0;
            // $modes[MODE_USERDETAILS]['link'] = '#detailed';
        }

        return $modes;
    }
}