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
 * Renderer for outputting the pgrid course format.
 *
 * @package format_pgrid
 * @copyright 2020 CAPDM Ltd (https://www.capdm.com)
 * @copyright based on work by 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for pgrid format.
 *
 * @copyright 2020 CAPDM Ltd (https://www.capdm.com)
 * @copyright based on work by 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_pgrid_renderer extends format_section_renderer_base {

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_pgrid_renderer::section_edit_control_items() only displays the 'Highlight' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other
        // managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'pgrid'));
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
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;

        if ($PAGE->user_is_editing()) {
            // Use default rendering when editing.
            // Now the dashboard.
            echo $this->build_dashboard($course);
            parent::print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
            return;
        }

        // Now the dashboard.
        echo $this->build_dashboard($course);

        $this->print_pearson_grid($course, $sections, $mods, $modnames, $modnamesused);
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
        global $CFG, $PAGE;

        if ($PAGE->user_is_editing()) {
            // Now the dashboard.
            echo $this->build_dashboard($course, $displaysection);
            // Use default rendering when editing.
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
        echo $this->build_dashboard($course, $displaysection);

        // Start single-section div.
        echo html_writer::start_tag('div', array('class' => 'single-section pgrid'));

        // And another div for centering purposes.
        echo html_writer::start_tag('div', array('class' => 'contentsblock'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-navigation navigationtitle'));
        $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
        $sectiontitle .= html_writer::start_tag('span', array('class' => 'pucl-weeks-back-link mdl-left'));
        $sectiontitle .= html_writer::tag('a', '&lt; '.get_string('weeks', 'format_pgrid'),
            array('href' => $courseurl->out(), 'aria-label' => get_string('backtoweeks', 'format_pgrid')));
        $sectiontitle .= html_writer::end_tag('span');
        if (file_exists($CFG->dirroot . "/mod/journal/index.php")) {
            $journalsurl = new moodle_url('/mod/journal/index.php', array('id' => $course->id));
            $sectiontitle .= html_writer::start_tag('span', array('class' => 'pucl-journals-link mdl-right'));
            $sectiontitle .= html_writer::start_tag('a', array('href' => $journalsurl->out(),
                                                               'title' => get_string('journalslist', 'format_pgrid'),
                                                               'aria-label' => get_string('journalslist', 'format_pgrid')));
            $sectiontitle .= html_writer::img($this->output->image_url('JournalWhite', 'format_pgrid'), '');
            $sectiontitle .= html_writer::end_tag('a');
            $sectiontitle .= html_writer::end_tag('span');
        }

        // Title attributes.
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }
        $sectionname = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');
        echo $sectiontitle;

        // Layout a section.
        if (!empty($modinfo->sections[$thissection->section])) {
            $currentlevel = -1;
            echo html_writer::start_tag('ul', array('class' => 'pucl-topic-level'));
            $nummods = count($modinfo->sections[$thissection->section]);
            for ($i = 0; $i < $nummods;) {
                $mod = $modinfo->cms[$modinfo->sections[$thissection->section][$i]];
                if ($mod->indent != 0) {
                    break;
                }
                $i = $this->print_topic($course, $modinfo, $thissection, $i, $nummods);
            }
            echo html_writer::end_tag('ul');
        }
        // Close contents block div.
        echo html_writer::end_tag('div');
        // Close single-section div.
        echo html_writer::end_tag('div');
    }

    /**
     * Output the html for a topic within a section.
     * A topic is a label at 0 indent.
     *
     * @param stdClass $course The course entry from DB
     * @param course_modinfo $modinfo The modinfo for the course
     * @param section_info $section The current section
     * @param int $i Current module index - the topic label
     * @param int $nummods Count of modules within this section
     * @return int New index into section modules after handling this topic
     */
    protected function print_topic($course, $modinfo, $section, $i, $nummods) {
        $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
        echo html_writer::start_tag('li', array('class' => 'pucl-topic'));
        // Output topic heading.
        echo html_writer::start_tag('a', array('class' => 'pucl-toggle',
                                               'aria-expanded' => ($i === 0)?"true":"false"));
        echo html_writer::start_tag('div', array('class' => 'pucl-topic-heading'));
        if ($i === 0) {
            echo '<i class="fa fa-chevron-up" aria-hidden="true"></i>&nbsp;';
        } else {
            echo '<i class="fa fa-chevron-down" aria-hidden="true"></i>&nbsp;';
        }
        echo $mod->name;
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('a');
        // Start prep for next level.
        if ($i === 0) {
            echo html_writer::start_tag('ul', array('class' => 'pucl-collection-parts-level show'));
        } else {
            echo html_writer::start_tag('ul', array('class' => 'pucl-collection-parts-level', 'style' => 'display: none;'));
        }
        $i++;
        for (; $i < $nummods;) {
            $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
            if ($mod->indent != 1) {
                break;
            }
            $i = $this->print_parts($course, $modinfo, $section, $i, $nummods);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
        return $i;
    }

    /**
     * Output the html for a part within a topic.
     * A part is a label at 1 indent.
     *
     * @param stdClass $course The course entry from DB
     * @param course_modinfo $modinfo The modinfo for the course
     * @param section_info $section The current section
     * @param int $i Current module index - the part label
     * @param int $nummods Count of modules within this section
     * @return int New index into section modules after handling this part
     */
    protected function print_parts($course, $modinfo, $section, $i, $nummods) {
        $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
        echo html_writer::start_tag('li', array('class' => 'pucl-collection-parts'));
        // Output collection of parts heading.
        echo html_writer::start_tag('div', array('class' => 'pucl-collection-parts-heading'));
        echo $mod->name;
        echo html_writer::end_tag('div');
        // Start prep for next level.
        echo html_writer::start_tag('ul', array('class' => 'pucl-parts-level'));
        $i++;
        for (; $i < $nummods;) {
            $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
            if ($mod->indent != 2) {
                break;
            }
            $i = $this->print_parts_contents($course, $modinfo, $section, $i, $nummods);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
        return $i;
    }

    /**
     * Output the html for an activity.
     * An activity is at 2 indent.
     *
     * @param stdClass $course The course entry from DB
     * @param course_modinfo $modinfo The modinfo for the course
     * @param section_info $section The current section
     * @param int $i Current module index - the part label
     * @param int $nummods Count of modules within this section
     * @return int New index into section modules after handling this activity
     */
    protected function print_parts_contents($course, $modinfo, $section, $i, $nummods) {
        $mod = $modinfo->cms[$modinfo->sections[$section->section][$i]];
        if ($mod->indent != 2) {
            return $i;
        }

        // If Recycle bin is being used, and an activity is deleted, then
        // this field will be set to 1, so is in the process of being deleted
        // as a background task, but still exists as a $mod for these purposes,
        // so skip showing a blank whitespace if being deleted.
        if ($mod->deletioninprogress === 0) {

            $completioninfo = new completion_info($course);

            echo html_writer::start_tag('li', array('class' => 'pucl-part'));
            echo html_writer::start_tag('div', array('class' => 'pucl-part-box'));
            if ($mod->uservisible && !$mod->is_stealth()) {
                echo $this->courserenderer->course_section_cm_name($mod, array());
                echo $this->courserenderer->course_section_cm_completion($course, $completioninfo, $mod, array());
            } else {
                // Hidden activity - so display title without a link
                $textclasses = 'instancename dimmed dimmed_text';
                echo html_writer::tag('div', $mod->get_formatted_name(), array('class' => $textclasses));
                echo html_writer::tag('div', get_string('notyetavailable', 'format_pgrid'), array('class' => 'dimmed dimmed_text'));
            }

            echo html_writer::end_tag('div');
            echo html_writer::end_tag('li');
        }
        return $i + 1;
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

        // Generate array with count of activities in this section.
        $sectionmods = array();
        $total = 0;
        $complete = 0;
        $cancomplete = isloggedin() && !isguestuser();
        $completioninfo = new completion_info($course);
        foreach ($modinfo->sections[$section->section] as $cmid) {
            $thismod = $modinfo->cms[$cmid];

            if ($thismod->uservisible) {
                if (isset($sectionmods[$thismod->modname])) {
                    $sectionmods[$thismod->modname]['name'] = $thismod->modplural;
                    $sectionmods[$thismod->modname]['count']++;
                } else {
                    $sectionmods[$thismod->modname]['name'] = $thismod->modfullname;
                    $sectionmods[$thismod->modname]['count'] = 1;
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

        if (empty($sectionmods)) {
            // No sections.
            return '';
        }

        // Output section completion data.
        $o = '';

        if ($total > 0) {
            $a = new stdClass;
            $a->complete = $complete;
            $a->total = $total;

            $o .= html_writer::start_tag('div', array('class' => 'section-summary-progress pr-2 mdl-align'));
            $o .= html_writer::tag('span', get_string('progresstotal', 'completion', $a), array('class' => 'activity-count'));
            $o .= html_writer::end_tag('div');
        }

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
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                                               'name' => $highlightoff,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'removemarker'));
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                                               'name' => $highlight,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'setmarker'));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
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
    protected function print_pearson_grid($course, $sections, $mods, $modnames, $modnamesused) {
        global $CFG;

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

        $sections = $modinfo->get_section_info_all();
        $thissection = array_shift($sections);
        // The variable $thissection is currently section-0 which is handled specially.
        if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
            // Since we are not using ul, replace the li that section_header
            // outputs with div.
            echo str_replace('<li', '<div', $this->section_header($thissection, $course, false, 0));

            echo html_writer::start_tag('div', ['class' => 'topsection-wrap d-flex flex-wrap align-items-stretch']);

            $title = get_section_name($course, 0);
            $sectionsummary = $this->output->heading($title, 3, 'section-title');
            $class = '';

            $sectioncm = $this->courserenderer->course_section_cm_list($course, $thissection, 0);

            echo html_writer::tag('div', $sectionsummary, ['class' => 'course-summary col p-3 bg-light']);
            echo html_writer::tag('div', $sectioncm, ['class' => 'col-12 '.$class.' px-3 pb-3 bg-light']);

            echo html_writer::end_tag('div');

            echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);

            // Since we are not using ul, replace the /li that
            // section_footer outputs with /div.
            echo str_replace('</li>', '</div>', $this->section_footer());
        }
        // Render as a ul, using css to turn into a responsive grid.
        echo html_writer::start_tag('div', array('class' => 'weeks-holder'));
        echo html_writer::start_tag('div', array('class' => 'weeks-layout'));
        echo html_writer::start_tag('div', array('class' => 'sectionnamex'));
        echo get_string('weeks', 'format_pgrid');
        if (file_exists($CFG->dirroot . "/mod/journal/index.php")) {
            $journalsurl = new moodle_url('/mod/journal/index.php', array('id' => $course->id));
            echo html_writer::start_tag('span', array('class' => 'pucl-journals-link float-right'));
            echo html_writer::start_tag('a', array('href' => $journalsurl->out(),
                                                   'title' => get_string('journalslist', 'format_pgrid'),
                                                   'aria-label' => get_string('journalslist', 'format_pgrid')));
            echo html_writer::img($this->output->image_url('JournalWhite', 'format_pgrid'), '');
            echo html_writer::end_tag('a');
            echo html_writer::end_tag('span');
        }
        echo html_writer::end_tag('div');
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
     * Generate the html for the dashboard
     * @param stdClass $course The course entry from DB
     * @param int $displaysection The section number in the course which is being displayed
     * @return string HTML to output.
     */
    public function build_dashboard($course, $displaysection = null) {
        $o = '';
        $dashrenderclass = "local_culcourse_dashboard\output\dashboard";

        if (class_exists($dashrenderclass)) {
            $config = course_get_format($course)->get_format_options();
            $dashboard = new $dashrenderclass($course, $displaysection, $config);
            $templatecontext = $dashboard->export_for_template($this);
            $o .= $this->render_from_template('local_culcourse_dashboard/dashboard', $templatecontext);
        }

        return $o;
    }
}
