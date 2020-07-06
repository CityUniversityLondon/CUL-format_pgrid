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
 * Topic toggling for pgrid course format
 *
 * @package    course/format
 * @subpackage pgrid
 * @copyright  2020 CAPDM Ltd (https://www.capdm.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

/**
 * @module format_pgrid/topictoggle
 */
define(['jquery'], function($) {

    return {
        init: function() {
            $('.pucl-toggle').click(function(e) {
                e.preventDefault();
                var $this = $(this);
                if ($this.next().hasClass('show')) {
                    $this.next().removeClass('show');
                    $this.next().slideUp(350);
                    $this.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    $this.attr('aria-expanded', 'false');
                } else {
                    $this.parent().parent().find('li .pucl-collection-parts-level')
                        .removeClass('show');
                    $this.parent().parent().find('li .pucl-collection-parts-level')
                        .slideUp(350);
                    $this.parent().parent().find('li .pucl-collection-parts-level')
                        .prev().find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    $this.parent().parent().find('li.pucl-topic a.pucl-toggle').attr('aria-expanded', 'false');
                    $this.next().toggleClass('show');
                    $this.next().slideToggle(350);
                    $this.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    $this.attr('aria-expanded', 'true');
                }
            });
        }
    };
});
