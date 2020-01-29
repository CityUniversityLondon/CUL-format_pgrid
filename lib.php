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
 * This file contains main class for format_pgrid.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $COURSE;

require_once($CFG->dirroot. '/course/format/lib.php');
require_once($CFG->dirroot. '/course/format/topics/lib.php');
require_once($CFG->dirroot. '/course/format/weeks/lib.php');
require_once($CFG->dirroot. '/course/format/culcourse/topics_trait.php');
require_once($CFG->dirroot. '/course/format/culcourse/weeks_trait.php');
require_once($CFG->dirroot . '/course/format/pgrid/dashboard/lib.php');

// Already defined in culcourse
defined('FORMATTOPICS') or define('FORMATTOPICS', 1);
defined('FORMATWEEKS') or define('FORMATWEEKS', 2);

/**
 * Main class for the CUL Course course format
 *
 * @package    format_pgrid
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_pgrid extends format_base {

    use format_topics_trait;
    use format_weeks_trait;

    /** @var string baseformat used for this course. Please note that it can be different from
     * course.format field if course referes to non-existing or disabled format */
    public $baseclass;

    /**
     * Creates a new instance of class
     *
     * Please use {@link course_get_format($courseorid)} to get an instance of the format class
     *
     * @param string $format
     * @param int $courseid
     * @return format_base
     */
    protected function __construct($format, $courseid) {        
        global $DB, $USER;

        $baseclasses = [
            1 => 'format_topics_',
            2 => 'format_weeks_'
        ];

        if (isset($USER->baseclass)) {
            // Set when the user changes the baseclass
            // in the course edit form. We need to show the
            // correct format options.
            $baseclass = $USER->baseclass;
            // Unset the temporary user value storing the edited baseclass.
            unset($USER->baseclass);
        } else {
            // Get record from db or default.
            $record = $DB->get_record('course_format_options',
                                    [
                                        'courseid' => $courseid,
                                        'format' => 'pgrid',
                                        'name' => 'baseclass'
                                    ], 
                                    'value');

            if ($record) {
                $baseclass = $record->value;
            } else {
                $config = get_config('format_pgrid');
                $baseclass = $config->defaultbaseclass;
            }
        }

        parent::__construct($format, $courseid);

        $this->baseclass = $baseclasses[$baseclass];
    }

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }    

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     * @return Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * Returns the default section using format_base's implementation of get_section_name.
     *
     * @param int|stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name based on the given course format.
     */
    public function get_default_section_name($section) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;

        return $ajaxsupport;
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }
 
    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }
  
    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @see blocks_add_default_course_blocks()
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        global $DB;

        $blocks = $DB->get_records('block', null, '', 'name');
        $defaultblocks = get_config('format_pgrid', 'defaultblocks_pgrid');
        $defaultblocks = preg_replace('/\s+/', '', $defaultblocks);
        $defaultblocks = explode(',', $defaultblocks);

        foreach ($defaultblocks as $key => $defaultblock) {
            if (!array_key_exists($defaultblock, $blocks)) {
                unset($defaultblocks[$key]);
            }
        }

        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => $defaultblocks
        ];
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * cul format uses the following options:
     * - baseclass
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) { 
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'baseclass' => [
                    'default' => get_config('format_pgrid', 'defaultbaseclass'),
                    'type' => PARAM_INT,
                ],
                'showsectionsummary' => [
                    'default' => get_config('format_pgrid', 'defaultshowsectionsummary'),
                    'type' => PARAM_INT,
                ]
            ];
        }

        // Splice in the dashboard options.
        $dashboard = new format_pgrid_dashboard();
        $dashboard->set_dashboard_options($courseformatoptions);

        if ($foreditform && !isset($courseformatoptions['baseclass']['label'])) {
            $baseclasses = [
                1 => new lang_string('pluginname', 'format_topics'),
                2 => new lang_string('pluginname', 'format_weeks')
            ];

            $courseformatoptionsedit = [
                'baseclass' => [
                    'label' => new lang_string('baseclass', 'format_pgrid'),
                    'help' => 'baseclass',
                    'help_component' => 'format_pgrid',
                    'element_type' => 'select',
                    'element_attributes' => [$baseclasses]
                ],
                'showsectionsummary' => [
                    'label' => new lang_string('showsectionsummary', 'format_pgrid'),
                    'help' => 'showsectionsummary',
                    'help_component' => 'format_pgrid',
                    'element_type' => 'select',
                    'element_attributes' => [[
                        1 => new lang_string('no'),
                        2 => new lang_string('yes')
                    ]]
                ]
            ];

            // Splice in the dashboard edit options.
            $dashboard->set_dashboard_edit_options($courseformatoptionsedit);
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }        

        $args = func_get_args();
        $pcourseformatoptions = $this->call_base_function(__FUNCTION__, $args);
        $courseformatoptions = $pcourseformatoptions + $courseformatoptions;

        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form
     *
     * This function is called from {@link course_edit_form::definition_after_data()}
     *
     * Format singleactivity adds a warning when format of the course is about to be changed.
     *
     * @param MoodleQuickForm $mform form the elements are added to
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form
     * @return array array of references to the added form elements
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $PAGE;

        $args = func_get_args();
        $elements = $this->call_base_function(__FUNCTION__, [&$mform, $forsection]);
        // Weekly format unsets a key which leads to an error as the 
        // combined parent and child array have a gap in the key sequence.
        // /course/edit_form.php #373.
        // So we reindex the array.
        $elements = array_values($elements);

        if ($forsection == false) {
            global $USER;
  
            // Convert saved course_format_options value back to an array to set the value.
            if ($selectmoduleleaders = $mform->getElementValue('selectmoduleleaders')) {
                if (!is_array($selectmoduleleaders)) {
                    $mform->setDefault('selectmoduleleaders', explode(',', $selectmoduleleaders ));
                } else {
                    $mform->setDefault('selectmoduleleaders', $selectmoduleleaders);
                }
            }

            // Put module leader setting in own dropdown.
            $selectmoduleleaderhdr = $mform->addElement('header', 'selectmoduleleadershdr', get_string('setselectmoduleleadershdr', 'format_pgrid'));
            $mform->addHelpButton('selectmoduleleadershdr', 'setselectmoduleleadershdr', 'format_pgrid', '', true);
            array_splice($elements, -1, 0, [$selectmoduleleaderhdr]);

            // Put dashboard settings in own dropdown.
            $dashboardhdr = $mform->addElement('header', 'dashboardhdr', get_string('setdashboardhdr', 'format_pgrid'));
            array_splice($elements, 4, 0, [$dashboardhdr]);
        }

        $PAGE->requires->js_call_amd('format_pgrid/updatebaseclass', 'init');

        return $elements;
    }

   /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'weeks', we try to copy options
     * 'coursedisplay', 'numsections' and 'hiddensections' from the previous format.
     * If previous course format did not have 'numsections' option, we populate it with the
     * current number of sections
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $dashboard = new format_pgrid_dashboard();
        $data = $dashboard->update_dashboard_options($data, $oldcourse);

        return $this->call_base_function(__FUNCTION__, [$data, $oldcourse]);
    }

    /**
     * Return the start and end date of the passed section
     *
     * @param int|stdClass|section_info $section section to get the dates for
     * @param int $startdate Force course start date, useful when the course is not yet created
     * @return stdClass property start for startdate, property end for enddate
     */
    public function get_section_dates($section, $startdate = false) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * Returns true if the specified section is current
     *
     * By default we analyze $course->marker
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function is_section_current($section) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * Whether this format allows to delete sections
     *
     * If format supports deleting sections it is also recommended to define language string
     * 'deletesection' inside the format.
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

   /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                                         $editable = null, $edithint = null, $editlabel = null) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * Returns the default end date value based on the start date.
     *
     * This is the default implementation for course formats, it is based on
     * moodlecourse/courseduration setting. Course formats like format_weeks for
     * example can overwrite this method and return a value based on their internal options.
     *
     * @param moodleform $mform
     * @param array $fieldnames The form - field names mapping.
     * @return int
     */
    public function get_default_course_enddate($mform, $fieldnames = array()) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * Indicates whether the course format supports the creation of the Announcements forum.
     *
     * For course format plugin developers, please override this to return true if you want the Announcements forum
     * to be created upon course creation.
     *
     * @return bool
     */
    public function supports_news() {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide)
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register
     *
     * @param stdClass|section_info $section
     * @param string $action
     * @param int $sr
     * @return null|array|stdClass any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr) {
        $args = func_get_args();

        return $this->call_base_function(__FUNCTION__, $args);
    }

    /**
     * There is no way to dynamically inherit from a choice of course formats. So to ease
     * upgrades, the methods of each course format we may want to inherit from have been
     * copied into traits. Each function has been prepended with the format name eg
     * format_weeks_section_action(). This function can then use the format_pgrid.baseclass 
     * ($this->baseclass) to determine which of the functions to call.
     *
     * The functions in the traits will be easier to compare to the format_<name>/lib.php they mock 
     * when these are upgraded. It is not a perfect solution but the course id is not always
     * available when lib.php is called. Therefore format_pgrid.baseclass is only available after
     * instantiation. This prevents the use of dynamic inheritance, dynamic traits,
     * decorator pattern, returning a class instantiated in format_pgrid.__construct (the classname 
     * format_pgrid is used under the hood) and anything else I thought of!
     *
     *
     * @param string $method
     * @param array $args
     * @return mixed result of the function called.
     */
    protected function call_base_function ($method, $args) {
        $function = $this->baseclass . $method;

        if (is_callable([$this, $function])) {
            return call_user_func_array([$this, $function], $args);
        } else {
            $method = 'parent::' . $method;
            return call_user_func_array([$this, $method], $args);
        }        
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_pgrid_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');

    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'pgrid'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Returns the name of the user preferences as well as the details this plugin uses.
 *
 * @return array
 */
// function format_pgrid_user_preferences() {
//     global $COURSE;

//     $preferences = [];

//     $sections = get_sections();

//     foreach ($sections as $section) {
//         $preferences['format_pgrid_expanded' . $section->id] = [
//             'type' => PARAM_INT,
//             'null' => NULL_NOT_ALLOWED,
//             'default' => 0,
//             'choices' => [0, 1]
//         ];
//     }    

//     return $preferences;
// }

/**
 * Get icon mapping for font-awesome.
 */
function format_pgrid_get_fontawesome_icon_map() {
    return [
        'format_pgrid:highlightoff' => 'fa-toggle-off',
        'format_pgrid:highlight' => 'fa-toggle-on',
    ];
}
