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
 * Ajax autocomplete for alert report selection.
 *
 * @module     block_adeptus_insights/alert_report_search
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function($, Ajax) {
    'use strict';

    return /** @alias module:block_adeptus_insights/alert_report_search */ {
        /**
         * List of reports matching the query.
         *
         * @param {String} _selector The selector of the autocomplete element.
         * @param {String} query The query string.
         * @param {Function} callback A callback function receiving an array of results.
         * @param {Function} failure A function to call when AJAX fails.
         */
        transport: function(_selector, query, callback, failure) {
            var promise;

            // Build the args for the web service.
            var args = {
                query: query
            };

            // Call the web service.
            promise = Ajax.call([{
                methodname: 'block_adeptus_insights_search_reports',
                args: args
            }]);

            // Moodle autocomplete API requires callback pattern.
            // eslint-disable-next-line promise/no-callback-in-promise
            promise[0].then(function(results) {
                var options = [];

                // Format results for autocomplete.
                if (results && results.reports) {
                    results.reports.forEach(function(report) {
                        options.push({
                            value: report.slug,
                            label: report.label
                        });
                    });
                }

                callback(options);
                return options;
            }).catch(failure);
        },

        /**
         * Process the results for display.
         *
         * @param {String} _selector The selector for the autocomplete element.
         * @param {Array} results The results from transport.
         * @return {Array} The processed results.
         */
        processResults: function(_selector, results) {
            return results;
        }
    };
});
