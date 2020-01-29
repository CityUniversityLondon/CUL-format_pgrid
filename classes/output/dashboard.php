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
 * Dashboard renderable.
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

require_once($CFG->dirroot . '/course/format/pgrid/dashboard/locallib.php');

class dashboard implements templatable, renderable {

    /**
     * @var $course - The course instance.
     */
    public $course = null;

    /**
     * @var $culconfig - The plugin settings.
     */
    public $culconfig = null;

    /**
     * @var $culconfigchanged - Boolean indicating if settings require updating.
     */
    public $culconfigchanged = false;

    /**
     * @var $usercanedit
     */
    public $usercanedit = false;   

    /**
     * @var $userisediting
     */
    public $userisediting = false;

    /**
     * @var $adminurl
     */
    public $adminurl = null;

    /**
     * @var $externalurls
     */
    public $externalurls = null;    

    /**
     * Constructor method, calls the parent constructor
     *
     * @param stdClass $course
     * @param array $culconfig
     */
    public function __construct($course, $culconfig) {
        global $PAGE;

        $this->usercanedit = has_capability('moodle/course:update', \context_course::instance($course->id));

        if ($this->usercanedit) {
            $this->userisediting = $PAGE->user_is_editing();
        }

        if ($this->userisediting) {
            $adminurl = new \moodle_url('/course/format/pgrid/dashboard/dashlink_edit_ajax.php');
            $this->adminurl = $adminurl->out();
        }

        $this->course = $course;
        $this->culconfig = $culconfig;
        $this->externalurls = format_pgrid_get_external_urls_data($course);
    }

    public function export_for_template(renderer_base $output) {
        global $PAGE;
        $export = new stdClass();
        $export->courseid = $this->course->id;
        $export->quicklinksexist = false;
        $export->activitiesexist = false;
        $export->userisediting = $this->userisediting;
        $export->adminurl = $this->adminurl;
        $export->quicklinks = $this->quicklink_display($this->course);

        if(count($export->quicklinks)) {
            $export->quicklinksexist = true;
        }

        $export->activities = $this->activity_modules_display($this->course);

        if(count($export->activities)) {
            $export->activitiesexist = true;
        }

        $export->ismovingquicklink = $this->show_is_moving($export->quicklinks, 'quicklink');
        $export->ismovingactivitylink = $this->show_is_moving($export->activities, 'activitylink');
        $export->cancelquicklink = $this->dashboard_clipboard('quicklink');
        $export->cancelactivitylink = $this->dashboard_clipboard('activitylink');

        list($export->qmovetourl, $export->qmovetoicon, $export->qmovetoattrs) = format_pgrid_get_moveto_link(
                            $this->course->id, 
                            'end',
                            'quicklink'
                            );

        list($export->amovetourl, $export->amovetoicon, $export->amovetoattrs) = format_pgrid_get_moveto_link(
                            $this->course->id, 
                            'end',
                            'activitylink'
                            );

        if ($this->culconfigchanged) {
            // Update course format settings.
            $data = (object)$this->culconfig;
            $format = course_get_format($this->course);
            $format->update_course_format_options($data);
        }

        $export->courseimgurl = new \moodle_url('/theme/'.$PAGE->theme->name.'/pix/coursebg.jpg');

        return $export;
    }

    public function get_quicklink($name, $course, $lnktxt = '') {
        global $USER;

        if (get_string_manager()->string_exists($name, 'format_pgrid')) {
            $lnktxt = get_string($name, 'format_pgrid');
        }

        $class = '';
        $editurl = '';
        $editicon = '';
        $editattrs = '';
        $moveurl = '';
        $moveicon = '';
        $moveattrs = '';
        $movetourl = '';
        $movetoicon = '';
        $movetoattrs = '';

        if ($this->userisediting) {
            list($editurl, $editicon, $editattrs) = format_pgrid_get_edit_link(
                $course->id, 
                $name, 
                $this->culconfig['show' . $name],
                $lnktxt
                );

            list($moveurl, $moveicon, $moveattrs) = format_pgrid_get_move_link(
                $course->id, 
                $name,
                'quicklink',
                $lnktxt
                );

            list($movetourl, $movetoicon, $movetoattrs) = format_pgrid_get_moveto_link(
                $course->id, 
                $name,
                'quicklink'
                );
        }            

        if ($this->userisediting && ($this->culconfig['show' . $name] != 2)) {
            $class = 'linkhidden';                
        }

        return [
            'name' => $name,
            'text' => $lnktxt,
            'class' => $class,
            'editurl' => $editurl, 
            'editicon' => $editicon, 
            'editattrs' => $editattrs,
            'moveurl' => $moveurl, 
            'moveicon' => $moveicon, 
            'moveattrs' => $moveattrs,
            'movetourl' => $movetourl,
            'movetoicon' => $movetoicon,
            'movetoattrs' => $movetoattrs
        ];
    }

    public function get_photoboard_quicklink($rolename, $name, $course, $options) {
        global $DB;

        $role = $DB->get_record('role', ['shortname' => $rolename]);
        $coursecontext = \context_course::instance($course->id);

        if ($role) {
            $name = $name;
            $icon = 'fa-users';
            $data = [];
            $extradata =[];
            $attrs  = [];
            $liattrs = [];            
            $alias = $options[$role->id];
            $lnktxt = $alias . 's';
            $data = $this->get_quicklink($name, $course, $lnktxt);
            $available = false;

            if (count_role_users($role->id, $coursecontext, false)) {
                $available = true;
                $attrs['title']  = get_string("view-$rolename-photoboard", 'format_pgrid', $alias);
                $attrs['target'] = '';
                $url = format_pgrid_get_photoboard_url($course, $role->id);
            } else {
                $attrs['class'] = 'nolink';
                $attrs['title']  = get_string("no-view-$rolename-photoboard", 'format_pgrid', $alias);
                $url = 'javascript:void(0);';
            }

            if ($this->usercanedit || $available) {
                $extradata = [
                    'url' => $url,
                    'icon' => $icon,
                    'attrs' => $attrs,
                    'liattrs' => $liattrs,
                    'text' => $lnktxt
                ];

                return array_merge($data, $extradata);
            }
        }

        return false;
    }

    /**
     * 
     *
     * @param stdClass $course
     * @return string
     *
     * TODO: Create a master mapping/dispatch table to make it easy to add additional
     *       Quick Links in future. This could automatically be picked-up by the block
     *       config form. For now, it's over-engineering.
     */
    public function quicklink_display($course) {
        global $CFG, $DB, $OUTPUT, $USER;

        $linkitems = [];
        $sortedlinkitems = [];
        $coursecontext = \context_course::instance($course->id);
        $sequence = [];
        $deletefromsequence = false;

        if (isset($this->culconfig['quicklinksequence']) && $this->culconfig['quicklinksequence']) {
            $sequence = explode(',', $this->culconfig['quicklinksequence']);
        }

        // Reading list
        if ($this->culconfig['showreadinglists'] == 2 || $this->userisediting) {
            $name = 'readinglists';
            $icon = 'fa-bookmark';
            $data = [];
            $extradata = [];
            $attrs  = [];
            $liattrs = [];
            $data = $this->get_quicklink($name, $course);
            $urldata = $this->externalurls['readinglists'];
            $available = false;

            if (!$urldata) {
                // Not installed or not configured                
                $attrs['title'] = get_string('not-installed-readinglist', 'format_pgrid');
                $attrs['class'] = 'nolink';
                $url = 'javascript:void(0);';
                $liattrs['class'] = 'wide';
            } else {
                if (OK == $urldata['status']) {
                    $available = true;
                    $listtype = $urldata['listtype'];
                    $url = $urldata['url'];

                    if ('module' == $listtype) {
                        $attrs['title'] = get_string('view-readinglist-module', 'format_pgrid');
                        $attrs['target'] = '_blank';
                    } else if ('module-year' == $listtype) {
                        $attrs['title'] = get_string('view-readinglist-module-year', 'format_pgrid');
                        $attrs['target'] = '_blank';
                    }
                } else if (NODATA == $urldata['status']) {
                    $attrs['title'] = get_string('no-readinglist', 'format_pgrid');
                    $attrs['class'] = 'nolink';
                    $url = 'javascript:void(0);';
                } else if (ERROR == $urldata['status']) {
                    $attrs['title'] = get_string('error-readinglist', 'format_pgrid');
                    $attrs['class'] = 'nolink';
                    $url = 'javascript:void(0);';
                }
            }

            if ($this->usercanedit || $available) {
                $extradata = [
                    'url' => $url,
                    'icon' => $icon,
                    'attrs' => $attrs,
                    'liattrs' => $liattrs,
                ];

                $linkitems[$name] = array_merge($data, $extradata);
            }
        }

        // Lib Guides
        if ($this->culconfig['showlibguides'] == 2 || $this->userisediting) {
            $name = 'libguides';
            $icon = 'fa-bookmark';
            $data = [];
            $extradata = [];
            $attrs  = [];
            $liattrs = [];
            $data = $this->get_quicklink($name, $course);
            $urldata = $this->externalurls['libguides'];
            $available = false;

            if (OK == $urldata['status']) {
                $available = true;
                $url = $urldata['url'];
                $attrs['title'] = get_string('view-libguide-module', 'format_pgrid');
                $attrs['target'] = '_blank';
            } else if (NODATA == $urldata['status']) {
                $attrs['title'] = get_string('default-libguide', 'format_pgrid');
                $attrs['target'] = '_blank';
                $url = $urldata['url'];
            } else if (ERROR == $urldata['status']) {
                $attrs['title'] = get_string('error-libguide', 'format_pgrid');
                $attrs['class'] = 'nolink';
                $url = 'javascript:void(0);';
            }

            if ($this->usercanedit || $available) {
                $extradata = [
                    'url' => $url,
                    'icon' => $icon,
                    'attrs' => $attrs,
                    'liattrs' => $liattrs,
                ];

                $linkitems[$name] = array_merge($data, $extradata);
            }
        }

        // Timetable link
        if ($this->culconfig['showtimetable'] == 2 || $this->userisediting) {
            $name = 'timetable';
            $icon = 'fa-clock-o';
            $data = [];
            $extradata =[];
            $attrs  = [];
            $liattrs = [];
            $data = $this->get_quicklink($name, $course);
            $urldata = $this->externalurls['timetable'];
            $available = false;

            if (!$urldata) {
                // Not installed or not configured.
                $attrs['title'] = get_string('not-installed-timetable', 'format_pgrid');
                $attrs['class'] = 'nolink';
                $url = 'javascript:void(0);';
            } else {
                if (OK == $urldata['status']) {
                    $available = true;
                    $attrs['title']  = get_string('view-timetable', 'format_pgrid');
                    $attrs['target'] = '_blank';
                    $url = $urldata['url'];
                } else if (NODATA == $urldata['status']) {
                    $attrs['title']  = get_string('no-timetable', 'format_pgrid');
                    $attrs['class'] = 'nolink';
                    $attrs['target'] = '_blank';
                    $url = $urldata['url'];
                } else if (ERROR == $urldata['status']) {
                    $attrs['title'] = get_string('error-timetable', 'format_pgrid');
                    $attrs['class'] = 'nolink';
                    $url = 'javascript:void(0);';
                }
            }

            if ($this->usercanedit || $available) {
                $extradata = [
                    'url' => $url,
                    'icon' => $icon,
                    'attrs' => $attrs,
                    'liattrs' => $liattrs,
                ];

                $linkitems[$name] = array_merge($data, $extradata);
            }
        }

        // Grades
        if ($this->culconfig['showgraderreport'] == 2 || $this->userisediting) {
            $name = 'graderreport';
            $icon = 'fa-mortar-board';
            $data = [];
            $extradata =[];
            $attrs  = [];
            $liattrs = [];
            $data = $this->get_quicklink($name, $course);

            if (has_capability('gradereport/grader:view', $coursecontext)) { // Teacher, ...
                $lnktxt = get_string('graderreport', 'grades');
                $attrs['title'] = get_string('view-graderreport', 'format_pgrid');
                $url = new \moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
            } else if (has_capability('moodle/grade:view', $coursecontext)) { // Student
                $attrs['title'] = get_string('viewgrades', 'grades');
                $url = new \moodle_url('/grade/report/culuser/index.php', array('id' => $course->id));
            } else  {
                $attrs['title'] = get_string('no-view-grades', 'format_pgrid');
                $attrs['class'] = 'nolink';
                $url = 'javascript:void(0);';
            }

            $extradata = [
                'url' => $url,
                'icon' => $icon,
                'attrs' => $attrs,
                'liattrs' => $liattrs,
            ];

            $linkitems[$name] = array_merge($data, $extradata);
        }

        // Calendar
        if ($this->culconfig['showcalendar'] == 2 || $this->userisediting) {
            $name = 'calendar';
            $icon = 'fa-calendar';
            $data = [];
            $extradata =[];
            $attrs  = [];
            $liattrs = [];
            $data = $this->get_quicklink($name, $course);
            $attrs['title'] = get_string('view-calendar', 'format_pgrid');
            $url  = new \moodle_url('/calendar/view.php', ['view' => 'month', 'course' => $course->id]);

            $extradata = [
                'url' => $url,
                'icon' => $icon,
                'attrs' => $attrs,
                'liattrs' => $liattrs,
            ];

            $linkitems[$name] = array_merge($data, $extradata);
        }

        // Photoboards
        foreach (role_get_names($coursecontext, ROLENAME_ALIAS) as $role) {
            $options[$role->id] = $role->localname;
        }

        // Student Photoboard
        if ($this->culconfig['showstudents'] == 2 || $this->userisediting) {
            $role = 'student';
            $name = 'students';
            $data = $this->get_photoboard_quicklink($role, $name, $course, $options);

            if ($data) {
                $linkitems[$name] = $data;
            }
        }

        // Lecturer Photoboard
        if ($this->culconfig['showlecturers'] == 2 || $this->userisediting) {
            $role = 'lecturer';
            $name = 'lecturers';
            $data = $this->get_photoboard_quicklink($role, $name, $course, $options);

            if ($data) {
                $linkitems[$name] = $data;
            }
        }

        // Course Officer Photoboard
        if ($this->culconfig['showcourseofficers'] == 2 || $this->userisediting) {
            $role = 'courseofficer';
            $name = 'courseofficers';
            $data = $this->get_photoboard_quicklink($role, $name, $course, $options);

            if ($data) {
                $linkitems[$name] = $data;
            }
        }

        // Media gallery
        if ($this->culconfig['showmedia'] == 2 || $this->userisediting) {
            $name = 'media';
            $icon = 'fa-file-video-o';
            $data = [];
            $extradata =[];
            $attrs  = [];
            $liattrs = [];
            $data = $this->get_quicklink($name, $course);
            $attrs['title'] = get_string('view-media', 'format_pgrid');
            $url  = new \moodle_url('/local/kalturamediagallery/index.php', array('courseid' => $course->id));

            $extradata = [
                'url' => $url,
                'icon' => $icon,
                'attrs' => $attrs,
                'liattrs' => $liattrs,
            ];

            $linkitems[$name] = array_merge($data, $extradata);
        }

        if ($sequence) {
            foreach ($sequence as $key => $linkitem) {
                // Items may have changed since the sequence was last edited.
                if(isset($linkitems[$linkitem])) {
                    $sortedlinkitems[] = $linkitems[$linkitem];
                    unset($linkitems[$linkitem]);
                } else {
                    unset($sequence[$key]);
                    $deletefromsequence = true;
                }
            }            
        }

        if (count($linkitems) || $deletefromsequence) {
            // Add the remaining linkitems to the sequence and update the 
            // setting.
            $addsequence = array_keys($linkitems);
            $sequence = array_merge($sequence, $addsequence);
            $value = join(',', $sequence);
            $this->culconfig['quicklinksequence'] = $value;

            if (!\core\session\manager::is_loggedinas() && !is_role_switched($this->course->id)) {
                // Don't update preference if user is logged in someone else.
                $this->culconfigchanged = true;
            }

            // Remove the associative keys from any remaining items as
            // mustache does not like them.
            $linkitems = array_values($linkitems);
            // Merge any remaining items in case they have changed since sequence
            // was last edited.
            $sortedlinkitems = array_merge($sortedlinkitems, $linkitems);
        }

        return $sortedlinkitems;
    }

    /**
     * 
     *
     * @param stdClass $course
     * @return
     */
    public function activity_modules_display($course) {
        global $CFG, $USER, $OUTPUT;

        require_once($CFG->dirroot . '/course/lib.php');

        $modinfo = get_fast_modinfo($course);
        $modfullnames = [];
        $archetypes = [];
        $activities = [];
        $ltiactivities = [];
        $sortedactivities = [];
        $sequence = [];
        $deletefromsequence = false;

        if (isset($this->culconfig['activitylinksequence']) && $this->culconfig['activitylinksequence']) {
            $sequence = explode(',', $this->culconfig['activitylinksequence']);
        }

        foreach($modinfo->cms as $cm) {

            // Exclude activities which are not visible or have no link (=label)
            if (!$cm->uservisible or !$cm->has_view()) {
                continue;
            }

            if (array_key_exists($cm->modname, $modfullnames)) {
                continue;
            }

            if (!array_key_exists($cm->modname, $archetypes)) {
                $archetypes[$cm->modname] = plugin_supports('mod', $cm->modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
            }

            if ($archetypes[$cm->modname] == MOD_ARCHETYPE_RESOURCE) {

                if (!array_key_exists('resources', $modfullnames)) {
                    $modfullnames['resources'] = get_string('resources');
                }

            } else {
                $modfullnames[$cm->modname] = $cm->modplural;
            }
        }

        if (!count($modfullnames)) {
            return $sortedactivities;
        }

        \core_collator::asort($modfullnames); // sort by setting if it exists

        foreach ($modfullnames as $modname => $modfullname) {
            if ($modname == 'lti') {
                $ltiactivities = $this->exttools_modules_display($course, $modinfo);
                $activities = array_merge($activities, $ltiactivities);
                continue;
            }

            if ((isset($this->culconfig['show' . $modname]) && $this->culconfig['show' . $modname] == 2)
                || $this->userisediting) 
            {
                $attrs = [];
                $liattrs = [];
                $attrs['title']  = get_string('view-mod', 'format_pgrid', strtolower($modfullname));
                $class = '';
                $editurl = '';
                $editicon = '';
                $editattrs = '';
                $moveurl = '';
                $moveicon = '';
                $moveattrs = '';
                $movetourl = '';
                $movetoicon = '';
                $movetoattrs = '';
                
                if ($this->userisediting) {
                    list($editurl, $editicon, $editattrs) = format_pgrid_get_edit_link(
                        $course->id, 
                        $modname, 
                        $this->culconfig['show' . $modname],
                        $modfullname
                        );

                    list($moveurl, $moveicon, $moveattrs) = format_pgrid_get_move_link(
                        $course->id, 
                        $modname,
                        'activitylink',
                        $modfullname
                        );

                    list($movetourl, $movetoicon, $movetoattrs) = format_pgrid_get_moveto_link(
                        $course->id, 
                        $modname,
                        'activitylink'
                        );
                }

                if ($this->userisediting && ($this->culconfig['show' . $modname] != 2)) {
                        $class = 'linkhidden';                
                }

                if ($modname === 'resources') {
                    $url = new \moodle_url('/course/resources.php', array('id' => $course->id));
                    $icon = $OUTPUT->pix_icon('icon', '', 'mod_page', array('class' => 'iconsmall'));
                } else {
                    // CMDLTWO-603: Exclude activity modules which don't have an index.php (such as Kaltura Video Assignment).
                    if (!file_exists($CFG->dirroot . "/mod/{$modname}/index.php")) {
                        continue;
                    }

                    $url = new \moodle_url('/mod/' . $modname . '/index.php', array('id' => $course->id));
                    $icon = $OUTPUT->pix_icon('icon', '', $modname, array('class' => 'iconsmall'));
                }

                $activities[$modname] = [
                    'name' => $modname,
                    'url' => $url,
                    'icon' => $icon,
                    'text' => $modfullname,
                    'class' => $class,
                    'attrs' => $attrs,
                    'liattrs' => $liattrs,
                    'editurl' => $editurl, 
                    'editicon' => $editicon, 
                    'editattrs' => $editattrs,
                    'moveurl' => $moveurl, 
                    'moveicon' => $moveicon, 
                    'moveattrs' => $moveattrs,
                    'movetourl' => $movetourl,
                    'movetoicon' => $movetoicon,
                    'movetoattrs' => $movetoattrs
                ];
            }            
        }

        if ($sequence) {
            foreach ($sequence as $key => $activity) {
                // Items may have changed since the sequence was last edited.
                if (isset($activities[$activity])) {
                    $sortedactivities[] = $activities[$activity];
                    unset($activities[$activity]);
                } else {
                    unset($sequence[$key]);
                    $deletefromsequence = true;
                }
            }            
        }        

        if (count($activities) || $deletefromsequence) {
            // Add the remaining linkitems to the sequence and update the 
            // setting.
            $addsequence = array_keys($activities);
            $sequence = array_merge($sequence, $addsequence);
            $value = join(',', $sequence);
            $this->culconfig['activitylinksequence'] = $value;

            if (!\core\session\manager::is_loggedinas() && !is_role_switched($this->course->id)) {
                // Don't update preference if user is logged in someone else.
                $this->culconfigchanged = true;
            }

            // Remove the associative keys from any remaining items as
            // mustache does not like them.
            $activities = array_values($activities);
            // Merge any remaining items in case they have changed since sequence
            // was last edited.
            $sortedactivities = array_merge($sortedactivities, $activities);
        }

        return $sortedactivities;
    }

    /**
     * 
     *
     * @param stdClass $course
     * @param course_modinfo $modinfo
     * @return
     */
    public function exttools_modules_display($course, $modinfo) {
        global $CFG, $DB, $OUTPUT;

        require_once($CFG->dirroot. '/mod/lti/locallib.php');

        $content = '';
        $modfullnames = [];
        $cms = $modinfo->get_instances_of('lti');
        $activities = [];

        foreach($cms as $cm) {
            // Exclude activities which are not visible or have no link (=label)
            if (!$cm->uservisible or !$cm->has_view()) {
                continue;
            }            

            $instance = $DB->get_record('lti', array('id' => $cm->instance));
            $type = lti_get_type($instance->typeid);

            if(is_https()) {
                $iconfield = 'secureicon';
            } else {
                $iconfield = 'icon';
            }

            if(!$type) {
                $type = new stdClass();
                $type->id = 0;
                $type->$iconfield = null;
            }

            $nametype = $cm->modname . 'type' . $instance->typeid;

            if (array_key_exists($nametype, $modfullnames)) {
                continue;
            }

            if($type->id) {
                $modfullnames[$nametype] = [
                    'modname' => $type->name,
                    'modfullname' => $type->name,
                    'type' => $type,
                    'icon' => $type->$iconfield
                ];
            } else {
                $modfullnames[$nametype] = [
                    'modname' => $cm->modplural,
                    'modfullname' => $cm->modplural,
                    'type' => $type,
                    'icon' => $type->$iconfield
                ];
            }            
        }

        if (!count($modfullnames)) {
            return '';
        }

        foreach ($modfullnames as $nametype => $modnames) {
            if ((isset($this->culconfig['show' . $nametype]) && $this->culconfig['show' . $nametype] == 2)
                || $this->userisediting) 
            {
                $attrs = [];
                $liattrs = [];
                $attrs['title']  = get_string('view-mod', 'format_pgrid', strtolower($modnames['modfullname']));
                $class = '';
                $editurl = '';
                $editicon = '';
                $editattrs = '';
                $moveurl = '';
                $moveicon = '';
                $moveattrs = '';
                $movetourl = '';
                $movetoicon = '';
                $movetoattrs = '';

                
                if ($this->userisediting) {
                    list($editurl, $editicon, $editattrs) = format_pgrid_get_edit_link(
                            $course->id, 
                            $nametype, 
                            $this->culconfig['show' . $nametype],
                            $modnames['modfullname']
                            );

                    list($moveurl, $moveicon, $moveattrs) = format_pgrid_get_move_link(
                        $course->id, 
                        $nametype,
                        'activitylink',
                        $modnames['modfullname']
                        );

                    list($movetourl, $movetoicon, $movetoattrs) = format_pgrid_get_moveto_link(
                        $course->id, 
                        $nametype,
                        'activitylink'
                        );
                }

                if ($this->userisediting && ($this->culconfig['show' . $nametype] != 2)) {
                        $class = 'linkhidden';                
                }

                $url = new \moodle_url('/course/format/pgrid/dashboard/ltiindex.php', array('id' => $course->id, 'typeid' => $modnames['type']->id));

                if (!$modnames['type']->icon) {
                    $icon = $OUTPUT->pix_icon('icon', '', 'mod_lti', array('class' => 'iconsmall'));
                } else {
                    $icon = \html_writer::empty_tag('img', array('src' => $modnames['type']->icon, 'alt' => $modnames['type']->name, 'class' => 'iconsmall'));
                }

                $activities[$nametype] = [
                    'name' => $nametype,
                    'url' => $url,
                    'icon' => $icon,
                    'text' => $modnames['modfullname'],
                    'class' => $class,
                    'attrs' => $attrs,
                    'liattrs' => $liattrs,
                    'editurl' => $editurl, 
                    'editicon' => $editicon, 
                    'editattrs' => $editattrs,
                    'moveurl' => $moveurl, 
                    'moveicon' => $moveicon, 
                    'moveattrs' => $moveattrs,
                    'movetourl' => $movetourl,
                    'movetoicon' => $movetoicon,
                    'movetoattrs' => $movetoattrs
                ];
            }
        }

        return $activities;
    }

    public function show_is_moving(&$links, $type) {
        global $USER;

        $fn = 'ismoving' . $type;
        $name = $type . 'copy';

        // check if we are currently in the process of moving a link with JavaScript disabled
        $ismoving = $this->userisediting && $fn($this->course->id);

        if ($ismoving) {
            // $movingpix = new \pix_icon('movehere', get_string('movehere'), 'moodle', array('class' => 'movetarget'));
            // $strmovefull = strip_tags(get_string("movefull", "", "'$USER->linkcopy'"));

            foreach ($links as $key => $link) {
                if ($link['name'] == $USER->$name) {
                    unset($links[$key]);
                    $links = array_values($links);
                    break;               
                }
            }            
        }

        return $ismoving;
    }


    /**
     * Show if something is on on the course clipboard (moving around)
     *
     * @param stdClass $course The course entry from DB
     * @param int $sectionno The section number in the coruse which is being dsiplayed
     * @return string HTML to output.
     */
    protected function dashboard_clipboard($type) {
        global $USER;

        $fn = 'ismoving' . $type;
        $setting = $type . 'sequence';
        $name = $type . 'copy';

        // If currently moving a file then show the current clipboard.
        if ($fn($this->course->id)) {
            $cancel = new stdClass();

            $cancel->url = new \moodle_url(
                '/course/format/pgrid/dashboard/dashlink_edit.php',
                [
                    'courseid' => $this->course->id,
                    'action' => MOVE,
                    'sesskey' => sesskey(),
                    'cancelcopy' => true,
                    'name' => $setting
                ]
            );

            if (get_string_manager()->string_exists($USER->$name, 'format_pgrid')) {
                $cancel->name = get_string($USER->$name, 'format_pgrid');
            } else if (get_string_manager()->string_exists('pluginname', 'mod_' . $USER->$name)) {
                $cancel->name = get_string('pluginname', 'mod_' . $USER->$name);
            } else {
                $cancel->name = $USER->$name;
            }       

            return $cancel;
        } 
            
        return false;
    }    
}
