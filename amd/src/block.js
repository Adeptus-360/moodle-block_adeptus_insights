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

        // Backend API URL - use config value or fall back to default
        this.backendUrl = this.config.backendUrl || 'https://backend.adeptus360.com/api/v1';

        // Cache settings
        this.cacheKey = 'adeptus_block_reports_' + this.blockId;
        this.cacheTTL = 5 * 60 * 1000; // 5 minutes
        this.reportDataCache = {}; // In-memory cache for executed report data
        this.preloadTimeout = null;
        this.preloadingSlug = null;

        // Tabs mode state (per-pane state for view toggle, pagination, chart)
        this.tabPaneStates = {};
        this.tabChartInstances = {};

        // Snapshot schedule registration tracking
        this.registeredSnapshots = {};

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

            // Tabs mode - View toggle (Table/Chart).
            this.container.on('click', '.tab-view-toggle-btn', function(e) {
                e.preventDefault();
                var pane = $(this).closest('.tab-pane');
                var view = $(this).data('view');
                self.switchTabView(pane, view);
            });

            // Tabs mode - Table pagination.
            this.container.on('click', '.tab-pagination-prev', function(e) {
                e.preventDefault();
                var pane = $(this).closest('.tab-pane');
                var tabId = pane.attr('id');
                var state = self.tabPaneStates[tabId];
                if (state && state.tablePage > 1) {
                    state.tablePage--;
                    self.renderTabTablePage(pane, state);
                }
            });

            this.container.on('click', '.tab-pagination-next', function(e) {
                e.preventDefault();
                var pane = $(this).closest('.tab-pane');
                var tabId = pane.attr('id');
                var state = self.tabPaneStates[tabId];
                if (state) {
                    var totalPages = Math.ceil(state.data.length / state.rowsPerPage);
                    if (state.tablePage < totalPages) {
                        state.tablePage++;
                        self.renderTabTablePage(pane, state);
                    }
                }
            });

            // Tabs mode - Chart type change.
            this.container.on('change', '.tab-chart-type', function() {
                var pane = $(this).closest('.tab-pane');
                var tabId = pane.attr('id');
                var state = self.tabPaneStates[tabId];
                if (state) {
                    state.chartType = $(this).val();
                    self.renderTabChart(pane, state);
                }
            });

            // Tabs mode - X-Axis change.
            this.container.on('change', '.tab-chart-x-axis', function() {
                var pane = $(this).closest('.tab-pane');
                var tabId = pane.attr('id');
                var state = self.tabPaneStates[tabId];
                if (state) {
                    state.xAxis = $(this).val();
                    self.renderTabChart(pane, state);
                }
            });

            // Tabs mode - Y-Axis change.
            this.container.on('change', '.tab-chart-y-axis', function() {
                var pane = $(this).closest('.tab-pane');
                var tabId = pane.attr('id');
                var state = self.tabPaneStates[tabId];
                if (state) {
                    state.yAxis = $(this).val();
                    self.renderTabChart(pane, state);
                }
            });

            // Tabs mode - Export.
            this.container.on('click', '.tab-export', function(e) {
                e.preventDefault();
                var pane = $(this).closest('.tab-pane');
                var tabId = pane.attr('id');
                var state = self.tabPaneStates[tabId];
                var format = $(this).data('format');
                if (state) {
                    self.exportTabReport(state, format);
                }
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
                    url: '' + this.backendUrl + '/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    if (response.success && response.report) {
                        var reportData = response.report;
                        var data = response.data || [];

                        // Check if local execution is required (SaaS model)
                        var sqlQuery = response.sql || (reportData && reportData.sql_query) || (reportData && reportData.sql);
                        var needsLocalExecution = response.execution_required || ((!data || data.length === 0) && sqlQuery);

                        if (needsLocalExecution && sqlQuery) {
                            // Execute SQL locally against Moodle database
                            self.executeReportLocally(sqlQuery, reportData.params || {})
                                .then(function(executionResult) {
                                    var localData = executionResult.data || [];
                                    self.reportDataCache[cacheKey] = {
                                        report: reportData,
                                        results: localData,
                                        chartData: null,
                                        chartType: null
                                    };
                                    self.preloadingSlug = null;
                                })
                                .catch(function() {
                                    self.preloadingSlug = null;
                                });
                            return;
                        }

                        self.reportDataCache[cacheKey] = {
                            report: reportData,
                            results: data,
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

            // For 'manual' and 'category' sources, we need to fetch all reports first,
            // then filter them in filterReports(). Same for 'all'.
            if (source === 'all' || source === 'wizard' || source === 'manual' || source === 'category') {
                promises.push(this.fetchFromApi('/wizard-reports', token));
            }

            if (source === 'all' || source === 'ai' || source === 'manual' || source === 'category') {
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
                        // Determine report source: for sources that fetch both APIs (all, manual, category),
                        // the second promise (i === 1) is AI reports.
                        var fetchesBoth = (source === 'all' || source === 'manual' || source === 'category');
                        var reportSource = (source === 'ai' || (i === 1 && fetchesBoth)) ? 'ai' : 'wizard';
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
            var baseUrl = '' + this.backendUrl + '';

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

            // Extract and populate category filter (only for modes that use it)
            if (mode === 'embedded' || mode === 'links') {
                this.populateCategoryFilter();
            }

            // Filter reports based on selected category (for links/embedded modes)
            var filteredReports = this.filterReports();

            switch (mode) {
                case 'embedded':
                    this.renderEmbedded(filteredReports);
                    break;
                case 'kpi':
                    // For KPI mode, use ALL reports for matching since user explicitly selected them
                    // (category filter doesn't apply - user chose specific reports)
                    var kpiReports = this.getSelectedReportsForMode('kpi', this.reports);
                    this.renderKpi(kpiReports);
                    break;
                case 'tabs':
                    // For Tabs mode, use ALL reports for matching since user explicitly selected them
                    // (category filter doesn't apply - user chose specific reports)
                    var tabsReports = this.getSelectedReportsForMode('tabs', this.reports);
                    this.renderTabs(tabsReports);
                    break;
                case 'links':
                default:
                    this.renderLinks(filteredReports);
                    break;
            }

            this.hideLoading();
            this.updateTimestamp();
        },

        /**
         * Get reports filtered by the selected reports configuration for a specific mode.
         *
         * @param {string} mode - 'kpi' or 'tabs'
         * @param {Array} allReports - All available reports
         * @return {Array} Filtered reports in configured order
         */
        getSelectedReportsForMode: function(mode, allReports) {
            var selectedConfig = [];

            if (mode === 'kpi' && this.config.kpiSelectedReports && this.config.kpiSelectedReports.length > 0) {
                selectedConfig = this.config.kpiSelectedReports;
            } else if (mode === 'tabs' && this.config.tabsSelectedReports && this.config.tabsSelectedReports.length > 0) {
                selectedConfig = this.config.tabsSelectedReports;
            }

            // If no specific reports configured, return all reports
            if (selectedConfig.length === 0) {
                return allReports;
            }

            // Filter and order reports according to selection
            var result = [];
            selectedConfig.forEach(function(item) {
                var slug = item.slug || item;
                var source = item.source || 'wizard';
                var report = allReports.find(function(r) {
                    return r.slug === slug && (r.source === source || !item.source);
                });
                if (report) {
                    // Clone report and add custom icon if configured
                    var enrichedReport = Object.assign({}, report);
                    if (item.icon) {
                        enrichedReport.customIcon = item.icon;
                    }
                    result.push(enrichedReport);
                }
            });

            return result;
        },

        /**
         * Extract categories from reports and populate the filter dropdown.
         */
        populateCategoryFilter: function() {
            var self = this;
            var categoryMap = {};
            var config = this.config;

            // If category is configured in block settings, pre-select it.
            // The category filter from config is always applied (locked).
            var categoryLocked = !!config.reportCategory;
            if (categoryLocked) {
                this.selectedCategory = config.reportCategory;
            }

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

            // If category is configured but not found in reports, add it as a fallback.
            // This ensures the dropdown shows the configured category even if no reports match.
            if (categoryLocked && !categoryMap[config.reportCategory]) {
                // Format slug as display name (capitalize, replace dashes/underscores with spaces).
                var displayName = config.reportCategory
                    .replace(/[-_]/g, ' ')
                    .replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                categoryMap[config.reportCategory] = {
                    slug: config.reportCategory,
                    name: displayName,
                    color: '#6c757d'
                };
            }

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

                // If category is locked via config, hide the entire category filter
                if (categoryLocked) {
                    this.container.find('.block-adeptus-category-filter').hide();
                }
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

            // Filter by category from block config (always apply if set).
            if (config.reportCategory) {
                reports = reports.filter(function(r) {
                    var catSlug = r.category_info ? r.category_info.slug : (r.category || '');
                    return catSlug === config.reportCategory;
                });
            }

            // Filter by selected category from dropdown (runtime selection, further filters config).
            if (this.selectedCategory && this.selectedCategory !== config.reportCategory) {
                reports = reports.filter(function(r) {
                    var catSlug = r.category_info ? r.category_info.slug : (r.category || '');
                    return catSlug === self.selectedCategory;
                });
            }

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
                    url: '' + this.backendUrl + '/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    if (response.success && response.report) {
                        var reportData = response.report;
                        var data = response.data || [];

                        // Check if local execution is required (SaaS model)
                        var sqlQuery = response.sql || (reportData && reportData.sql_query) || (reportData && reportData.sql);
                        var needsLocalExecution = response.execution_required || ((!data || data.length === 0) && sqlQuery);

                        if (needsLocalExecution && sqlQuery) {
                            // Execute SQL locally against Moodle database
                            self.executeReportLocally(sqlQuery, reportData.params || {})
                                .then(function(executionResult) {
                                    self.hideEmbeddedLoadingOverlay();
                                    var localData = executionResult.data || [];
                                    self.reportDataCache[cacheKey] = {
                                        report: reportData,
                                        results: localData,
                                        chartData: null,
                                        chartType: null
                                    };
                                    self.embeddedData = localData;
                                    self.renderEmbeddedContent(reportData, localData);
                                })
                                .catch(function(error) {
                                    self.hideEmbeddedLoadingOverlay();
                                    self.showError('Failed to execute report');
                                });
                            return;
                        }

                        self.hideEmbeddedLoadingOverlay();
                        // Cache the result
                        self.reportDataCache[cacheKey] = {
                            report: reportData,
                            results: data,
                            chartData: null,
                            chartType: null
                        };
                        self.embeddedData = data;
                        self.renderEmbeddedContent(reportData, data);
                    } else {
                        self.hideEmbeddedLoadingOverlay();
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
            maxCards = Math.max(1, Math.min(4, maxCards)); // Clamp between 1-4

            gridContainer.empty();

            var kpiReports = reports.slice(0, maxCards);
            var cardMap = {}; // Map slug to card element
            var wizardReports = [];
            var aiReports = [];

            // Create all cards first
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

                // Set icon - use custom icon if configured, otherwise default based on index
                var defaultIcons = ['fa-users', 'fa-graduation-cap', 'fa-clock-o', 'fa-check-circle'];
                var iconClass = report.customIcon || defaultIcons[index % defaultIcons.length];
                $card.find('.kpi-card-icon i').removeClass('fa-bar-chart').addClass(iconClass);

                gridContainer.append($card);

                // Store reference for batch update
                cardMap[report.slug] = {
                    $card: $card,
                    report: report,
                    index: index
                };

                // Separate wizard and AI reports
                if (report.source === 'wizard') {
                    wizardReports.push(report);
                } else {
                    aiReports.push(report);
                }
            });

            gridContainer.removeClass('d-none');

            // Use batch loading for wizard reports (single request for all)
            if (wizardReports.length > 0) {
                this.loadKpiBatch(wizardReports, cardMap);
            }

            // Load AI reports individually (they use external API)
            aiReports.forEach(function(report, index) {
                var cardInfo = cardMap[report.slug];
                setTimeout(function() {
                    self.loadKpiData(report, cardInfo.$card, cardInfo.index);
                }, index * 100);
            });
        },

        /**
         * Load multiple KPI cards in a single batch request.
         *
         * @param {Array} reports Wizard reports to load
         * @param {Object} cardMap Map of slug to card info
         */
        loadKpiBatch: function(reports, cardMap) {
            var self = this;
            var startTime = performance.now();

            // Get report IDs (names) for batch request
            var reportIds = reports.map(function(r) {
                return r.report_template_id || r.id || r.name || r.slug;
            });

            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/batch_kpi_data.php',
                method: 'POST',
                data: {
                    reportids: JSON.stringify(reportIds),
                    sesskey: M.cfg.sesskey
                },
                dataType: 'json',
                timeout: 30000
            }).done(function(response) {
                if (response.success && response.reports) {
                    // Update each card with its data
                    reports.forEach(function(report) {
                        var reportId = report.report_template_id || report.id || report.name || report.slug;
                        var reportData = response.reports[reportId];
                        var cardInfo = cardMap[report.slug];

                        if (!cardInfo) {
                            return;
                        }

                        var $card = cardInfo.$card;
                        var cacheKey = report.slug + '_' + report.source;

                        if (reportData && reportData.success) {
                            // Cache the results
                            self.reportDataCache[cacheKey] = {
                                report: report,
                                results: reportData.results || []
                            };

                            // Render the KPI value
                            self.renderKpiValue($card, reportData.results || []);
                        } else {
                            $card.find('.kpi-card-value').text('--');
                            $card.find('.kpi-card-trend').addClass('d-none');
                        }
                    });
                } else {
                    // Batch failed, fall back to individual loading
                    reports.forEach(function(report, index) {
                        var cardInfo = cardMap[report.slug];
                        setTimeout(function() {
                            self.loadKpiData(report, cardInfo.$card, cardInfo.index);
                        }, index * 100);
                    });
                }
            }).fail(function() {
                // Fall back to individual loading
                reports.forEach(function(report, index) {
                    var cardInfo = cardMap[report.slug];
                    setTimeout(function() {
                        self.loadKpiData(report, cardInfo.$card, cardInfo.index);
                    }, index * 100);
                });
            });
        },

        /**
         * Load KPI data for a single card.
         *
         * @param {Object} report
         * @param {jQuery} $card
         * @param {number} cardIndex Card index for logging
         * @param {number} retryCount Current retry attempt (optional)
         */
        loadKpiData: function(report, $card, cardIndex, retryCount) {
            var self = this;
            var cacheKey = report.slug + '_' + report.source;
            retryCount = retryCount || 0;
            var maxRetries = 2;
            var startTime = performance.now();

            // Check cache first
            if (this.reportDataCache[cacheKey]) {
                var cached = this.reportDataCache[cacheKey];
                this.renderKpiValue($card, cached.results);
                return;
            }

            // Get the report ID - try multiple possible fields
            var reportId = report.report_template_id || report.id || report.name || report.slug;

            // Load data based on source
            if (report.source === 'wizard') {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/generate_report.php',
                    method: 'POST',
                    data: {
                        reportid: reportId,
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
                        self.renderKpiValue($card, response.results || []);
                    } else {
                        $card.find('.kpi-card-value').text('--');
                        $card.find('.kpi-card-trend').addClass('d-none');
                    }
                }).fail(function(jqXHR, textStatus) {
                    // Retry on timeout or server error
                    if (retryCount < maxRetries && (textStatus === 'timeout' || jqXHR.status >= 500)) {
                        setTimeout(function() {
                            self.loadKpiData(report, $card, cardIndex, retryCount + 1);
                        }, 1000 * (retryCount + 1));
                    } else {
                        $card.find('.kpi-card-value').text('--');
                        $card.find('.kpi-card-trend').addClass('d-none');
                    }
                });
            } else {
                // AI reports
                var token = this.apiKey || (window.adeptusAuthData ? window.adeptusAuthData.api_key : null);
                if (!token) {
                    $card.find('.kpi-card-value').text('--');
                    $card.find('.kpi-card-trend').addClass('d-none');
                    return;
                }

                $.ajax({
                    url: '' + this.backendUrl + '/ai-reports/' + report.slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 30000
                }).done(function(response) {
                    if (response.success) {
                        var reportData = response.report || report;
                        var data = response.data || [];

                        // Check if local execution is required (SaaS model)
                        var sqlQuery = response.sql || (reportData && reportData.sql_query) || (reportData && reportData.sql);
                        var needsLocalExecution = response.execution_required || ((!data || data.length === 0) && sqlQuery);

                        if (needsLocalExecution && sqlQuery) {
                            // Execute SQL locally against Moodle database
                            self.executeReportLocally(sqlQuery, reportData.params || {})
                                .then(function(executionResult) {
                                    var localData = executionResult.data || [];
                                    self.reportDataCache[cacheKey] = {
                                        report: reportData,
                                        results: localData
                                    };
                                    self.renderKpiValue($card, localData);
                                })
                                .catch(function(error) {
                                    $card.find('.kpi-card-value').text('--');
                                    $card.find('.kpi-card-trend').addClass('d-none');
                                });
                            return;
                        }

                        if (data && data.length > 0) {
                            self.reportDataCache[cacheKey] = {
                                report: reportData,
                                results: data
                            };
                            self.renderKpiValue($card, data);
                        } else {
                            $card.find('.kpi-card-value').text('--');
                            $card.find('.kpi-card-trend').addClass('d-none');
                        }
                    } else {
                        $card.find('.kpi-card-value').text('--');
                        $card.find('.kpi-card-trend').addClass('d-none');
                    }
                }).fail(function(jqXHR, textStatus) {
                    // Retry on timeout or server error
                    if (retryCount < maxRetries && (textStatus === 'timeout' || jqXHR.status >= 500)) {
                        setTimeout(function() {
                            self.loadKpiData(report, $card, cardIndex, retryCount + 1);
                        }, 1000 * (retryCount + 1));
                    } else {
                        $card.find('.kpi-card-value').text('--');
                        $card.find('.kpi-card-trend').addClass('d-none');
                    }
                });
            }
        },

        /**
         * Render KPI value on card with trend and sparkline.
         *
         * @param {jQuery} $card
         * @param {Array} data
         */
        renderKpiValue: function($card, data) {
            var slug = $card.data('slug');
            var source = $card.data('source') || 'wizard';
            var label = $card.find('.kpi-card-label').text() || '';

            if (!data || data.length === 0) {
                $card.find('.kpi-card-value').text('0');
                // Save zero value and update trend from server
                this.saveKpiHistoryToServer($card, slug, 0, source, label, 0);
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
            formattedValue = this.formatKpiValue(value);

            $card.find('.kpi-card-value').text(formattedValue);

            // Save to server and update trend/sparkline from response
            this.saveKpiHistoryToServer($card, slug, value, source, label, data.length);
        },

        /**
         * Format a KPI value for display.
         *
         * @param {number} value
         * @return {string}
         */
        formatKpiValue: function(value) {
            if (value >= 1000000) {
                return (value / 1000000).toFixed(1) + 'M';
            } else if (value >= 1000) {
                return (value / 1000).toFixed(1) + 'K';
            } else if (Number.isInteger(value)) {
                return value.toLocaleString();
            } else {
                return value.toFixed(1);
            }
        },

        /**
         * Save KPI snapshot to backend and update trend/sparkline.
         * This is an enterprise feature - requires snapshots permission.
         *
         * @param {jQuery} $card
         * @param {string} slug
         * @param {number} value
         * @param {string} source Report source (wizard/ai)
         * @param {string} label Metric label
         * @param {number} rowCount Number of data rows
         * @param {number} executionTimeMs Execution time in milliseconds (optional)
         */
        saveKpiHistoryToServer: function($card, slug, value, source, label, rowCount, executionTimeMs) {
            var self = this;

            // Check if snapshots feature is enabled (enterprise feature)
            if (!this.config.snapshotsEnabled) {
                // Feature not enabled - hide trend and sparkline
                $card.find('.kpi-card-trend').addClass('d-none');
                $card.find('.kpi-card-sparkline').addClass('d-none');
                return;
            }

            // For AI reports, use the backend snapshots API
            if (source === 'ai') {
                this.postSnapshotToBackend($card, slug, value, executionTimeMs || 0);
                return;
            }

            // For wizard reports, also use backend API
            this.postSnapshotToBackend($card, slug, value, executionTimeMs || 0);
        },

        /**
         * Post snapshot to backend API and update trend/sparkline from response.
         *
         * @param {jQuery} $card
         * @param {string} slug Report slug
         * @param {number} metricValue The actual metric value (e.g., 122 users)
         * @param {number} executionTimeMs Execution time in milliseconds
         */
        postSnapshotToBackend: function($card, slug, metricValue, executionTimeMs) {
            var self = this;
            var token = this.apiKey;
            var source = $card.data('source') || 'wizard';

            if (!token) {
                $card.find('.kpi-card-trend').addClass('d-none');
                $card.find('.kpi-card-sparkline').addClass('d-none');
                return;
            }

            // Use correct endpoint based on report source
            var endpoint = (source === 'ai') ? '/ai-reports/' : '/wizard-reports/';

            $.ajax({
                url: this.backendUrl + endpoint + encodeURIComponent(slug) + '/snapshots',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify({
                    row_count: metricValue,
                    execution_time_ms: executionTimeMs,
                    evaluate_alerts: true
                }),
                timeout: 15000
            }).done(function(response) {
                if (response.success) {
                    // Register snapshot schedule for cron-based execution on first run
                    self.registerSnapshotSchedule(slug, source, metricValue);

                    // Update trend from response
                    if (response.trend && response.trend.has_previous) {
                        self.updateKpiTrendFromBackend($card, response.trend);
                    } else {
                        // First execution - no previous data
                        $card.find('.kpi-card-trend').addClass('d-none');
                    }

                    // Update sparkline from history
                    if (response.history && response.history.length >= 2) {
                        self.renderKpiSparklineFromBackend($card, slug, response.history);
                    } else {
                        $card.find('.kpi-card-sparkline').addClass('d-none');
                    }

                    // Handle any triggered alerts - pass context we have here.
                    if (response.alerts && response.alerts.triggered_count > 0) {
                        var reportName = $card.find('.kpi-card-label').text() || slug;
                        self.handleTriggeredAlerts(response.alerts.triggered, slug, metricValue, response.trend, reportName);
                    }
                }
            }).fail(function() {
                // Silent fail - trend won't update but KPI value is still shown
                $card.find('.kpi-card-trend').addClass('d-none');
                $card.find('.kpi-card-sparkline').addClass('d-none');
            });
        },

        /**
         * Register a report for cron-based snapshot scheduling.
         *
         * Called on first successful snapshot POST to ensure subsequent
         * snapshots are taken automatically by cron at the configured interval.
         *
         * @param {string} slug Report slug
         * @param {string} source Report source (wizard or ai)
         * @param {number} metricValue Initial metric value
         */
        registerSnapshotSchedule: function(slug, source, metricValue) {
            var self = this;
            var scheduleKey = this.blockId + '_' + slug;

            // Only register once per session per report
            if (this.registeredSnapshots[scheduleKey]) {
                return;
            }

            // Get the history interval from config (in seconds)
            var intervalSeconds = this.config.kpiHistoryInterval || 3600; // Default 1 hour

            // Call Moodle AJAX to register the schedule
            Ajax.call([{
                methodname: 'block_adeptus_insights_register_snapshot_schedule',
                args: {
                    blockinstanceid: this.blockId,
                    reportslug: slug,
                    reportsource: source,
                    intervalseconds: intervalSeconds,
                    rowcount: metricValue
                }
            }])[0].done(function(response) {
                if (response.success) {
                    // Mark as registered for this session
                    self.registeredSnapshots[scheduleKey] = true;
                }
            }).fail(function() {
                // Silent fail - schedule registration is not critical for UI
            });
        },

        /**
         * Update KPI trend indicator from backend response.
         *
         * @param {jQuery} $card
         * @param {Object} trend Trend data from backend
         */
        updateKpiTrendFromBackend: function($card, trend) {
            var trendContainer = $card.find('.kpi-card-trend');
            trendContainer.removeClass('d-none trend-up trend-down trend-neutral');

            var direction = trend.direction;
            var percentage = Math.abs(trend.change_percent || 0);
            var changeText;

            if (percentage >= 100) {
                changeText = Math.round(percentage) + '%';
            } else if (percentage >= 10) {
                changeText = percentage.toFixed(0) + '%';
            } else {
                changeText = percentage.toFixed(1) + '%';
            }

            if (direction === 'increase') {
                trendContainer.addClass('trend-up');
                trendContainer.find('.trend-icon').html('<i class="fa fa-arrow-up"></i>');
                trendContainer.find('.trend-value').text('+' + changeText + ' vs previous');
            } else if (direction === 'decrease') {
                trendContainer.addClass('trend-down');
                trendContainer.find('.trend-icon').html('<i class="fa fa-arrow-down"></i>');
                trendContainer.find('.trend-value').text('-' + changeText + ' vs previous');
            } else {
                trendContainer.addClass('trend-neutral');
                trendContainer.find('.trend-icon').html('<i class="fa fa-minus"></i>');
                trendContainer.find('.trend-value').text('No change');
            }
        },

        /**
         * Render sparkline from backend history data.
         *
         * @param {jQuery} $card
         * @param {string} slug
         * @param {Array} history History data from backend
         */
        renderKpiSparklineFromBackend: function($card, slug, history) {
            // Convert backend history format to sparkline data
            var sparklineData = history.map(function(point) {
                return point.row_count;
            }).reverse(); // Oldest first for chart

            this.renderKpiSparklineFromData($card, slug, sparklineData, sparklineData[sparklineData.length - 1]);
        },

        /**
         * Handle triggered alerts from snapshot response.
         *
         * Sends notifications via Moodle's messaging system to configured recipients.
         * Notifications appear in the bell icon notification area.
         *
         * @param {Array} triggeredAlerts Array of triggered alert objects
         * @param {string} reportSlug Report slug from snapshot context
         * @param {number} currentValue Current metric value from snapshot context
         * @param {Object} trend Trend data from snapshot response (optional)
         * @param {string} reportName Human-readable report name (optional)
         */
        handleTriggeredAlerts: function(triggeredAlerts, reportSlug, currentValue, trend, reportName) {
            var self = this;

            if (!triggeredAlerts || triggeredAlerts.length === 0) {
                return;
            }

            // Prepare alerts data for the Moodle notification API.
            // Alerts fire once and are archived - no cooldown period needed.
            // Use context values (reportSlug, currentValue) as fallbacks since backend may not include them.
            var alertsToSend = triggeredAlerts.map(function(alert) {
                var alertName = alert.alert_name || alert.name || 'Alert';
                var value = alert.current_value || alert.actual_value || alert.value || currentValue || '';
                var severity = alert.severity || (alert.is_critical ? 'critical' : 'warning');
                var displayReportName = reportName || alert.report_name || reportSlug || 'Report';

                // Build a meaningful message with context and trend info.
                // Format: "Your {report_name} has reached {value}, exceeding your {severity} threshold.
                //          {alert_name} increased/decreased by X (Y%) since last measurement."
                var message = 'Your ' + displayReportName + ' has reached ' + value +
                    ', exceeding your ' + severity + ' threshold.';

                // Add trend information if available.
                if (trend && trend.has_previous) {
                    var direction = trend.direction || 'change';
                    var changeAbs = Math.abs(trend.change_absolute || 0);
                    var changePct = Math.abs(trend.change_percent || 0).toFixed(1);

                    if (direction === 'increase') {
                        message += ' ' + alertName + ' increased by ' + changeAbs + ' (' + changePct + '%) since last measurement.';
                    } else if (direction === 'decrease') {
                        message += ' ' + alertName + ' decreased by ' + changeAbs + ' (' + changePct + '%) since last measurement.';
                    } else {
                        message += ' ' + alertName + ' has remained stable since last measurement.';
                    }
                }

                return {
                    alert_id: alert.id || alert.alert_id || 0,
                    alert_name: alertName,
                    message: message,
                    severity: severity,
                    report_name: displayReportName,
                    report_slug: alert.report_slug || alert.slug || reportSlug || '',
                    current_value: String(value),
                    threshold: String(alert.threshold || alert.threshold_value || ''),
                    notify_users: JSON.stringify(alert.notify_users || [])
                };
            });

            // Send notifications via Moodle's messaging system (silent - no on-page alerts).
            require(['core/ajax'], function(Ajax) {
                Ajax.call([{
                    methodname: 'block_adeptus_insights_send_alert_notification',
                    args: {
                        blockinstanceid: self.blockId,
                        alerts: alertsToSend
                    }
                }])[0].done(function(response) {
                    if (response.success && response.sent_count > 0) {
                        // Trigger notification area refresh so bell icon updates.
                        // Small delay to ensure notification is fully committed to database.
                        setTimeout(function() {
                            self.refreshNotificationArea();
                        }, 500);
                    }
                }).fail(function() {
                    // Silent fail - notifications will appear on next poll.
                });
            });
        },

        /**
         * Refresh the Moodle notification area to show new notifications.
         * Calls the Moodle API to get the unread count and updates the badge.
         */
        refreshNotificationArea: function() {
            require(['jquery', 'core/ajax'], function($, Ajax) {
                try {
                    // Find the notification popover container and get user ID.
                    var $popover = $('#nav-notification-popover-container');
                    if (!$popover.length) {
                        return;
                    }

                    var userId = $popover.attr('data-userid');
                    if (!userId) {
                        return;
                    }

                    // Call Moodle API to get unread notification count.
                    Ajax.call([{
                        methodname: 'message_popup_get_unread_popup_notification_count',
                        args: {
                            useridto: parseInt(userId, 10)
                        }
                    }])[0].done(function(count) {
                        // Update the notification badge in the DOM.
                        var $countContainer = $popover.find('[data-region="count-container"]');
                        if ($countContainer.length) {
                            if (count > 0) {
                                $countContainer.text(count);
                                $countContainer.removeClass('hidden');
                            } else {
                                $countContainer.addClass('hidden');
                            }
                        }
                    }).fail(function() {
                        // Silent fail - notifications will appear on next page load.
                    });
                } catch (e) {
                    // Silent fail.
                }
            });
        },

        /**
         * Load KPI history from server for sparkline (legacy - kept for backward compatibility).
         *
         * @param {jQuery} $card
         * @param {string} slug
         * @param {number} currentValue
         * @deprecated Use postSnapshotToBackend instead which returns history in response
         */
        loadKpiSparklineFromServer: function($card, slug, currentValue) {
            // This is now handled by postSnapshotToBackend which returns history in response
            // Keeping this method for any legacy code paths
            if (!this.config.snapshotsEnabled) {
                $card.find('.kpi-card-sparkline').addClass('d-none');
                return;
            }
            // Sparkline is now loaded from snapshot response, no separate call needed
        },

        /**
         * Update KPI trend indicator from server response.
         *
         * @param {jQuery} $card
         * @param {string} direction Trend direction (up/down/neutral)
         * @param {number} percentage Trend percentage
         */
        updateKpiTrendFromServer: function($card, direction, percentage) {
            var trendContainer = $card.find('.kpi-card-trend');
            trendContainer.removeClass('d-none trend-up trend-down trend-neutral');

            var absChange = Math.abs(percentage);
            var changeText;
            if (absChange >= 100) {
                changeText = Math.round(absChange) + '%';
            } else if (absChange >= 10) {
                changeText = absChange.toFixed(0) + '%';
            } else {
                changeText = absChange.toFixed(1) + '%';
            }

            if (direction === 'up') {
                trendContainer.addClass('trend-up');
                trendContainer.find('.trend-icon').html('<i class="fa fa-arrow-up"></i>');
                trendContainer.find('.trend-value').text(changeText + ' vs previous');
            } else if (direction === 'down') {
                trendContainer.addClass('trend-down');
                trendContainer.find('.trend-icon').html('<i class="fa fa-arrow-down"></i>');
                trendContainer.find('.trend-value').text(changeText + ' vs previous');
            } else {
                trendContainer.addClass('trend-neutral');
                trendContainer.find('.trend-icon').html('<i class="fa fa-minus"></i>');
                trendContainer.find('.trend-value').text('No change');
            }
        },

        /**
         * Legacy method for backwards compatibility.
         *
         * @param {jQuery} $card
         * @param {string} slug
         * @param {number} currentValue
         */
        updateKpiTrend: function($card, slug, currentValue) {
            // Show neutral trend initially, server call will update
            var trendContainer = $card.find('.kpi-card-trend');
            trendContainer.removeClass('d-none');
            trendContainer.addClass('trend-neutral');
            trendContainer.find('.trend-icon').html('<i class="fa fa-spinner fa-spin"></i>');
            trendContainer.find('.trend-value').text('Loading...');
        },

        /**
         * Render sparkline chart from server data.
         *
         * @param {jQuery} $card
         * @param {string} slug
         * @param {Array} sparklineData Array of values
         * @param {number} currentValue Current value to append
         */
        renderKpiSparklineFromData: function($card, slug, sparklineData, currentValue) {
            var sparklineContainer = $card.find('.kpi-card-sparkline');
            var canvas = sparklineContainer.find('.sparkline-chart')[0];

            if (!canvas) {
                return;
            }

            // Build data points array
            var dataPoints = sparklineData.slice();
            // Add current value if different from last
            if (dataPoints.length === 0 || dataPoints[dataPoints.length - 1] !== currentValue) {
                dataPoints.push(currentValue);
            }

            // Need at least 2 points for a meaningful sparkline
            if (dataPoints.length < 2) {
                sparklineContainer.addClass('d-none');
                return;
            }

            sparklineContainer.removeClass('d-none');

            // Determine sparkline color based on overall trend
            var firstValue = dataPoints[0];
            var lastValue = dataPoints[dataPoints.length - 1];
            var trendColor = lastValue >= firstValue ? 'rgba(40, 167, 69, 0.8)' : 'rgba(220, 53, 69, 0.8)';
            var trendBgColor = lastValue >= firstValue ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)';

            // Destroy existing chart if any
            var chartKey = 'sparkline_' + this.blockId + '_' + slug;
            if (this.kpiSparklineCharts && this.kpiSparklineCharts[chartKey]) {
                this.kpiSparklineCharts[chartKey].destroy();
            }

            // Initialize chart storage
            if (!this.kpiSparklineCharts) {
                this.kpiSparklineCharts = {};
            }

            // Create sparkline chart
            try {
                this.kpiSparklineCharts[chartKey] = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: dataPoints.map(function() { return ''; }),
                        datasets: [{
                            data: dataPoints,
                            borderColor: trendColor,
                            backgroundColor: trendBgColor,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0,
                            pointHoverRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false }
                        },
                        scales: {
                            x: { display: false },
                            y: { display: false }
                        },
                        elements: {
                            line: { borderCapStyle: 'round' }
                        },
                        animation: { duration: 500 }
                    }
                });
            } catch (e) {
                // Chart.js not available or error
                sparklineContainer.addClass('d-none');
            }
        },

        /**
         * Legacy method for backwards compatibility.
         *
         * @param {jQuery} $card
         * @param {string} slug
         * @param {number} currentValue
         */
        renderKpiSparkline: function($card, slug, currentValue) {
            // Sparkline is loaded via loadKpiSparklineFromServer
            // This is a no-op for backwards compatibility
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
                    url: '' + this.backendUrl + '/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    if (response.success) {
                        var reportData = response.report || report;
                        var data = response.data || [];

                        // Check if local execution is required (SaaS model)
                        var sqlQuery = response.sql || (reportData && reportData.sql_query) || (reportData && reportData.sql);
                        var needsLocalExecution = response.execution_required || ((!data || data.length === 0) && sqlQuery);

                        if (needsLocalExecution && sqlQuery) {
                            // Execute SQL locally against Moodle database
                            self.executeReportLocally(sqlQuery, reportData.params || {})
                                .then(function(executionResult) {
                                    var localData = executionResult.data || [];
                                    self.reportDataCache[cacheKey] = {
                                        report: reportData,
                                        results: localData
                                    };
                                    self.renderTabContent(pane, reportData, localData);
                                })
                                .catch(function(error) {
                                    pane.find('.tab-pane-loading').addClass('d-none');
                                    pane.find('.tab-pane-content')
                                        .removeClass('d-none')
                                        .html('<div class="text-center text-muted py-4"><i class="fa fa-exclamation-circle"></i><p class="mt-2">Failed to execute report</p></div>');
                                });
                            return;
                        }

                        self.reportDataCache[cacheKey] = {
                            report: reportData,
                            results: data
                        };
                        self.renderTabContent(pane, reportData, data);
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
         * Render tab content with report data - consistent with embedded/modal design.
         *
         * @param {jQuery} pane
         * @param {Object} report
         * @param {Array} data
         */
        renderTabContent: function(pane, report, data) {
            var tabId = pane.attr('id');
            pane.find('.tab-pane-loading').addClass('d-none');
            var contentArea = pane.find('.tab-pane-content');

            if (!data || data.length === 0) {
                contentArea.removeClass('d-none')
                    .html('<div class="text-center text-muted py-4"><i class="fa fa-inbox"></i><p class="mt-2">No data available</p></div>');
                return;
            }

            // Detect headers and numeric columns
            var headers = Object.keys(data[0]);
            var numericCols = this.detectNumericColumns(data, headers);

            // Initialize state for this tab pane
            var state = {
                data: data,
                report: report,
                headers: headers,
                numericCols: numericCols,
                currentView: 'table',
                tablePage: 1,
                rowsPerPage: 25,
                chartType: 'bar',
                xAxis: headers[0],
                yAxis: numericCols.length > 0 ? numericCols[numericCols.length - 1] : headers[headers.length - 1]
            };
            this.tabPaneStates[tabId] = state;

            // Show content area
            contentArea.removeClass('d-none');

            // Update meta bar
            this.updateTabMetaBar(pane, report, data);

            // Populate chart axis selectors
            this.populateTabChartControls(pane, state);

            // Render table (default view)
            this.renderTabTablePage(pane, state);

            // Reset view toggle to table
            pane.find('.tab-view-toggle-btn').removeClass('active');
            pane.find('.tab-view-toggle-btn[data-view="table"]').addClass('active');
            pane.find('.tab-table-view').removeClass('d-none');
            pane.find('.tab-chart-view').addClass('d-none');
        },

        /**
         * Update tab meta bar with report info.
         *
         * @param {jQuery} pane
         * @param {Object} report
         * @param {Array} data
         */
        updateTabMetaBar: function(pane, report, data) {
            // Category badge
            var category = report.category || report.category_name || '';
            var categoryBadge = pane.find('.report-category');
            if (category) {
                categoryBadge.text(category).removeClass('d-none');
                // Add category color if available
                if (report.category_color) {
                    categoryBadge.css('background-color', report.category_color);
                } else {
                    categoryBadge.addClass('bg-primary');
                }
            } else {
                categoryBadge.addClass('d-none');
            }

            // Row count
            pane.find('.row-count-num').text(data.length);

            // Report date
            var dateStr = report.updated_at || report.created_at || '';
            if (dateStr) {
                var date = new Date(dateStr);
                pane.find('.report-date').text(date.toLocaleDateString());
            }
        },

        /**
         * Populate chart control selectors for a tab.
         *
         * @param {jQuery} pane
         * @param {Object} state
         */
        populateTabChartControls: function(pane, state) {
            var xAxisSelect = pane.find('.tab-chart-x-axis');
            var yAxisSelect = pane.find('.tab-chart-y-axis');

            xAxisSelect.empty();
            yAxisSelect.empty();

            state.headers.forEach(function(header) {
                var formatted = header.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                xAxisSelect.append($('<option>').val(header).text(formatted));
                yAxisSelect.append($('<option>').val(header).text(formatted));
            });

            // Set default selections
            xAxisSelect.val(state.xAxis);
            yAxisSelect.val(state.yAxis);
        },

        /**
         * Switch view in a tab pane (table/chart).
         *
         * @param {jQuery} pane
         * @param {string} view - 'table' or 'chart'
         */
        switchTabView: function(pane, view) {
            var tabId = pane.attr('id');
            var state = this.tabPaneStates[tabId];
            if (!state) return;

            state.currentView = view;

            // Update toggle buttons
            pane.find('.tab-view-toggle-btn').removeClass('active');
            pane.find('.tab-view-toggle-btn[data-view="' + view + '"]').addClass('active');

            // Show/hide views
            if (view === 'table') {
                pane.find('.tab-table-view').removeClass('d-none');
                pane.find('.tab-chart-view').addClass('d-none');
            } else {
                pane.find('.tab-table-view').addClass('d-none');
                pane.find('.tab-chart-view').removeClass('d-none');
                // Render chart if not already rendered
                this.renderTabChart(pane, state);
            }
        },

        /**
         * Render table page in tab pane with pagination.
         *
         * @param {jQuery} pane
         * @param {Object} state
         */
        renderTabTablePage: function(pane, state) {
            var table = pane.find('.tab-table');
            var thead = table.find('thead');
            var tbody = table.find('tbody');
            var data = state.data;
            var headers = state.headers;

            thead.empty();
            tbody.empty();

            // Build header row
            var headerRow = $('<tr>');
            headers.forEach(function(h) {
                var formatted = h.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                headerRow.append($('<th>').text(formatted));
            });
            thead.append(headerRow);

            // Calculate pagination
            var totalRows = data.length;
            var totalPages = Math.ceil(totalRows / state.rowsPerPage);
            var startIndex = (state.tablePage - 1) * state.rowsPerPage;
            var endIndex = Math.min(startIndex + state.rowsPerPage, totalRows);
            var pageData = data.slice(startIndex, endIndex);

            // Render rows
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
            var showingText = 'Showing ' + (startIndex + 1) + '-' + endIndex + ' of ' + totalRows;
            pane.find('.row-count').text(showingText);
            pane.find('.tab-pagination-info').text('Page ' + state.tablePage + ' of ' + totalPages);

            // Update pagination buttons
            pane.find('.tab-pagination-prev').prop('disabled', state.tablePage <= 1);
            pane.find('.tab-pagination-next').prop('disabled', state.tablePage >= totalPages);

            // Show/hide pagination
            if (totalPages > 1) {
                pane.find('.tab-table-pagination').removeClass('d-none');
            } else {
                pane.find('.tab-table-pagination').addClass('d-none');
            }
        },

        /**
         * Render chart in tab pane with current settings.
         *
         * @param {jQuery} pane
         * @param {Object} state
         */
        renderTabChart: function(pane, state) {
            var tabId = pane.attr('id');
            var canvas = pane.find('.tab-chart')[0];

            if (!canvas || !state.data || state.data.length === 0) {
                return;
            }

            // Destroy existing chart for this tab
            if (this.tabChartInstances && this.tabChartInstances[tabId]) {
                this.tabChartInstances[tabId].destroy();
            }

            var chartType = state.chartType;
            var xAxis = state.xAxis;
            var yAxis = state.yAxis;
            var data = state.data;

            // Limit data for chart
            var chartData = data.slice(0, 20);
            var labels = chartData.map(function(row) {
                var label = row[xAxis];
                if (label === null || label === undefined) return 'Unknown';
                var labelStr = String(label);
                return labelStr.length > 20 ? labelStr.substring(0, 20) + '...' : labelStr;
            });
            var values = chartData.map(function(row) {
                return parseFloat(row[yAxis]) || 0;
            });

            var colors = this.generateChartColors(values.length, chartType);
            var yAxisFormatted = yAxis.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });

            var config = {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: yAxisFormatted,
                        data: values,
                        backgroundColor: chartType === 'pie' || chartType === 'doughnut' ? colors : colors[0],
                        borderColor: chartType === 'line' ? colors[0] : (chartType === 'pie' || chartType === 'doughnut' ? '#fff' : colors[0]),
                        borderWidth: chartType === 'pie' || chartType === 'doughnut' ? 2 : 1,
                        fill: chartType === 'line' ? false : undefined,
                        tension: chartType === 'line' ? 0.1 : undefined
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: chartType === 'pie' || chartType === 'doughnut',
                            position: 'right'
                        }
                    },
                    scales: chartType === 'pie' || chartType === 'doughnut' ? {} : {
                        y: { beginAtZero: true },
                        x: { display: data.length <= 10 }
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
         * Export report data from a tab.
         *
         * @param {Object} state - Tab pane state
         * @param {string} format - Export format (csv)
         */
        exportTabReport: function(state, format) {
            if (!state || !state.data || state.data.length === 0) {
                return;
            }

            if (format === 'csv') {
                var headers = state.headers;
                var csvRows = [];

                // Header row
                csvRows.push(headers.map(function(h) {
                    return '"' + h.replace(/"/g, '""') + '"';
                }).join(','));

                // Data rows
                state.data.forEach(function(row) {
                    var rowData = headers.map(function(h) {
                        var val = row[h];
                        if (val === null || val === undefined) val = '';
                        return '"' + String(val).replace(/"/g, '""') + '"';
                    });
                    csvRows.push(rowData.join(','));
                });

                var csvContent = csvRows.join('\n');
                var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                var reportName = state.report.name || state.report.slug || 'report';
                link.href = url;
                link.download = reportName.replace(/[^a-z0-9]/gi, '_') + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }
        },

        /**
         * Render table in tab pane (legacy fallback).
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
                    url: '' + this.backendUrl + '/ai-reports/' + slug,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    timeout: 15000
                }).done(function(response) {
                    if (response.success && response.report) {
                        var reportData = response.report;
                        var data = response.data || [];

                        // Check if local execution is required (SaaS model)
                        var sqlQuery = response.sql || (reportData && reportData.sql_query) || (reportData && reportData.sql);
                        var needsLocalExecution = response.execution_required || ((!data || data.length === 0) && sqlQuery);

                        if (needsLocalExecution && sqlQuery) {
                            // Execute SQL locally against Moodle database
                            self.executeReportLocally(sqlQuery, reportData.params || {})
                                .then(function(executionResult) {
                                    modalBody.find('.modal-loading').addClass('d-none');
                                    var localData = executionResult.data || [];
                                    self.reportDataCache[cacheKey] = {
                                        report: reportData,
                                        results: localData,
                                        chartData: null,
                                        chartType: null
                                    };
                                    self.renderModalContent(modalBody, reportData, localData, null, null);
                                })
                                .catch(function(error) {
                                    modalBody.find('.modal-loading').addClass('d-none');
                                    modalBody.find('.modal-error').removeClass('d-none');
                                });
                            return;
                        }

                        modalBody.find('.modal-loading').addClass('d-none');
                        // Cache the result for future use
                        self.reportDataCache[cacheKey] = {
                            report: reportData,
                            results: data,
                            chartData: null,
                            chartType: null
                        };
                        self.renderModalContent(modalBody, reportData, data, null, null);
                    } else {
                        modalBody.find('.modal-loading').addClass('d-none');
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
         * Execute AI report SQL locally against Moodle database.
         * Used when backend returns SQL query but no data (SaaS model).
         *
         * @param {string} sql - The SQL query to execute
         * @param {Object} params - Query parameters
         * @return {Promise}
         */
        executeReportLocally: function(sql, params) {
            params = params || {};

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/execute_ai_report.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        sql: sql,
                        params: params,
                        sesskey: M.cfg.sesskey
                    }),
                    timeout: 60000,
                    success: function(response) {
                        if (response.success) {
                            resolve({
                                data: response.data || [],
                                headers: response.headers || [],
                                row_count: response.row_count || 0,
                                error: null
                            });
                        } else {
                            resolve({
                                data: [],
                                headers: [],
                                row_count: 0,
                                error: response.message || 'Query execution failed'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(new Error('Failed to execute report: ' + error));
                    }
                });
            });
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
            if (!this.lastUpdated) {
                return;
            }

            var text = this.formatTimeAgo(this.lastUpdated);

            // Handle KPI mode footer (contains both timestamp and refresh)
            var kpiFooter = this.container.find('.block-adeptus-kpi-footer');
            if (kpiFooter.length) {
                kpiFooter.find('.timestamp-text').text(text);
                kpiFooter.removeClass('d-none');
                return;
            }

            // Handle standard timestamp element
            if (this.config.showTimestamp) {
                var timestampEl = this.container.find('.block-adeptus-timestamp');
                timestampEl.find('.timestamp-text').text(text);
                timestampEl.removeClass('d-none');
            }
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
