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
 * Local functions for format_pgrid.
 *
 * @package   format_pgrid
 * @copyright 2018 Amanda Doughty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* CONSTANTS */
if (!defined('OK')) {
    define('OK', 1);
}

if (!defined('NODATA')) {
    define('NODATA', 0);
}

if (!defined('ERROR')) {
    define('ERROR', -1);
}

if (!defined('SHOWHIDE')) {
    define('SHOWHIDE', 1);
}

if (!defined('MOVE')) {
    define('MOVE', 2);
}

if (!defined('QUICKLINK')) {
    define('QUICKLINK', 1);
}

if (!defined('ACTIVITYLINK')) {
    define('ACTIVITYLINK', 2);
}

/**
 * format_pgrid_get_external_urls_data()
 *
 * @param mixed $course
 * @return
 */
function format_pgrid_get_external_urls_data($course) {
    global $DB;

    $record = $DB->get_record('format_pgrid_ext_urls', ['courseid' => $course->id]);
    $today = strtotime('today midnight');
    $update = true;

    if ($record) {
        if ($record->timemodified > $today) {
            // If the last save came back with an error then call the api's again.
            // Check timetable firstas that is most flaky.
            $data = json_decode($record->data, true);
            if (($data['timetable']['status'] != ERROR) && 
                ($data['readinglists']['status'] != ERROR) && 
                ($data['libguides']['status'] != ERROR)) {
                $update = false;
                return $data;
            }
        }
    }

    // Get the data.
    $dataobj = new stdClass();
    $dataobj->courseid = $course->id;
    $dataobj->timemodified = time();

    $data = [];
    $data['readinglists'] = format_pgrid_get_reading_list_url_data($course);
    $data['libguides'] = format_pgrid_get_libguide_url_data($course);
    $data['timetable'] = format_pgrid_get_timetable_url($course);

    $dataobj->data = json_encode($data);

    if ($record) {
        $dataobj->id = $record->id;
        // Update.
        $DB->update_record('format_pgrid_ext_urls', $dataobj);
    } else {
        // Insert.
        $DB->insert_record('format_pgrid_ext_urls', $dataobj);
    }

    return $data;
}

/**
 * format_pgrid_get_reading_list_url_data()
 *
 * @param mixed $course
 * @return
 */
function format_pgrid_get_reading_list_url_data($course) {
    global $CFG;        

    if (!file_exists($CFG->dirroot . '/mod/aspirelists/settings.php')) {
        return false;
    }

    $timePeriod = false;
    $baseKGCode = false;
    $aspireURL = get_config('format_pgrid', 'aspireAPI');

    // https://support.talis.com/hc/en-us/articles/205860531
    $pluginSettings = get_config('mod_aspirelists');
    $baseKGCode = $course->{$pluginSettings->courseCodeField};

    if(isset($pluginSettings->moduleCodeRegex)) {
        if(preg_match("/".$pluginSettings->moduleCodeRegex."/", $baseKGCode, $matches)) {
            if(!empty($matches) && isset($matches[1])) {
                $baseKGCode = $matches[1];
                $baseKGCode = strtolower($baseKGCode);
            }
        }
    }

    if(isset($pluginSettings->timePeriodRegex) && isset($pluginSettings->timePeriodMapping)) {
        $timePeriodMapping = json_decode($pluginSettings->timePeriodMapping, true);

        if(preg_match("/".$pluginSettings->timePeriodRegex."/", $course->{$pluginSettings->courseCodeField}, $matches)) {
            if(!empty($matches) && isset($matches[1]) && isset($timePeriodMapping[$matches[1]])) {
                $timePeriod = $timePeriodMapping[$matches[1]];
            }
        }
    }

    if (!$baseKGCode || empty($pluginSettings->targetAspire)) {
        return ['status' => 'NODATA'];
    }

    $path = "{$aspireURL}/modules/{$baseKGCode}";
    $format = ".json";

    if ($timePeriod) {
        $curl = "{$path}/lists/{$timePeriod}{$format}";
        $url = "{$path}/lists/{$timePeriod}";
    } else {
        $curl = "{$path}{$format}";
        $url = "{$path}";
    }

    // Get the config timeout values.
    $connectiontimeout = trim(get_config('pgrid', 'connection_timeout'));
    $transfertimeout = trim(get_config('pgrid', 'transfer_timeout'));

    $data = format_pgrid_get_reading_list_data($path, $curl, $connectiontimeout, $transfertimeout);

    // If we have a year but it returned no results then check for lists for 
    // any year.
    if ($timePeriod && (NODATA == $data['status'])) {
        $curl = "{$path}{$format}";
        $url = "{$path}";
        $data = format_pgrid_get_reading_list_data($path, $curl, $connectiontimeout, $transfertimeout);
    }

    if (OK == $data['status']) {
        $data['url'] = $url;

        if ($timePeriod) {
            $data['listtype'] = 'module-year';
        } else {
            $data['listtype'] = 'module';
        }    
    }

    return $data;
}

/**
 * format_pgrid_get_reading_list_data()
 *
 * Request the JSON data from Talis Aspire using cURL.
 * If the year filter is included in the url, then the response includes:-
 *     1. An object representing each list for the module for that year (if any exist)
 *     2. An object representing the module
 * If the year filter is not included in the url, then the response includes:-
 *     1. An object representing every list for the module
 *     2. An object representing the module
 *     3. An object representing the subject
 *
 * @param string $path
 * @param string $url
 * @param integer $connectiontimeout
 * @param integer $transfertimeout
 * @return array
 */
function format_pgrid_get_reading_list_data($path, $url, $connectiontimeout = null, $transfertimeout = null) {
    $connectiontimeout = (empty($connectiontimeout)) ? 4 : $connectiontimeout;
    $transfertimeout = (empty($transfertimeout))   ? 8 : $transfertimeout;

    // Check validity of $connectiontimeout, and limit maximum value.
    if (!preg_match('/\A\d+\z/', $connectiontimeout) || ($connectiontimeout > 6)) {
        $connectiontimeout = 6;
    }

    // Check validity of $transfertimeout, and limit maximum value.
    if (!preg_match('/\A\d+\z/', $transfertimeout) || ($transfertimeout > 16)) {
        $transfertimeout = 16;
    }

    $rldata = ['status' => null];
    $urldebug = empty($urldebug) ? '' : $urldebug;
    $ch = curl_init();

    $options = [
        CURLOPT_URL            => empty($urldebug) ? $url : $urldebug,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_FAILONERROR    => true,
        CURLOPT_HEADER         => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectiontimeout,
        CURLOPT_TIMEOUT        => $transfertimeout,
        CURLOPT_FOLLOWLOCATION => true
    ];

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $curlinfo = curl_getinfo($ch);
    $curlerrno = curl_errno($ch);

    if ($curlerrno) {
        curl_close($ch);
        $rldata['curl_errno'] = $curlerrno;

        if ((22 == $curlerrno) && ($curlinfo['http_code'] < 500)) {
            $rldata['status'] = NODATA;
        } else {
            $rldata['status'] = ERROR;
        }

        return $rldata;
    }

    curl_close($ch);

    if (empty($response)) {
        $rldata['status'] = NODATA;
        return $rldata;
    }

    $responsedata = json_decode($response, true);
    $resourcelists = [];

    if (!empty($responsedata[$path]['http://purl.org/vocab/resourcelist/schema#usesList'])) {
        $resourcelists = $responsedata[$path]['http://purl.org/vocab/resourcelist/schema#usesList'];
    }

    foreach ($resourcelists as $resourcelist) {
        if (!empty($responsedata[$resourcelist['value']])) {
            $rldata['status'] = OK;
            return $rldata;
        }
    }

    $rldata['status'] = NODATA;
    return $rldata;
}

/**
 * format_pgrid_get_libguide_url_data()
 *
 * @param mixed $course
 * @return
 */
function format_pgrid_get_libguide_url_data($course) {
    global $CFG;

    $libAppsDefaultURL = get_config('format_pgrid', 'libAppsDefaultURL');
    $libAppsAPI = get_config('format_pgrid', 'libAppsAPI');
    // Get the config timeout values.
    $connectiontimeout = trim(get_config('pgrid', 'connection_timeout'));
    $transfertimeout = trim(get_config('pgrid', 'transfer_timeout'));
    $connectiontimeout = (empty($connectiontimeout)) ? 5 : $connectiontimeout;
    $transfertimeout = (empty($transfertimeout)) ? 10 : $transfertimeout;

    // Check validity of $connectiontimeout, and limit maximum value.
    if (!preg_match('/\A\d+\z/', $connectiontimeout) || ($connectiontimeout > 6)) {
        $connectiontimeout = 6;
    }

    // Check validity of $transfertimeout, and limit maximum value.
    if (!preg_match('/\A\d+\z/', $transfertimeout) || ($transfertimeout > 16)) {
        $transfertimeout = 16;
    }

    $lgdata = ['status' => null, 'url' => null];
    $codedata = format_pgrid_get_coursecode_data($course->shortname);
    $module = $codedata['module_code'];    
    $siteid = get_config('format_pgrid', 'libAppsSiteId');
    $key = get_config('format_pgrid', 'libAppsKey');
    $metadata = $module;

    $params = [
        'site_id' => $siteid,
        'key' => $key,
        'metadata' => $metadata
    ];

    $query = http_build_query($params, '', '&');
    $urldebug = empty($urldebug) ? '' : $urldebug;
    $url = "{$libAppsAPI}?$query";
    $ch = curl_init();

    $options = [
        CURLOPT_URL => empty($urldebug) ? $url : $urldebug,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_FAILONERROR    => true,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $connectiontimeout,
        CURLOPT_TIMEOUT        => $transfertimeout,
        CURLOPT_RETURNTRANSFER => true

    ];

    curl_setopt_array($ch, $options);
    $data = curl_exec($ch);
    $curlinfo = curl_getinfo($ch);
    $curlerrno = curl_errno($ch);

    if ($curlerrno) {
        curl_close($ch);

        if ((22 == $curlerrno) && ($curlinfo['http_code'] < 500)) {
            $lgdata['status'] = NODATA;
        } else {
            $lgdata['status'] = ERROR;
        }

        return $lgdata;
    }

    curl_close($ch);
    $responsedata = json_decode($data, true);    

    if ($responsedata) {
        $lgdata['status'] = OK;
        $lgdata['url'] = $responsedata[0]["friendly_url"];
    } else {
        $lgdata['status'] = NODATA;
        $lgdata['url'] = $libAppsDefaultURL;
    }

    return $lgdata;
}

/**
 * format_pgrid_get_timetable_url()
 *
 * @param mixed $course
 * @return string timetable URL
 */
function format_pgrid_get_timetable_url($course) {
    global $COURSE, $CFG;

    $ttdata = ['url' => null, 'status' => OK];
    $codedata = format_pgrid_get_coursecode_data($COURSE->shortname);
    $module = $codedata['module_code'];
    $year = $codedata['year_description'];
    $tturl = new moodle_url('/local/cultimetable_api/timetable.php',
        [
            'cid' => $COURSE->id,
            'mcode' => $module,
            'yr' => $year
        ]
    );
    $ttdata['url'] = $tturl->out();

    try {
        require_once($CFG->dirroot . '/local/cultimetable_api/classes/timetable_class.php');
        list($weekoptions, $defaultweeks, $formatoptions, $defaultformat) = local_cultimetable_api\timetable::get_timetable_config();
        $timetable = new local_cultimetable_api\timetable();
        $result = $timetable->display_module_timetable($module, $defaultweeks, $defaultformat, $COURSE->id);

        if ($result['http'] == 500) { // No matching timetable page results in a 500 error!
            $ttdata['status'] = NODATA;
        } else if ($result['http'] <> 200) {
            $ttdata['status'] = ERROR;
        } else {
            $ttdata['status'] = OK;
        }
    } catch (Exception $e) {
        $ttdata['status'] = ERROR;
    }

    return $ttdata;
}


/**
 * format_pgrid_get_photoboard_url()
 *
 * @param mixed $course
 * @return string photoboard URL
 */
function format_pgrid_get_photoboard_url($course, $roleid) {
    global $COURSE, $CFG;

    $cname = explode(" ",$COURSE->fullname);
    $cid = $cname[0];
    $coursecontext = context_course::instance($COURSE->id);
    $url = new moodle_url(
        '/course/format/pgrid/dashboard/photoboard.php',
        ['contextid' => $coursecontext->id, 'roleid' => $roleid]
    );

    return $url;
}


/**
 * format_pgrid_get_coursecode_data()
 *
 * @param mixed $coursecode Course object or course-code string.
 * @return mixed array of coursecode data, e.g.:-
 *     array (
 *         'full_code' => 'MDL_IN3001-INM370_PRD2_A_2013-14',
 *         'code_type' => 'MDL',
 *         'module_codes_string' => 'IN3001-INM370',
 *         'module_codes' => array (
 *                              0 => 'IN3001',
 *                              1 => 'INM370',
 *                          ),
 *         'module_code' => 'IN3001',
 *         'module_type' => 'undergraduate',
 *         'period' => 'PRD2',
 *         'period_num' => '2',
 *         'occurrence_code' => 'A',
 *         'year_description' => '2013-14',
 *         'year_start' => '2013',
 *         'year_end' => '2014',
 *     )
 * @todo This function should be centralised, rather than duplicated here! I'm sure it'll come in handy elsewhere.
 *       Perhaps we should have a City course (module) metadata utility class in local/culutily or something.
 */
function format_pgrid_get_coursecode_data($coursecode) { //TODO: Candidate for a static utility method.

    $codedata = [];

    if (is_object($coursecode) && property_exists($coursecode, 'shortname')) {
        $codedata['full_code'] = trim(strtoupper($coursecode->shortname));
    } else if (is_string($coursecode)) {
        $codedata['full_code'] = trim(strtoupper($coursecode));
    } else {
        debugging(__FUNCTION__ . '(): Parameter 1 must be a course object or course-code string.');
    }

    if (empty($codedata['full_code'])) {
        return false;
    }

    $codedatakeys = ['code_type',
                          'module_codes_string', 'module_codes', 'module_code', 'module_type',
                          'period', 'period_num', 'occurrence_code',
                          'year_description', 'year_start', 'year_end'];

    // Initialise $codedata elements to null.
    foreach($codedatakeys as $key) {
        $codedata[$key] = null;
    }

    $components = explode('_', $codedata['full_code']);

    // Check code type prefix.
    if (preg_match('/\AMDL|GEN|PLAY|TEMP\z/sim', $components[0])) {
        $codedata['code_type'] = strtoupper($components[0]);
        $codedata['module_codes_string'] = strtoupper($components[1]);
    } else {
        $codedata['module_codes_string'] = strtoupper($components[0]);
    }

    // Populate module codes. A super-module will have multiple codes.
    if (strpos($codedata['module_codes_string'], '-')) {
        $codedata['module_codes'] = explode('-', $codedata['module_codes_string']);

        // Apply the prefix from the first code to subsequent ones, iff they consist entirely of digits. //TODO: Check this assumption!
        if (preg_match('/\A([a-z]+)\d+\z/sim', $codedata['module_codes'][0], $matches)) {
            for ($i = 1; $i < count($codedata['module_codes']); $i++) {
                if (preg_match('/\A\d+\z/', $codedata['module_codes'][$i])) {
                    $prefix = $matches[1];
                    $codedata['module_codes'][$i] = $prefix . $codedata['module_codes'][$i];
                }
            }
        }
    } else {
        $codedata['module_codes'][] = $codedata['module_codes_string'];
    }

    // Primary (first) module code.
    $codedata['module_code'] = $codedata['module_codes'][0];

    // Extract module type from the primary module code.
    if (preg_match('/\A[a-z]{2}\d{4}\z/sim', $codedata['module_code'])) {
        $codedata['module_type'] = 'undergraduate';
    } else if (preg_match('/\A[a-z]{3}\d{3}\z/sim', $codedata['module_code'])) {
        $codedata['module_type'] = 'postgraduate';
    }

    // Extract period and occurrence code, if present.
    if (preg_match('/\A.+_(PRD(\d+))_(?:(\w+)_)?.*\z/sim', $codedata['full_code'], $matches)) {
        $codedata['period']     = strtoupper($matches[1]);
        $codedata['period_num'] = $matches[2];
        if (!empty($matches[3])) {
            $codedata['occurrence_code'] = $matches[3];
        }
    }

    // Extract year components, if present.
    if (preg_match('/\A.+_((20)(\d{2})-(\d{2}))\z/sim', $codedata['full_code'], $matches)) {
        $codedata['year_description'] = $matches[1];
        $codedata['year_start'] = $matches[2] . $matches[3];
        $codedata['year_end']   = $matches[2] . $matches[4];
    }

    return $codedata;
}

function format_pgrid_quicklink_visibility($courseid, $name, $value, $lnktxt = '') {
    global $DB;

    // Important: Cannot use $format->update_course_format_options($options)
    // because activity links do not exist as default values and therefore never
    // get updated.
    $options = $DB->get_records('course_format_options', ['courseid' => $courseid, 'name' => $name]);
    
    if($options) {
        $option = array_pop($options);
        $option->value = $value;
        $DB->update_record('course_format_options', $option);
    } else {
        $option = new stdClass();
        $option->courseid = $courseid;
        $option->name = $name;
        $option->value = $value;
        $option->format = 'pgrid';
        $DB->insert_record('course_format_options', $option);
    }

    list($editurl, $editicon, $editattrs) = format_pgrid_get_edit_link(
                $courseid, 
                $name, 
                $value,
                $lnktxt
                );

    $data = new stdClass();
    $data->editurl = $editurl;
    $data->editicon = $editicon;
    $data->editattrs = $editattrs;
    $data->userisediting = true;

    return json_encode($data);
}

function format_pgrid_dashlink_move($courseid, $name, $link, $moveto = null) {
    $format = course_get_format($courseid);
    $options = $format->get_format_options();    

    if ($options) {
        if (isset($options[$name])) {
            $value = $options[$name];
            $value = explode(',', $value);
            $flipped = array_flip($value);
            $fromindex = $flipped[$link];

            if ($moveto == 'end') {
                $out = array_splice($value, $fromindex, 1);
                array_push($out, $value);
            } else {
                $toindex = $flipped[$moveto];
                $newvalue = [];
                $out = $value[$fromindex];

                foreach ($value as $key => $item) {
                    if ($key == $fromindex || $key == $toindex) {
                        if ($key == $fromindex) {
                            // We do not insert the moved item in the same place.
                            continue;
                        } else if ($fromindex < $toindex) {
                            $newvalue[] = $item;
                            // We insert the moved item in the new position.
                            $newvalue[] = $out;
                            continue;
                        } else {
                            // We insert the moved item in the new position.
                            $newvalue[] = $out; 
                        }
                    }
                    // All other items are inserted in their original order.
                    $newvalue[] = $item;
                }

                $value = $newvalue;
            }

            $value = join(',', $value);
            $options[$name] = $value;
            $options = (object)$options;
            $format->update_course_format_options($options);

            return true;            
        } 
    }

    return false;
}

function format_pgrid_get_edit_link($courseid, $name, $value, $namestr) {    
    // Course format settings are 2 = show, 1 = hide.
    if ($value == 2) {
        $newvalue = 1;
        $editicon = 'fa-eye';        
        $title = 'dashhidelink';
    } else {
        $newvalue = 2;
        $editicon = 'fa-eye-slash';
        $title = 'dashshowlink';
    }

    $name = 'show' . $name;
    $editattrs['title'] = get_string($title, 'format_pgrid', $namestr);
    $editattrs['class'] = 'dashlinkedit';
    $params = [
        'courseid' => $courseid,
        'action' => SHOWHIDE,
        'name' => $name,
        'showhide' => $newvalue,
        'sesskey' => sesskey()
    ];
    $editurl = new moodle_url(
        '/course/format/pgrid/dashboard/dashlink_edit.php', $params
        );
        

    $editurl = $editurl->out();

    return [$editurl, $editicon, $editattrs];
}

function format_pgrid_get_move_link($courseid, $copy, $name, $namestr) {    
    $moveicon = 'fa-arrows';        
    $title = 'dashmovelink';
    $moveattrs['title'] = get_string('movequicklink', 'format_pgrid', $namestr);
    $moveattrs['class'] = 'dashlinkmove';
    $params = [
        'courseid' => $courseid,
        'action' => MOVE,
        'copy' => $copy,
        'name' => $name . 'sequence',
        'sesskey' => sesskey()
    ];

    $moveurl = new moodle_url(
        '/course/format/pgrid/dashboard/dashlink_edit.php', $params
        );

    $moveurl = $moveurl->out();

    return [$moveurl, $moveicon, $moveattrs];
}

function format_pgrid_get_moveto_link($courseid, $moveto, $type) {
    global $USER, $OUTPUT;

    $setting = $type . 'sequence';
    $name = $type . 'copy';
    $movetourl = '';
    $movetoicon = '';
    $movetoattrs = [];

    if (!isset($USER->$name)) {
        return [$movetourl, $movetoicon, $movetoattrs];
    }

    if (get_string_manager()->string_exists($USER->$name, 'format_pgrid')) {
        $copyfullname = get_string($USER->$name, 'format_pgrid');
    } else if (get_string_manager()->string_exists('pluginname', 'mod_' . $USER->$name)) {
        $copyfullname = get_string('pluginname', 'mod_' . $USER->$name);
    } else {
        $copyfullname = $USER->$name;
    }

    $movetoicon = $OUTPUT->image_url('movehere', 'core');     
    $title = 'dashmovelink';
    $movetoattrs['title'] = get_string($title, 'format_pgrid', $copyfullname);
    $movetoattrs['class'] = 'dashlinkmove';

    $params = [
        'courseid' => $courseid,
        'action' => MOVE,
        'name' => $setting,
        'moveto' => $moveto,
        'sesskey' => sesskey()
    ];
    $movetourl = new moodle_url(
        '/course/format/pgrid/dashboard/dashlink_edit.php', $params
        );

    $movetourl = $movetourl->out();

    return [$movetourl, $movetoicon, $movetoattrs];
}

/**
 * Determines if the logged in user is currently moving a quicklink
 *
 * @param int $courseid The id of the course being tested
 * @return bool
 */
function ismovingquicklink($courseid) {
    global $USER;

    if (!empty($USER->quicklinkcopy)) {
        return ($USER->quicklinkcopycourse == $courseid);
    }
    return false;
}

/**
 * Determines if the logged in user is currently moving an activity link
 *
 * @param int $courseid The id of the course being tested
 * @return bool
 */
function ismovingactivitylink($courseid) {
    global $USER;

    if (!empty($USER->activitylinkcopy)) {
        return ($USER->activitylinkcopycourse == $courseid);
    }
    return false;
}

function format_pgrid_get_lti_instances($course, $type) {
    global $CFG, $USER;

    require_once($CFG->dirroot.'/mod/lti/lib.php');

    $ltis = [];
    $basicltis = get_coursemodules_in_course('lti', $course->id, 'm.typeid');
    $basicltis = get_all_instances_in_course('lti', $course, $USER->id, true);

    // lti_filter_get_types($course)

    // lti_get_type_config_by_instance($instance)

    foreach($basicltis as $basiclti) {
        if($basiclti->typeid == $type->id) {
            $ltis[] = $basiclti;
        }
    }

    return $ltis;
}