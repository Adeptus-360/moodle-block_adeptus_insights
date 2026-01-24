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
 * JavaScript for the block edit form - report selection UI with searchable dropdown.
 *
 * @module     block_adeptus_insights/edit_form
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/notification'], function($, Str, Notification) {
    'use strict';


    /**
     * Report Selector class for managing report selection in the edit form.
     *
     * @param {Object} options Configuration options
     */
    // Available icons for KPI cards (FontAwesome 4).
    var KPI_ICONS = [
        {id: 'fa-users', label: 'Users', category: 'People'},
        {id: 'fa-user', label: 'User', category: 'People'},
        {id: 'fa-user-plus', label: 'New User', category: 'People'},
        {id: 'fa-graduation-cap', label: 'Education', category: 'Education'},
        {id: 'fa-book', label: 'Book', category: 'Education'},
        {id: 'fa-certificate', label: 'Certificate', category: 'Education'},
        {id: 'fa-clock-o', label: 'Time', category: 'Time'},
        {id: 'fa-calendar', label: 'Calendar', category: 'Time'},
        {id: 'fa-hourglass-half', label: 'Hourglass', category: 'Time'},
        {id: 'fa-check-circle', label: 'Complete', category: 'Status'},
        {id: 'fa-check', label: 'Check', category: 'Status'},
        {id: 'fa-trophy', label: 'Trophy', category: 'Status'},
        {id: 'fa-star', label: 'Star', category: 'Status'},
        {id: 'fa-bar-chart', label: 'Bar Chart', category: 'Analytics'},
        {id: 'fa-line-chart', label: 'Line Chart', category: 'Analytics'},
        {id: 'fa-pie-chart', label: 'Pie Chart', category: 'Analytics'},
        {id: 'fa-area-chart', label: 'Area Chart', category: 'Analytics'},
        {id: 'fa-percent', label: 'Percent', category: 'Numbers'},
        {id: 'fa-hashtag', label: 'Number', category: 'Numbers'},
        {id: 'fa-sort-numeric-asc', label: 'Ranking', category: 'Numbers'},
        {id: 'fa-dollar', label: 'Dollar', category: 'Financial'},
        {id: 'fa-gbp', label: 'Pound', category: 'Financial'},
        {id: 'fa-money', label: 'Money', category: 'Financial'},
        {id: 'fa-tasks', label: 'Tasks', category: 'Progress'},
        {id: 'fa-spinner', label: 'Progress', category: 'Progress'},
        {id: 'fa-bullseye', label: 'Target', category: 'Progress'},
        {id: 'fa-flag', label: 'Flag', category: 'Progress'},
        {id: 'fa-envelope', label: 'Email', category: 'Communication'},
        {id: 'fa-comments', label: 'Comments', category: 'Communication'},
        {id: 'fa-bell', label: 'Notifications', category: 'Communication'},
        {id: 'fa-exclamation-triangle', label: 'Warning', category: 'Alerts'},
        {id: 'fa-info-circle', label: 'Info', category: 'Alerts'},
        {id: 'fa-thumbs-up', label: 'Thumbs Up', category: 'Feedback'},
        {id: 'fa-thumbs-down', label: 'Thumbs Down', category: 'Feedback'},
        {id: 'fa-smile-o', label: 'Satisfaction', category: 'Feedback'}
    ];

    // Default icons for KPI cards (fallback when no icon selected).
    var DEFAULT_KPI_ICONS = ['fa-users', 'fa-graduation-cap', 'fa-clock-o', 'fa-check-circle'];

    /**
     * Report Selector component for picking KPI/Tab reports.
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
        this.filteredReports = [];
        this.selectedReports = [];
        this.strings = {};
        this.searchTimeout = null;
        this.highlightedIndex = -1;
        this.isDropdownOpen = false;

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

                // Show container - Moodle's hideIf handles visibility based on display_mode.
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
                {key: 'loading', component: 'block_adeptus_insights'},
                {key: 'config_alert_report_placeholder', component: 'block_adeptus_insights'},
                {key: 'js_failed_load_reports', component: 'block_adeptus_insights'}
            ]).then(function(strings) {
                self.strings = {
                    addreport: strings[0],
                    removereport: strings[1],
                    noreportsselected: strings[2],
                    selectareport: strings[3],
                    loading: strings[4],
                    searchplaceholder: strings[5] || 'Search reports by name, category, or type...',
                    failedLoadReports: strings[6]
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
         * Render the report selector UI with searchable dropdown.
         */
        render: function() {
            var modeLabels = {
                'kpi': 'KPI Reports',
                'tabs': 'Tab Reports',
                'manual': 'Selected Reports'
            };
            var modeLabel = modeLabels[this.mode] || 'Reports';
            var uniqueId = this.mode + '-search-' + Date.now();

            var html = '<div class="report-selector-ui">' +
                '<div class="report-selector-header d-flex justify-content-between align-items-center mb-2">' +
                '<span class="font-weight-bold">' + modeLabel + '</span>' +
                '<span class="badge badge-secondary block-adeptus-report-count">0 selected</span>' +
                '</div>' +

                // Searchable dropdown container
                '<div class="report-selector-search-container mb-3 position-relative">' +
                '<div class="input-group">' +
                '<div class="input-group-prepend">' +
                '<span class="input-group-text"><i class="fa fa-search"></i></span>' +
                '</div>' +
                '<input type="text" class="form-control report-search-input" ' +
                'id="' + uniqueId + '" ' +
                'placeholder="' + this.strings.searchplaceholder + '" ' +
                'autocomplete="off" ' +
                'aria-haspopup="listbox" ' +
                'aria-expanded="false">' +
                '<div class="input-group-append">' +
                '<button type="button" class="btn btn-primary report-selector-add-btn" disabled>' +
                '<i class="fa fa-plus"></i> ' + this.strings.addreport +
                '</button>' +
                '</div>' +
                '</div>' +

                // Dropdown results container
                '<div class="report-search-dropdown position-absolute w-100 bg-white border rounded shadow-sm" ' +
                'style="display:none; z-index:1050; max-height:350px; overflow-y:auto; top:100%; left:0;">' +
                '<div class="report-search-results"></div>' +
                '<div class="report-search-empty text-muted text-center py-3" style="display:none;">' +
                '<i class="fa fa-search"></i> No matching reports found' +
                '</div>' +
                '<div class="report-search-loading text-center py-3" style="display:none;">' +
                '<i class="fa fa-spinner fa-spin"></i> ' + this.strings.loading +
                '</div>' +
                '</div>' +
                '</div>' +

                // Selected reports list
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
            this.updateSelectedCount();
        },

        /**
         * Fetch reports from the API.
         */
        fetchReports: function() {
            var self = this;

            if (!this.apiKey) {
                this.container.find('.report-search-loading').hide();
                this.container.find('.report-search-empty').show().html(
                    '<i class="fa fa-exclamation-triangle"></i> API key not configured'
                );
                return;
            }

            this.container.find('.report-search-loading').show();

            var baseUrl = 'https://backend.adeptus360.com/api/v1';

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
                    r.displayName = r.name || r.title || r.display_name || r.slug;
                    r.categoryName = r.category_info ? r.category_info.name : 'General';
                    allReports.push(r);
                });

                // Process AI reports
                var aiReports = aiResult[0].reports || aiResult[0].data || [];
                aiReports.forEach(function(r) {
                    r.source = 'ai';
                    r.displayName = r.name || r.title || r.display_name || r.slug;
                    r.categoryName = r.category_info ? r.category_info.name : 'AI Generated';
                    allReports.push(r);
                });

                // Sort alphabetically by name
                allReports.sort(function(a, b) {
                    return a.displayName.localeCompare(b.displayName);
                });

                self.reports = allReports;
                self.filteredReports = self.getAvailableReports();
                self.container.find('.report-search-loading').hide();
                self.renderSelectedList();
            }).fail(function() {
                self.container.find('.report-search-loading').hide();
                Notification.addNotification({
                    message: self.strings.failedLoadReports,
                    type: 'error'
                });
            });
        },

        /**
         * Get reports that haven't been selected yet.
         *
         * @return {Array}
         */
        getAvailableReports: function() {
            var self = this;
            return this.reports.filter(function(report) {
                return !self.selectedReports.some(function(s) {
                    return s.slug === report.slug && s.source === report.source;
                });
            });
        },

        /**
         * Filter reports based on search query.
         *
         * @param {string} query Search query
         * @return {Array} Filtered reports
         */
        filterReports: function(query) {
            var available = this.getAvailableReports();

            if (!query || query.length < 1) {
                return available;
            }

            query = query.toLowerCase().trim();
            var terms = query.split(/\s+/);

            return available.filter(function(report) {
                var searchText = (
                    report.displayName + ' ' +
                    report.categoryName + ' ' +
                    report.source + ' ' +
                    (report.slug || '')
                ).toLowerCase();

                // All terms must match
                return terms.every(function(term) {
                    return searchText.indexOf(term) !== -1;
                });
            }).slice(0, 50); // Limit to 50 results for performance
        },

        /**
         * Render the search dropdown results.
         *
         * @param {string} query Current search query for highlighting
         */
        renderSearchResults: function(query) {
            var self = this;
            var resultsContainer = this.container.find('.report-search-results');
            var emptyContainer = this.container.find('.report-search-empty');

            resultsContainer.empty();

            if (this.filteredReports.length === 0) {
                emptyContainer.show();
                return;
            }

            emptyContainer.hide();

            // Group by category
            var byCategory = {};
            this.filteredReports.forEach(function(report) {
                var cat = report.categoryName || 'Other';
                if (!byCategory[cat]) {
                    byCategory[cat] = [];
                }
                byCategory[cat].push(report);
            });

            var index = 0;
            Object.keys(byCategory).sort().forEach(function(category) {
                // Category header
                resultsContainer.append(
                    '<div class="report-search-category px-3 py-1 bg-light border-bottom">' +
                    '<small class="text-muted font-weight-bold">' + self.escapeHtml(category) + '</small>' +
                    '</div>'
                );

                // Reports in category
                byCategory[category].forEach(function(report) {
                    var highlightedName = self.highlightMatch(report.displayName, query);
                    var sourceBadge = report.source === 'ai'
                        ? '<span class="badge badge-info ml-2">AI</span>'
                        : '<span class="badge badge-primary ml-2">Wizard</span>';

                    var itemHtml = '<div class="report-search-item px-3 py-2 cursor-pointer" ' +
                        'data-index="' + index + '" ' +
                        'data-slug="' + report.slug + '" ' +
                        'data-source="' + report.source + '" ' +
                        'role="option" ' +
                        'tabindex="-1">' +
                        '<div class="d-flex justify-content-between align-items-center">' +
                        '<span class="report-name">' + highlightedName + '</span>' +
                        sourceBadge +
                        '</div>' +
                        '</div>';

                    resultsContainer.append(itemHtml);
                    index++;
                });
            });

            // Apply hover styling
            resultsContainer.find('.report-search-item')
                .css('cursor', 'pointer')
                .hover(
                    function() {
                        $(this).addClass('bg-light');
                        self.highlightedIndex = parseInt($(this).data('index'), 10);
                        self.updateHighlight();
                    },
                    function() {
                        $(this).removeClass('bg-light');
                    }
                );
        },

        /**
         * Highlight matching text in a string.
         *
         * @param {string} text Text to highlight
         * @param {string} query Search query
         * @return {string} HTML with highlighted matches
         */
        highlightMatch: function(text, query) {
            if (!query || !text) {
                return this.escapeHtml(text);
            }

            var escaped = this.escapeHtml(text);
            var terms = query.toLowerCase().trim().split(/\s+/);

            terms.forEach(function(term) {
                if (term.length > 0) {
                    var regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                    escaped = escaped.replace(regex, '<mark class="bg-warning px-0">$1</mark>');
                }
            });

            return escaped;
        },

        /**
         * Update the highlighted item in dropdown.
         */
        updateHighlight: function() {
            var items = this.container.find('.report-search-item');
            items.removeClass('bg-primary text-white bg-light');

            if (this.highlightedIndex >= 0 && this.highlightedIndex < items.length) {
                var item = items.eq(this.highlightedIndex);
                item.removeClass('bg-light').addClass('bg-primary text-white');

                // Scroll into view
                var dropdown = this.container.find('.report-search-dropdown');
                var itemTop = item.position().top;
                var itemHeight = item.outerHeight();
                var dropdownHeight = dropdown.height();
                var scrollTop = dropdown.scrollTop();

                if (itemTop < 0) {
                    dropdown.scrollTop(scrollTop + itemTop);
                } else if (itemTop + itemHeight > dropdownHeight) {
                    dropdown.scrollTop(scrollTop + itemTop + itemHeight - dropdownHeight);
                }
            }
        },

        /**
         * Open the search dropdown.
         */
        openDropdown: function() {
            if (this.isDropdownOpen) {
                return;
            }

            this.isDropdownOpen = true;
            this.highlightedIndex = -1;

            var dropdown = this.container.find('.report-search-dropdown');
            var input = this.container.find('.report-search-input');

            dropdown.show();
            input.attr('aria-expanded', 'true');

            // Render current results
            var query = input.val();
            this.filteredReports = this.filterReports(query);
            this.renderSearchResults(query);
        },

        /**
         * Close the search dropdown.
         */
        closeDropdown: function() {
            if (!this.isDropdownOpen) {
                return;
            }

            this.isDropdownOpen = false;
            this.highlightedIndex = -1;

            var dropdown = this.container.find('.report-search-dropdown');
            var input = this.container.find('.report-search-input');

            dropdown.hide();
            input.attr('aria-expanded', 'false');
        },

        /**
         * Handle search input.
         *
         * @param {string} query Search query
         */
        handleSearch: function(query) {
            var self = this;

            // Clear previous timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            // Debounce search
            this.searchTimeout = setTimeout(function() {
                self.filteredReports = self.filterReports(query);
                self.renderSearchResults(query);
                self.highlightedIndex = -1;
            }, 150);
        },

        /**
         * Select the currently highlighted item.
         */
        selectHighlightedItem: function() {
            if (this.highlightedIndex < 0 || this.highlightedIndex >= this.filteredReports.length) {
                return;
            }

            var report = this.filteredReports[this.highlightedIndex];
            this.addReport(report);
        },

        /**
         * Add a report to the selection.
         *
         * @param {Object} report Report object
         */
        addReport: function(report) {
            // Determine default icon based on current count (for KPI mode)
            var defaultIcon = DEFAULT_KPI_ICONS[this.selectedReports.length % DEFAULT_KPI_ICONS.length];

            // Add to selected list
            var reportData = {
                slug: report.slug,
                source: report.source,
                name: report.displayName || report.name || report.title || report.slug
            };

            // Add icon for KPI mode only
            if (this.mode === 'kpi') {
                reportData.icon = defaultIcon;
            }

            this.selectedReports.push(reportData);

            this.saveSelection();
            this.updateSelectedCount();

            // Update available reports and re-render
            this.filteredReports = this.getAvailableReports();

            // Clear search and close dropdown
            var input = this.container.find('.report-search-input');
            input.val('');
            this.closeDropdown();

            // Re-render selected list
            this.renderSelectedList();
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

                var name = selected.name || (report ? report.displayName : selected.slug);
                var categoryName = report ? report.categoryName : 'Unknown';

                // Build icon picker for KPI mode
                var iconPicker = '';
                if (self.mode === 'kpi') {
                    var currentIcon = selected.icon || DEFAULT_KPI_ICONS[index % DEFAULT_KPI_ICONS.length];
                    iconPicker = self.buildIconPicker(currentIcon, index);
                }

                var item = '<li class="list-group-item d-flex justify-content-between align-items-center" ' +
                    'data-index="' + index + '" data-slug="' + selected.slug + '" data-source="' + selected.source + '">' +
                    '<div class="d-flex align-items-center flex-grow-1 min-width-0">' +
                    '<span class="drag-handle mr-2" style="cursor: move;"><i class="fa fa-bars text-muted"></i></span>' +
                    iconPicker +
                    '<span class="badge badge-secondary mr-2">' + (index + 1) + '</span>' +
                    '<span class="text-truncate" title="' + self.escapeHtml(name) + '">' + self.escapeHtml(name) + '</span>' +
                    '<small class="text-muted ml-2 d-none d-md-inline">[' + self.escapeHtml(categoryName) + ']</small>' +
                    '<span class="badge badge-' + (selected.source === 'ai' ? 'info' : 'primary') + ' ml-2">' +
                    selected.source.toUpperCase() + '</span>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger report-selector-remove ml-2" title="' +
                    self.strings.removereport + '">' +
                    '<i class="fa fa-times"></i>' +
                    '</button>' +
                    '</li>';

                list.append(item);
            });

            this.updateListDisplay();
            this.initSortable();
            this.initIconPickers();
        },

        /**
         * Build icon picker dropdown HTML.
         *
         * @param {string} currentIcon Current icon class
         * @param {number} index Report index
         * @return {string} HTML for icon picker
         */
        buildIconPicker: function(currentIcon, index) {
            var html = '<div class="dropdown block-adeptus-kpi-icon-picker mr-2" data-index="' + index + '">' +
                '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" ' +
                'data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Select icon">' +
                '<i class="fa ' + currentIcon + '"></i>' +
                '</button>' +
                '<div class="dropdown-menu block-adeptus-kpi-icon-dropdown">' +
                '<div class="px-2 py-1">' +
                '<small class="text-muted">Select an icon</small>' +
                '</div>' +
                '<div class="dropdown-divider"></div>' +
                '<div class="block-adeptus-kpi-icon-grid px-2">';

            // Group icons by category
            var categories = {};
            KPI_ICONS.forEach(function(icon) {
                if (!categories[icon.category]) {
                    categories[icon.category] = [];
                }
                categories[icon.category].push(icon);
            });

            // Render icons grouped by category
            Object.keys(categories).forEach(function(category) {
                html += '<div class="block-adeptus-kpi-icon-category mb-1">' +
                    '<small class="text-muted d-block mb-1">' + category + '</small>' +
                    '<div class="d-flex flex-wrap">';

                categories[category].forEach(function(icon) {
                    var isActive = icon.id === currentIcon ? ' active' : '';
                    html += '<button type="button" class="btn btn-sm btn-light block-adeptus-kpi-icon-option m-1' + isActive + '" ' +
                        'data-icon="' + icon.id + '" title="' + icon.label + '">' +
                        '<i class="fa ' + icon.id + '"></i>' +
                        '</button>';
                });

                html += '</div></div>';
            });

            html += '</div></div></div>';

            return html;
        },

        /**
         * Initialize icon picker event handlers.
         * Manually handles dropdown toggle since Bootstrap doesn't auto-init dynamic content.
         */
        initIconPickers: function() {
            var self = this;

            // Handle dropdown toggle (manual implementation for dynamic content)
            this.container.find('.block-adeptus-kpi-icon-picker .dropdown-toggle').off('click.iconpicker').on('click.iconpicker', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $btn = $(this);
                var $picker = $btn.closest('.block-adeptus-kpi-icon-picker');
                var $menu = $picker.find('.dropdown-menu');
                var isOpen = $menu.hasClass('show');

                // Close all other open dropdowns first
                self.container.find('.block-adeptus-kpi-icon-picker .dropdown-menu.show').removeClass('show');
                self.container.find('.block-adeptus-kpi-icon-picker .dropdown-toggle').attr('aria-expanded', 'false');

                if (!isOpen) {
                    // Calculate if dropdown would overflow - use dropup if needed
                    var pickerRect = $picker[0].getBoundingClientRect();
                    var viewportHeight = window.innerHeight;
                    var spaceBelow = viewportHeight - pickerRect.bottom;

                    // Use dropup if less than 320px below
                    if (spaceBelow < 320) {
                        $picker.addClass('dropup');
                    } else {
                        $picker.removeClass('dropup');
                    }

                    // Open this dropdown
                    $menu.addClass('show');
                    $btn.attr('aria-expanded', 'true');
                }
            });

            // Handle icon selection
            this.container.find('.block-adeptus-kpi-icon-option').off('click.iconpicker').on('click.iconpicker', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $btn = $(this);
                var $picker = $btn.closest('.block-adeptus-kpi-icon-picker');
                var index = parseInt($picker.data('index'), 10);
                var newIcon = $btn.data('icon');

                // Update selected report
                if (self.selectedReports[index]) {
                    self.selectedReports[index].icon = newIcon;
                    self.saveSelection();

                    // Update picker button icon
                    $picker.find('.dropdown-toggle i').removeClass().addClass('fa ' + newIcon);

                    // Update active state
                    $picker.find('.block-adeptus-kpi-icon-option').removeClass('active');
                    $btn.addClass('active');

                    // Close dropdown
                    $picker.find('.dropdown-menu').removeClass('show');
                    $picker.find('.dropdown-toggle').attr('aria-expanded', 'false');
                }
            });

            // Close dropdown when clicking outside
            $(document).off('click.iconpicker-close').on('click.iconpicker-close', function(e) {
                if (!$(e.target).closest('.block-adeptus-kpi-icon-picker').length) {
                    self.container.find('.block-adeptus-kpi-icon-picker .dropdown-menu.show').removeClass('show');
                    self.container.find('.block-adeptus-kpi-icon-picker .dropdown-toggle').attr('aria-expanded', 'false');
                }
            });
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
         * Update the selected count badge.
         */
        updateSelectedCount: function() {
            var count = this.selectedReports.length;
            var text = count === 1 ? '1 selected' : count + ' selected';
            this.container.find('.block-adeptus-report-count').text(text);
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
                $(this).addClass('dragging opacity-50');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
            });

            list.on('dragend', 'li', function() {
                $(this).removeClass('dragging opacity-50');
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
            var input = this.container.find('.report-search-input');
            var dropdown = this.container.find('.report-search-dropdown');

            // Focus on input opens dropdown
            input.on('focus', function() {
                self.openDropdown();
            });

            // Input changes trigger search
            input.on('input', function() {
                var query = $(this).val();
                self.openDropdown();
                self.handleSearch(query);
            });

            // Keyboard navigation
            input.on('keydown', function(e) {
                var items = self.container.find('.report-search-item');
                var maxIndex = items.length - 1;

                switch (e.keyCode) {
                    case 40: // Down arrow
                        e.preventDefault();
                        if (!self.isDropdownOpen) {
                            self.openDropdown();
                        }
                        self.highlightedIndex = Math.min(self.highlightedIndex + 1, maxIndex);
                        self.updateHighlight();
                        break;

                    case 38: // Up arrow
                        e.preventDefault();
                        self.highlightedIndex = Math.max(self.highlightedIndex - 1, 0);
                        self.updateHighlight();
                        break;

                    case 13: // Enter
                        e.preventDefault();
                        if (self.highlightedIndex >= 0) {
                            self.selectHighlightedItem();
                        }
                        break;

                    case 27: // Escape
                        e.preventDefault();
                        self.closeDropdown();
                        input.blur();
                        break;

                    case 9: // Tab
                        self.closeDropdown();
                        break;
                }
            });

            // Click on dropdown item
            dropdown.on('click', '.report-search-item', function() {
                var slug = $(this).data('slug');
                var source = $(this).data('source');
                var report = self.reports.find(function(r) {
                    return r.slug === slug && r.source === source;
                });
                if (report) {
                    self.addReport(report);
                }
            });

            // Click outside closes dropdown
            $(document).on('click', function(e) {
                if (!$(e.target).closest(self.container).length) {
                    self.closeDropdown();
                }
            });

            // Remove report button
            this.container.on('click', '.report-selector-remove', function(e) {
                e.stopPropagation();
                var item = $(this).closest('li');
                var slug = item.data('slug');
                var source = item.data('source');
                self.removeReport(slug, source);
            });

            // Note: Container visibility is handled by Moodle's hideIf based on display_mode.
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
            this.updateSelectedCount();
            this.filteredReports = this.getAvailableReports();
            this.renderSelectedList();

            // Re-render dropdown if open
            if (this.isDropdownOpen) {
                var query = this.container.find('.report-search-input').val();
                this.filteredReports = this.filterReports(query);
                this.renderSearchResults(query);
            }
        },

        /**
         * Save selection to textarea.
         */
        saveSelection: function() {
            this.textarea.val(JSON.stringify(this.selectedReports)).trigger('change');
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

    /**
     * AlertsManager class for managing multiple alert configurations.
     *
     * @param {Object} options Configuration options
     */
    var AlertsManager = function(options) {
        this.apiKey = options.apiKey || '';
        this.container = null;
        this.textarea = null;
        this.alerts = [];
        this.reports = [];
        this.filteredReports = [];
        this.operators = {};
        this.intervals = {};
        this.cooldowns = {};
        this.roles = {};
        this.editingIndex = -1;
        this.isDropdownOpen = false;
        this.isUserDropdownOpen = false;
        this.searchTimeout = null;
        this.userSearchTimeout = null;
        this.selectedUsers = [];
        this.strings = {};

        this.init();
    };

    AlertsManager.prototype = {
        /**
         * Initialize the alerts manager.
         */
        init: function() {
            var self = this;

            this.container = $('#alerts-manager-container');
            this.textarea = $('#id_config_alerts_json');

            if (!this.container.length) {
                return;
            }

            // Load language strings first, then proceed with initialization.
            this.loadStrings().then(function() {
                // Load configuration data from container attributes.
                self.operators = JSON.parse(self.container.attr('data-operators') || '{}');
                self.intervals = JSON.parse(self.container.attr('data-intervals') || '{}');
                self.cooldowns = JSON.parse(self.container.attr('data-cooldowns') || '{}');
                self.roles = JSON.parse(self.container.attr('data-roles') || '{}');

                // Load existing alerts.
                var existingAlerts = JSON.parse(self.container.attr('data-existing-alerts') || '[]');
                self.alerts = existingAlerts;

                // Clean up any orphaned backend alerts that no longer exist in local config.
                // This ensures backend stays in sync with Moodle's source of truth.
                if (existingAlerts.length > 0) {
                    self.cleanupOrphanedAlerts();
                }

                // Populate select options.
                self.populateSelectOptions();

                // Fetch reports for the dropdown.
                self.fetchReports();

                // Bind events.
                self.bindEvents();

                // Initial render.
                self.renderAlertsList();
                self.saveToTextarea();

                // Show/hide based on alerts_enabled checkbox.
                self.handleAlertsEnabledToggle();
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
                {key: 'edit_alert', component: 'block_adeptus_insights'},
                {key: 'delete_alert', component: 'block_adeptus_insights'},
                {key: 'alert_delete_confirm', component: 'block_adeptus_insights'},
                {key: 'add_new_alert', component: 'block_adeptus_insights'},
                {key: 'alert_disabled', component: 'block_adeptus_insights'},
                {key: 'config_add_alert', component: 'block_adeptus_insights'},
                {key: 'js_selected', component: 'block_adeptus_insights'},
                {key: 'js_alert_no_reports', component: 'block_adeptus_insights'},
                {key: 'js_no_thresholds_set', component: 'block_adeptus_insights'},
                {key: 'alert_threshold_warning_only', component: 'block_adeptus_insights'},
                {key: 'alert_threshold_critical_only', component: 'block_adeptus_insights'},
                {key: 'alert_status_badge_warning', component: 'block_adeptus_insights'},
                {key: 'alert_status_badge_critical', component: 'block_adeptus_insights'},
                {key: 'alert_status_badge_ok', component: 'block_adeptus_insights'},
                {key: 'js_failed_load_reports', component: 'block_adeptus_insights'},
                {key: 'js_alert_failed_create', component: 'block_adeptus_insights'},
                {key: 'js_alert_failed_update', component: 'block_adeptus_insights'},
                {key: 'js_alert_failed_delete', component: 'block_adeptus_insights'},
                {key: 'js_alert_created', component: 'block_adeptus_insights'},
                {key: 'js_alert_updated', component: 'block_adeptus_insights'},
                {key: 'js_alert_deleted', component: 'block_adeptus_insights'},
                {key: 'js_alert_select_report', component: 'block_adeptus_insights'},
                {key: 'js_alert_enter_threshold', component: 'block_adeptus_insights'}
            ]).then(function(strings) {
                self.strings = {
                    editAlert: strings[0],
                    deleteAlert: strings[1],
                    deleteConfirm: strings[2],
                    addNewAlert: strings[3],
                    disabled: strings[4],
                    addAlert: strings[5],
                    selected: strings[6],
                    noReports: strings[7],
                    noThresholdsSet: strings[8],
                    thresholdWarning: strings[9],
                    thresholdCritical: strings[10],
                    statusWarning: strings[11],
                    statusCritical: strings[12],
                    statusOk: strings[13],
                    failedLoadReports: strings[14],
                    failedCreateAlert: strings[15],
                    failedUpdateAlert: strings[16],
                    failedDeleteAlert: strings[17],
                    alertCreated: strings[18],
                    alertUpdated: strings[19],
                    alertDeleted: strings[20],
                    selectReport: strings[21],
                    enterThreshold: strings[22]
                };
            });
        },

        /**
         * Populate select element options.
         */
        populateSelectOptions: function() {
            var self = this;

            // Populate operators.
            var operatorSelect = $('#alert-edit-operator');
            operatorSelect.empty();
            $.each(this.operators, function(key, value) {
                operatorSelect.append($('<option>', {value: key, text: value}));
            });

            // Populate intervals.
            var intervalSelect = $('#alert-edit-interval');
            intervalSelect.empty();
            $.each(this.intervals, function(key, value) {
                intervalSelect.append($('<option>', {value: key, text: value}));
            });

            // Populate cooldowns.
            var cooldownSelect = $('#alert-edit-cooldown');
            cooldownSelect.empty();
            $.each(this.cooldowns, function(key, value) {
                cooldownSelect.append($('<option>', {value: key, text: value}));
            });

            // Populate role filter dropdown.
            var roleFilter = $('#alert-edit-role-filter');
            if (roleFilter.length) {
                roleFilter.find('option:not(:first)').remove(); // Keep "All users" option.
                $.each(this.roles, function(key, value) {
                    roleFilter.append($('<option>', {value: key, text: value}));
                });
            } else {
                // Element not found yet, might be in a modal - will be populated on openEditPanel.
                console.warn('Role filter element not found during init, will populate on panel open');
            }
        },

        /**
         * Load reports from the block's selected reports.
         *
         * Only shows reports that are already configured in the block's
         * KPI or Tabs report selectors, not all available reports.
         */
        fetchReports: function() {
            var self = this;
            var allReports = [];

            // Get selected reports from the KPI report selector textarea.
            var kpiTextarea = $('[name="config_kpi_selected_reports"]');
            if (kpiTextarea.length && kpiTextarea.val()) {
                try {
                    var kpiReports = JSON.parse(kpiTextarea.val());
                    if (Array.isArray(kpiReports)) {
                        kpiReports.forEach(function(r) {
                            r.displayName = r.name || r.title || r.display_name || r.slug;
                            r.categoryName = r.category_info ? r.category_info.name : (r.source === 'ai' ? 'AI Generated' : 'General');
                            allReports.push(r);
                        });
                    }
                } catch (e) {
                    // Invalid JSON, ignore.
                }
            }

            // Get selected reports from the Tabs report selector textarea.
            var tabsTextarea = $('[name="config_tabs_selected_reports"]');
            if (tabsTextarea.length && tabsTextarea.val()) {
                try {
                    var tabsReports = JSON.parse(tabsTextarea.val());
                    if (Array.isArray(tabsReports)) {
                        tabsReports.forEach(function(r) {
                            // Avoid duplicates (same slug).
                            var exists = allReports.some(function(existing) {
                                return existing.slug === r.slug;
                            });
                            if (!exists) {
                                r.displayName = r.name || r.title || r.display_name || r.slug;
                                r.categoryName = r.category_info ? r.category_info.name : (r.source === 'ai' ? 'AI Generated' : 'General');
                                allReports.push(r);
                            }
                        });
                    }
                } catch (e) {
                    // Invalid JSON, ignore.
                }
            }

            // Sort alphabetically by display name.
            allReports.sort(function(a, b) {
                return (a.displayName || '').localeCompare(b.displayName || '');
            });

            this.reports = allReports;
            this.filteredReports = allReports;

            // Update UI to show message if no reports selected.
            if (allReports.length === 0) {
                this.container.find('.alert-report-empty-message').remove();
                this.container.find('.alerts-edit-panel').prepend(
                    '<div class="alert alert-info alert-report-empty-message">' +
                    '<i class="fa fa-info-circle"></i> ' +
                    this.strings.noReports +
                    '</div>'
                );
            }
        },

        /**
         * Handle alerts_enabled checkbox toggle.
         */
        handleAlertsEnabledToggle: function() {
            var self = this;
            var checkbox = $('[name="config_alerts_enabled"]');
            var displayMode = $('[name="config_display_mode"]');

            var updateVisibility = function() {
                var enabled = checkbox.is(':checked');
                var isKpi = displayMode.val() === 'kpi';

                if (enabled && isKpi) {
                    self.container.show();
                } else {
                    self.container.hide();
                }
            };

            checkbox.on('change', updateVisibility);
            displayMode.on('change', updateVisibility);
            updateVisibility();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Listen for changes to the report selector textareas.
            // When reports are added/removed from KPI or Tabs, refresh the alerts report list.
            $('[name="config_kpi_selected_reports"], [name="config_tabs_selected_reports"]').on('change', function() {
                self.fetchReports();
            });

            // Add alert button.
            this.container.on('click', '#add-alert-btn', function() {
                self.openEditPanel(-1);
            });

            // Edit alert button.
            this.container.on('click', '.alert-edit-btn', function() {
                var index = $(this).closest('.alert-item').data('index');
                self.openEditPanel(index);
            });

            // Delete alert button.
            this.container.on('click', '.alert-delete-btn', function() {
                var index = $(this).closest('.alert-item').data('index');
                if (confirm(self.strings.deleteConfirm)) {
                    self.deleteAlert(index);
                }
            });

            // Toggle alert enabled/disabled.
            this.container.on('change', '.alert-enabled-toggle', function() {
                var index = $(this).closest('.alert-item').data('index');
                self.alerts[index].enabled = $(this).is(':checked');
                self.saveToTextarea();
                self.renderAlertsList();
            });

            // Panel close buttons.
            this.container.on('click', '.alert-panel-close, .alert-panel-cancel', function() {
                self.closeEditPanel();
            });

            // Panel save button.
            this.container.on('click', '.alert-panel-save', function() {
                self.saveEditPanel();
            });

            // Toggle email addresses visibility based on notify_email checkbox.
            $('#alert-edit-notify-email').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#email-addresses-group').show();
                } else {
                    $('#email-addresses-group').hide();
                }
            });

            // Report search input.
            var searchInput = $('#alert-edit-report-search');
            var dropdown = $('#alert-report-dropdown');

            searchInput.on('focus', function() {
                self.openReportDropdown();
            });

            searchInput.on('input', function() {
                var query = $(this).val();
                self.handleReportSearch(query);
            });

            searchInput.on('keydown', function(e) {
                if (e.keyCode === 27) { // Escape
                    self.closeReportDropdown();
                }
            });

            dropdown.on('click', '.report-dropdown-item', function() {
                var slug = $(this).data('slug');
                var name = $(this).data('name');
                self.selectReport(slug, name);
            });

            // Close dropdown when clicking outside.
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#alert-edit-report-search, #alert-report-dropdown').length) {
                    self.closeReportDropdown();
                }
                if (!$(e.target).closest('#alert-edit-user-search, #alert-user-dropdown').length) {
                    self.closeUserDropdown();
                }
            });

            // Role filter change - reload users.
            $('#alert-edit-role-filter').on('change', function() {
                // Clear search and reload with new filter.
                $('#alert-edit-user-search').val('');
                self.loadUsers();
            });

            // User search input.
            var userSearchInput = $('#alert-edit-user-search');
            var userDropdown = $('#alert-user-dropdown');

            userSearchInput.on('focus', function() {
                self.openUserDropdown();
            });

            userSearchInput.on('input', function() {
                var query = $(this).val();
                self.handleUserSearch(query);
            });

            userSearchInput.on('keydown', function(e) {
                if (e.keyCode === 27) { // Escape.
                    self.closeUserDropdown();
                }
            });

            userDropdown.on('click', '.user-dropdown-item', function() {
                var userId = $(this).data('userid');
                var userName = $(this).data('name');
                var userEmail = $(this).data('email');
                self.addSelectedUser(userId, userName, userEmail);
            });

            // Remove selected user.
            this.container.on('click', '.remove-selected-user', function() {
                var userId = $(this).data('userid');
                self.removeSelectedUser(userId);
            });
        },

        /**
         * Render the alerts list.
         */
        renderAlertsList: function() {
            var self = this;
            var list = $('#alerts-list');
            var emptyState = list.find('.alerts-empty');

            // Clear existing items (except empty state).
            list.find('.alert-item').remove();

            if (this.alerts.length === 0) {
                emptyState.show();
            } else {
                emptyState.hide();

                this.alerts.forEach(function(alert, index) {
                    var reportName = alert.report_name || alert.report_slug;
                    var alertName = alert.alert_name || reportName;
                    var operatorLabel = self.operators[alert.operator] || alert.operator;

                    // Build threshold display.
                    var thresholds = [];
                    if (alert.warning_value !== null && alert.warning_value !== '') {
                        thresholds.push(self.strings.thresholdWarning.replace('{$a}', alert.warning_value));
                    }
                    if (alert.critical_value !== null && alert.critical_value !== '') {
                        thresholds.push(self.strings.thresholdCritical.replace('{$a}', alert.critical_value));
                    }
                    var thresholdText = thresholds.join(', ') || self.strings.noThresholdsSet;

                    // Get interval label.
                    var intervalLabel = self.intervals[alert.check_interval] || alert.check_interval + 's';

                    // Status badge.
                    var statusBadge = '';
                    if (alert.current_status === 'warning') {
                        statusBadge = '<span class="badge badge-warning ml-2">' + self.strings.statusWarning + '</span>';
                    } else if (alert.current_status === 'critical') {
                        statusBadge = '<span class="badge badge-danger ml-2">' + self.strings.statusCritical + '</span>';
                    } else if (alert.current_status) {
                        statusBadge = '<span class="badge badge-success ml-2">' + self.strings.statusOk + '</span>';
                    }

                    var html = '<div class="alert-item card mb-2" data-index="' + index + '">' +
                        '<div class="card-body py-2 px-3">' +
                        '<div class="d-flex justify-content-between align-items-start">' +
                        '<div class="flex-grow-1">' +
                        '<div class="d-flex align-items-center mb-1">' +
                        '<strong>' + self.escapeHtml(alertName) + '</strong>' +
                        statusBadge +
                        (alert.enabled ? '' : '<span class="badge badge-secondary ml-2">' + self.strings.disabled + '</span>') +
                        '</div>' +
                        '<small class="text-muted d-block">' + self.escapeHtml(reportName) + '</small>' +
                        '<small class="text-muted d-block">' + self.escapeHtml(operatorLabel) + ' | ' +
                        thresholdText + ' | ' + intervalLabel + '</small>' +
                        '</div>' +
                        '<div class="alert-actions d-flex align-items-center">' +
                        '<div class="custom-control custom-switch mr-2">' +
                        '<input type="checkbox" class="custom-control-input alert-enabled-toggle" ' +
                        'id="alert-enabled-' + index + '"' + (alert.enabled ? ' checked' : '') + '>' +
                        '<label class="custom-control-label" for="alert-enabled-' + index + '"></label>' +
                        '</div>' +
                        '<button type="button" class="btn btn-sm btn-outline-primary alert-edit-btn mr-1" title="' + self.strings.editAlert + '">' +
                        '<i class="fa fa-pencil"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger alert-delete-btn" title="' + self.strings.deleteAlert + '">' +
                        '<i class="fa fa-trash"></i>' +
                        '</button>' +
                        '</div>' +
                        '</div>' +
                        '</div>' +
                        '</div>';

                    list.append(html);
                });
            }

            // Update count badge.
            this.container.find('.alerts-count').text(this.alerts.length);
        },

        /**
         * Open the edit panel for an alert.
         *
         * @param {number} index Alert index (-1 for new)
         */
        openEditPanel: function(index) {
            var self = this;
            var panel = $('#alert-edit-panel');
            this.editingIndex = index;

            // Ensure role filter is populated (might not have been during init).
            var roleFilter = $('#alert-edit-role-filter');
            if (roleFilter.length && roleFilter.find('option').length <= 1) {
                $.each(this.roles, function(key, value) {
                    roleFilter.append($('<option>', {value: key, text: value}));
                });
            }

            // Reset form.
            $('#alert-edit-report').val('');
            $('#alert-edit-report-search').val('');
            $('#alert-edit-report-display').text('');
            $('#alert-edit-name').val('');
            $('#alert-edit-operator').val('gt');
            $('#alert-edit-warning').val('');
            $('#alert-edit-critical').val('');
            $('#alert-edit-interval').val('3600');
            $('#alert-edit-cooldown').val('3600');
            $('#alert-edit-notify-warning').prop('checked', true);
            $('#alert-edit-notify-critical').prop('checked', true);
            $('#alert-edit-notify-recovery').prop('checked', true);
            $('#alert-edit-notify-email').prop('checked', false);
            $('#alert-edit-email-addresses').val('');
            $('#email-addresses-group').hide();
            $('#alert-edit-role-filter').val('');
            this.selectedUsers = [];
            this.renderSelectedUsers();

            // Populate if editing existing.
            if (index >= 0 && this.alerts[index]) {
                var alert = this.alerts[index];
                $('#alert-edit-report').val(alert.report_slug);
                $('#alert-edit-report-search').val(alert.report_name || alert.report_slug);
                $('#alert-edit-report-display').text(this.strings.selected.replace('{$a}', alert.report_name || alert.report_slug));
                $('#alert-edit-name').val(alert.alert_name || '');
                $('#alert-edit-operator').val(alert.operator || 'gt');
                $('#alert-edit-warning').val(alert.warning_value || '');
                $('#alert-edit-critical').val(alert.critical_value || '');
                $('#alert-edit-interval').val(alert.check_interval || 3600);
                $('#alert-edit-cooldown').val(alert.cooldown_seconds || 3600);
                $('#alert-edit-notify-warning').prop('checked', alert.notify_on_warning !== false);
                $('#alert-edit-notify-critical').prop('checked', alert.notify_on_critical !== false);
                $('#alert-edit-notify-recovery').prop('checked', alert.notify_on_recovery !== false);
                $('#alert-edit-notify-email').prop('checked', !!alert.notify_email);
                $('#alert-edit-email-addresses').val(alert.notify_emails || '');
                if (alert.notify_email) {
                    $('#email-addresses-group').show();
                }
                // Load selected users from alert data.
                this.selectedUsers = alert.notify_users || [];
                this.renderSelectedUsers();

                panel.find('.alert-panel-title').text(this.strings.editAlert);
            } else {
                panel.find('.alert-panel-title').text(this.strings.addNewAlert);
            }

            panel.show();
        },

        /**
         * Close the edit panel.
         */
        closeEditPanel: function() {
            $('#alert-edit-panel').hide();
            this.editingIndex = -1;
        },

        /**
         * Get the API endpoint base URL for a report based on its source.
         *
         * @param {string} reportSlug Report slug.
         * @return {string} API endpoint path ('/wizard-reports/' or '/ai-reports/').
         */
        getReportEndpoint: function(reportSlug) {
            // Find the report in our list to get its source.
            var report = this.reports.find(function(r) {
                return r.slug === reportSlug;
            });

            // Default to wizard-reports if source is 'wizard' or unknown.
            if (report && report.source === 'ai') {
                return '/ai-reports/';
            }
            return '/wizard-reports/';
        },

        /**
         * Save the edit panel data - syncs to backend API.
         */
        saveEditPanel: function() {
            var self = this;
            var reportSlug = $('#alert-edit-report').val();
            var reportName = $('#alert-edit-report-search').val();
            var thresholdValue = $('#alert-edit-warning').val() || $('#alert-edit-critical').val();
            var operator = $('#alert-edit-operator').val();

            // Validation.
            if (!reportSlug) {
                Notification.addNotification({
                    message: this.strings.selectReport,
                    type: 'error'
                });
                return;
            }

            if (!thresholdValue) {
                Notification.addNotification({
                    message: this.strings.enterThreshold,
                    type: 'error'
                });
                return;
            }

            // Map operator to condition_type for backend API.
            var conditionType = 'threshold';
            var backendOperator = operator;
            if (operator === 'change_pct') {
                conditionType = 'change_percent';
                backendOperator = 'gte'; // Change by X% or more.
            } else if (operator === 'increase_pct') {
                conditionType = 'change_percent';
                backendOperator = 'gte';
            } else if (operator === 'decrease_pct') {
                conditionType = 'change_percent';
                backendOperator = 'lte';
                thresholdValue = -Math.abs(parseFloat(thresholdValue)); // Negative for decrease.
            }

            // Prepare backend API payload.
            // Note: cooldown_minutes is set to 0 to disable backend cooldown.
            // Moodle handles fire-once logic per severity (warning/critical/recovery)
            // via the block_adeptus_insights_alert_log table.
            var notifyEmail = $('#alert-edit-notify-email').is(':checked');
            var backendPayload = {
                name: $('#alert-edit-name').val() || reportName,
                condition_type: conditionType,
                operator: backendOperator,
                value: parseFloat(thresholdValue) || 0,
                cooldown_minutes: 0,
                is_enabled: true,
                notification_channels: notifyEmail ? ['email'] : ['moodle']
            };

            // Local alert data for UI.
            var alertData = {
                report_slug: reportSlug,
                report_name: reportName,
                alert_name: backendPayload.name,
                operator: operator,
                condition_type: conditionType,
                threshold_value: parseFloat(thresholdValue),
                warning_value: $('#alert-edit-warning').val() ? parseFloat($('#alert-edit-warning').val()) : null,
                critical_value: $('#alert-edit-critical').val() ? parseFloat($('#alert-edit-critical').val()) : null,
                check_interval: parseInt($('#alert-edit-interval').val(), 10),
                cooldown_seconds: parseInt($('#alert-edit-cooldown').val(), 10),
                notify_on_warning: $('#alert-edit-notify-warning').is(':checked'),
                notify_on_critical: $('#alert-edit-notify-critical').is(':checked'),
                notify_on_recovery: $('#alert-edit-notify-recovery').is(':checked'),
                notify_email: $('#alert-edit-notify-email').is(':checked'),
                notify_emails: $('#alert-edit-email-addresses').val().trim(),
                notify_users: this.selectedUsers,
                enabled: true
            };

            var isUpdate = this.editingIndex >= 0 && this.alerts[this.editingIndex] &&
                           this.alerts[this.editingIndex].backend_id;

            if (isUpdate) {
                // Update existing alert on backend.
                var backendId = this.alerts[this.editingIndex].backend_id;
                this.updateAlertOnBackend(reportSlug, backendId, backendPayload, alertData);
            } else {
                // Create new alert on backend.
                this.createAlertOnBackend(reportSlug, backendPayload, alertData);
            }
        },

        /**
         * Create a new alert on the backend.
         *
         * @param {string} reportSlug Report slug.
         * @param {Object} payload Backend API payload.
         * @param {Object} alertData Local alert data.
         */
        createAlertOnBackend: function(reportSlug, payload, alertData) {
            var self = this;
            var endpoint = this.getReportEndpoint(reportSlug);

            $.ajax({
                url: 'https://backend.adeptus360.com/api/v1' + endpoint +
                     encodeURIComponent(reportSlug) + '/alerts',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + this.apiKey,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify(payload),
                timeout: 15000
            }).done(function(response) {
                if (response.success && response.alert) {
                    // Store backend ID for future updates.
                    alertData.backend_id = response.alert.id;
                    alertData.current_status = 'ok';

                    if (self.editingIndex >= 0) {
                        self.alerts[self.editingIndex] = alertData;
                    } else {
                        self.alerts.push(alertData);
                    }

                    self.saveToTextarea();
                    self.renderAlertsList();
                    self.closeEditPanel();

                    Notification.addNotification({
                        message: self.strings.alertCreated,
                        type: 'success'
                    });
                } else {
                    Notification.addNotification({
                        message: response.message || self.strings.failedCreateAlert,
                        type: 'error'
                    });
                }
            }).fail(function(xhr) {
                var message = self.strings.failedCreateAlert;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    // Show validation errors if present.
                    if (xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        var errorDetails = [];
                        for (var field in errors) {
                            if (errors.hasOwnProperty(field)) {
                                errorDetails.push(field + ': ' + errors[field].join(', '));
                            }
                        }
                        if (errorDetails.length > 0) {
                            message += ' - ' + errorDetails.join('; ');
                        }
                    }
                }
                console.error('Alert creation failed:', xhr.responseJSON);
                Notification.addNotification({
                    message: message,
                    type: 'error'
                });
            });
        },

        /**
         * Update an existing alert on the backend.
         *
         * @param {string} reportSlug Report slug.
         * @param {number} alertId Backend alert ID.
         * @param {Object} payload Backend API payload.
         * @param {Object} alertData Local alert data.
         */
        updateAlertOnBackend: function(reportSlug, alertId, payload, alertData) {
            var self = this;
            var endpoint = this.getReportEndpoint(reportSlug);

            $.ajax({
                url: 'https://backend.adeptus360.com/api/v1' + endpoint +
                     encodeURIComponent(reportSlug) + '/alerts/' + alertId,
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + this.apiKey,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify(payload),
                timeout: 15000
            }).done(function(response) {
                if (response.success) {
                    alertData.backend_id = alertId;
                    alertData.current_status = self.alerts[self.editingIndex].current_status;
                    alertData.enabled = self.alerts[self.editingIndex].enabled;
                    self.alerts[self.editingIndex] = alertData;

                    self.saveToTextarea();
                    self.renderAlertsList();
                    self.closeEditPanel();

                    Notification.addNotification({
                        message: self.strings.alertUpdated,
                        type: 'success'
                    });
                } else {
                    Notification.addNotification({
                        message: response.message || self.strings.failedUpdateAlert,
                        type: 'error'
                    });
                }
            }).fail(function(xhr) {
                var message = self.strings.failedUpdateAlert;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                Notification.addNotification({
                    message: message,
                    type: 'error'
                });
            });
        },

        /**
         * Delete an alert - syncs to backend API.
         *
         * @param {number} index Alert index
         */
        deleteAlert: function(index) {
            var self = this;
            var alert = this.alerts[index];

            if (!alert) {
                return;
            }

            // If alert has backend ID, delete from backend first.
            if (alert.backend_id && alert.report_slug) {
                var endpoint = this.getReportEndpoint(alert.report_slug);
                $.ajax({
                    url: 'https://backend.adeptus360.com/api/v1' + endpoint +
                         encodeURIComponent(alert.report_slug) + '/alerts/' + alert.backend_id,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + this.apiKey,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    // Remove from local list.
                    self.alerts.splice(index, 1);
                    self.saveToTextarea();
                    self.renderAlertsList();

                    Notification.addNotification({
                        message: self.strings.alertDeleted,
                        type: 'success'
                    });
                }).fail(function(xhr) {
                    var message = self.strings.failedDeleteAlert;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    Notification.addNotification({
                        message: message,
                        type: 'error'
                    });
                });
            } else {
                // No backend ID, just remove locally.
                this.alerts.splice(index, 1);
                this.saveToTextarea();
                this.renderAlertsList();
            }
        },

        /**
         * Clean up orphaned backend alerts that no longer exist in local config.
         * This runs on initialization to sync backend with local Moodle config.
         */
        cleanupOrphanedAlerts: function() {
            var self = this;

            // Get unique report slugs from local alerts.
            var reportSlugs = {};
            var localBackendIds = {};

            this.alerts.forEach(function(alert) {
                if (alert.report_slug) {
                    reportSlugs[alert.report_slug] = true;
                    if (alert.backend_id) {
                        if (!localBackendIds[alert.report_slug]) {
                            localBackendIds[alert.report_slug] = [];
                        }
                        localBackendIds[alert.report_slug].push(alert.backend_id);
                    }
                }
            });

            // For each report slug, fetch backend alerts and delete orphans.
            Object.keys(reportSlugs).forEach(function(reportSlug) {
                var endpoint = self.getReportEndpoint(reportSlug);
                var localIds = localBackendIds[reportSlug] || [];

                $.ajax({
                    url: 'https://backend.adeptus360.com/api/v1' + endpoint +
                         encodeURIComponent(reportSlug) + '/alerts',
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + self.apiKey,
                        'Accept': 'application/json'
                    },
                    timeout: 10000
                }).done(function(response) {
                    if (response.success && response.alerts) {
                        var backendAlerts = response.alerts;

                        // Find orphans (backend alerts not in local config).
                        backendAlerts.forEach(function(backendAlert) {
                            if (localIds.indexOf(backendAlert.id) === -1) {
                                // This backend alert is orphaned - delete it.
                                self.deleteOrphanedAlert(reportSlug, backendAlert.id);
                            }
                        });
                    }
                }).fail(function() {
                    // Silently fail - orphan cleanup is best-effort.
                    console.warn('Failed to fetch backend alerts for orphan cleanup:', reportSlug);
                });
            });
        },

        /**
         * Delete an orphaned alert from the backend (no local state changes).
         *
         * @param {string} reportSlug Report slug.
         * @param {number} alertId Backend alert ID to delete.
         */
        deleteOrphanedAlert: function(reportSlug, alertId) {
            var endpoint = this.getReportEndpoint(reportSlug);

            $.ajax({
                url: 'https://backend.adeptus360.com/api/v1' + endpoint +
                     encodeURIComponent(reportSlug) + '/alerts/' + alertId,
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + this.apiKey,
                    'Accept': 'application/json'
                },
                timeout: 10000
            }).done(function() {
                console.log('Cleaned up orphaned backend alert:', alertId, 'for report:', reportSlug);
            }).fail(function() {
                console.warn('Failed to delete orphaned alert:', alertId, 'for report:', reportSlug);
            });
        },

        /**
         * Save alerts to the hidden textarea.
         */
        saveToTextarea: function() {
            this.textarea.val(JSON.stringify(this.alerts));
        },

        /**
         * Open the report dropdown.
         */
        openReportDropdown: function() {
            if (this.isDropdownOpen) {
                return;
            }

            this.isDropdownOpen = true;
            var dropdown = $('#alert-report-dropdown');
            var input = $('#alert-edit-report-search');

            // Position dropdown.
            dropdown.css('width', input.outerWidth() + 'px');
            dropdown.show();

            this.filteredReports = this.reports;
            this.renderReportDropdown('');
        },

        /**
         * Close the report dropdown.
         */
        closeReportDropdown: function() {
            this.isDropdownOpen = false;
            $('#alert-report-dropdown').hide();
        },

        /**
         * Handle report search input.
         *
         * @param {string} query Search query
         */
        handleReportSearch: function(query) {
            var self = this;

            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            this.searchTimeout = setTimeout(function() {
                self.filteredReports = self.filterReports(query);
                self.renderReportDropdown(query);
            }, 150);
        },

        /**
         * Filter reports based on search query.
         *
         * @param {string} query Search query
         * @return {Array} Filtered reports
         */
        filterReports: function(query) {
            if (!query || query.length < 1) {
                return this.reports;
            }

            query = query.toLowerCase().trim();
            var terms = query.split(/\s+/);

            return this.reports.filter(function(report) {
                var searchText = (
                    report.displayName + ' ' +
                    report.categoryName + ' ' +
                    report.source + ' ' +
                    (report.slug || '')
                ).toLowerCase();

                return terms.every(function(term) {
                    return searchText.indexOf(term) !== -1;
                });
            }).slice(0, 30);
        },

        /**
         * Render the report dropdown.
         *
         * @param {string} query Current search query
         */
        renderReportDropdown: function(query) {
            var self = this;
            var dropdown = $('#alert-report-dropdown');
            dropdown.empty();

            if (this.filteredReports.length === 0) {
                dropdown.append('<div class="p-3 text-muted text-center">No matching reports found</div>');
                return;
            }

            // Group by category.
            var byCategory = {};
            this.filteredReports.forEach(function(report) {
                var cat = report.categoryName || 'Other';
                if (!byCategory[cat]) {
                    byCategory[cat] = [];
                }
                byCategory[cat].push(report);
            });

            Object.keys(byCategory).sort().forEach(function(category) {
                dropdown.append(
                    '<div class="px-3 py-1 bg-light border-bottom">' +
                    '<small class="text-muted font-weight-bold">' + self.escapeHtml(category) + '</small>' +
                    '</div>'
                );

                byCategory[category].forEach(function(report) {
                    var sourceBadge = report.source === 'ai'
                        ? '<span class="badge badge-info ml-2">AI</span>'
                        : '<span class="badge badge-primary ml-2">Wizard</span>';

                    dropdown.append(
                        '<div class="report-dropdown-item px-3 py-2" style="cursor:pointer;" ' +
                        'data-slug="' + report.slug + '" data-name="' + self.escapeHtml(report.displayName) + '">' +
                        '<span>' + self.escapeHtml(report.displayName) + '</span>' +
                        sourceBadge +
                        '</div>'
                    );
                });
            });

            // Add hover effects.
            dropdown.find('.report-dropdown-item').hover(
                function() { $(this).addClass('bg-light'); },
                function() { $(this).removeClass('bg-light'); }
            );
        },

        /**
         * Select a report from the dropdown.
         *
         * @param {string} slug Report slug
         * @param {string} name Report display name
         */
        selectReport: function(slug, name) {
            $('#alert-edit-report').val(slug);
            $('#alert-edit-report-search').val(name);
            $('#alert-edit-report-display').text(this.strings.selected.replace('{$a}', name));
            this.closeReportDropdown();
        },

        /**
         * Open the user search dropdown.
         */
        openUserDropdown: function() {
            if (this.isUserDropdownOpen) {
                return;
            }

            this.isUserDropdownOpen = true;
            var dropdown = $('#alert-user-dropdown');
            dropdown.show();

            // Load users if not already loaded.
            this.loadUsers();
        },

        /**
         * Close the user search dropdown.
         */
        closeUserDropdown: function() {
            this.isUserDropdownOpen = false;
            $('#alert-user-dropdown').hide();
        },

        /**
         * Load users from the server.
         */
        loadUsers: function() {
            var self = this;
            var roleId = $('#alert-edit-role-filter').val() || 0;
            var search = $('#alert-edit-user-search').val() || '';

            var dropdown = $('#alert-user-dropdown');
            dropdown.html('<div class="p-3 text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');

            require(['core/ajax'], function(Ajax) {
                Ajax.call([{
                    methodname: 'block_adeptus_insights_get_users_by_role',
                    args: {
                        roleid: parseInt(roleId, 10),
                        search: search,
                        limit: 50
                    }
                }])[0].done(function(response) {
                    self.renderUserDropdown(response.users);
                }).fail(function() {
                    dropdown.html('<div class="p-3 text-center text-danger">Failed to load users</div>');
                });
            });
        },

        /**
         * Handle user search input with debouncing.
         *
         * @param {string} query Search query
         */
        handleUserSearch: function(query) {
            var self = this;

            if (this.userSearchTimeout) {
                clearTimeout(this.userSearchTimeout);
            }

            this.userSearchTimeout = setTimeout(function() {
                self.loadUsers();
            }, 300);
        },

        /**
         * Render the user dropdown.
         *
         * @param {Array} users Array of user objects
         */
        renderUserDropdown: function(users) {
            var self = this;
            var dropdown = $('#alert-user-dropdown');
            dropdown.empty();

            if (users.length === 0) {
                dropdown.html('<div class="p-3 text-center text-muted">No users found</div>');
                return;
            }

            users.forEach(function(user) {
                // Check if already selected.
                var isSelected = self.selectedUsers.some(function(s) {
                    return s.id === user.id;
                });

                if (isSelected) {
                    return; // Skip already selected users.
                }

                var html = '<div class="user-dropdown-item px-3 py-2 d-flex align-items-center" ' +
                    'style="cursor:pointer;" ' +
                    'data-userid="' + user.id + '" ' +
                    'data-name="' + self.escapeHtml(user.fullname) + '" ' +
                    'data-email="' + self.escapeHtml(user.email) + '">' +
                    '<img src="' + user.profileimageurl + '" class="rounded-circle mr-2" ' +
                    'width="32" height="32" alt="">' +
                    '<div class="flex-grow-1 min-width-0">' +
                    '<div class="text-truncate font-weight-bold">' + self.escapeHtml(user.fullname) + '</div>' +
                    '<small class="text-muted text-truncate d-block">' + self.escapeHtml(user.email) + '</small>' +
                    '</div>' +
                    '</div>';

                dropdown.append(html);
            });

            if (dropdown.children().length === 0) {
                dropdown.html('<div class="p-3 text-center text-muted">All matching users already selected</div>');
            }

            // Add hover effects.
            dropdown.find('.user-dropdown-item').hover(
                function() { $(this).addClass('bg-light'); },
                function() { $(this).removeClass('bg-light'); }
            );
        },

        /**
         * Add a user to the selected list.
         *
         * @param {number} userId User ID
         * @param {string} userName User full name
         * @param {string} userEmail User email
         */
        addSelectedUser: function(userId, userName, userEmail) {
            // Check if already selected.
            var exists = this.selectedUsers.some(function(u) {
                return u.id === userId;
            });

            if (exists) {
                return;
            }

            this.selectedUsers.push({
                id: userId,
                name: userName,
                email: userEmail
            });

            this.renderSelectedUsers();
            this.closeUserDropdown();
            $('#alert-edit-user-search').val('');
        },

        /**
         * Remove a user from the selected list.
         *
         * @param {number} userId User ID
         */
        removeSelectedUser: function(userId) {
            this.selectedUsers = this.selectedUsers.filter(function(u) {
                return u.id !== userId;
            });

            this.renderSelectedUsers();
        },

        /**
         * Render the selected users list.
         */
        renderSelectedUsers: function() {
            var self = this;
            var container = $('#alert-selected-users');
            container.empty();

            if (this.selectedUsers.length === 0) {
                container.html('<div class="text-muted small py-2"><i class="fa fa-user-o mr-1"></i> No recipients selected</div>');
                return;
            }

            this.selectedUsers.forEach(function(user) {
                var html = '<div class="selected-user-chip d-inline-flex align-items-center bg-light border rounded px-2 py-1 mr-2 mb-2">' +
                    '<span class="mr-2">' + self.escapeHtml(user.name) + '</span>' +
                    '<button type="button" class="btn btn-sm btn-link p-0 text-danger remove-selected-user" ' +
                    'data-userid="' + user.id + '" title="Remove">' +
                    '<i class="fa fa-times"></i>' +
                    '</button>' +
                    '</div>';

                container.append(html);
            });

            // Update hidden input with user data.
            $('#alert-edit-notify-users-data').val(JSON.stringify(this.selectedUsers));
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

    // Track if module has been initialized to prevent duplicates.
    var moduleInitialized = false;

    return {
        /**
         * Initialize report selectors for the edit form.
         * Uses MutationObserver to detect when modal content is loaded.
         *
         * @param {Object} options Configuration options
         */
        init: function(options) {
            // Prevent duplicate initialization (can happen when both block and edit_form load the module).
            if (moduleInitialized) {
                return;
            }
            moduleInitialized = true;

            var initialized = {
                kpi: false,
                tabs: false,
                alerts: false
            };

            /**
             * Try to initialize selectors if their containers exist.
             */
            var tryInitialize = function() {
                // Initialize KPI report selector.
                if (!initialized.kpi && $('#block-adeptus-kpi-report-selector-container').length) {
                    new ReportSelector({
                        mode: 'kpi',
                        apiKey: options.apiKey,
                        containerId: 'block-adeptus-kpi-report-selector-container',
                        textareaName: 'config_kpi_selected_reports'
                    });
                    initialized.kpi = true;
                }

                // Initialize Tabs report selector.
                if (!initialized.tabs && $('#block-adeptus-tabs-report-selector-container').length) {
                    new ReportSelector({
                        mode: 'tabs',
                        apiKey: options.apiKey,
                        containerId: 'block-adeptus-tabs-report-selector-container',
                        textareaName: 'config_tabs_selected_reports'
                    });
                    initialized.tabs = true;
                }

                // Initialize Alerts manager.
                if (!initialized.alerts && $('#alerts-manager-container').length) {
                    new AlertsManager({
                        apiKey: options.apiKey
                    });
                    initialized.alerts = true;
                }

                // Handle Alert Configuration notice visibility based on display mode.
                // Shows a notice when non-KPI mode is selected, hides it for KPI mode.
                var displayModeSelect = $('[name="config_display_mode"]');
                var alertsNotice = $('#block-adeptus-alerts-kpi-only-notice');
                if (displayModeSelect.length && alertsNotice.length && !displayModeSelect.data('alerts-notice-bound')) {

                    var updateAlertNoticeVisibility = function() {
                        var isKpi = displayModeSelect.val() === 'kpi';
                        if (isKpi) {
                            alertsNotice.hide();
                        } else {
                            alertsNotice.show();
                        }
                    };

                    // Bind change handler.
                    displayModeSelect.on('change', updateAlertNoticeVisibility);

                    // Set initial visibility.
                    setTimeout(updateAlertNoticeVisibility, 50);

                    // Mark as bound to prevent duplicate handlers.
                    displayModeSelect.data('alerts-notice-bound', true);
                }
            };

            // Try immediately in case elements exist.
            tryInitialize();

            // Watch for modal content to be added to DOM.
            var observer = new MutationObserver(function() {
                tryInitialize();

                // Stop observing once all are initialized.
                if (initialized.kpi && initialized.tabs && initialized.alerts) {
                    observer.disconnect();
                }
            });

            // Observe the entire document for added nodes.
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            // Also listen for Moodle modal events.
            $(document).on('modal-shown', function() {
                setTimeout(tryInitialize, 100);
            });
        }
    };
});
