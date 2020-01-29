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
 * Renderer for outputting the format_pgrid.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/format/renderer.php');

use format_pgrid\output\dashboard;

/**
 * Basic renderer for topics format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_pgrid_renderer extends format_section_renderer_base {

    /** @var array format Settings for the format
    */
    private $culconfig;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $this->page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        global $COURSE;

        parent::__construct($page, $target);

        $this->culconfig = course_get_format($COURSE)->get_format_options();

        // @TODO
        // Since format_pgrid_renderer::section_edit_controls() only displays the 'Set current section' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing capability.
        $this->page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function build_dashboard() {
        global $COURSE;

        $o = '';
        $dashboard = new dashboard($COURSE, $this->culconfig);
        $templatecontext = $dashboard->export_for_template($this);
        $o .= $this->render_from_template('format_pgrid/dashboard', $templatecontext);

        return $o;
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', ['class' => 'pgrid']);
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    } 

    /**
     * Generate the content to displayed on the right part of a section
     * before course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_right_content($section, $course, $onsectionpage) {
        $o = '';
        $controls = $this->section_edit_control_items($course, $section, $onsectionpage);

        if (!empty($controls)) {
            $o = implode('', $controls);
            $o = html_writer::div(
            $o, 
                'section_action_menu',
                ['data-sectionid' => $section->id]
            );
        }       

        return $o;
    }

    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $title = get_section_name($course, $section);       

        $o .= html_writer::start_tag(
            'li', 
            [
                'id' => 'section-'.$section->section,
                'class' => 'section main clearfix'.$sectionstyle, 
                'role'=>'region',
                'aria-label'=> get_section_name($course, $section)
            ]
        );

        // $o .= html_writer::link(
        //     "#endofsection-{$section->section}",
        //     get_string('skipsection', 'format_pgrid', $title),
        //     ['class' => 'sr-only sr-only-focusable']
        // );

        // Create a span that contains the section title to be used to create the keyboard section move menu.
        $o .= html_writer::tag(
            'span', 
            get_section_name($course, $section), 
            ['class' => 'hidden sectionname']
        );

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o .= html_writer::tag(
            'div', 
            $leftcontent, 
            ['class' => 'left side']
        );

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o .= html_writer::tag(
            'div', 
            $rightcontent, 
            ['class' => 'right side']
        );
        $o .= html_writer::start_tag('div', ['class' => 'content']);

        // When not on a section page, we display the section titles except the general section if null.
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one.
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));
        $classes = ' accesshide';

        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }

        $ariapressed = 'true';

        if ($section->section != 0) {
            // user_preference_allow_ajax_update('format_pgrid_expanded' . $section->id, PARAM_INT);
            // $userpref = 'format_pgrid_expanded' . $section->id;
            $attributes = [
                'class' => 'sectionhead toggle',
                'id' => 'toggle-' . $section->id,
                'data-toggle' => 'collapse',
                'data-target' => '.course-content #togglesection-' . $section->id,
                'role' => 'button', 
                'aria-pressed' => $ariapressed
            ];

            if ($onsectionpage) {
                $attributes['class'] = 'toggle';
                $attributes['data-toggle'] = '';
                $attributes['data-target'] = '';
            }

            $o .= html_writer::start_tag(
                'div',
                $attributes
            );

            $sectionname = html_writer::tag('span', $this->section_title($section, $course));
            $o .= $this->output->heading($sectionname, 3, 'sectionname' . $classes);
            $o .= $this->section_availability($section);

            
            if ($this->culconfig['showsectionsummary'] == 2) {
                $o .= $this->section_summary_container($section, $onsectionpage);
            }

            if(!$onsectionpage) {
                $o .= $this->section_activity_summary($section, $course, null);
            }

            $o .= html_writer::end_tag('div'); // .sectionhead.

            $attributes = [
                'class' => 'sectionbody togglesection collapse show',
                'id' => 'togglesection-' . $section->id,
                'data-preference-key' => $section->id
            ];

            if ($onsectionpage) {
                $attributes['class'] = 'sectionbody';
            }

            $o .= html_writer::start_tag(
                'div',
                $attributes
            );

            if ($this->culconfig['showsectionsummary'] == 1) {
                $o .= $this->section_summary_container($section, $onsectionpage);
            }

        }  else {
            $o .= html_writer::tag('div', '', ['class' => 'summary']); //@TODO
        }

        return $o;
    }  

    /**
     * Generate the display of the footer part of a section
     *
     * @param stdClass $course The course entry from DB
     * @param int $section The section order number
     * @param stdClass $thissection The course_section entry from DB
     * @param stdClass $context 
     * @param bool $onsectionpage true if being printed on a section page

     * @return string HTML to output.
     */
    protected function injected_section_footer($course, $section, $context, $thissection, $onsectionpage) {
        $o = '';
        $ariapressed = 'true';

        if ($section != 0) {
            $o .= html_writer::end_tag('div'); // .sectionbody.

            $attributes = [
                'class' => 'sectionclose',
                'id' => 'footertoggle-' . $thissection->id,
                'href' => '',
                'data-toggle' => 'collapse',
                'data-target' => '.course-content #togglesection-' . $thissection->id,
                'role' => 'button', 
                'aria-pressed' => $ariapressed
            ];

            if ($onsectionpage) {
                $attributes['class'] = 'toggle';
                $attributes['data-toggle'] = '';
                $attributes['data-target'] = '';
            }

            $o .= html_writer::start_tag(
                'a',
                $attributes
            );

            $o .= get_string('closesection', 'format_pgrid');
            $o .= html_writer::end_tag('a');
        }

        $o .= html_writer::end_tag('div'); // .content.

        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {                    
            $o .= $this->insert_section($course, $section + 1);
        }

        // $o .= html_writer::tag('span', '', ['id' => "endofsection-{$thissection->section}"]);

        $o .= html_writer::end_tag('li');        

        return $o;
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {

        if (!$this->page->user_is_editing()) {
            return [];
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }

        $url->param('sesskey', sesskey());
        $controls = [];
        $culcontrols = [];

        if ($this->culconfig['baseclass'] == FORMATTOPICS) {
            if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
                if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                    $url->param('marker', 0);
                    $markedthistopic = get_string('markedthistopic');
                    $highlightoff = get_string('highlightoff');
                    $controls['highlight'] = [
                        'url' => $url, 
                        'icon' => 'i/marked',
                        'component' => 'moodle',
                        'name' => $highlightoff,
                        'pixattr' => ['class' => '', 'alt' => $markedthistopic],
                        'attr' => [
                            'class' => 'icon ', 
                            // 'title' => $markedthistopic,
                            'data-action' => 'removemarker'
                        ]
                    ];
                } else {
                    $url->param('marker', $section->section);
                    $markthistopic = get_string('markthistopic');
                    $highlight = get_string('highlight');
                    $controls['highlight'] = [
                        'url' => $url, 
                        'icon' => 'i/marker',
                        'component' => 'moodle',
                        'name' => $highlight,
                        'pixattr' => ['class' => '', 'alt' => $markthistopic],
                        'attr' => [
                            'class' => 'icon ', 
                            // 'title' => $markthistopic,
                            'data-action' => 'setmarker'
                        ]
                    ];
                }
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = [];
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;

                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

        } else {
            $merged = array_merge($controls, $parentcontrols);
        }

        foreach ($merged as $key => $item) {
            $url = empty($item['url']) ? '' : $item['url'];
            $icon = empty($item['icon']) ? '' : $item['icon'];
            $component = empty($item['component']) ? 'moodle' : $item['component'];
            $name = empty($item['name']) ? '' : $item['name'];
            $attr = empty($item['attr']) ? '' : $item['attr'];
            // CMDLTWO-1649 Fixing WAVE alerts (Redundant title text).
            unset($attr['title']);
            $class = empty($item['pixattr']['class']) ? '' : $item['pixattr']['class'];
            $alt = empty($item['pixattr']['alt']) ? '' : $item['pixattr']['alt'];
            $url = new moodle_url($url);
            $icon = $this->output->pix_icon($icon, $alt, $component, $item['attr']);
            $screenreadertxt = html_writer::tag('span', $name, ['class' => 'sr-only']);

            $link = html_writer::link(
                    $url,
                    $icon . $screenreadertxt,
                    $attr
                );            

            $culcontrols[] = $link;
        }

        return $culcontrols;        
    }

    /**
     * Generate a summary of a section for display on the 'course index page'
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param array    $mods (argument not used)
     * @return string HTML to output.
     */
    protected function section_summary($section, $course, $mods) {
        $classattr = 'section section-summary clearfix';
        $linkclasses = '';

        // If section is hidden then display grey section link
        if (!$section->visible) {
            $classattr .= ' hidden';
            $linkclasses .= ' dimmed_text';
        } else if (course_get_format($course)->is_section_current($section)) {
            $classattr .= ' current';
        }

        $o = '';
        $title = get_section_name($course, $section);        

        $o .= html_writer::start_tag(
            'li', 
            [
                'id' => 'section-' . $section->section,
                'class' => $classattr, 
                'role'=>'region', 
                'aria-label'=> $title
            ]
        );

        // $o .= html_writer::link(
        //     "#endofsection-{$section->section}",
        //     get_string('skipsection', 'format_pgrid', $title),
        //     ['class' => 'sr-only sr-only-focusable']
        // );

        $o .= html_writer::tag('div', '', ['class' => 'left side']);
        $o .= html_writer::tag('span', '', ['class' => 'hidden sectionname']);
        $o .= html_writer::tag('div', '', ['class' => 'right side']);
        $o .= html_writer::start_tag('div', ['class' => 'content']);

        if ($section->uservisible) {
            $title = html_writer::tag(
                'a', 
                $title,
                [
                    'href' => course_get_url($course, $section->section), 
                    'class' => $linkclasses
                ]
            );
        }

        $o .= $this->output->heading($title, 3, 'section-title');
        $o .= html_writer::start_tag('div', ['class' => 'summarytext']);
        $o .= $this->format_summary_text($section);
        $o .= html_writer::end_tag('div');
        $o .= $this->section_activity_summary($section, $course, null);
        $o .= html_writer::end_tag('div');
        // $o .= html_writer::tag('span', '', ['id' => "endofsection-{$section->section}"]);
        $o .= html_writer::end_tag('li');        

        return $o;
    }

   /**
     * Generate a summary of the activites in a section
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course the course record from DB
     * @param array    $mods (argument not used)
     * @return string HTML to output.
     */
    protected function section_activity_summary($section, $course, $mods) {
        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->sections[$section->section])) {
            return '';
        }

        // Generate array with count of activities in this section:
        $sectionmods = [];
        $resource = get_string('resources');
        $activity = get_string('activities');
        $total = 0;
        $complete = 0;
        $cancomplete = isloggedin() && !isguestuser();
        $completioninfo = new completion_info($course);

        foreach ($modinfo->sections[$section->section] as $cmid) {
            $thismod = $modinfo->cms[$cmid];

            if ($thismod->modname == 'label') {
                // Labels are special (not interesting for students)!
                continue;
            }

            if ($thismod->uservisible) {
                if (plugin_supports('mod', $thismod->modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER) == MOD_ARCHETYPE_RESOURCE) {
                    $type = $resource;
                } else {
                    $type = $activity;
                }

                if (isset($sectionmods[$type]['name'])) {
                    $sectionmods[$type]['count']++;
                } else {
                    $sectionmods[$type]['name'] = $type;
                    $sectionmods[$type]['count'] = 1;
                }

                if ($cancomplete && $completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
                    $total++;
                    $completiondata = $completioninfo->get_data($thismod, true);

                    if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                            $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $complete++;
                    }
                }
            }
        }

        asort($sectionmods);

        if (empty($sectionmods)) {
            // No sections.
            return '';
        }

        // Output section activities summary:
        $o = '';
        $o .= html_writer::start_tag('div', ['class' => 'section-summary-activities']);

        foreach ($sectionmods as $mod) {
            $o .= html_writer::start_tag('span', ['class' => 'activity-count']);
            $o .= $mod['name'].': '.$mod['count'];
            $o .= html_writer::end_tag('span');
        }

        $o .= html_writer::end_tag('div');

        // Output section completion data.
        if ($total > 0) {
            $a = new stdClass;
            $a->complete = $complete;
            $a->total = $total;

            $o .= html_writer::start_tag('div', ['class' => 'section-summary-progress']);
            $o .= html_writer::tag('span', get_string('progresstotal', 'completion', $a), ['class' => 'activity-count']);
            $o .= html_writer::end_tag('div');
        }

        return $o;
    }    

    /**
     * Output the html for the main course home page as a grid
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    private function print_pearson_grid($course, $sections, $mods, $modnames, $modnamesused) {
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);
        $numsections = course_get_format($course)->get_last_section_number();

        // Now the dashboard.
        echo $this->build_dashboard();

        $sections = $modinfo->get_section_info_all();
        $thissection = array_shift($sections);
        // $thissection is currently section-0 which is handled specially.
        if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
            // Since we are not using ul, replace the li that section_header
            // outputs with div
            echo str_replace('<li', '<div', $this->section_header($thissection, $course, false, 0));

            echo html_writer::start_tag('div', ['class'=>'topsection-wrap d-flex flex-wrap align-items-stretch']);

            $title = get_section_name($course, 0);
            $sectionsummary = $this->output->heading($title, 3, 'section-title');
            $sectionsummary .= $this->section_summary_container($thissection);
            $class = '';

            $sectioncm = $this->courserenderer->course_section_cm_list($course, $thissection, 0);

            echo html_writer::tag('div', $sectionsummary, ['class'=>'course-summary col p-3 bg-light']);
            echo html_writer::tag('div', $sectioncm, ['class'=>'col-12 '.$class.' px-3 pb-3 bg-light']);

            echo html_writer::end_tag('div');

            echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);

            // Since we are not using ul, replace the /li that
            // injected_section_footer outputs with /div
            echo str_replace('</li>', '</div>', $this->injected_section_footer($course, 0, $context, $thissection, false));
        }
        // Render as a ul, using css to turn into a responsive grid
        echo html_writer::start_tag('div', array('class' => 'weeks-holder'));
        echo html_writer::start_tag('div', array('class' => 'weeks-layout'));
        echo html_writer::start_tag('h3', array('class' => 'sectionnamex'));
        echo 'Weeks';
        $journalsurl = new moodle_url('/mod/journal/index.php', array('id' => $course->id));
        echo html_writer::start_tag('span', array('class' => 'pucl-journals-link float-right'));
        echo html_writer::start_tag('a', array('href' => $journalsurl->out(), 'alt' => 'List of Journals in this course'));
        echo html_writer::img(new moodle_url('/theme/image.php/cul_boost/format_culcourse/-1/JournalWhite'), '');
        echo html_writer::end_tag('a');
        echo html_writer::end_tag('span');
        echo html_writer::end_tag('h3');
        echo html_writer::start_tag('ul');
        foreach ($sections as $thissection) {
            echo $this->section_summary($thissection, $course, null);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
        echo $this->end_section_list();
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        if (!$this->page->user_is_editing()) {
            $this->print_pearson_grid($course, $sections, $mods, $modnames, $modnamesused);
            return;
        }

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();
        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);

        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');
        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);
        // Now the dashboard.
        echo $this->build_dashboard();
        // Now the list of sections..
        echo $this->start_section_list();

        $numsections = course_get_format($course)->get_last_section_number();       

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);

                    echo html_writer::start_tag('div', ['class'=>'topsection-wrap d-flex flex-wrap align-items-stretch']);

                    $title = get_section_name($course, $section);
                    $sectionsummary = $this->output->heading($title, 3, 'section-title');
                    $sectionsummary .= $this->section_summary_container($thissection);
                    $class = '';

                    $sectioncm = $this->courserenderer->course_section_cm_list($course, $thissection, 0);

                    echo html_writer::tag('div', $sectionsummary, ['class'=>'course-summary col p-3 bg-light']);
                    echo html_writer::tag('div', $sectioncm, ['class'=>'col-12 '.$class.' px-3 pb-3 bg-light']);

                    echo html_writer::end_tag('div');

                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);

                    echo $this->injected_section_footer($course, $section, $context, $thissection, false);
                }
                continue;
            }

            if (($this->page->user_is_editing() || $course->coursedisplay == COURSE_DISPLAY_SINGLEPAGE) 
                && $numsections > 1 && $section == 1)
            {                
                // Collapse/Expand all.
                echo $this->toggle_all();
            }

            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available.
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available &&
                    !empty($thissection->availableinfo));

            if (!$showsection) {
                // If the hiddensections option is set to 'show hidden sections in collapsed
                // form', then display the hidden section message - UNLESS the section is
                // hidden by the availability system, which is set to hide the reason.
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section, $course->id);
                }

                continue;
            }

            if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);

                if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }

                // We don't insert a section at the end. We allow user to append multiple sections instead.
                // if ($numsections != $section) {
                    echo $this->injected_section_footer($course, $section, $context, $thissection, false);
                // }
            }
        }        

        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }

                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();
	    $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }        
    }

    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        global $PAGE;
        if ($this->page->user_is_editing()) {
            parent::print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection);
            return;
        }

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection)) || !$sectioninfo->uservisible) {
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            print_error('unknowncoursesection', 'error', course_get_url($course),
                format_string($course->fullname));
        }

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, $displaysection);

        // Now the dashboard.
        echo $this->build_dashboard();

        // Start single-section div
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // And another div for centering purposes
        echo html_writer::start_tag('div', array('class' => 'contentsblock'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-navigation navigationtitle'));
        $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
        $sectiontitle .= html_writer::start_tag('span', array('class' => 'pucl-weeks-back-link mdl-left'));
        $sectiontitle .= html_writer::tag('a', '&lt; Weeks', array('href' => $courseurl->out(), 'alt' => 'Back to weeks page'));
        $sectiontitle .= html_writer::end_tag('span');
        $journalsurl = new moodle_url('/mod/journal/index.php', array('id' => $course->id));
        $sectiontitle .= html_writer::start_tag('span', array('class' => 'pucl-journals-link mdl-right'));
        $sectiontitle .= html_writer::start_tag('a', array('href' => $journalsurl->out(), 'alt' => 'List of Journals in this course'));
        $sectiontitle .= html_writer::img(new moodle_url('/theme/image.php/cul_boost/format_culcourse/-1/JournalWhite'), '');
        $sectiontitle .= html_writer::end_tag('a');
        $sectiontitle .= html_writer::end_tag('span');

        // Title attributes
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }
        $sectionname = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');
        echo $sectiontitle;

        // Layout a section
        if (!empty($modinfo->sections[$thissection->section])) {
            $currentlevel = -1;
            echo html_writer::start_tag('ul', array('class' => 'pucl-topic-level'));
            $nummods = count($modinfo->sections[$thissection->section]);
            for ($i = 0; $i < $nummods;) {
                $mod = $modinfo->cms[$modinfo->sections[$thissection->section][$i]];
                if ($mod->indent != 0) { break; }
                $i = $this->print_topic($course, $modinfo, $thissection, $i, $nummods);
            }
            echo html_writer::end_tag('ul');
        }
        // Close contents block div
        echo html_writer::end_tag('div');
        // Close single-section div.
        echo html_writer::end_tag('div');
        // Output toggle jquery
        echo html_writer::script('$(\'.pucl-toggle\').click(function(e) {
            e.preventDefault();
            var $this = $(this);
            if ($this.next().hasClass(\'show\')) {
                $this.next().removeClass(\'show\');
                $this.next().slideUp(350);
                $this.find(\'i\').removeClass(\'fa-chevron-up\').addClass(\'fa-chevron-down\');
            } else {
                $this.parent().parent().find(\'li .pucl-collection-parts-level\').removeClass(\'show\');
                $this.parent().parent().find(\'li .pucl-collection-parts-level\').slideUp(350);
                $this.parent().parent().find(\'li .pucl-collection-parts-level\').prev().find(\'i\').removeClass(\'fa-chevron-up\').addClass(\'fa-chevron-down\');
                $this.next().toggleClass(\'show\');
                $this.next().slideToggle(350);
                $this.find(\'i\').removeClass(\'fa-chevron-down\').addClass(\'fa-chevron-up\');
            }
        });');
    }

    protected function print_topic($course, $modinfo, $section, $i, $nummods) {
        $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
        echo html_writer::start_tag('li', array('class' => 'pucl-topic'));
        // Output topic heading
        echo html_writer::start_tag('a', array('class' => 'pucl-toggle'));
        echo html_writer::start_tag('div', array('class' => 'pucl-topic-heading'));
        if ($i === 0) {
            echo '<i class="fa fa-chevron-up" aria-hidden="true"></i>&nbsp;';
        } else {
            echo '<i class="fa fa-chevron-down" aria-hidden="true"></i>&nbsp;';
        }
        echo $mod->name;
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('a');
        // Start prep for next level
        if ($i === 0) {
            echo html_writer::start_tag('ul', array('class' => 'pucl-collection-parts-level show'));
        } else {
            echo html_writer::start_tag('ul', array('class' => 'pucl-collection-parts-level', 'style' => 'display: none;'));
        }
        $i++;
        for (;$i < $nummods; ) {
            $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
            if ($mod->indent != 1) { break; }
            $i = $this->print_parts($course, $modinfo, $section, $i, $nummods);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
        return $i;
    }

    protected function print_parts($course, $modinfo, $section, $i, $nummods) {
        $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
        echo html_writer::start_tag('li', array('class' => 'pucl-collection-parts'));
        // Output collection of parts heading
        echo html_writer::start_tag('div', array('class' => 'pucl-collection-parts-heading'));
        echo $mod->name;
        echo html_writer::end_tag('div');
        // Start prep for next level
        echo html_writer::start_tag('ul', array('class' => 'pucl-parts-level'));
        $i++;
        for (;$i < $nummods; ) {
            $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
            if ($mod->indent != 2) { break; }
            $i = $this->print_parts_contents($course, $modinfo, $section, $i, $nummods);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
        return $i;
    }

    protected function print_parts_contents($course, $modinfo, $section, $i, $nummods) {
        $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
        if ($mod->indent != 2) { return $i; }

        $completioninfo = new completion_info($course);

        echo html_writer::start_tag('li', array('class' => 'pucl-part'));
        echo html_writer::start_tag('div', array('class' => 'pucl-part-box'));
        echo $this->courserenderer->course_section_cm_name($mod, array());
        echo $this->courserenderer->course_section_cm_completion($course, $completioninfo, $mod, array());
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('li');

        return $i+1;
    }

    /**
     * Returns button to insert section.
     *
     * @param stdClass $course
     * @param int|null $sectionreturn
     * @return string
     */
    protected function insert_section($course, $section = 0) {
        $o = '';
        $coursecontext = context_course::instance($course->id);

        if (course_get_format($course)->uses_sections()) {
            // Current course format does not have 'numsections' option but it has multiple sections suppport.
            // Display the "Add section" link that will insert a section in the end.
            // Note to course format developers: inserting sections in the other positions should check both
            // capabilities 'moodle/course:update' and 'moodle/course:movesections'.

            $o .= html_writer::start_tag('div', ['class' => 'add_section mdl-align']);

            if (get_string_manager()->string_exists('insertsection', 'format_'.$course->format)) {
                $strinsertsection = get_string('insertsection', 'format_'.$course->format);
            } else {
                $strinsertsection = get_string('insertsection');
            }

            $url = new moodle_url(
                '/course/changenumsections.php',
                [
                    'courseid' => $course->id, 
                    'insertsection' => $section,
                    'sectionreturn' => 0,
                    'sesskey' => sesskey()
                ]
            );

            $addsectionbutton = new single_button($url, $strinsertsection, 'get');
            $addsectionbutton->class = 'sectionbutton btn-city';
            $o .= $this->output->render($addsectionbutton);
            $o .= html_writer::end_tag('div');
        }

        return $o;
    } 

    /**
     * Returns controls in the bottom of the page to increase/decrease number of sections
     *
     * @param stdClass $course
     * @param int|null $sectionreturn
     * @return string
     */
    protected function change_number_sections($course, $sectionreturn = null) {
        $coursecontext = context_course::instance($course->id);
        if (!has_capability('moodle/course:update', $coursecontext)) {
            return '';
        }

        // Current course format does not have 'numsections' option but it has multiple sections suppport.
        // Display the "Add section" link that will insert a section in the end.
        // Note to course format developers: inserting sections in the other positions should check both
        // capabilities 'moodle/course:update' and 'moodle/course:movesections'.

        echo html_writer::start_tag('div', ['id' => 'changenumsections', 'class' => 'add_section mdl-align']);
        if (get_string_manager()->string_exists('addsections', 'format_'.$course->format)) {
            $straddsections = get_string('addsections', 'format_'.$course->format);
        } else {
            $straddsections = get_string('addsections');
        }
        $url = new moodle_url('/course/changenumsections.php',
            ['courseid' => $course->id, 'insertsection' => 0, 'sesskey' => sesskey()]);
        if ($sectionreturn !== null) {
            $url->param('sectionreturn', $sectionreturn);
        }
        // $icon = $this->output->pix_icon('t/add', $straddsections);
        echo html_writer::link($url, $straddsections,
            array('class' => 'btn btn-secondary add-sections', 'data-add-sections' => $straddsections));
        echo html_writer::end_tag('div');
    }     

   /**
     * generate truncated html for a section summary text
     *
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function truncate_summary_text($section) {
        $context = context_course::instance($section->course);
        $summarytext = file_rewrite_pluginfile_urls($section->summary, 'pluginfile.php',
            $context->id, 'course', 'section', $section->id);
        $extraclass = '';

        if (strlen($summarytext) > 250) {
            $extraclass = ' truncated';
        }

        $options = new stdClass();
        $options->noclean = false;
        $options->overflowdiv = false;
        $summarytext = format_text($summarytext, $section->summaryformat, $options);
        $summarytext = html_writer::tag('div', $summarytext);
        $summarytext = html_writer::tag('div', $summarytext, ['class' => 'truncate' . $extraclass]);

        return $summarytext;
    }

    protected function section_summary_container($section, $onsectionpage = false) {
        $summarytext = $this->format_summary_text($section);

        if ($summarytext) {
            $classextra = ($this->culconfig['showsectionsummary'] == 1) ? '' : ' summaryalwaysshown';
            $o = html_writer::start_tag('div', ['class' => 'summary' . $classextra]);
            $o .= $summarytext;

            if (!$onsectionpage && $section->section > 0 && $this->culconfig['showsectionsummary'] == 2) {
                $o .= $this->truncate_summary_text($section);
            }

            $o .= html_writer::end_tag('div');
        } else {
            $o = '';
        }

        return $o;
    }    
    /**
     * Displays the toggle all functionality.
     * @return string HTML to output.
     */
    protected function toggle_all() {
        $o = html_writer::start_tag(
            'li', 
            ['class' => 'clearfix', 'id' => 'toggle-all']
        );

        if ($this->page->user_is_editing()) {
            $o .= html_writer::tag(
                'div', 
                $this->output->spacer(), 
                ['class' => 'left side']
            );
            $o .= html_writer::tag(
                'div', 
                $this->output->spacer(), 
                ['class' => 'right side']
            );
        }

        $o .= html_writer::start_tag('div', ['class' => 'content']);
        $iconsetclass = 'toggle';
        $o .= html_writer::start_tag('div', ['class' => $iconsetclass]);
        $o .= html_writer::start_tag('h4', null);
        $o .= html_writer::tag(
            'a', 
            get_string('pgridopened', 'format_pgrid'),
            ['class' => 'on btn btn-primary ', 'href' => '#', 'id' => 'toggles-all-opened']
        );
        $o .= html_writer::tag(
            'a', 
            get_string('pgridclosed', 'format_pgrid'),
            ['class' => 'off btn btn-primary ', 'href' => '#', 'id' => 'toggles-all-closed']
        );
        $o .= html_writer::end_tag('h4');
        $o .= html_writer::end_tag('div');
        $o .= html_writer::end_tag('div');
        $o .= html_writer::end_tag('li');

        return $o;
    }
}
