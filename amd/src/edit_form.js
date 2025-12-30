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
 * JavaScript for the block edit form - report selection UI.
 *
 * @module     block_adeptus_insights/edit_form
 * @copyright  2025 Adeptus Analytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/notification'], function($, Str, Notification) {
    'use strict';

    /**
     * Report Selector class for managing report selection in the edit form.
     *
     * @param {Object} options Configuration options
     */
    var ReportSelector = function(options) {
        this.mode = options.mode || 'kpi'; // 'kpi' or 'tabs'
        this.apiKey = options.apiKey || '';
        this.containerId = options.containerId;
        this.textareaName = options.textareaName;
        this.container = null;
        this.textarea = null;
        this.reports = [];
        this.selectedReports = [];
        this.strings = {};

        this.init();
    };

    ReportSelector.prototype = {
        /**
         * Initialize the report selector.
         */
        init: function() {
            var self = this;

            this.container = $('#' + this.containerId);
            this.textarea = $('[name="' + this.textareaName + '"]');

            if (!this.container.length || !this.textarea.length) {
                return;
            }

            // Load existing selection from textarea
            this.loadExistingSelection();

            // Load language strings
            this.loadStrings().then(function() {
                // Render the UI
                self.render();

                // Fetch reports from API
                self.fetchReports();

                // Bind events
                self.bindEvents();

                // Show container
                self.container.show();
            });
        },

        /**
         * Load language strings.
         *
         * @return {Promise}
         */
        loadStrings: function() {
            var self = this;
            return Str.get_strings([
                {key: 'addreport', component: 'block_adeptus_insights'},
                {key: 'removereport', component: 'block_adeptus_insights'},
                {key: 'noreportsselected', component: 'block_adeptus_insights'},
                {key: 'selectareport', component: 'block_adeptus_insights'},
                {key: 'loading', component: 'block_adeptus_insights'}
            ]).then(function(strings) {
                self.strings = {
                    addreport: strings[0],
                    removereport: strings[1],
                    noreportsselected: strings[2],
                    selectareport: strings[3],
                    loading: strings[4]
                };
            });
        },

        /**
         * Load existing selection from textarea.
         */
        loadExistingSelection: function() {
            try {
                var value = this.textarea.val();
                if (value) {
                    this.selectedReports = JSON.parse(value);
                }
            } catch (e) {
                this.selectedReports = [];
            }
        },

        /**
         * Render the report selector UI.
         */
        render: function() {
            var html = '<div class="report-selector-ui">' +
                '<div class="report-selector-header d-flex justify-content-between align-items-center mb-2">' +
                '<span class="font-weight-bold">' + (this.mode === 'kpi' ? 'KPI Reports' : 'Tab Reports') + '</span>' +
                '</div>' +
                '<div class="report-selector-add-container mb-3">' +
                '<div class="input-group">' +
                '<select class="form-control report-selector-dropdown">' +
                '<option value="">' + this.strings.selectareport + '</option>' +
                '</select>' +
                '<div class="input-group-append">' +
                '<button type="button" class="btn btn-primary report-selector-add-btn">' +
                '<i class="fa fa-plus"></i> ' + this.strings.addreport +
                '</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="report-selector-list-container">' +
                '<ul class="list-group report-selector-list"></ul>' +
                '<div class="report-selector-empty text-muted text-center py-3">' +
                '<i class="fa fa-inbox fa-2x mb-2"></i><br>' +
                this.strings.noreportsselected +
                '</div>' +
                '</div>' +
                '</div>';

            this.container.html(html);
            this.updateListDisplay();
        },

        /**
         * Fetch reports from the API.
         */
        fetchReports: function() {
            var self = this;

            if (!this.apiKey) {
                return;
            }

            var baseUrl = 'https://a360backend.stagingwithswift.com/api/v1';

            // Fetch both wizard and AI reports
            $.when(
                $.ajax({
                    url: baseUrl + '/wizard-reports',
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + this.apiKey,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }),
                $.ajax({
                    url: baseUrl + '/ai-reports',
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + this.apiKey,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                })
            ).then(function(wizardResult, aiResult) {
                var allReports = [];

                // Process wizard reports
                var wizardReports = wizardResult[0].reports || wizardResult[0].data || [];
                wizardReports.forEach(function(r) {
                    r.source = 'wizard';
                    allReports.push(r);
                });

                // Process AI reports
                var aiReports = aiResult[0].reports || aiResult[0].data || [];
                aiReports.forEach(function(r) {
                    r.source = 'ai';
                    allReports.push(r);
                });

                self.reports = allReports;
                self.populateDropdown();
                self.renderSelectedList();
            }).fail(function() {
                Notification.addNotification({
                    message: 'Failed to load reports',
                    type: 'error'
                });
            });
        },

        /**
         * Populate the report dropdown.
         */
        populateDropdown: function() {
            var self = this;
            var dropdown = this.container.find('.report-selector-dropdown');
            dropdown.find('option:not(:first)').remove();

            this.reports.forEach(function(report) {
                var name = report.name || report.title || report.display_name || report.slug;
                var categoryName = report.category_info ? report.category_info.name : 'General';
                var value = report.slug + '::' + report.source;

                // Check if already selected
                var isSelected = self.selectedReports.some(function(s) {
                    return s.slug === report.slug && s.source === report.source;
                });

                if (!isSelected) {
                    dropdown.append('<option value="' + value + '">' + self.escapeHtml(name) +
                        ' [' + categoryName + '] (' + report.source + ')</option>');
                }
            });
        },

        /**
         * Render the selected reports list.
         */
        renderSelectedList: function() {
            var self = this;
            var list = this.container.find('.report-selector-list');
            list.empty();

            this.selectedReports.forEach(function(selected, index) {
                // Find full report info
                var report = self.reports.find(function(r) {
                    return r.slug === selected.slug && r.source === selected.source;
                });

                var name = selected.name || (report ? (report.name || report.title || report.display_name) : selected.slug);
                var categoryName = report && report.category_info ? report.category_info.name : 'Unknown';

                var item = '<li class="list-group-item d-flex justify-content-between align-items-center" ' +
                    'data-index="' + index + '" data-slug="' + selected.slug + '" data-source="' + selected.source + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<span class="drag-handle mr-2" style="cursor: move;"><i class="fa fa-bars text-muted"></i></span>' +
                    '<span class="badge badge-secondary mr-2">' + (index + 1) + '</span>' +
                    '<span>' + self.escapeHtml(name) + '</span>' +
                    '<small class="text-muted ml-2">[' + categoryName + ']</small>' +
                    '<span class="badge badge-' + (selected.source === 'ai' ? 'info' : 'primary') + ' ml-2">' +
                    selected.source.toUpperCase() + '</span>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger report-selector-remove" title="' +
                    self.strings.removereport + '">' +
                    '<i class="fa fa-times"></i>' +
                    '</button>' +
                    '</li>';

                list.append(item);
            });

            this.updateListDisplay();
            this.initSortable();
        },

        /**
         * Update list display (show/hide empty state).
         */
        updateListDisplay: function() {
            var list = this.container.find('.report-selector-list');
            var empty = this.container.find('.report-selector-empty');

            if (this.selectedReports.length > 0) {
                list.show();
                empty.hide();
            } else {
                list.hide();
                empty.show();
            }
        },

        /**
         * Initialize sortable for drag-and-drop reordering.
         */
        initSortable: function() {
            var self = this;
            var list = this.container.find('.report-selector-list');

            // Simple sortable implementation using jQuery UI if available
            if ($.fn.sortable) {
                list.sortable({
                    handle: '.drag-handle',
                    update: function() {
                        self.updateOrderFromDOM();
                    }
                });
            } else {
                // Fallback: manual drag handling
                this.initManualSort();
            }
        },

        /**
         * Initialize manual sort without jQuery UI.
         */
        initManualSort: function() {
            var self = this;
            var list = this.container.find('.report-selector-list');
            var dragItem = null;

            list.on('dragstart', 'li', function(e) {
                dragItem = this;
                $(this).addClass('dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
            });

            list.on('dragend', 'li', function() {
                $(this).removeClass('dragging');
                self.updateOrderFromDOM();
            });

            list.on('dragover', 'li', function(e) {
                e.preventDefault();
                var rect = this.getBoundingClientRect();
                var midY = rect.top + rect.height / 2;
                if (e.originalEvent.clientY < midY) {
                    $(this).before(dragItem);
                } else {
                    $(this).after(dragItem);
                }
            });

            // Make items draggable
            list.find('li').attr('draggable', 'true');
        },

        /**
         * Update the order from DOM after sorting.
         */
        updateOrderFromDOM: function() {
            var self = this;
            var newOrder = [];

            this.container.find('.report-selector-list li').each(function() {
                var slug = $(this).data('slug');
                var source = $(this).data('source');
                var existing = self.selectedReports.find(function(s) {
                    return s.slug === slug && s.source === source;
                });
                if (existing) {
                    newOrder.push(existing);
                }
            });

            this.selectedReports = newOrder;
            this.saveSelection();
            this.renderSelectedList();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Add report button
            this.container.on('click', '.report-selector-add-btn', function() {
                self.addSelectedReport();
            });

            // Remove report button
            this.container.on('click', '.report-selector-remove', function() {
                var item = $(this).closest('li');
                var slug = item.data('slug');
                var source = item.data('source');
                self.removeReport(slug, source);
            });

            // Display mode change - show/hide containers
            $('[name="config_display_mode"]').on('change', function() {
                var mode = $(this).val();
                if (mode === 'kpi') {
                    $('#kpi-report-selector-container').show();
                    $('#tabs-report-selector-container').hide();
                } else if (mode === 'tabs') {
                    $('#kpi-report-selector-container').hide();
                    $('#tabs-report-selector-container').show();
                } else {
                    $('#kpi-report-selector-container').hide();
                    $('#tabs-report-selector-container').hide();
                }
            }).trigger('change');
        },

        /**
         * Add the selected report from dropdown.
         */
        addSelectedReport: function() {
            var dropdown = this.container.find('.report-selector-dropdown');
            var value = dropdown.val();

            if (!value) {
                return;
            }

            var parts = value.split('::');
            var slug = parts[0];
            var source = parts[1] || 'wizard';

            // Find the report
            var report = this.reports.find(function(r) {
                return r.slug === slug && r.source === source;
            });

            if (!report) {
                return;
            }

            // Add to selected list
            this.selectedReports.push({
                slug: slug,
                source: source,
                name: report.name || report.title || report.display_name || slug
            });

            this.saveSelection();
            this.populateDropdown();
            this.renderSelectedList();

            // Reset dropdown
            dropdown.val('');
        },

        /**
         * Remove a report from selection.
         *
         * @param {string} slug
         * @param {string} source
         */
        removeReport: function(slug, source) {
            this.selectedReports = this.selectedReports.filter(function(s) {
                return !(s.slug === slug && s.source === source);
            });

            this.saveSelection();
            this.populateDropdown();
            this.renderSelectedList();
        },

        /**
         * Save selection to textarea.
         */
        saveSelection: function() {
            this.textarea.val(JSON.stringify(this.selectedReports));
        },

        /**
         * Escape HTML special characters.
         *
         * @param {string} text
         * @return {string}
         */
        escapeHtml: function(text) {
            if (!text) {
                return '';
            }
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    return {
        /**
         * Initialize report selectors for the edit form.
         *
         * @param {Object} options Configuration options
         */
        init: function(options) {
            // Initialize KPI report selector
            new ReportSelector({
                mode: 'kpi',
                apiKey: options.apiKey,
                containerId: 'kpi-report-selector-container',
                textareaName: 'config_kpi_selected_reports'
            });

            // Initialize Tabs report selector
            new ReportSelector({
                mode: 'tabs',
                apiKey: options.apiKey,
                containerId: 'tabs-report-selector-container',
                textareaName: 'config_tabs_selected_reports'
            });
        }
    };
});
