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
 * Section collapse functions for CUL Course Format
 *
 * @package    course/format
 * @subpackage cul
 * @copyright  2016 Amanda Doughty <amanda.doughty.1@city.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

/**
 * @module format_pgrid/sectiontoggle
 */
define(['jquery', 'core/ajax', 'core/config', 'core/notification'], function($, Ajax, config, Notification) {

     /**
      * Used CSS selectors
      * @access private
      */
    var SELECTORS = {
        OPENALLLINK: 'a#toggles-all-opened',
        CLOSEALLLINK: 'a#toggles-all-closed',
        SECTIONHEAD: '.sectionhead',
        SECTIONBODY: '.sectionbody',
        TOGGLEHEAD: '.course-content #toggle-',
        TOGGLEBODY: '#togglesection-',
        TOGGLEFOOTER: '.course-content #footertoggle-',
        DDPROXY: '.yui3-dd-proxy',
        SECTIONNAME: '.sectionname a'
        };
    // From Bootstrap collapse.js.    
    var ClassName = {
        SHOW: 'show',
        COLLAPSE: 'collapse',
        COLLAPSING: 'collapsing',
        COLLAPSED: 'collapsed'
    };

    var GETURL = config.wwwroot + '/course/format/pgrid/getuserprefsections.php';
    var SETURL = config.wwwroot + '/course/format/pgrid/setuserprefsections.php';
    var courseId;

    /**
     * Opens all collapsed sections.
     *
     * @access private
     * @method handleOpenAll
     * @param {event} e
     */
    var handleOpenAll = function(e){
        e.preventDefault();
        e.stopPropagation();

        $('ul.pgrid').find(SELECTORS.SECTIONBODY).collapse('show');
    };

    /**
     * Closes all open sections.
     *
     * @access private
     * @method handleOpenAll
     * @param {event} e
     */
    var handleCloseAll = function(e){
        e.preventDefault();
        e.stopPropagation();

        $('ul.pgrid').find(SELECTORS.SECTIONBODY).collapse('hide');
    };

    /**
     * Saves user preference.
     *
     * @access private
     * @method handleOpen
     * @param {event} e
     */
    var handleOpen = function(e){
        e.stopPropagation();

        if ($(this).is(e.target)) {
            var sectionid = $(e.currentTarget).data('preference-key');

            var data = {
                courseid: courseId,
                sectionid: sectionid,
                value: 1,
                // sesskey: config.sesskey,
            };

            var settings = {
                type: 'POST',
                dataType: 'json',
                data: data
            };

            $.ajax(SETURL, settings);
                // .fail(function (request, status, error) {
                //     Notification.exception(request);
                //     console.log(error);
                // });

            $(SELECTORS.TOGGLEHEAD + sectionid).removeClass(ClassName.COLLAPSED).attr('aria-expanded', true);
            $(SELECTORS.TOGGLEFOOTER + sectionid).removeClass(ClassName.COLLAPSED).attr('aria-expanded', true);
        }
    };

    /**
     * Saves user preference.
     *
     * @access private
     * @method handleClose
     * @param {event} e
     */
    var handleClose = function(e){
        e.stopPropagation();

        if ($(this).is(e.target)) {
            var sectionid = $(e.currentTarget).data('preference-key');

            var data = {
                courseid: courseId,
                sectionid: sectionid,
                value: 0,
                // sesskey: config.sesskey,
            };

            var settings = {
                type: 'POST',
                dataType: 'json',
                data: data
            };

            $.ajax(SETURL, settings);

            $(SELECTORS.TOGGLEHEAD + sectionid).addClass(ClassName.COLLAPSED).attr('aria-expanded', false);
            $(SELECTORS.TOGGLEFOOTER + sectionid).addClass(ClassName.COLLAPSED).attr('aria-expanded', false);
        }
    };

    return /** @alias module:format_pgrid/sectiontoggle */ {
        /**
         * Initialize sectiontogglemanager
         * @access public
         * @param {int} courseid
         */
        init : function(courseid) {
            courseId = courseid;
            var body = $('body');

            body.delegate(SELECTORS.SECTIONNAME, 'click', function(e) {e.preventDefault();});

            body.delegate(SELECTORS.OPENALLLINK, 'click', handleOpenAll);
            body.delegate(SELECTORS.CLOSEALLLINK, 'click', handleCloseAll);

            body.delegate(SELECTORS.SECTIONBODY, 'hide.bs.collapse', handleClose);
            body.delegate(SELECTORS.SECTIONBODY, 'show.bs.collapse', handleOpen);

            // Get the userprefs. They are too large to pass to the function.
            var params = {
                courseid: courseid,
                // userid: userid,
                // sesskey: M.cfg.sesskey
            };
            $.post(GETURL, params, function() {})
                .done(function(data) {
                    var sectiontoggles = data;

                    for (var sectionid in sectiontoggles) {
                        // If expanded is set to false then change the classes to
                        // collapse the section.
                        if (sectiontoggles[sectionid] == 0) {
                            $(SELECTORS.TOGGLEHEAD + sectionid).addClass('collapsed');
                            $(SELECTORS.TOGGLEFOOTER + sectionid).addClass('collapsed');
                            $(SELECTORS.TOGGLEBODY + sectionid).removeClass('show');
                        }
                    }
                })
                .then(function() {
                    $(document).trigger($.Event('pgridsectiontoggle'));
                })
                .fail(function(jqXHR, status, error) {
                    Notification.exception(error);
                });
        }
    };
});
