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
 * Helper functions for CUL Course Format
 *
 * @package    course/format
 * @subpackage cul
 * @copyright  2016 Amanda Doughty <amanda.doughty.1@city.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

/**
  * @module format_pgrid/quicklinks
  */
define(['jquery','core/notification', 'core/templates'], function($, notification, templates) {

     /**
      * Used CSS selectors
      * @access private
      */
    var SELECTORS = {
        QUICKLINK: 'a.dash-link',
        EDITLINK: 'a.dashlinkedit',
        };
    var adminurl;

    /**
     * Perform the UI changes after server change
     *
     * @access private
     * @method changeVisibility
     * @param {jQuery node} link
     * @param {int} courseid
     * @param {string} name
     * @param {int} value
     */
    var changeVisibility = function(link, courseid, name, action, showhide, lnktxt) {
        var params = {
            courseid: courseid,
            name: name,
            action: action,
            showhide: showhide,
            lnktxt: lnktxt,
            sesskey: M.cfg.sesskey
        };
        $.post(adminurl, params, function() {})
        .done(function(data) {
            try {
                var span = link.parent();
                link.remove();

                templates.render('format_pgrid/editlink', data).done(function(html, js) {
                    templates.appendNodeContents(span, html, js);
                }).fail(notification.exception);

                if(span.hasClass('linkhidden')) {
                    span.removeClass('linkhidden');
                } else {
                    span.addClass('linkhidden');
                }
            }
            catch(err) {
                notification.exception(err);
            }
        })
        .fail(function(jqXHR, status, error) {
            notification.exception(error);
        });
    };

    var getUrlParameter = function (url, name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(url);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    };

    /**
     * Prompts user when removing permission
     *
     * @access private
     * @method handleRemoveRole
     * @param {event} e
     */
    var handleToggleVisibility = function(e){
        e.preventDefault();

        var link = $(e.currentTarget);
        var url = (link.attr('href'));
        var courseid = getUrlParameter(url, 'courseid');
        var name = getUrlParameter(url, 'name');
        var action = getUrlParameter(url, 'action');
        var showhide = getUrlParameter(url, 'showhide');
        var lnktxt = link.attr('title');
        lnktxt = lnktxt.match(/'([^']+)'/)[1];


        changeVisibility(link, courseid, name, action, showhide, lnktxt);
    };

    return /** @alias module:format_pgrid/quicklinks */ {
        /**
         * Initialize quicklinksmanager
         * @access public
         * @param {string} adminurl
         */
        init : function(url) {
            adminurl = url;
            var body = $('body');
            body.delegate(SELECTORS.EDITLINK, 'click', handleToggleVisibility);
        }
    };
});
