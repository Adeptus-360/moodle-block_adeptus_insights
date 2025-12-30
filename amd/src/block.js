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
 * Main JavaScript controller for the Adeptus Insights block.
 *
 * @module     block_adeptus_insights/block
 * @copyright  2025 Adeptus Analytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/templates',
    'core/chartjs'
], function($, Ajax, Notification, Str, ModalFactory, ModalEvents, Templates, Chart) {
    'use strict';

    /**
     * Block controller class.
     *
     * @param {Object} options Configuration options
     */
    var BlockController = function(options) {
        this.blockId = options.blockid;
        this.contextId = options.contextid;
        this.config = options.config || {};
        this.apiKey = options.apiKey || '';  // API key passed directly from PHP
        this.isAdmin = options.isAdmin || false;  // Site admin flag
        this.container = null;
        this.reports = [];
        this.lastUpdated = null;
        this.refreshTimer = null;
        this.modal = null;
        this.modalData = null;      // Current modal report data
        this.modalReport = null;    // Current modal report metadata
        this.chartInstance = null;  // Current Chart.js instance
        this.currentView = 'table'; // Current view mode

        // Pagination state for report list
        this.listCurrentPage = 1;
        this.listItemsPerPage = this.config.maxLinkItems || 10;
        this.listTotalPages = 1;

        // Pagination state for modal table
        this.tableCurrentPage = 1;
        this.tableRowsPerPage = 25;
        this.tableTotalPages = 1;

        // Category filter state
        this.selectedCategory = '';
        this.availableCategories = [];

        // Report selector state (for embedded mode)
        this.selectedReportSlug = this.config.defaultReport || '';
        this.selectedReportSource = '';

        // Embedded mode state (mirrors modal functionality)
        this.embeddedCurrentView = 'table';
        this.embeddedTablePage = 1;
        this.embeddedTableRowsPerPage = 25;
        this.embeddedChartType = 'bar';
        this.embeddedXAxis = '';
        this.embeddedYAxis = '';

        // Cache settings
        this.cacheKey = 'adeptus_block_reports_' + this.blockId;
        this.cacheTTL = 5 * 60 * 1000; // 5 minutes
        this.reportDataCache = {}; // In-memory cache for executed report data
        this.preloadTimeout = null;
        this.preloadingSlug = null;

        this.init();
    };

    BlockController.prototype = {
        /**
         * Initialize the block.
         */
        init: function() {
            // Find the block container.
            this.container = $('[data-blockid="' + this.blockId + '"]');
            if (!this.container.length) {
                return;
            }

            // Bind event handlers.
            this.bindEvents();

            // Load initial data.
            this.loadReports();

            // Setup auto-refresh if configured.
            this.setupAutoRefresh();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Refresh button.
            this.container.on('click', '.block-adeptus-refresh, .block-adeptus-retry', function(e) {
                e.preventDefault();
                self.refresh();
            });

            // Report item click.
            this.container.on('click', '.block-adeptus-report-item', function(e) {
                e.preventDefault();
                var slug = $(this).data('slug');
                var source = $(this).data('source');
                self.handleReportClick(slug, source);
            });

            // Report item keyboard activation (Enter/Space).
            this.container.on('keydown', '.block-adeptus-report-item', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var slug = $(this).data('slug');
                    var source = $(this).data('source');
                    self.handleReportClick(slug, source);
                }
            });

            // KPI card click.
            this.container.on('click', '.block-adeptus-kpi-card', function(e) {
                e.preventDefault();
                var slug = $(this).data('slug');
                var source = $(this).data('source');
                self.handleReportClick(slug, source);
            });

            // Tab click.
            this.container.on('shown.bs.tab', '.block-adeptus-tab-nav a', function(e) {
                var tabPane = $($(e.target).attr('href'));
                var slug = tabPane.data('slug');
                var source = tabPane.data('source');
                if (!tabPane.data('loaded')) {
                    self.loadTabContent(tabPane, slug, source);
                }
            });

            // Export buttons.
            this.container.on('click', '.block-adeptus-export', function(e) {
                e.preventDefault();
                var format = $(this).data('format');
                self.exportReport(format);
            });

            // Pagination - Previous button.
            this.container.on('click', '.pagination-prev', function(e) {
                e.preventDefault();
                if (self.listCurrentPage > 1) {
                    self.listCurrentPage--;
                    self.renderCurrentPage();
                    self.scrollToListTop();
                }
            });

            // Pagination - Next button.
            this.container.on('click', '.pagination-next', function(e) {
                e.preventDefault();
                if (self.listCurrentPage < self.listTotalPages) {
                    self.listCurrentPage++;
                    self.renderCurrentPage();
                    self.scrollToListTop();
                }
            });

            // Category filter change.
            this.container.on('change', '.category-filter-select', function() {
                self.selectedCategory = $(this).val();
                self.listCurrentPage = 1; // Reset to first page
                // For embedded mode, update the report selector based on new category
                if (self.config.displayMode === 'embedded') {
                    self.populateReportSelector();
                } else {
                    self.renderReports();
                }
            });

            // Report selector change (for embedded mode) - hidden select fallback.
            this.container.on('change', '.report-selector-select', function() {
                var selectedValue = $(this).val();
                if (selectedValue) {
                    var parts = selectedValue.split('::');
                    var slug = parts[0];
                    var source = parts[1] || 'wizard';
                    self.selectedReportSlug = slug;
                    self.selectedReportSource = source;
                    // Reset embedded state
                    self.embeddedTablePage = 1;
                    self.embeddedCurrentView = 'table';
                    self.loadEmbeddedReport(slug, source);
                }
            });

            // Searchable dropdown - Toggle open/close.
            this.container.on('click', '.searchable-dropdown-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var dropdown = $(this).closest('.searchable-dropdown');
                self.toggleSearchableDropdown(dropdown);
            });

            // Searchable dropdown - Search input.
            this.container.on('input', '.searchable-dropdown-input', function() {
                var dropdown = $(this).closest('.searchable-dropdown');
                var query = $(this).val().toLowerCase().trim();
                self.filterSearchableDropdown(dropdown, query);
            });

            // Searchable dropdown - Item selection.
            this.container.on('click', '.searchable-dropdown-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var dropdown = $(this).closest('.searchable-dropdown');
                var value = $(this).data('value');
                var text = $(this).find('.dropdown-item-name').text();
                self.selectSearchableDropdownItem(dropdown, value, text);
            });

            // Searchable dropdown - Keyboard navigation.
            this.container.on('keydown', '.searchable-dropdown-input', function(e) {
                var dropdown = $(this).closest('.searchable-dropdown');
                var items = dropdown.find('.searchable-dropdown-item:visible');
                var focused = dropdown.find('.searchable-dropdown-item.focused');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (focused.length === 0) {
                        items.first().addClass('focused');
                    } else {
                        var next = focused.nextAll('.searchable-dropdown-item:visible').first();
                        if (next.length) {
                            focused.removeClass('focused');
                            next.addClass('focused');
                            self.scrollDropdownItemIntoView(next);
                        }
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (focused.length) {
                        var prev = focused.prevAll('.searchable-dropdown-item:visible').first();
                        if (prev.length) {
                            focused.removeClass('focused');
                            prev.addClass('focused');
                            self.scrollDropdownItemIntoView(prev);
                        }
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (focused.length) {
                        focused.trigger('click');
                    } else if (items.length === 1) {
                        items.first().trigger('click');
                    }
                } else if (e.key === 'Escape') {
                    self.closeSearchableDropdown(dropdown);
                }
            });

            // Close dropdown when clicking outside.
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.searchable-dropdown').length) {
                    self.container.find('.searchable-dropdown.open').each(function() {
                        self.closeSearchableDropdown($(this));
                    });
                }
            });

            // Embedded mode - View toggle (Table/Chart).
            this.container.on('click', '.embedded-view-toggle-btn', function(e) {
                e.preventDefault();
                var view = $(this).data('view');
                self.switchEmbeddedView(view);
            });

            // Embedded mode - Table pagination.
            this.container.on('click', '.embedded-pagination-prev', function(e) {
                e.preventDefault();
                if (self.embeddedTablePage > 1) {
                    self.embeddedTablePage--;
                    self.renderEmbeddedTablePage();
                    self.scrollToEmbeddedTableTop();
                }
            });

            this.container.on('click', '.embedded-pagination-next', function(e) {
                e.preventDefault();
                var totalPages = Math.ceil((self.embeddedData || []).length / self.embeddedTableRowsPerPage);
                if (self.embeddedTablePage < totalPages) {
                    self.embeddedTablePage++;
                    self.renderEmbeddedTablePage();
                    self.scrollToEmbeddedTableTop();
                }
            });

            // Embedded mode - Chart type change.
            this.container.on('change', '.embedded-chart-type', function() {
                self.embeddedChartType = $(this).val();
                self.renderEmbeddedChart();
            });

            // Embedded mode - X-Axis change.
            this.container.on('change', '.embedded-chart-x-axis', function() {
                self.embeddedXAxis = $(this).val();
                self.renderEmbeddedChart();
            });

            // Embedded mode - Y-Axis change.
            this.container.on('change', '.embedded-chart-y-axis', function() {
                self.embeddedYAxis = $(this).val();
                self.renderEmbeddedChart();
            });

            // Embedded mode - Export.
            this.container.on('click', '.embedded-export', function(e) {
                e.preventDefault();
                var format = $(this).data('format');
                self.exportEmbeddedReport(format);
            });

            // Preload report data on hover (300ms delay to avoid unnecessary loads).
            this.container.on('mouseenter', '.block-adeptus-report-item', function() {
                var slug = $(this).data('slug');
                var source = $(this).data('source');

                // Clear any pending preload
                if (self.preloadTimeout) {
                    clearTimeout(self.preloadTimeout);
                }

                // Start preload after 300ms hover
                self.preloadTimeout = setTimeout(function() {
                    self.preloadReportData(slug, source);
                }, 300);
            });

            this.container.on('mouseleave', '.block-adeptus-report-item', function() {
                // Cancel pending preload if user moves away
                if (self.preloadTimeout) {
                    clearTimeout(self.preloadTimeout);
                    self.preloadTimeout = null;
                }
            });
        },

        /**
         * Load reports from the server or cache.
         */
        loadReports: function() {
            var self = this;

            this.showLoading();

            // Try to load from cache first
            var cachedData = this.getFromCache();
            if (cachedData) {
                this.reports = cachedData;
                this.lastUpdated = new Date();
                this.renderReports();
                return;
            }

            // Wait for auth data to be available.
            this.waitForAuth(function() {
                self.fetchReports();
            });
        },

        /**
         * Get reports from sessionStorage cache.
         *
         * @return {Array|null}
         */
        getFromCache: function() {
            try {
                var cached = sessionStorage.getItem(this.cacheKey);
                if (cached) {
                    var data = JSON.parse(cached);
                    if (data.timestamp && (Date.now() - data.timestamp) < this.cacheTTL) {
                        return data.reports;
                    }
                    // Cache expired, remove it
                    sessionStorage.removeItem(this.cacheKey);
                }
            } catch (e) {
                // Ignore storage errors
            }
            return null;
        },

        /**
         * Save reports to sessionStorage cache.
         *
         * @param {Array} reports
         */
        saveToCache: function(reports) {
            try {
                sessionStorage.setItem(this.cacheKey, JSON.stringify({
                    timestamp: Date.now(),
                    reports: reports
                }));
            } catch (e) {
                // Ignore storage errors (quota exceeded, etc.)
            }
        },

        /**
         * Preload report data on hover for faster modal loading.
         *
         * @param {string} slug
         * @param {string} source
         */
        preloadReportData: function(slug, source) {
            var self = this;
            var cacheKey = slug + '_' + source;

            // Already cached or currently preloading
            if (this.reportDataCache[cacheKey] || this.preloadingSlug === slug) {
                return;
            }

            this.preloadingSlug = slug;

            // Find the report from our cached list
            var report = this.reports.find(function(r) {
                return r.slug === slug;
            });

            if (!report) {
                return;
            }

            if (source === 'wizard') {
                // Preload wizard report
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/generate_report.php',
                    method: 'POST',
                    data: {
                        reportid: report.name || report.report_template_id,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    timeout: 30000
                }).done(function(response) {
                    if (response.success) {
                        self.reportDataCache[cacheKey] = {
                            report: report,
                            results: response.results || [],
                            chartData: response.chart_data,
                            chartType: response.chart_type
                        };
                    }
                    self.preloadingSlug = null;
                }).fail(function() {
                    self.preloadingSlug = null;
                });
            } else {
                // Preload AI report
                var token = this.apiKey || (window.adeptusAuthData ? window.adeptusAuthData.api_key : null);
                if (!token) {
                    return;
                }

                $.ajax({
                    url: 'https://a360backend.stagingwithswift.com/api/v1/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    if (response.success && response.report) {
                        self.reportDataCache[cacheKey] = {
                            report: response.report,
                            results: response.data || [],
                            chartData: null,
                            chartType: null
                        };
                    }
                    self.preloadingSlug = null;
                }).fail(function() {
                    self.preloadingSlug = null;
                });
            }
        },

        /**
         * Wait for authentication data to be available.
         * Uses API key passed directly from PHP, or falls back to AJAX.
         *
         * @param {Function} callback
         */
        waitForAuth: function(callback) {
            var self = this;

            // Use API key passed directly from PHP (preferred - no AJAX needed)
            if (this.apiKey) {
                window.adeptusAuthData = window.adeptusAuthData || {};
                window.adeptusAuthData.api_key = this.apiKey;
                callback();
                return;
            }

            // Fallback: Check if already authenticated via parent plugin
            if (window.adeptusAuthData && window.adeptusAuthData.api_key) {
                callback();
                return;
            }

            // Last resort: Try to fetch auth via AJAX
            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/get_auth_status.php',
                method: 'GET',
                dataType: 'json',
                timeout: 10000
            }).done(function(response) {
                if (response && response.success && response.data) {
                    window.adeptusAuthData = response.data;
                    callback();
                } else {
                    self.showError('Authentication failed');
                }
            }).fail(function() {
                self.showError('Could not authenticate');
            });
        },

        /**
         * Fetch reports from the backend API.
         */
        fetchReports: function() {
            var self = this;
            var token = this.apiKey || (window.adeptusAuthData ? window.adeptusAuthData.api_key : null);

            if (!token) {
                this.showError('No authentication token');
                return;
            }

            var promises = [];
            var source = this.config.reportSource || 'all';

            if (source === 'all' || source === 'wizard') {
                promises.push(this.fetchFromApi('/wizard-reports', token));
            }

            if (source === 'all' || source === 'ai') {
                promises.push(this.fetchFromApi('/ai-reports', token));
            }

            $.when.apply($, promises).then(function() {
                var allReports = [];

                for (var i = 0; i < promises.length; i++) {
                    var result;
                    if (promises.length === 1) {
                        result = arguments[0];
                    } else {
                        result = arguments[i][0];
                    }

                    var reports = result.reports || result.data || [];
                    if (reports && reports.length) {
                        var reportSource = (source === 'ai' || (i === 1 && source === 'all')) ? 'ai' : 'wizard';
                        reports.forEach(function(report) {
                            report.source = report.source || reportSource;
                            allReports.push(report);
                        });
                    }
                }

                self.reports = allReports;
                self.lastUpdated = new Date();
                self.saveToCache(allReports);
                self.renderReports();
            }).fail(function() {
                self.showError();
            });
        },

        /**
         * Fetch data from the backend API.
         *
         * @param {string} endpoint API endpoint
         * @param {string} token Auth token
         * @return {Promise}
         */
        fetchFromApi: function(endpoint, token) {
            var baseUrl = 'https://a360backend.stagingwithswift.com/api/v1';

            return $.ajax({
                url: baseUrl + endpoint,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                timeout: 15000
            });
        },

        /**
         * Render reports based on display mode.
         */
        renderReports: function() {
            var mode = this.config.displayMode || 'links';

            if (this.reports.length === 0) {
                this.showEmpty();
                return;
            }

            // Extract and populate category filter
            this.populateCategoryFilter();

            // Filter reports based on selected category
            var reports = this.filterReports();

            switch (mode) {
                case 'embedded':
                    this.renderEmbedded(reports);
                    break;
                case 'kpi':
                    this.renderKpi(reports);
                    break;
                case 'tabs':
                    this.renderTabs(reports);
                    break;
                case 'links':
                default:
                    this.renderLinks(reports);
                    break;
            }

            this.hideLoading();
            this.updateTimestamp();
        },

        /**
         * Extract categories from reports and populate the filter dropdown.
         */
        populateCategoryFilter: function() {
            var self = this;
            var categoryMap = {};

            // Extract unique categories from reports
            this.reports.forEach(function(report) {
                var catInfo = report.category_info;
                if (catInfo && catInfo.slug) {
                    categoryMap[catInfo.slug] = {
                        slug: catInfo.slug,
                        name: catInfo.name || catInfo.slug,
                        color: catInfo.color || '#6c757d'
                    };
                } else if (report.category) {
                    categoryMap[report.category] = {
                        slug: report.category,
                        name: report.category,
                        color: '#6c757d'
                    };
                }
            });

            // Convert to array and sort by name
            this.availableCategories = Object.values(categoryMap).sort(function(a, b) {
                return a.name.localeCompare(b.name);
            });

            // Populate hidden select
            var select = this.container.find('.category-filter-select');
            var dropdown = this.container.find('.category-dropdown');
            var dropdownList = dropdown.find('.searchable-dropdown-list');

            if (select.length) {
                // Keep the first "All Categories" option, remove the rest
                select.find('option:not(:first)').remove();

                // Add category options to hidden select
                this.availableCategories.forEach(function(cat) {
                    var selected = self.selectedCategory === cat.slug ? ' selected' : '';
                    select.append('<option value="' + cat.slug + '"' + selected + '>' + cat.name + '</option>');
                });
            }

            // Populate searchable dropdown list
            if (dropdownList.length) {
                dropdownList.empty();

                // Add "All Categories" option first
                var allSelected = !this.selectedCategory ? 'selected' : '';
                var allItemHtml = '<li class="searchable-dropdown-item ' + allSelected + '" data-value="" ' +
                    'data-search="all categories" role="option">' +
                    '<span class="dropdown-item-name">All Categories</span>' +
                    '</li>';
                dropdownList.append(allItemHtml);

                // Add category options
                this.availableCategories.forEach(function(cat) {
                    var isSelected = self.selectedCategory === cat.slug ? 'selected' : '';
                    var itemHtml = '<li class="searchable-dropdown-item ' + isSelected + '" data-value="' + cat.slug + '" ' +
                        'data-search="' + cat.name.toLowerCase() + '" role="option">' +
                        '<span class="dropdown-item-name">' + self.escapeHtml(cat.name) + '</span>' +
                        '<span class="dropdown-item-category" style="background-color: ' + cat.color + '">' +
                        '<i class="fa fa-circle" style="font-size: 0.5rem;"></i></span>' +
                        '</li>';
                    dropdownList.append(itemHtml);
                });

                // Update dropdown text to show selected category
                var selectedText = 'All Categories';
                if (this.selectedCategory) {
                    var selectedCat = this.availableCategories.find(function(c) {
                        return c.slug === self.selectedCategory;
                    });
                    if (selectedCat) {
                        selectedText = selectedCat.name;
                    }
                }
                dropdown.find('.searchable-dropdown-text').text(selectedText);
            }
        },

        /**
         * Populate the report selector dropdown (for embedded mode).
         * Filters reports based on selected category.
         */
        populateReportSelector: function() {
            var self = this;
            var select = this.container.find('.report-selector-select');
            var dropdown = this.container.find('.block-adeptus-report-selector .searchable-dropdown');
            var dropdownList = dropdown.find('.searchable-dropdown-list');

            if (!select.length) {
                return;
            }

            // Get filtered reports based on category
            var reports = this.filterReports();

            // Clear existing options
            select.find('option:not(:first)').remove();
            dropdownList.empty();

            // Add report options to both hidden select and searchable dropdown
            reports.forEach(function(report) {
                var reportName = report.name || report.title || report.display_name || report.slug || 'Untitled';
                var value = report.slug + '::' + report.source;
                var categoryInfo = report.category_info || {name: 'General', color: '#6c757d'};
                var categoryLabel = ' [' + categoryInfo.name + ']';
                var selected = (self.selectedReportSlug === report.slug) ? ' selected' : '';

                // Hidden select option
                select.append('<option value="' + value + '"' + selected + '>' + reportName + categoryLabel + '</option>');

                // Searchable dropdown item
                var itemHtml = '<li class="searchable-dropdown-item" data-value="' + value + '" data-search="' +
                    reportName.toLowerCase() + ' ' + categoryInfo.name.toLowerCase() + '" role="option">' +
                    '<span class="dropdown-item-name">' + self.escapeHtml(reportName) + '</span>' +
                    '<span class="dropdown-item-category" style="background-color: ' + categoryInfo.color + '">' +
                    self.escapeHtml(categoryInfo.name) + '</span>' +
                    '</li>';
                dropdownList.append(itemHtml);
            });

            // If we have a default report configured and it's in the list, select it
            if (this.selectedReportSlug && !select.val()) {
                var defaultOption = select.find('option[value^="' + this.selectedReportSlug + '::"]');
                if (defaultOption.length) {
                    defaultOption.prop('selected', true);
                }
            }

            // If no report selected and we have reports, auto-select the first one
            if (!select.val() && reports.length > 0) {
                var firstReport = reports[0];
                var firstValue = firstReport.slug + '::' + firstReport.source;
                var firstName = firstReport.name || firstReport.title || firstReport.display_name || firstReport.slug || 'Untitled';
                select.val(firstValue);
                this.selectedReportSlug = firstReport.slug;
                this.selectedReportSource = firstReport.source;
                // Update searchable dropdown display
                dropdown.find('.searchable-dropdown-text').text(firstName);
                dropdown.find('.searchable-dropdown-item').removeClass('selected');
                dropdown.find('.searchable-dropdown-item[data-value="' + firstValue + '"]').addClass('selected');
                // Load the first report
                this.loadEmbeddedReport(firstReport.slug, firstReport.source);
            } else if (select.val()) {
                // A report is selected, load it and update display
                var parts = select.val().split('::');
                var selectedReport = reports.find(function(r) { return r.slug === parts[0]; });
                if (selectedReport) {
                    var selectedName = selectedReport.name || selectedReport.title || selectedReport.display_name || selectedReport.slug || 'Untitled';
                    dropdown.find('.searchable-dropdown-text').text(selectedName);
                    dropdown.find('.searchable-dropdown-item').removeClass('selected');
                    dropdown.find('.searchable-dropdown-item[data-value="' + select.val() + '"]').addClass('selected');
                }
                this.loadEmbeddedReport(parts[0], parts[1] || 'wizard');
            } else {
                // No reports available
                dropdown.find('.searchable-dropdown-text').text('-- No Reports --');
                this.showEmpty();
            }
        },

        /**
         * Filter reports based on configuration and selected category.
         *
         * @return {Array}
         */
        filterReports: function() {
            var self = this;
            var reports = this.reports.slice();
            var config = this.config;

            // Filter by selected category (from dropdown).
            if (this.selectedCategory) {
                reports = reports.filter(function(r) {
                    var catSlug = r.category_info ? r.category_info.slug : (r.category || '');
                    return catSlug === self.selectedCategory;
                });
            }

            // Filter to manually selected reports (from block config).
            if (config.reportSource === 'manual' && config.selectedReports && config.selectedReports.length) {
                var selected = config.selectedReports;
                reports = reports.filter(function(r) {
                    return selected.indexOf(r.slug) !== -1;
                });
            }

            // Note: We don't limit here anymore - pagination handles display limits

            // Sort by name (try multiple possible name fields).
            reports.sort(function(a, b) {
                var nameA = a.name || a.title || a.display_name || a.report_name || a.slug || '';
                var nameB = b.name || b.title || b.display_name || b.report_name || b.slug || '';
                return nameA.localeCompare(nameB);
            });

            return reports;
        },

        /**
         * Render link list mode with pagination.
         *
         * @param {Array} reports
         */
        renderLinks: function(reports) {
            // Store filtered reports for pagination
            this.filteredReports = reports;

            // Calculate pagination
            this.listItemsPerPage = this.config.maxLinkItems || 10;
            this.listTotalPages = Math.ceil(reports.length / this.listItemsPerPage);
            this.listCurrentPage = 1; // Reset to first page

            // Render the first page
            this.renderCurrentPage();
        },

        /**
         * Render the current page of reports.
         */
        renderCurrentPage: function() {
            var self = this;
            var reports = this.filteredReports || [];
            var listContainer = this.container.find('.block-adeptus-report-list');
            var template = $('#block-adeptus-report-item-template-' + this.blockId);
            var paginationContainer = this.container.find('.block-adeptus-pagination');

            // Calculate page slice
            var startIndex = (this.listCurrentPage - 1) * this.listItemsPerPage;
            var endIndex = startIndex + this.listItemsPerPage;
            var pageReports = reports.slice(startIndex, endIndex);

            listContainer.empty();

            pageReports.forEach(function(report) {
                var item = template.html();
                var $item = $(item);

                $item.attr('data-slug', report.slug);
                $item.attr('data-source', report.source);

                // Try multiple possible name fields (API may use different field names)
                var reportName = report.name || report.title || report.display_name || report.report_name || report.slug || 'Untitled Report';
                $item.find('.report-item-name').text(reportName);

                // Set category badge with color
                var categoryName = report.category_info ? report.category_info.name : report.category || 'General';
                var categoryColor = report.category_info ? report.category_info.color : '#6c757d';
                $item.find('.report-item-category')
                    .text(categoryName)
                    .css('background-color', categoryColor)
                    .css('color', '#fff');

                listContainer.append($item);
            });

            listContainer.removeClass('d-none');

            // Update pagination controls
            if (this.listTotalPages > 1) {
                // Use actual page data length for accurate count
                var actualEnd = startIndex + pageReports.length;
                paginationContainer.find('.pagination-showing')
                    .text('Showing ' + (startIndex + 1) + '-' + actualEnd + ' of ' + reports.length);
                paginationContainer.find('.pagination-pages')
                    .text('Page ' + this.listCurrentPage + ' of ' + this.listTotalPages);

                // Update button states
                paginationContainer.find('.pagination-prev').prop('disabled', this.listCurrentPage <= 1);
                paginationContainer.find('.pagination-next').prop('disabled', this.listCurrentPage >= this.listTotalPages);

                paginationContainer.removeClass('d-none');
            } else {
                paginationContainer.addClass('d-none');
            }
        },

        /**
         * Render embedded report mode.
         *
         * @param {Array} reports
         */
        renderEmbedded: function(reports) {
            // Initialize embedded report state
            this.embeddedReport = null;
            this.embeddedData = null;
            this.embeddedChartInstance = null;

            // Populate the report selector dropdown
            // This will also auto-load the first/default report
            this.populateReportSelector();

            // Hide loading state if no reports
            if (this.reports.length === 0) {
                this.showEmpty();
            }
        },

        /**
         * Load report data for embedded display.
         *
         * @param {string} slug
         * @param {string} source
         */
        loadEmbeddedReport: function(slug, source) {
            var self = this;
            var cacheKey = slug + '_' + source;

            // Check cache first for instant loading (no overlay needed)
            if (this.reportDataCache[cacheKey]) {
                var cached = this.reportDataCache[cacheKey];
                this.embeddedData = cached.results;
                this.renderEmbeddedContent(cached.report, cached.results);
                return;
            }

            // Show loading overlay for non-cached requests
            this.showEmbeddedLoadingOverlay();

            // Find the report from our cached list
            var report = this.reports.find(function(r) {
                return r.slug === slug;
            });

            if (!report) {
                this.hideEmbeddedLoadingOverlay();
                this.showError('Report not found');
                return;
            }

            // For wizard reports, execute locally via generate_report.php
            if (source === 'wizard') {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/generate_report.php',
                    method: 'POST',
                    data: {
                        reportid: report.name || report.report_template_id,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    timeout: 30000
                }).done(function(response) {
                    self.hideEmbeddedLoadingOverlay();
                    if (response.success) {
                        // Cache the result
                        self.reportDataCache[cacheKey] = {
                            report: report,
                            results: response.results || [],
                            chartData: response.chart_data,
                            chartType: response.chart_type
                        };
                        self.embeddedData = response.results || [];
                        self.renderEmbeddedContent(report, response.results || []);
                    } else {
                        self.showError(response.message || 'Failed to load report');
                    }
                }).fail(function() {
                    self.hideEmbeddedLoadingOverlay();
                    self.showError('Connection error');
                });
            } else {
                // AI reports - fetch from backend API
                var token = this.apiKey || (window.adeptusAuthData ? window.adeptusAuthData.api_key : null);

                if (!token) {
                    this.hideEmbeddedLoadingOverlay();
                    this.showError('No authentication token');
                    return;
                }

                $.ajax({
                    url: 'https://a360backend.stagingwithswift.com/api/v1/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    self.hideEmbeddedLoadingOverlay();
                    if (response.success && response.report) {
                        // Cache the result
                        self.reportDataCache[cacheKey] = {
                            report: response.report,
                            results: response.data || [],
                            chartData: null,
                            chartType: null
                        };
                        self.embeddedData = response.data || [];
                        self.renderEmbeddedContent(response.report, response.data || []);
                    } else {
                        self.showError('Failed to load report');
                    }
                }).fail(function() {
                    self.hideEmbeddedLoadingOverlay();
                    self.showError('Connection error');
                });
            }
        },

        /**
         * Render embedded content with report data.
         *
         * @param {Object} report
         * @param {Array} data
         */
        renderEmbeddedContent: function(report, data) {
            var contentArea = this.container.find('.block-adeptus-content');

            if (!data || data.length === 0) {
                this.showEmpty();
                return;
            }

            // Store data for later use
            this.embeddedReport = report;
            this.embeddedData = data;

            // Update meta bar
            var category = report.category_info || {name: 'General', color: '#6c757d'};
            this.container.find('.report-category')
                .text(category.name)
                .css('background-color', category.color)
                .css('color', '#fff');
            this.container.find('.row-count-num').text(data.length);
            this.container.find('.report-date').text('Updated: ' + new Date().toLocaleTimeString());

            // Populate chart axis selectors
            this.populateEmbeddedChartControls(data);

            // Reset to table view
            this.embeddedCurrentView = 'table';
            this.embeddedTablePage = 1;
            this.container.find('.embedded-view-toggle-btn').removeClass('active');
            this.container.find('.embedded-view-toggle-btn[data-view="table"]').addClass('active');
            this.container.find('.embedded-table-view').removeClass('d-none');
            this.container.find('.embedded-chart-view').addClass('d-none');

            // Render table with pagination
            this.renderEmbeddedTablePage();

            contentArea.removeClass('d-none');
            this.hideLoading();
            this.updateTimestamp();
        },

        /**
         * Switch between table and chart views in embedded mode.
         *
         * @param {string} view - 'table' or 'chart'
         */
        switchEmbeddedView: function(view) {
            this.embeddedCurrentView = view;

            // Update toggle buttons
            this.container.find('.embedded-view-toggle-btn').removeClass('active');
            this.container.find('.embedded-view-toggle-btn[data-view="' + view + '"]').addClass('active');

            // Show/hide views
            if (view === 'table') {
                this.container.find('.embedded-table-view').removeClass('d-none');
                this.container.find('.embedded-chart-view').addClass('d-none');
            } else {
                this.container.find('.embedded-table-view').addClass('d-none');
                this.container.find('.embedded-chart-view').removeClass('d-none');
                // Render chart when switching to chart view
                this.renderEmbeddedChart();
            }
        },

        /**
         * Populate chart control dropdowns for embedded mode.
         *
         * @param {Array} data
         */
        populateEmbeddedChartControls: function(data) {
            if (!data || data.length === 0) return;

            var headers = Object.keys(data[0]);
            var numericCols = this.detectNumericColumns(data, headers);

            var xAxisSelect = this.container.find('.embedded-chart-x-axis');
            var yAxisSelect = this.container.find('.embedded-chart-y-axis');

            xAxisSelect.empty();
            yAxisSelect.empty();

            // Populate X-axis (all columns)
            headers.forEach(function(h) {
                var formatted = h.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                xAxisSelect.append('<option value="' + h + '">' + formatted + '</option>');
            });

            // Populate Y-axis (prefer numeric columns)
            var yOptions = numericCols.length > 0 ? numericCols : headers;
            yOptions.forEach(function(h) {
                var formatted = h.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                yAxisSelect.append('<option value="' + h + '">' + formatted + '</option>');
            });

            // Set defaults
            this.embeddedXAxis = headers[0];
            this.embeddedYAxis = numericCols.length > 0 ? numericCols[numericCols.length - 1] : headers[headers.length - 1];

            xAxisSelect.val(this.embeddedXAxis);
            yAxisSelect.val(this.embeddedYAxis);
        },

        /**
         * Render current page of table in embedded mode.
         */
        renderEmbeddedTablePage: function() {
            var data = this.embeddedData || [];
            var table = this.container.find('.embedded-table');
            var thead = table.find('thead');
            var tbody = table.find('tbody');

            thead.empty();
            tbody.empty();

            if (data.length === 0) {
                return;
            }

            // Build headers
            var headers = Object.keys(data[0]);
            var headerRow = $('<tr>');
            headers.forEach(function(h) {
                var formatted = h.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                headerRow.append($('<th>').text(formatted));
            });
            thead.append(headerRow);

            // Calculate pagination
            var totalPages = Math.ceil(data.length / this.embeddedTableRowsPerPage);
            var startIndex = (this.embeddedTablePage - 1) * this.embeddedTableRowsPerPage;
            var endIndex = Math.min(startIndex + this.embeddedTableRowsPerPage, data.length);
            var pageData = data.slice(startIndex, endIndex);

            // Build rows
            pageData.forEach(function(row) {
                var tr = $('<tr>');
                headers.forEach(function(h) {
                    var val = row[h];
                    if (val === null || val === undefined) val = '';
                    var displayVal = String(val);
                    if (displayVal.length > 50) {
                        displayVal = displayVal.substring(0, 50) + '...';
                    }
                    tr.append($('<td>').attr('title', val).text(displayVal));
                });
                tbody.append(tr);
            });

            // Update pagination info
            this.container.find('.row-count').text('Showing ' + (startIndex + 1) + '-' + endIndex + ' of ' + data.length);
            this.container.find('.embedded-pagination-info').text('Page ' + this.embeddedTablePage + ' of ' + totalPages);

            // Update button states
            this.container.find('.embedded-pagination-prev').prop('disabled', this.embeddedTablePage <= 1);
            this.container.find('.embedded-pagination-next').prop('disabled', this.embeddedTablePage >= totalPages);
        },

        /**
         * Render chart in embedded mode with current settings.
         */
        renderEmbeddedChart: function() {
            var data = this.embeddedData || [];
            var canvas = this.container.find('.embedded-chart')[0];

            if (!canvas || data.length === 0) {
                return;
            }

            // Destroy existing chart
            if (this.embeddedChartInstance) {
                this.embeddedChartInstance.destroy();
                this.embeddedChartInstance = null;
            }

            var chartType = this.embeddedChartType || 'bar';
            var xAxis = this.embeddedXAxis || Object.keys(data[0])[0];
            var yAxis = this.embeddedYAxis || Object.keys(data[0])[1];

            // Prepare chart data (limit to 30 items)
            var chartData = data.slice(0, 30);
            var labels = chartData.map(function(row) {
                var label = row[xAxis];
                if (label === null || label === undefined) return 'Unknown';
                var labelStr = String(label);
                return labelStr.length > 20 ? labelStr.substring(0, 20) + '...' : labelStr;
            });
            var values = chartData.map(function(row) {
                return parseFloat(row[yAxis]) || 0;
            });

            // Generate colors
            var colors = this.generateChartColors(values.length, chartType);

            // Build dataset config
            var datasetConfig = {
                label: yAxis.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }),
                data: values
            };

            if (chartType === 'pie' || chartType === 'doughnut') {
                datasetConfig.backgroundColor = colors;
                datasetConfig.borderWidth = 1;
            } else {
                datasetConfig.backgroundColor = colors[0];
                datasetConfig.borderColor = colors[0].replace('0.7', '1');
                datasetConfig.borderWidth = 1;
            }

            // Chart config
            var config = {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [datasetConfig]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: chartType === 'pie' || chartType === 'doughnut',
                            position: 'right'
                        }
                    }
                }
            };

            // Add scales for bar/line charts
            if (chartType === 'bar' || chartType === 'line') {
                config.options.scales = {
                    y: { beginAtZero: true },
                    x: { display: data.length <= 15 }
                };
            }

            try {
                this.embeddedChartInstance = new Chart(canvas.getContext('2d'), config);
            } catch (error) {
                this.container.find('.embedded-chart-container').html(
                    '<div class="alert alert-warning text-center"><i class="fa fa-exclamation-circle"></i> Could not render chart</div>'
                );
            }
        },

        /**
         * Export embedded report data.
         *
         * @param {string} format
         */
        exportEmbeddedReport: function(format) {
            var data = this.embeddedData || [];
            var report = this.embeddedReport || {};

            if (data.length === 0) {
                return;
            }

            if (format === 'csv') {
                var headers = Object.keys(data[0]);
                var csvContent = headers.join(',') + '\n';

                data.forEach(function(row) {
                    var rowValues = headers.map(function(h) {
                        var val = row[h];
                        if (val === null || val === undefined) return '';
                        var strVal = String(val);
                        // Escape quotes and wrap in quotes if contains comma
                        if (strVal.includes(',') || strVal.includes('"') || strVal.includes('\n')) {
                            strVal = '"' + strVal.replace(/"/g, '""') + '"';
                        }
                        return strVal;
                    });
                    csvContent += rowValues.join(',') + '\n';
                });

                var blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
                var link = document.createElement('a');
                var reportName = report.name || report.slug || 'report';
                link.href = URL.createObjectURL(blob);
                link.download = reportName.replace(/[^a-z0-9]/gi, '_') + '_' + new Date().toISOString().split('T')[0] + '.csv';
                link.click();
            }
        },

        /**
         * Render KPI cards mode.
         *
         * @param {Array} reports
         */
        renderKpi: function(reports) {
            var self = this;
            var gridContainer = this.container.find('.block-adeptus-kpi-grid');
            var template = $('#block-adeptus-kpi-card-template-' + this.blockId);
            var maxCards = parseInt(this.config.kpiColumns, 10) || 2;
            maxCards = Math.min(maxCards * 2, 4); // Max 4 KPI cards

            gridContainer.empty();

            var kpiReports = reports.slice(0, maxCards);

            kpiReports.forEach(function(report, index) {
                var card = template.html();
                var $card = $(card);

                var reportName = report.name || report.title || report.display_name || report.slug || 'Metric';
                $card.attr('data-slug', report.slug);
                $card.attr('data-source', report.source);
                $card.find('.kpi-card-label').text(reportName);
                $card.find('.kpi-card-value').html('<i class="fa fa-spinner fa-spin"></i>');
                $card.find('.kpi-card-trend').addClass('d-none');
                $card.find('.kpi-card-sparkline').addClass('d-none');

                // Set different icons based on index
                var icons = ['fa-users', 'fa-graduation-cap', 'fa-clock', 'fa-check-circle'];
                $card.find('.kpi-card-icon i').removeClass('fa-chart-line').addClass(icons[index % icons.length]);

                gridContainer.append($card);

                // Load KPI data for this card
                self.loadKpiData(report, $card);
            });

            gridContainer.removeClass('d-none');
        },

        /**
         * Load KPI data for a single card.
         *
         * @param {Object} report
         * @param {jQuery} $card
         */
        loadKpiData: function(report, $card) {
            var self = this;
            var cacheKey = report.slug + '_' + report.source;

            // Check cache first
            if (this.reportDataCache[cacheKey]) {
                var cached = this.reportDataCache[cacheKey];
                this.renderKpiValue($card, cached.results);
                return;
            }

            // Load data based on source
            if (report.source === 'wizard') {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/generate_report.php',
                    method: 'POST',
                    data: {
                        reportid: report.name || report.report_template_id,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    timeout: 15000
                }).done(function(response) {
                    if (response.success) {
                        self.reportDataCache[cacheKey] = {
                            report: report,
                            results: response.results || []
                        };
                        self.renderKpiValue($card, response.results || []);
                    } else {
                        $card.find('.kpi-card-value').text('--');
                    }
                }).fail(function() {
                    $card.find('.kpi-card-value').text('--');
                });
            } else {
                // AI reports
                var token = this.apiKey || (window.adeptusAuthData ? window.adeptusAuthData.api_key : null);
                if (!token) {
                    $card.find('.kpi-card-value').text('--');
                    return;
                }

                $.ajax({
                    url: 'https://a360backend.stagingwithswift.com/api/v1/ai-reports/' + report.slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    if (response.success && response.data) {
                        self.reportDataCache[cacheKey] = {
                            report: response.report || report,
                            results: response.data || []
                        };
                        self.renderKpiValue($card, response.data || []);
                    } else {
                        $card.find('.kpi-card-value').text('--');
                    }
                }).fail(function() {
                    $card.find('.kpi-card-value').text('--');
                });
            }
        },

        /**
         * Render KPI value on card.
         *
         * @param {jQuery} $card
         * @param {Array} data
         */
        renderKpiValue: function($card, data) {
            if (!data || data.length === 0) {
                $card.find('.kpi-card-value').text('0');
                return;
            }

            // Find numeric column for KPI value
            var headers = Object.keys(data[0]);
            var numericCols = this.detectNumericColumns(data, headers);

            var value;
            var formattedValue;

            if (numericCols.length > 0) {
                // Use sum or count of the last numeric column
                var valueCol = numericCols[numericCols.length - 1];

                // If it's a count/total column, sum all values
                if (valueCol.toLowerCase().includes('count') ||
                    valueCol.toLowerCase().includes('total') ||
                    data.length === 1) {
                    value = data.reduce(function(sum, row) {
                        return sum + (parseFloat(row[valueCol]) || 0);
                    }, 0);
                } else {
                    // Otherwise show row count
                    value = data.length;
                }
            } else {
                // No numeric columns, show row count
                value = data.length;
            }

            // Format the value nicely
            if (value >= 1000000) {
                formattedValue = (value / 1000000).toFixed(1) + 'M';
            } else if (value >= 1000) {
                formattedValue = (value / 1000).toFixed(1) + 'K';
            } else if (Number.isInteger(value)) {
                formattedValue = value.toLocaleString();
            } else {
                formattedValue = value.toFixed(1);
            }

            $card.find('.kpi-card-value').text(formattedValue);

            // Add a simple trend indicator (placeholder - would need historical data)
            var trendContainer = $card.find('.kpi-card-trend');
            trendContainer.removeClass('d-none trend-up trend-down trend-neutral');
            trendContainer.addClass('trend-neutral');
            trendContainer.find('.trend-icon').html('<i class="fa fa-minus"></i>');
            trendContainer.find('.trend-value').text('vs previous');
        },

        /**
         * Render tabbed reports mode.
         *
         * @param {Array} reports
         */
        renderTabs: function(reports) {
            var self = this;
            var tabNav = this.container.find('.block-adeptus-tab-nav');
            var tabContent = this.container.find('.block-adeptus-tab-content');
            var tabTemplate = $('#block-adeptus-tab-template-' + this.blockId);
            var paneTemplate = $('#block-adeptus-tab-pane-template-' + this.blockId);
            var maxTabs = 5;

            tabNav.empty();
            tabContent.empty();

            // Store tab chart instances
            this.tabChartInstances = {};

            reports.slice(0, maxTabs).forEach(function(report, index) {
                var tabId = 'tab-' + self.blockId + '-' + index;
                var reportName = report.name || report.title || report.display_name || report.slug || 'Report';

                // Create tab
                var tab = $(tabTemplate.html());
                tab.find('a')
                    .attr('href', '#' + tabId)
                    .attr('aria-controls', tabId);
                tab.find('.tab-name').text(reportName);

                if (index === 0) {
                    tab.find('a').addClass('active').attr('aria-selected', 'true');
                }

                tabNav.append(tab);

                // Create pane
                var pane = $(paneTemplate.html());
                pane.attr('id', tabId);
                pane.attr('data-slug', report.slug);
                pane.attr('data-source', report.source);

                if (index === 0) {
                    pane.addClass('show active');
                }

                tabContent.append(pane);
            });

            // Load first tab content
            if (reports.length > 0) {
                var firstPane = tabContent.find('.tab-pane').first();
                this.loadTabContent(firstPane, reports[0].slug, reports[0].source);
            }

            this.container.find('.block-adeptus-tabs-container').removeClass('d-none');
        },

        /**
         * Load content for a tab.
         *
         * @param {jQuery} pane
         * @param {string} slug
         * @param {string} source
         */
        loadTabContent: function(pane, slug, source) {
            var self = this;
            var cacheKey = slug + '_' + source;
            pane.data('loaded', true);

            // Check cache first
            if (this.reportDataCache[cacheKey]) {
                var cached = this.reportDataCache[cacheKey];
                this.renderTabContent(pane, cached.report, cached.results);
                return;
            }

            // Find the report from our cached list
            var report = this.reports.find(function(r) {
                return r.slug === slug;
            });

            if (!report) {
                pane.find('.tab-pane-loading').addClass('d-none');
                pane.find('.tab-pane-content')
                    .removeClass('d-none')
                    .html('<div class="text-center text-muted py-4"><i class="fa fa-exclamation-circle"></i><p class="mt-2">Report not found</p></div>');
                return;
            }

            // Load data based on source
            if (source === 'wizard') {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/generate_report.php',
                    method: 'POST',
                    data: {
                        reportid: report.name || report.report_template_id,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    timeout: 30000
                }).done(function(response) {
                    if (response.success) {
                        self.reportDataCache[cacheKey] = {
                            report: report,
                            results: response.results || []
                        };
                        self.renderTabContent(pane, report, response.results || []);
                    } else {
                        pane.find('.tab-pane-loading').addClass('d-none');
                        pane.find('.tab-pane-content')
                            .removeClass('d-none')
                            .html('<div class="text-center text-muted py-4"><i class="fa fa-exclamation-circle"></i><p class="mt-2">' + (response.message || 'Failed to load') + '</p></div>');
                    }
                }).fail(function() {
                    pane.find('.tab-pane-loading').addClass('d-none');
                    pane.find('.tab-pane-content')
                        .removeClass('d-none')
                        .html('<div class="text-center text-muted py-4"><i class="fa fa-exclamation-circle"></i><p class="mt-2">Connection error</p></div>');
                });
            } else {
                // AI reports
                var token = this.apiKey || (window.adeptusAuthData ? window.adeptusAuthData.api_key : null);
                if (!token) {
                    pane.find('.tab-pane-loading').addClass('d-none');
                    pane.find('.tab-pane-content')
                        .removeClass('d-none')
                        .html('<div class="text-center text-muted py-4"><i class="fa fa-exclamation-circle"></i><p class="mt-2">Authentication required</p></div>');
                    return;
                }

                $.ajax({
                    url: 'https://a360backend.stagingwithswift.com/api/v1/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    if (response.success) {
                        self.reportDataCache[cacheKey] = {
                            report: response.report || report,
                            results: response.data || []
                        };
                        self.renderTabContent(pane, response.report || report, response.data || []);
                    } else {
                        pane.find('.tab-pane-loading').addClass('d-none');
                        pane.find('.tab-pane-content')
                            .removeClass('d-none')
                            .html('<div class="text-center text-muted py-4"><i class="fa fa-exclamation-circle"></i><p class="mt-2">Failed to load report</p></div>');
                    }
                }).fail(function() {
                    pane.find('.tab-pane-loading').addClass('d-none');
                    pane.find('.tab-pane-content')
                        .removeClass('d-none')
                        .html('<div class="text-center text-muted py-4"><i class="fa fa-exclamation-circle"></i><p class="mt-2">Connection error</p></div>');
                });
            }
        },

        /**
         * Render tab content with report data.
         *
         * @param {jQuery} pane
         * @param {Object} report
         * @param {Array} data
         */
        renderTabContent: function(pane, report, data) {
            var self = this;
            pane.find('.tab-pane-loading').addClass('d-none');
            var contentArea = pane.find('.tab-pane-content');

            if (!data || data.length === 0) {
                contentArea.removeClass('d-none')
                    .html('<div class="text-center text-muted py-4"><i class="fa fa-inbox"></i><p class="mt-2">No data available</p></div>');
                return;
            }

            // Create content HTML
            var html = '';

            // Chart container
            if (this.config.showChart) {
                html += '<div class="tab-chart-container" style="height: ' + (this.config.chartHeight || 200) + 'px;">' +
                    '<canvas class="tab-chart"></canvas></div>';
            }

            // Table container
            if (this.config.showTable) {
                html += '<div class="tab-table-container mt-2"><div class="table-responsive">' +
                    '<table class="table table-sm table-striped"><thead></thead><tbody></tbody></table>' +
                    '</div></div>';
            }

            contentArea.html(html).removeClass('d-none');

            // Render chart
            if (this.config.showChart) {
                this.renderTabChart(pane, report, data);
            }

            // Render table
            if (this.config.showTable) {
                this.renderTabTable(pane, data);
            }
        },

        /**
         * Render chart in tab pane.
         *
         * @param {jQuery} pane
         * @param {Object} report
         * @param {Array} data
         */
        renderTabChart: function(pane, report, data) {
            var tabId = pane.attr('id');
            var canvas = pane.find('.tab-chart')[0];

            if (!canvas || !data || data.length === 0) {
                return;
            }

            // Destroy existing chart for this tab
            if (this.tabChartInstances && this.tabChartInstances[tabId]) {
                this.tabChartInstances[tabId].destroy();
            }

            var headers = Object.keys(data[0]);
            var numericCols = this.detectNumericColumns(data, headers);

            var labelKey = headers[0];
            var valueKey = numericCols.length > 0 ? numericCols[numericCols.length - 1] : headers[headers.length - 1];
            var valueKeyFormatted = valueKey.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });

            // Limit data
            var chartData = data.slice(0, 15);
            var labels = chartData.map(function(row) {
                var label = row[labelKey];
                if (label === null || label === undefined) return 'Unknown';
                var labelStr = String(label);
                return labelStr.length > 15 ? labelStr.substring(0, 15) + '...' : labelStr;
            });
            var values = chartData.map(function(row) {
                return parseFloat(row[valueKey]) || 0;
            });

            var colors = this.generateChartColors(values.length, 'bar');

            var config = {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: valueKeyFormatted,
                        data: values,
                        backgroundColor: colors[0],
                        borderColor: colors[0].replace('0.7', '1'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true },
                        x: { display: data.length <= 8 }
                    }
                }
            };

            try {
                this.tabChartInstances = this.tabChartInstances || {};
                this.tabChartInstances[tabId] = new Chart(canvas.getContext('2d'), config);
            } catch (error) {
                pane.find('.tab-chart-container').html(
                    '<div class="alert alert-warning text-center"><i class="fa fa-exclamation-circle"></i> Could not render chart</div>'
                );
            }
        },

        /**
         * Render table in tab pane.
         *
         * @param {jQuery} pane
         * @param {Array} data
         */
        renderTabTable: function(pane, data) {
            var table = pane.find('table');
            var thead = table.find('thead');
            var tbody = table.find('tbody');
            var maxRows = this.config.tableMaxRows || 10;

            thead.empty();
            tbody.empty();

            if (!data || data.length === 0) {
                return;
            }

            var headers = Object.keys(data[0]);
            var headerRow = $('<tr>');
            headers.forEach(function(h) {
                var formatted = h.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                headerRow.append($('<th>').text(formatted));
            });
            thead.append(headerRow);

            data.slice(0, maxRows).forEach(function(row) {
                var tr = $('<tr>');
                headers.forEach(function(h) {
                    var val = row[h];
                    if (val === null || val === undefined) val = '';
                    var displayVal = String(val);
                    if (displayVal.length > 40) {
                        displayVal = displayVal.substring(0, 40) + '...';
                    }
                    tr.append($('<td>').attr('title', val).text(displayVal));
                });
                tbody.append(tr);
            });
        },

        /**
         * Handle report click.
         *
         * @param {string} slug
         * @param {string} source
         */
        handleReportClick: function(slug, source) {
            var action = this.config.clickAction || 'modal';

            switch (action) {
                case 'modal':
                    this.openReportModal(slug, source);
                    break;
                case 'newtab':
                    this.openReportNewTab(slug, source);
                    break;
                case 'expand':
                    this.expandReportInline(slug, source);
                    break;
            }
        },

        /**
         * Open report in modal.
         *
         * @param {string} slug
         * @param {string} source
         */
        openReportModal: function(slug, source) {
            var self = this;

            // Find report data.
            var report = this.reports.find(function(r) {
                return r.slug === slug;
            });

            if (!report) {
                return;
            }

            // Reset state
            this.modalData = null;
            this.modalReport = null;
            this.currentView = 'table';
            if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }

            // First render the template, then create the modal with the rendered body
            Templates.render('block_adeptus_insights/report_modal', {blockid: this.blockId})
                .then(function(html) {
                    return ModalFactory.create({
                        type: ModalFactory.types.DEFAULT,
                        title: report.name,
                        body: html,
                        large: true
                    });
                })
                .then(function(modal) {
                    self.modal = modal;

                    // Bind modal event handlers
                    self.bindModalEvents(modal);

                    // Load report data after modal is shown.
                    modal.getRoot().on(ModalEvents.shown, function() {
                        self.loadModalReport(slug, source);
                    });

                    // Cleanup on close.
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        if (self.chartInstance) {
                            self.chartInstance.destroy();
                            self.chartInstance = null;
                        }
                        modal.destroy();
                        self.modal = null;
                        self.modalData = null;
                        self.modalReport = null;
                    });

                    modal.show();
                    return modal;
                }).catch(Notification.exception);
        },

        /**
         * Bind event handlers for modal UI elements.
         *
         * @param {Object} modal
         */
        bindModalEvents: function(modal) {
            var self = this;
            var modalRoot = modal.getRoot();

            // View toggle buttons
            modalRoot.on('click', '.view-toggle-btn', function(e) {
                e.preventDefault();
                var view = $(this).data('view');
                self.switchModalView(view);
            });

            // Chart control changes
            modalRoot.on('change', '#modal-chart-type, #modal-chart-x-axis, #modal-chart-y-axis', function() {
                if (self.currentView === 'chart') {
                    self.renderModalChart();
                }
            });

            // Retry button
            modalRoot.on('click', '.modal-retry', function(e) {
                e.preventDefault();
                if (self.modalReport) {
                    self.loadModalReport(self.modalReport.slug, self.modalReport.source);
                }
            });

            // Export CSV
            modalRoot.on('click', '.modal-export[data-format="csv"]', function(e) {
                e.preventDefault();
                self.exportModalData('csv');
            });

            // Table pagination - Previous
            modalRoot.on('click', '.table-pagination-prev', function(e) {
                e.preventDefault();
                if (self.tableCurrentPage > 1) {
                    self.tableCurrentPage--;
                    self.renderModalTablePage();
                }
            });

            // Table pagination - Next
            modalRoot.on('click', '.table-pagination-next', function(e) {
                e.preventDefault();
                if (self.tableCurrentPage < self.tableTotalPages) {
                    self.tableCurrentPage++;
                    self.renderModalTablePage();
                }
            });
        },

        /**
         * Switch between table and chart views in modal.
         *
         * @param {string} view - 'table' or 'chart'
         */
        switchModalView: function(view) {
            var modalBody = this.modal.getBody();

            // Update toggle buttons
            modalBody.find('.view-toggle-btn').removeClass('active');
            modalBody.find('.view-toggle-btn[data-view="' + view + '"]').addClass('active');

            // Switch views
            if (view === 'table') {
                modalBody.find('#modal-table-view').removeClass('d-none');
                modalBody.find('#modal-chart-view').addClass('d-none');
            } else {
                modalBody.find('#modal-table-view').addClass('d-none');
                modalBody.find('#modal-chart-view').removeClass('d-none');
                // Render chart when switching to chart view
                this.renderModalChart();
            }

            this.currentView = view;
        },

        /**
         * Load report data into modal.
         *
         * @param {string} slug
         * @param {string} source
         */
        loadModalReport: function(slug, source) {
            var self = this;
            var modalBody = this.modal.getBody();
            var cacheKey = slug + '_' + source;

            // Check cache first for instant loading
            if (this.reportDataCache[cacheKey]) {
                var cached = this.reportDataCache[cacheKey];
                modalBody.find('.modal-loading').addClass('d-none');
                this.renderModalContent(modalBody, cached.report, cached.results, cached.chartData, cached.chartType);
                return;
            }

            // Find the report from our cached list
            var report = this.reports.find(function(r) {
                return r.slug === slug;
            });

            if (!report) {
                modalBody.find('.modal-loading').addClass('d-none');
                modalBody.find('.modal-error').removeClass('d-none');
                return;
            }

            // For wizard reports, execute locally via generate_report.php
            // For AI reports, we need to fetch from backend
            if (source === 'wizard') {
                // Execute report via local Moodle AJAX endpoint
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/generate_report.php',
                    method: 'POST',
                    data: {
                        reportid: report.name || report.report_template_id,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    timeout: 30000
                }).done(function(response) {
                    modalBody.find('.modal-loading').addClass('d-none');

                    if (response.success) {
                        // Cache the result for future use
                        self.reportDataCache[cacheKey] = {
                            report: report,
                            results: response.results || [],
                            chartData: response.chart_data,
                            chartType: response.chart_type
                        };
                        self.renderModalContent(modalBody, report, response.results || [], response.chart_data, response.chart_type);
                    } else {
                        modalBody.find('.modal-error').removeClass('d-none');
                        modalBody.find('.modal-error p').text(response.message || 'Failed to load report');
                    }
                }).fail(function() {
                    modalBody.find('.modal-loading').addClass('d-none');
                    modalBody.find('.modal-error').removeClass('d-none');
                });
            } else {
                // AI reports - fetch from backend API
                var token = this.apiKey || (window.adeptusAuthData ? window.adeptusAuthData.api_key : null);

                if (!token) {
                    modalBody.find('.modal-loading').addClass('d-none');
                    modalBody.find('.modal-error').removeClass('d-none');
                    return;
                }

                $.ajax({
                    url: 'https://a360backend.stagingwithswift.com/api/v1/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    modalBody.find('.modal-loading').addClass('d-none');

                    if (response.success && response.report) {
                        // Cache the result for future use
                        self.reportDataCache[cacheKey] = {
                            report: response.report,
                            results: response.data || [],
                            chartData: null,
                            chartType: null
                        };
                        self.renderModalContent(modalBody, response.report, response.data || [], null, null);
                    } else {
                        modalBody.find('.modal-error').removeClass('d-none');
                    }
                }).fail(function() {
                    modalBody.find('.modal-loading').addClass('d-none');
                    modalBody.find('.modal-error').removeClass('d-none');
                });
            }
        },

        /**
         * Render modal content with report data.
         *
         * @param {jQuery} modalBody
         * @param {Object} report
         * @param {Array} data
         * @param {Object} chartData - Pre-configured chart data from backend (unused, we use controls)
         * @param {string} chartType - Chart type from backend (unused, we use controls)
         */
        renderModalContent: function(modalBody, report, data, chartData, chartType) {
            var self = this;

            // Store data for chart re-rendering
            this.modalData = data || [];
            this.modalReport = report;

            var contentArea = modalBody.find('.modal-content-area');
            contentArea.removeClass('d-none');

            // Set report metadata
            var category = report.category_info || {name: 'General', color: '#6c757d'};
            modalBody.find('.report-category')
                .text(category.name)
                .css('background-color', category.color);
            modalBody.find('.row-count-num').text(this.modalData.length);
            modalBody.find('.report-date')
                .text(report.last_executed_at ? 'Last run: ' + new Date(report.last_executed_at).toLocaleDateString() : '');

            // Populate axis selectors
            this.populateAxisSelectors(modalBody);

            // Render table
            this.renderModalTable(modalBody, this.modalData);

            // Set up view full report link (admin only)
            if (this.isAdmin) {
                var reportUrl = M.cfg.wwwroot + '/report/adeptus_insights/generated_reports.php?slug=' + encodeURIComponent(report.slug);
                modalBody.find('.view-full-report').attr('href', reportUrl);
                modalBody.find('.modal-footer-link').removeClass('d-none');
            } else {
                modalBody.find('.modal-footer-link').addClass('d-none');
            }

            // Reset to table view
            this.currentView = 'table';
            modalBody.find('.view-toggle-btn').removeClass('active');
            modalBody.find('.view-toggle-btn[data-view="table"]').addClass('active');
            modalBody.find('#modal-table-view').removeClass('d-none');
            modalBody.find('#modal-chart-view').addClass('d-none');
        },

        /**
         * Populate X-axis and Y-axis dropdown selectors.
         *
         * @param {jQuery} modalBody
         */
        populateAxisSelectors: function(modalBody) {
            var data = this.modalData;
            if (!data || data.length === 0) {
                return;
            }

            var headers = Object.keys(data[0]);
            var xAxisSelect = modalBody.find('#modal-chart-x-axis');
            var yAxisSelect = modalBody.find('#modal-chart-y-axis');

            // Clear existing options
            xAxisSelect.empty();
            yAxisSelect.empty();

            // Detect numeric columns for Y-axis
            var numericCols = this.detectNumericColumns(data, headers);

            // Populate X-axis with all columns
            headers.forEach(function(header, idx) {
                var formattedHeader = header.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                var selected = idx === 0 ? ' selected' : '';
                xAxisSelect.append('<option value="' + header + '"' + selected + '>' + formattedHeader + '</option>');
            });

            // Populate Y-axis with numeric columns (or all if none detected)
            var yAxisOptions = numericCols.length > 0 ? numericCols : headers;
            yAxisOptions.forEach(function(col, idx) {
                var formattedHeader = col.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                // Select the last numeric column by default (usually the main value)
                var selected = idx === yAxisOptions.length - 1 ? ' selected' : '';
                yAxisSelect.append('<option value="' + col + '"' + selected + '>' + formattedHeader + '</option>');
            });
        },

        /**
         * Detect numeric columns in data.
         *
         * @param {Array} data
         * @param {Array} headers
         * @return {Array}
         */
        detectNumericColumns: function(data, headers) {
            var numericCols = [];

            headers.forEach(function(header) {
                var isNumeric = data.every(function(row) {
                    var val = row[header];
                    if (val === null || val === undefined || val === '') {
                        return true; // Allow nulls
                    }
                    return !isNaN(parseFloat(val)) && isFinite(val);
                });

                // Also check if at least some values are actually numbers
                var hasNumbers = data.some(function(row) {
                    var val = row[header];
                    return val !== null && val !== undefined && val !== '' && !isNaN(parseFloat(val));
                });

                if (isNumeric && hasNumbers) {
                    numericCols.push(header);
                }
            });

            return numericCols;
        },

        /**
         * Render chart in modal using current control values.
         */
        renderModalChart: function() {
            var self = this;
            var modalBody = this.modal.getBody();
            var data = this.modalData;

            if (!data || data.length === 0) {
                modalBody.find('.chart-container').html(
                    '<div class="alert alert-warning text-center"><i class="fa fa-info-circle"></i> No data available for chart</div>'
                );
                return;
            }

            var canvas = modalBody.find('.modal-chart')[0];
            if (!canvas) {
                return;
            }

            // Destroy existing chart
            if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }

            // Get control values
            var chartType = modalBody.find('#modal-chart-type').val() || 'bar';
            var labelKey = modalBody.find('#modal-chart-x-axis').val();
            var valueKey = modalBody.find('#modal-chart-y-axis').val();

            // Fallback if not set
            if (!labelKey || !valueKey) {
                var headers = Object.keys(data[0]);
                labelKey = labelKey || headers[0];
                valueKey = valueKey || headers[headers.length - 1];
            }

            // Format value key for display
            var valueKeyFormatted = valueKey.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });

            // Limit data for chart readability (max 50 items)
            var chartData = data.slice(0, 50);
            var labels = chartData.map(function(row) {
                var label = row[labelKey];
                if (label === null || label === undefined) return 'Unknown';
                var labelStr = String(label);
                return labelStr.length > 30 ? labelStr.substring(0, 30) + '...' : labelStr;
            });
            var values = chartData.map(function(row) {
                return parseFloat(row[valueKey]) || 0;
            });

            // Generate colors
            var colors = this.generateChartColors(values.length, chartType);

            // Create chart config
            var config = this.createChartConfig(chartType, labels, values, valueKeyFormatted, colors);

            try {
                this.chartInstance = new Chart(canvas.getContext('2d'), config);
            } catch (error) {
                modalBody.find('.chart-container').html(
                    '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Error rendering chart</div>'
                );
            }
        },

        /**
         * Create Chart.js configuration.
         *
         * @param {string} chartType
         * @param {Array} labels
         * @param {Array} values
         * @param {string} valueKey
         * @param {Array} colors
         * @return {Object}
         */
        createChartConfig: function(chartType, labels, values, valueKey, colors) {
            var reportName = this.modalReport ? this.modalReport.name : 'Report';

            var baseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: reportName,
                        font: { size: 14, weight: 'bold' },
                        padding: { top: 10, bottom: 20 }
                    },
                    legend: {
                        display: chartType === 'pie' || chartType === 'doughnut',
                        position: 'right'
                    }
                }
            };

            if (chartType === 'pie' || chartType === 'doughnut') {
                return {
                    type: chartType,
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 2
                        }]
                    },
                    options: baseOptions
                };
            } else {
                // Bar or Line chart
                baseOptions.scales = {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: valueKey
                        }
                    },
                    x: {
                        title: {
                            display: false
                        }
                    }
                };

                return {
                    type: chartType,
                    data: {
                        labels: labels,
                        datasets: [{
                            label: valueKey,
                            data: values,
                            backgroundColor: chartType === 'line' ? 'transparent' : colors[0],
                            borderColor: colors[0].replace('0.7', '1'),
                            borderWidth: 2,
                            fill: chartType === 'line' ? false : true,
                            tension: 0.1
                        }]
                    },
                    options: baseOptions
                };
            }
        },

        /**
         * Generate chart colors.
         *
         * @param {number} count
         * @param {string} chartType
         * @return {Array}
         */
        generateChartColors: function(count, chartType) {
            var baseColors = [
                'rgba(37, 99, 235, 0.7)',   // Blue
                'rgba(16, 185, 129, 0.7)',  // Green
                'rgba(245, 158, 11, 0.7)',  // Amber
                'rgba(239, 68, 68, 0.7)',   // Red
                'rgba(139, 92, 246, 0.7)',  // Purple
                'rgba(6, 182, 212, 0.7)',   // Cyan
                'rgba(236, 72, 153, 0.7)',  // Pink
                'rgba(132, 204, 22, 0.7)',  // Lime
                'rgba(249, 115, 22, 0.7)',  // Orange
                'rgba(99, 102, 241, 0.7)'   // Indigo
            ];

            var colors = [];
            for (var i = 0; i < count; i++) {
                colors.push(baseColors[i % baseColors.length]);
            }
            return colors;
        },

        /**
         * Export modal data as CSV.
         *
         * @param {string} format
         */
        exportModalData: function(format) {
            var data = this.modalData;
            var report = this.modalReport;

            if (!data || data.length === 0) {
                Notification.addNotification({
                    message: 'No data to export',
                    type: 'warning'
                });
                return;
            }

            if (format === 'csv') {
                var headers = Object.keys(data[0]);
                var csvContent = headers.join(',') + '\n';

                data.forEach(function(row) {
                    var values = headers.map(function(h) {
                        var val = row[h];
                        if (val === null || val === undefined) val = '';
                        // Escape quotes and wrap in quotes if contains comma
                        val = String(val).replace(/"/g, '""');
                        if (val.indexOf(',') !== -1 || val.indexOf('"') !== -1 || val.indexOf('\n') !== -1) {
                            val = '"' + val + '"';
                        }
                        return val;
                    });
                    csvContent += values.join(',') + '\n';
                });

                // Download
                var blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = (report ? report.name.replace(/[^a-z0-9]/gi, '_') : 'report') + '.csv';
                link.click();
            }
        },

        /**
         * Render table in modal with pagination.
         *
         * @param {jQuery} modalBody
         * @param {Array} data
         */
        renderModalTable: function(modalBody, data) {
            // Reset pagination
            this.tableCurrentPage = 1;
            this.tableTotalPages = Math.ceil((data ? data.length : 0) / this.tableRowsPerPage);

            // Build table headers once
            var table = modalBody.find('.modal-table');
            var thead = table.find('thead');

            thead.empty();

            if (!data || data.length === 0) {
                modalBody.find('.modal-table-container').addClass('d-none');
                return;
            }

            // Build headers from first row
            var headers = Object.keys(data[0]);
            var headerRow = $('<tr>');
            headers.forEach(function(h) {
                // Format header nicely
                var formatted = h.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                headerRow.append($('<th>').text(formatted));
            });
            thead.append(headerRow);

            // Render first page
            this.renderModalTablePage();
        },

        /**
         * Render current page of table data.
         */
        renderModalTablePage: function() {
            var modalBody = this.modal.getBody();
            var data = this.modalData || [];
            var table = modalBody.find('.modal-table');
            var tbody = table.find('tbody');

            tbody.empty();

            if (!data || data.length === 0) {
                return;
            }

            var headers = Object.keys(data[0]);

            // Calculate page slice
            var startIndex = (this.tableCurrentPage - 1) * this.tableRowsPerPage;
            var endIndex = startIndex + this.tableRowsPerPage;
            var pageData = data.slice(startIndex, endIndex);

            // Build rows
            pageData.forEach(function(row) {
                var tr = $('<tr>');
                headers.forEach(function(h) {
                    var val = row[h];
                    if (val === null || val === undefined) val = '';
                    // Truncate long values
                    var displayVal = String(val);
                    if (displayVal.length > 100) {
                        displayVal = displayVal.substring(0, 100) + '...';
                    }
                    tr.append($('<td>').attr('title', val).text(displayVal));
                });
                tbody.append(tr);
            });

            // Update pagination info and controls
            var actualEnd = Math.min(endIndex, data.length);
            modalBody.find('.row-count').text(data.length + ' total rows');

            var paginationInfo = modalBody.find('.table-pagination-info');
            paginationInfo.text((startIndex + 1) + '-' + actualEnd + ' of ' + data.length);

            // Update button states
            modalBody.find('.table-pagination-prev').prop('disabled', this.tableCurrentPage <= 1);
            modalBody.find('.table-pagination-next').prop('disabled', this.tableCurrentPage >= this.tableTotalPages);

            // Show/hide pagination based on total pages
            if (this.tableTotalPages > 1) {
                modalBody.find('.table-pagination').removeClass('d-none');
            } else {
                modalBody.find('.table-pagination').addClass('d-none');
            }
        },

        /**
         * Open report in new tab.
         *
         * @param {string} slug
         * @param {string} source
         */
        openReportNewTab: function(slug, source) {
            var url = M.cfg.wwwroot + '/report/adeptus_insights/generated_reports.php?slug=' + encodeURIComponent(slug);
            window.open(url, '_blank');
        },

        /**
         * Expand report inline.
         *
         * @param {string} slug
         * @param {string} source
         */
        expandReportInline: function(slug, source) {
            // TODO: Implement inline expansion.
        },

        /**
         * Export report.
         *
         * @param {string} format
         */
        exportReport: function(format) {
            // TODO: Implement export functionality.
            Notification.addNotification({
                message: 'Export to ' + format.toUpperCase() + ' coming soon',
                type: 'info'
            });
        },

        /**
         * Refresh the block (clears cache and reloads).
         */
        refresh: function() {
            var refreshBtn = this.container.find('.block-adeptus-refresh i');
            refreshBtn.addClass('fa-spin');

            // Clear all caches for fresh data
            this.clearAllCaches();

            // Force reload from server
            this.showLoading();
            var self = this;
            this.waitForAuth(function() {
                self.fetchReports();
            });

            setTimeout(function() {
                refreshBtn.removeClass('fa-spin');
            }, 1000);
        },

        /**
         * Clear all caches.
         */
        clearAllCaches: function() {
            // Clear sessionStorage cache
            try {
                sessionStorage.removeItem(this.cacheKey);
            } catch (e) {
                // Ignore
            }
            // Clear in-memory report data cache
            this.reportDataCache = {};
        },

        /**
         * Setup auto-refresh timer.
         */
        setupAutoRefresh: function() {
            var interval = this.config.autoRefresh;
            if (!interval || interval === 'never') {
                return;
            }

            var ms;
            switch (interval) {
                case '5m':
                    ms = 5 * 60 * 1000;
                    break;
                case '15m':
                    ms = 15 * 60 * 1000;
                    break;
                case '30m':
                    ms = 30 * 60 * 1000;
                    break;
                case '1h':
                    ms = 60 * 60 * 1000;
                    break;
                default:
                    return;
            }

            var self = this;
            this.refreshTimer = setInterval(function() {
                // Only refresh if page is visible.
                if (!document.hidden) {
                    self.refresh();
                }
            }, ms);
        },

        /**
         * Show loading state.
         */
        showLoading: function() {
            this.container.find('.block-adeptus-loading').removeClass('d-none');
            this.container.find('.block-adeptus-report-list, .block-adeptus-kpi-grid, .block-adeptus-tabs-container, .block-adeptus-content').addClass('d-none');
            this.container.find('.block-adeptus-empty, .block-adeptus-error').addClass('d-none');
        },

        /**
         * Hide loading state.
         */
        hideLoading: function() {
            this.container.find('.block-adeptus-loading').addClass('d-none');
        },

        /**
         * Show empty state.
         */
        showEmpty: function() {
            this.hideLoading();
            this.container.find('.block-adeptus-empty').removeClass('d-none');
        },

        /**
         * Show error state.
         *
         * @param {string} message
         */
        showError: function(message) {
            this.hideLoading();
            this.container.find('.block-adeptus-error').removeClass('d-none');
        },

        /**
         * Update the timestamp display.
         */
        updateTimestamp: function() {
            if (!this.config.showTimestamp || !this.lastUpdated) {
                return;
            }

            var timestampEl = this.container.find('.block-adeptus-timestamp');
            var text = this.formatTimeAgo(this.lastUpdated);

            timestampEl.find('.timestamp-text').text(text);
            timestampEl.removeClass('d-none');
        },

        /**
         * Scroll to the top of the report list.
         */
        scrollToListTop: function() {
            var listContainer = this.container.find('.block-adeptus-report-list');
            if (listContainer.length) {
                listContainer[0].scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        },

        /**
         * Scroll to the top of the embedded table.
         */
        scrollToEmbeddedTableTop: function() {
            var tableContainer = this.container.find('.embedded-table-view');
            if (tableContainer.length) {
                tableContainer[0].scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        },

        /**
         * Show the embedded loading overlay.
         */
        showEmbeddedLoadingOverlay: function() {
            var overlay = this.container.find('.embedded-loading-overlay');
            var content = this.container.find('.block-adeptus-content');

            // Show content area if hidden (to show overlay over it)
            content.removeClass('d-none');

            // Show overlay with fade
            overlay.removeClass('d-none').css('opacity', 0).animate({opacity: 1}, 150);
        },

        /**
         * Hide the embedded loading overlay.
         */
        hideEmbeddedLoadingOverlay: function() {
            var overlay = this.container.find('.embedded-loading-overlay');
            overlay.animate({opacity: 0}, 150, function() {
                $(this).addClass('d-none');
            });
        },

        /**
         * Toggle searchable dropdown open/closed.
         *
         * @param {jQuery} dropdown
         */
        toggleSearchableDropdown: function(dropdown) {
            if (dropdown.hasClass('open')) {
                this.closeSearchableDropdown(dropdown);
            } else {
                this.openSearchableDropdown(dropdown);
            }
        },

        /**
         * Open searchable dropdown.
         *
         * @param {jQuery} dropdown
         */
        openSearchableDropdown: function(dropdown) {
            // Close any other open dropdowns first
            var self = this;
            this.container.find('.searchable-dropdown.open').each(function() {
                if (!$(this).is(dropdown)) {
                    self.closeSearchableDropdown($(this));
                }
            });

            dropdown.addClass('open');
            dropdown.find('.searchable-dropdown-toggle').attr('aria-expanded', 'true');

            // Clear search and show all items
            var input = dropdown.find('.searchable-dropdown-input');
            input.val('');
            dropdown.find('.searchable-dropdown-item').show().removeClass('focused');
            dropdown.find('.searchable-dropdown-empty').addClass('d-none');

            // Focus search input
            setTimeout(function() {
                input.focus();
            }, 50);
        },

        /**
         * Close searchable dropdown.
         *
         * @param {jQuery} dropdown
         */
        closeSearchableDropdown: function(dropdown) {
            dropdown.removeClass('open');
            dropdown.find('.searchable-dropdown-toggle').attr('aria-expanded', 'false');
            dropdown.find('.searchable-dropdown-item').removeClass('focused');
        },

        /**
         * Filter searchable dropdown items based on search query.
         *
         * @param {jQuery} dropdown
         * @param {string} query
         */
        filterSearchableDropdown: function(dropdown, query) {
            var items = dropdown.find('.searchable-dropdown-item');
            var emptyState = dropdown.find('.searchable-dropdown-empty');
            var visibleCount = 0;

            items.each(function() {
                var searchText = $(this).data('search') || '';
                if (query === '' || searchText.indexOf(query) !== -1) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            // Remove focus from hidden items
            items.filter(':hidden').removeClass('focused');

            // Show/hide empty state
            if (visibleCount === 0) {
                emptyState.removeClass('d-none');
            } else {
                emptyState.addClass('d-none');
            }
        },

        /**
         * Select an item from the searchable dropdown.
         *
         * @param {jQuery} dropdown
         * @param {string} value
         * @param {string} text
         */
        selectSearchableDropdownItem: function(dropdown, value, text) {
            // Update display text
            dropdown.find('.searchable-dropdown-text').text(text);

            // Mark item as selected
            dropdown.find('.searchable-dropdown-item').removeClass('selected');
            dropdown.find('.searchable-dropdown-item[data-value="' + value + '"]').addClass('selected');

            // Find the hidden select sibling and trigger change
            // Works for both category-filter-select and report-selector-select
            var select = dropdown.siblings('select');
            if (select.length) {
                select.val(value).trigger('change');
            }

            // Close dropdown
            this.closeSearchableDropdown(dropdown);
        },

        /**
         * Scroll a dropdown item into view.
         *
         * @param {jQuery} item
         */
        scrollDropdownItemIntoView: function(item) {
            var list = item.closest('.searchable-dropdown-list');
            var listHeight = list.height();
            var itemTop = item.position().top;
            var itemHeight = item.outerHeight();

            if (itemTop < 0) {
                list.scrollTop(list.scrollTop() + itemTop);
            } else if (itemTop + itemHeight > listHeight) {
                list.scrollTop(list.scrollTop() + (itemTop + itemHeight - listHeight));
            }
        },

        /**
         * Escape HTML special characters.
         *
         * @param {string} text
         * @return {string}
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Format a date as "X ago".
         *
         * @param {Date} date
         * @return {string}
         */
        formatTimeAgo: function(date) {
            var seconds = Math.floor((new Date() - date) / 1000);

            if (seconds < 60) {
                return 'Just now';
            }

            var minutes = Math.floor(seconds / 60);
            if (minutes < 60) {
                return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
            }

            var hours = Math.floor(minutes / 60);
            if (hours < 24) {
                return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
            }

            var days = Math.floor(hours / 24);
            return days + ' day' + (days !== 1 ? 's' : '') + ' ago';
        }
    };

    return {
        /**
         * Initialize the block.
         *
         * @param {Object} options
         */
        init: function(options) {
            return new BlockController(options);
        }
    };
});
