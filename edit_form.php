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
 * Block instance configuration form for Adeptus Insights.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing Adeptus Insights block instances.
 */
class block_adeptus_insights_edit_form extends block_edit_form {
    /**
     * Define the form fields.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG, $DB;

        // General settings section.
        $mform->addElement('header', 'config_header_general', get_string('settings_general', 'block_adeptus_insights'));

        // Block title.
        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_adeptus_insights'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->addHelpButton('config_title', 'configtitle', 'block_adeptus_insights');

        // Display mode.
        $displaymodes = [
            'embedded' => get_string('displaymode_embedded', 'block_adeptus_insights'),
            'kpi' => get_string('displaymode_kpi', 'block_adeptus_insights'),
            'links' => get_string('displaymode_links', 'block_adeptus_insights'),
            'tabs' => get_string('displaymode_tabs', 'block_adeptus_insights'),
        ];
        $mform->addElement(
            'select',
            'config_display_mode',
            get_string('configdisplaymode', 'block_adeptus_insights'),
            $displaymodes
        );
        $mform->setDefault('config_display_mode', 'links');
        $mform->addHelpButton('config_display_mode', 'configdisplaymode', 'block_adeptus_insights');

        // Report category filter - filters reports shown in this block.
        // Only applicable to Report Links and Embedded modes (KPI and Tabs have their own report selectors).
        $reportcategories = $this->get_report_category_options();
        $mform->addElement(
            'select',
            'config_report_category',
            get_string('configselectedcategory', 'block_adeptus_insights'),
            $reportcategories
        );
        $mform->setDefault('config_report_category', '');
        $mform->addHelpButton('config_report_category', 'configselectedcategory', 'block_adeptus_insights');
        $mform->hideIf('config_report_category', 'config_display_mode', 'eq', 'kpi');
        $mform->hideIf('config_report_category', 'config_display_mode', 'eq', 'tabs');

        // Display options section.
        $mform->addElement('header', 'config_header_display', get_string('settings_display', 'block_adeptus_insights'));

        // Chart height.
        $chartheights = [];
        for ($h = 150; $h <= 400; $h += 50) {
            $chartheights[$h] = $h . 'px';
        }
        $mform->addElement(
            'select',
            'config_chart_height',
            get_string('configchartheight', 'block_adeptus_insights'),
            $chartheights
        );
        $mform->setDefault('config_chart_height', 250);

        // Show category badges (links mode only).
        $mform->addElement(
            'advcheckbox',
            'config_show_category_badges',
            get_string('configshowcategorybadges', 'block_adeptus_insights')
        );
        $mform->setDefault('config_show_category_badges', 1);
        $mform->hideIf('config_show_category_badges', 'config_display_mode', 'neq', 'links');

        // KPI columns (for KPI mode).
        $kpicolumns = [1 => '1', 2 => '2', 3 => '3', 4 => '4'];
        $mform->addElement('select', 'config_kpi_columns', get_string('configkpicolumns', 'block_adeptus_insights'), $kpicolumns);
        $mform->setDefault('config_kpi_columns', 2);
        $mform->hideIf('config_kpi_columns', 'config_display_mode', 'neq', 'kpi');

        // KPI report selection (for KPI mode).
        $mform->addElement(
            'static',
            'config_kpi_reports_label',
            '',
            '<div class="alert alert-info small">' .
            '<i class="fa fa-info-circle"></i> ' .
            get_string('configkpireports_desc', 'block_adeptus_insights') .
            '</div>'
        );
        $mform->hideIf('config_kpi_reports_label', 'config_display_mode', 'neq', 'kpi');

        // KPI selected reports (stored as JSON - hidden, used by JS).
        $mform->addElement(
            'textarea',
            'config_kpi_selected_reports',
            get_string('configkpireports', 'block_adeptus_insights'),
            ['rows' => 4, 'class' => 'block-adeptus-kpi-reports-selector d-none']
        );
        $mform->setType('config_kpi_selected_reports', PARAM_RAW);
        $mform->hideIf('config_kpi_selected_reports', 'config_display_mode', 'neq', 'kpi');

        // KPI report selector UI container (rendered via JavaScript).
        $mform->addElement(
            'static',
            'kpi_report_selector_container',
            '',
            '<div id="block-adeptus-kpi-report-selector-container" ' .
                'class="block-adeptus-kpi-report-selector-ui mb-3">' .
                '<div class="block-adeptus-kpi-selector-loading text-center py-3">' .
                '<i class="fa fa-spinner fa-spin"></i> ' .
                get_string('loadingreportselector', 'block_adeptus_insights') . '</div></div>'
        );
        $mform->hideIf('kpi_report_selector_container', 'config_display_mode', 'neq', 'kpi');

        // Tabs report selection (for tabs mode).
        $mform->addElement(
            'static',
            'config_tabs_reports_label',
            '',
            '<div class="alert alert-info small">' .
            '<i class="fa fa-info-circle"></i> ' .
            get_string('configtabsreports_desc', 'block_adeptus_insights') .
            '</div>'
        );
        $mform->hideIf('config_tabs_reports_label', 'config_display_mode', 'neq', 'tabs');

        // Tabs selected reports (stored as JSON - hidden, used by JS).
        $mform->addElement(
            'textarea',
            'config_tabs_selected_reports',
            get_string('configtabsreports', 'block_adeptus_insights'),
            ['rows' => 4, 'class' => 'block-adeptus-tabs-reports-selector d-none']
        );
        $mform->setType('config_tabs_selected_reports', PARAM_RAW);
        $mform->hideIf('config_tabs_selected_reports', 'config_display_mode', 'neq', 'tabs');

        // Tabs report selector UI container (rendered via JavaScript).
        $mform->addElement(
            'static',
            'tabs_report_selector_container',
            '',
            '<div id="block-adeptus-tabs-report-selector-container" ' .
                'class="block-adeptus-tabs-report-selector-ui mb-3">' .
                '<div class="block-adeptus-tabs-selector-loading text-center py-3">' .
                '<i class="fa fa-spinner fa-spin"></i> ' .
                get_string('loadingreportselector', 'block_adeptus_insights') . '</div></div>'
        );
        $mform->hideIf('tabs_report_selector_container', 'config_display_mode', 'neq', 'tabs');

        // Max link items (for links mode).
        $maxitems = [5 => '5', 10 => '10', 15 => '15', 20 => '20'];
        $mform->addElement(
            'select',
            'config_max_link_items',
            get_string('configmaxlinkitems', 'block_adeptus_insights'),
            $maxitems
        );
        $mform->setDefault('config_max_link_items', 10);
        $mform->hideIf('config_max_link_items', 'config_display_mode', 'neq', 'links');

        // Data refresh settings section.
        $mform->addElement('header', 'config_header_behavior', get_string('settings_datarefresh', 'block_adeptus_insights'));

        // Auto-refresh.
        $refreshintervals = [
            'never' => get_string('autorefresh_never', 'block_adeptus_insights'),
            '5m' => get_string('autorefresh_5m', 'block_adeptus_insights'),
            '15m' => get_string('autorefresh_15m', 'block_adeptus_insights'),
            '30m' => get_string('autorefresh_30m', 'block_adeptus_insights'),
            '1h' => get_string('autorefresh_1h', 'block_adeptus_insights'),
        ];
        $mform->addElement(
            'select',
            'config_auto_refresh',
            get_string('configautorefresh', 'block_adeptus_insights'),
            $refreshintervals
        );
        $mform->setDefault('config_auto_refresh', 'never');

        // Show refresh button.
        $mform->addElement(
            'advcheckbox',
            'config_show_refresh_button',
            get_string('configshowrefreshbutton', 'block_adeptus_insights')
        );
        $mform->setDefault('config_show_refresh_button', 1);

        // Show timestamp.
        $mform->addElement(
            'advcheckbox',
            'config_show_timestamp',
            get_string('configshowtimestamp', 'block_adeptus_insights')
        );
        $mform->setDefault('config_show_timestamp', 1);

        // Context filtering section.
        $mform->addElement('header', 'config_header_context', get_string('configcontextfilter', 'block_adeptus_insights'));

        // Context filter mode.
        $contextmodes = [
            'auto' => get_string('contextfilter_auto', 'block_adeptus_insights'),
            'course' => get_string('configcontextcourse', 'block_adeptus_insights'),
            'category' => get_string('configcontextcategory', 'block_adeptus_insights'),
            'none' => get_string('contextfilter_none', 'block_adeptus_insights'),
        ];
        $mform->addElement(
            'select',
            'config_context_filter',
            get_string('configcontextfilter', 'block_adeptus_insights'),
            $contextmodes
        );
        $mform->setDefault('config_context_filter', 'auto');
        $mform->addHelpButton('config_context_filter', 'configcontextfilter', 'block_adeptus_insights');

        // Course selector (for manual course context).
        $courses = $this->get_course_options();
        $mform->addElement(
            'autocomplete',
            'config_context_course',
            get_string('configcontextcourse', 'block_adeptus_insights'),
            $courses
        );
        $mform->hideIf('config_context_course', 'config_context_filter', 'neq', 'course');

        // Category selector (for manual category context).
        $categories = $this->get_category_options();
        $mform->addElement(
            'autocomplete',
            'config_context_category',
            get_string('configcontextcategory', 'block_adeptus_insights'),
            $categories
        );
        $mform->hideIf('config_context_category', 'config_context_filter', 'neq', 'category');

        // Alert configuration section (for KPI mode).
        $mform->addElement('header', 'config_header_alerts', get_string('config_header_alerts', 'block_adeptus_insights'));
        $mform->addHelpButton('config_header_alerts', 'config_header_alerts', 'block_adeptus_insights');

        // Notice shown when non-KPI display mode is selected.
        $mform->addElement(
            'static',
            'alerts_kpi_only_notice',
            '',
            '<div id="block-adeptus-alerts-kpi-only-notice" class="alert alert-info">' .
            '<i class="fa fa-info-circle mr-2"></i>' .
            get_string('alerts_kpi_only_notice', 'block_adeptus_insights') .
            '</div>'
        );

        // Snapshot frequency - controls how often KPI metrics are captured for trend analysis.
        // Available for all tiers (snapshots enable trend indicators and sparklines).
        $snapshotintervals = [
            3600 => get_string('kpi_interval_1h', 'block_adeptus_insights'),
            21600 => get_string('kpi_interval_6h', 'block_adeptus_insights'),
            43200 => get_string('kpi_interval_12h', 'block_adeptus_insights'),
            86400 => get_string('kpi_interval_1d', 'block_adeptus_insights'),
            259200 => get_string('kpi_interval_3d', 'block_adeptus_insights'),
            604800 => get_string('kpi_interval_1w', 'block_adeptus_insights'),
            2592000 => get_string('kpi_interval_1m', 'block_adeptus_insights'),
        ];
        $mform->addElement(
            'select',
            'config_kpi_history_interval',
            get_string('configkpihistoryinterval', 'block_adeptus_insights'),
            $snapshotintervals
        );
        $mform->setDefault('config_kpi_history_interval', 3600);
        $mform->addHelpButton('config_kpi_history_interval', 'configkpihistoryinterval', 'block_adeptus_insights');
        $mform->hideIf('config_kpi_history_interval', 'config_display_mode', 'neq', 'kpi');

        // Baseline period - controls the reference point for overall trend calculation.
        $baselineperiods = [
            'all_time' => get_string('baseline_all_time', 'block_adeptus_insights'),
            'month_start' => get_string('baseline_month_start', 'block_adeptus_insights'),
            'week_start' => get_string('baseline_week_start', 'block_adeptus_insights'),
            'rolling_30d' => get_string('baseline_rolling_30d', 'block_adeptus_insights'),
            'rolling_7d' => get_string('baseline_rolling_7d', 'block_adeptus_insights'),
        ];
        $mform->addElement(
            'select',
            'config_baseline_period',
            get_string('configbaselineperiod', 'block_adeptus_insights'),
            $baselineperiods
        );
        $mform->setDefault('config_baseline_period', 'all_time');
        $mform->addHelpButton('config_baseline_period', 'configbaselineperiod', 'block_adeptus_insights');
        $mform->hideIf('config_baseline_period', 'config_display_mode', 'neq', 'kpi');

        // Check if alerts feature is enabled for this subscription (backend permission).
        $alertspermission = $this->check_alerts_permission();

        if (!$alertspermission) {
            // Show upgrade prompt - alerts feature not available for this subscription tier.
            $upgradeurl = new \moodle_url('/report/adeptus_insights/subscription.php');
            $mform->addElement(
                'static',
                'alerts_upgrade_prompt',
                '',
                '<div class="alert alert-warning">' .
                '<i class="fa fa-lock mr-2"></i>' .
                '<strong>' . get_string('feature_locked', 'block_adeptus_insights') . '</strong><br>' .
                get_string('alerts_upgrade_required', 'block_adeptus_insights') .
                '<br><br>' .
                '<a href="' . $upgradeurl->out() . '" class="btn btn-primary btn-sm" ' .
                    'style="color: #fff; background-color: #0f6cbf; border-color: #0f6cbf;">' .
                '<i class="fa fa-arrow-up mr-1"></i>' .
                get_string('upgrade_to_unlock', 'block_adeptus_insights') .
                '</a>' .
                '</div>'
            );
            $mform->hideIf('alerts_upgrade_prompt', 'config_display_mode', 'neq', 'kpi');

            // Add hidden fields with default values for form processing.
            $mform->addElement('hidden', 'config_alerts_enabled', 0);
            $mform->setType('config_alerts_enabled', PARAM_INT);
            $mform->addElement('hidden', 'config_alerts_json', '[]');
            $mform->setType('config_alerts_json', PARAM_RAW);

            return;
        }

        // Proactive monitoring concept explanation.
        $mform->addElement(
            'static',
            'alert_info',
            '',
            '<div class="alert alert-info">' .
            '<i class="fa fa-bell mr-2"></i>' .
            '<strong>' . get_string('insurance_policy_title', 'block_adeptus_insights') . '</strong><br>' .
            get_string('insurance_policy_desc', 'block_adeptus_insights') .
            '</div>'
        );
        $mform->hideIf('alert_info', 'config_display_mode', 'neq', 'kpi');

        // Enable alerts master toggle.
        $mform->addElement(
            'advcheckbox',
            'config_alerts_enabled',
            get_string('config_alerts_enabled', 'block_adeptus_insights')
        );
        $mform->setDefault('config_alerts_enabled', 0);
        $mform->addHelpButton('config_alerts_enabled', 'config_alerts_enabled', 'block_adeptus_insights');
        $mform->hideIf('config_alerts_enabled', 'config_display_mode', 'neq', 'kpi');

        // Alert configurations (stored as JSON array).
        $mform->addElement(
            'textarea',
            'config_alerts_json',
            '',
            ['rows' => 2, 'class' => 'd-none', 'id' => 'id_config_alerts_json']
        );
        $mform->setType('config_alerts_json', PARAM_RAW);
        $mform->setDefault('config_alerts_json', '[]');

        // Get existing alerts for this block (if editing).
        $existingalerts = $this->get_existing_alerts();
        $existingalertsjson = json_encode($existingalerts);

        // Build operator and interval options for JavaScript.
        $operators = $this->get_alert_operators();
        $checkintervals = $this->get_alert_check_intervals();
        $cooldowns = $this->get_cooldown_options();
        $roles = $this->get_alert_roles();

        // Multi-alert configuration UI container.
        $mform->addElement(
            'html',
            '<div id="alerts-manager-container" class="alerts-manager-ui mb-3" style="display:none;"
                  data-existing-alerts="' . htmlspecialchars($existingalertsjson, ENT_QUOTES, 'UTF-8') . '"
                  data-operators="' . htmlspecialchars(json_encode($operators), ENT_QUOTES, 'UTF-8') . '"
                  data-intervals="' . htmlspecialchars(json_encode($checkintervals), ENT_QUOTES, 'UTF-8') . '"
                  data-cooldowns="' . htmlspecialchars(json_encode($cooldowns), ENT_QUOTES, 'UTF-8') . '"
                  data-roles="' . htmlspecialchars(json_encode($roles), ENT_QUOTES, 'UTF-8') . '">

                <div class="alerts-list-header d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="font-weight-bold">' . get_string('config_alerts_list', 'block_adeptus_insights') . '</span>
                        <span class="badge badge-secondary alerts-count ml-2">0</span>
                    </div>
                    <button type="button" id="add-alert-btn" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-plus mr-1"></i> ' . get_string('config_add_alert', 'block_adeptus_insights') . '
                    </button>
                </div>

                <div id="alerts-list" class="alerts-list mb-3">
                    <div class="alerts-empty text-muted text-center py-4">
                        <i class="fa fa-bell-slash fa-2x mb-2"></i><br>
                        ' . get_string('no_alerts_configured', 'block_adeptus_insights') . '
                    </div>
                </div>

                <!-- Alert edit modal/panel template -->
                <div id="alert-edit-panel" class="alert-edit-panel card mb-3"
                     style="display:none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold alert-panel-title">' .
                            get_string('config_add_alert', 'block_adeptus_insights') . '</span>
                        <button type="button" class="close alert-panel-close" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="alert-edit-report">' .
                                get_string('config_alert_report', 'block_adeptus_insights') .
                                ' <span class="text-danger">*</span></label>
                            <input type="text" id="alert-edit-report-search"
                                   class="form-control"
                                   placeholder="' .
                                get_string('config_alert_report_placeholder', 'block_adeptus_insights') . '">
                            <div id="alert-report-dropdown"
                                 class="alert-report-dropdown position-absolute bg-white border rounded shadow-sm"
                                 style="display:none; z-index:1050; max-height:250px; overflow-y:auto;">
                            </div>
                            <input type="hidden" id="alert-edit-report" value="">
                            <small class="form-text text-muted" id="alert-edit-report-display"></small>
                        </div>

                        <div class="form-group">
                            <label for="alert-edit-name">' .
                                get_string('config_alert_name', 'block_adeptus_insights') . '</label>
                            <input type="text" id="alert-edit-name" class="form-control"
                                placeholder="' .
                                get_string('config_alert_name_placeholder', 'block_adeptus_insights') . '">
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="alert-edit-operator">' .
                                        get_string('config_alert_operator', 'block_adeptus_insights') .
                                        '</label>
                                    <select id="alert-edit-operator" class="form-control"></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="alert-edit-warning">' .
                                        get_string('config_alert_warning_value', 'block_adeptus_insights') .
                                        '</label>
                                    <input type="number" step="any" id="alert-edit-warning" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="alert-edit-critical">' .
                                        get_string('config_alert_critical_value', 'block_adeptus_insights') .
                                        '</label>
                                    <input type="number" step="any" id="alert-edit-critical" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="alert-edit-interval">' .
                                        get_string('config_alert_check_interval', 'block_adeptus_insights') .
                                        '</label>
                                    <select id="alert-edit-interval" class="form-control"></select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="alert-edit-cooldown">' .
                                        get_string('config_alert_cooldown', 'block_adeptus_insights') .
                                        '</label>
                                    <select id="alert-edit-cooldown" class="form-control"></select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>' . get_string('config_alert_notify_on', 'block_adeptus_insights') . '</label>
                            <div class="d-flex flex-wrap">
                                <div class="custom-control custom-checkbox mr-3">
                                    <input type="checkbox" class="custom-control-input"
                                        id="alert-edit-notify-warning" checked>
                                    <label class="custom-control-label" for="alert-edit-notify-warning">' .
                                        get_string('config_alert_notify_warning', 'block_adeptus_insights') . '</label>
                                </div>
                                <div class="custom-control custom-checkbox mr-3">
                                    <input type="checkbox" class="custom-control-input"
                                        id="alert-edit-notify-critical" checked>
                                    <label class="custom-control-label" for="alert-edit-notify-critical">' .
                                        get_string('config_alert_notify_critical', 'block_adeptus_insights') . '</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input"
                                        id="alert-edit-notify-recovery" checked>
                                    <label class="custom-control-label" for="alert-edit-notify-recovery">' .
                                        get_string('config_alert_notify_recovery', 'block_adeptus_insights') . '</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="text-muted mb-3"><i class="fa fa-envelope mr-1"></i> ' .
                            get_string('email_notifications_header', 'block_adeptus_insights') . '</h6>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="alert-edit-notify-email">
                                <label class="custom-control-label" for="alert-edit-notify-email">' .
                                    get_string('config_alert_notify_email', 'block_adeptus_insights') . '</label>
                            </div>
                            <small class="form-text text-muted">' .
                                get_string('config_alert_notify_email_desc', 'block_adeptus_insights') . '</small>
                        </div>

                        <div class="form-group" id="email-addresses-group" style="display: none;">
                            <label for="alert-edit-email-addresses">' .
                                get_string('config_alert_notify_email_addresses', 'block_adeptus_insights') . '</label>
                            <textarea id="alert-edit-email-addresses" class="form-control" rows="3"
                                placeholder="' .
                                get_string('config_alert_notify_email_addresses_placeholder', 'block_adeptus_insights') .
                                '"></textarea>
                            <small class="form-text text-muted">' .
                                get_string('config_alert_notify_email_addresses_desc', 'block_adeptus_insights') . '</small>
                        </div>

                        <hr class="my-3">
                        <h6 class="text-muted mb-3"><i class="fa fa-comment mr-1"></i> ' .
                            get_string('moodle_notifications_header', 'block_adeptus_insights') . '</h6>

                        <div class="form-group">
                            <label for="alert-edit-role-filter">' .
                                get_string('config_alert_role_filter', 'block_adeptus_insights') . '</label>
                            <select id="alert-edit-role-filter" class="form-control">
                                <option value="">' .
                                    get_string('config_alert_role_filter_all', 'block_adeptus_insights') .
                                    '</option>
                            </select>
                            <small class="form-text text-muted">' .
                                get_string('config_alert_role_filter_desc', 'block_adeptus_insights') . '</small>
                        </div>

                        <div class="form-group">
                            <label for="alert-edit-notify-users">' .
                                get_string('config_alert_notify_users', 'block_adeptus_insights') . '</label>
                            <div class="alert-users-search-container position-relative mb-2">
                                <input type="text" id="alert-edit-user-search" class="form-control"
                                    placeholder="' .
                                    get_string('config_alert_user_search_placeholder', 'block_adeptus_insights') . '"
                                    autocomplete="off">
                                <div id="alert-user-dropdown" class="position-absolute w-100 bg-white border rounded shadow-sm"
                                    style="display:none; z-index:1050; max-height:250px; overflow-y:auto; top:100%; left:0;">
                                </div>
                            </div>
                            <div id="alert-selected-users" class="selected-users-list mb-2"></div>
                            <input type="hidden" id="alert-edit-notify-users-data" value="[]">
                            <small class="form-text text-muted">' .
                                get_string('config_alert_notify_users_desc', 'block_adeptus_insights') . '</small>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button type="button" class="btn btn-secondary alert-panel-cancel">' . get_string('cancel') . '</button>
                        <button type="button" class="btn btn-primary alert-panel-save">' . get_string('savechanges') . '</button>
                    </div>
                </div>
            </div>'
        );

        $mform->hideIf('alerts-manager-container', 'config_display_mode', 'neq', 'kpi');

        // Load JavaScript for report selector initialization.
        // For modal: JS is pre-loaded by block_adeptus_insights.php when editing mode is on.
        // For full page: We load it here as a fallback since the block may not render.
        $this->init_edit_form_javascript();
    }

    /**
     * Get existing alerts for the current block instance.
     *
     * Alerts are stored in the block's config (config_alerts_json) and synced
     * with the backend API. This method loads alerts from the block config.
     *
     * @return array Array of alert configurations
     */
    private function get_existing_alerts() {
        $blockinstanceid = $this->block->instance->id ?? 0;
        if (empty($blockinstanceid)) {
            return [];
        }

        try {
            // Load alerts from block config (alerts_json field).
            $config = $this->block->config ?? null;
            if (empty($config) || empty($config->alerts_json)) {
                return [];
            }

            $alerts = json_decode($config->alerts_json, true);
            if (!is_array($alerts)) {
                return [];
            }

            // Return the alerts with all their properties intact.
            // The JavaScript UI expects these fields.
            $result = [];
            foreach ($alerts as $alert) {
                $result[] = [
                    'id' => $alert['id'] ?? $alert['backend_id'] ?? 0,
                    'backend_id' => $alert['backend_id'] ?? 0,
                    'report_slug' => $alert['report_slug'] ?? '',
                    'report_name' => $alert['report_name'] ?? self::get_report_display_name($alert['report_slug'] ?? ''),
                    'alert_name' => $alert['alert_name'] ?? $alert['name'] ?? '',
                    'operator' => $alert['operator'] ?? 'gt',
                    'warning_value' => $alert['warning_value'] ?? '',
                    'critical_value' => $alert['critical_value'] ?? '',
                    'check_interval' => $alert['check_interval'] ?? 3600,
                    'cooldown_seconds' => $alert['cooldown_seconds'] ?? 3600,
                    'notify_on_warning' => (bool) ($alert['notify_on_warning'] ?? true),
                    'notify_on_critical' => (bool) ($alert['notify_on_critical'] ?? true),
                    'notify_on_recovery' => (bool) ($alert['notify_on_recovery'] ?? true),
                    'notify_email' => (bool) ($alert['notify_email'] ?? false),
                    'email_addresses' => $alert['email_addresses'] ?? '',
                    'notify_users' => $alert['notify_users'] ?? [],
                    'enabled' => (bool) ($alert['enabled'] ?? true),
                    'current_status' => $alert['current_status'] ?? 'normal',
                ];
            }

            return $result;
        } catch (\Exception $e) {
            debugging('Failed to get existing alerts: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Check if the alerts feature is enabled for the current subscription.
     *
     * This calls the backend API to check permissions at the product-price level.
     * The backend is the single source of truth for feature permissions.
     *
     * @return bool True if alerts are enabled for this subscription
     */
    private function check_alerts_permission() {
        global $CFG;

        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            $installationmanager = new \report_adeptus_insights\installation_manager();

            return $installationmanager->is_feature_enabled('alerts');
        } catch (\Exception $e) {
            debugging('Failed to check alerts permission: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Get course options for the autocomplete.
     *
     * @return array
     */
    private function get_course_options() {
        global $DB;

        $courses = ['' => get_string('selectreport', 'block_adeptus_insights')];

        $records = $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, fullname, shortname');
        foreach ($records as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $courses[$course->id] = format_string($course->fullname) . ' (' . $course->shortname . ')';
        }

        return $courses;
    }

    /**
     * Get category options for the autocomplete.
     *
     * @return array
     */
    private function get_category_options() {
        global $DB;

        $categories = ['' => get_string('selectreport', 'block_adeptus_insights')];

        $records = $DB->get_records('course_categories', ['visible' => 1], 'name ASC', 'id, name');
        foreach ($records as $category) {
            $categories[$category->id] = format_string($category->name);
        }

        return $categories;
    }

    /**
     * Initialize the JavaScript for the edit form.
     * This handles full-page edit scenarios where the block may not render.
     * For modal scenarios, the JS is pre-loaded by the block itself.
     */
    private function init_edit_form_javascript() {
        global $CFG;

        // Get API key from parent plugin.
        $apikey = '';
        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
        } catch (\Exception $e) {
            debugging('Failed to get API key for report selector: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Load the edit_form module. This works for full-page edit scenarios.
        // For modal scenarios, the module may already be loaded by the block,
        // but calling init again is safe as the MutationObserver handles duplicates.
        // Access page through block instance to comply with Moodle coding standards.
        $this->block->page->requires->js_call_amd(
            'block_adeptus_insights/edit_form',
            'init',
            [['apiKey' => $apikey]]
        );
    }

    /**
     * Get report category options from the backend API.
     *
     * @return array
     */
    private function get_report_category_options() {
        global $CFG;

        $categories = ['' => get_string('allcategories', 'block_adeptus_insights')];

        // Try to fetch categories from the parent plugin.
        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $apiurl = \report_adeptus_insights\api_config::get_backend_url();

            if (!empty($apikey) && !empty($apiurl)) {
                $curl = new \curl();
                $curl->setHeader('Content-Type: application/json');
                $curl->setHeader('Accept: application/json');
                $curl->setHeader('Authorization: Bearer ' . $apikey);

                $options = [
                    'CURLOPT_TIMEOUT' => 10,
                    'CURLOPT_SSL_VERIFYPEER' => true,
                ];

                $response = $curl->get($apiurl . '/reports/categories', [], $options);
                $info = $curl->get_info();
                $httpcode = $info['http_code'] ?? 0;

                if ($httpcode === 200 && $response) {
                    $data = json_decode($response, true);
                    if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                        foreach ($data['data'] as $cat) {
                            $categories[$cat['slug']] = $cat['name'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            debugging('Failed to fetch report categories: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $categories;
    }

    /**
     * Get available alert operators.
     *
     * @return array
     */
    private function get_alert_operators() {
        return [
            'gt' => get_string('alert_op_gt', 'block_adeptus_insights'),
            'lt' => get_string('alert_op_lt', 'block_adeptus_insights'),
            'eq' => get_string('alert_op_eq', 'block_adeptus_insights'),
            'gte' => get_string('alert_op_gte', 'block_adeptus_insights'),
            'lte' => get_string('alert_op_lte', 'block_adeptus_insights'),
            'change_pct' => get_string('alert_op_change_pct', 'block_adeptus_insights'),
            'increase_pct' => get_string('alert_op_increase_pct', 'block_adeptus_insights'),
            'decrease_pct' => get_string('alert_op_decrease_pct', 'block_adeptus_insights'),
        ];
    }

    /**
     * Get available check intervals for alerts.
     *
     * @return array
     */
    private function get_alert_check_intervals() {
        return [
            300 => get_string('alert_interval_5m', 'block_adeptus_insights'),
            900 => get_string('alert_interval_15m', 'block_adeptus_insights'),
            1800 => get_string('alert_interval_30m', 'block_adeptus_insights'),
            3600 => get_string('alert_interval_1h', 'block_adeptus_insights'),
            7200 => get_string('alert_interval_2h', 'block_adeptus_insights'),
            14400 => get_string('alert_interval_4h', 'block_adeptus_insights'),
            28800 => get_string('alert_interval_8h', 'block_adeptus_insights'),
            86400 => get_string('alert_interval_24h', 'block_adeptus_insights'),
        ];
    }

    /**
     * Get cooldown options for notifications.
     *
     * @return array
     */
    private function get_cooldown_options() {
        return [
            900 => get_string('cooldown_15m', 'block_adeptus_insights'),
            1800 => get_string('cooldown_30m', 'block_adeptus_insights'),
            3600 => get_string('cooldown_1h', 'block_adeptus_insights'),
            7200 => get_string('cooldown_2h', 'block_adeptus_insights'),
            14400 => get_string('cooldown_4h', 'block_adeptus_insights'),
            28800 => get_string('cooldown_8h', 'block_adeptus_insights'),
            86400 => get_string('cooldown_24h', 'block_adeptus_insights'),
            172800 => get_string('cooldown_48h', 'block_adeptus_insights'),
        ];
    }

    /**
     * Get available KPI reports for alert monitoring.
     *
     * @return array
     */
    private function get_kpi_report_options() {
        global $CFG;

        $reports = ['' => get_string('selectreport', 'block_adeptus_insights')];

        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $apiurl = \report_adeptus_insights\api_config::get_backend_url();

            if (!empty($apikey) && !empty($apiurl)) {
                $curl = new \curl();
                $curl->setHeader('Content-Type: application/json');
                $curl->setHeader('Accept: application/json');
                $curl->setHeader('X-API-Key: ' . $apikey);

                $options = [
                    'CURLOPT_TIMEOUT' => 10,
                    'CURLOPT_SSL_VERIFYPEER' => true,
                ];

                $response = $curl->get($apiurl . '/reports/definitions', [], $options);
                $info = $curl->get_info();
                $httpcode = $info['http_code'] ?? 0;

                if ($httpcode === 200 && $response) {
                    $data = json_decode($response, true);
                    if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                        foreach ($data['data'] as $report) {
                            $name = $report['name'] ?? $report['title'] ?? $report['slug'] ?? 'Unnamed';
                            $slug = $report['slug'] ?? $report['name'] ?? '';
                            if (!empty($slug)) {
                                $reports[$slug] = $name;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            debugging('Failed to fetch KPI reports: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $reports;
    }

    /**
     * Get the display name for a report slug.
     *
     * @param string $slug The report slug
     * @return string The display name or empty string
     */
    public static function get_report_display_name(string $slug): string {
        global $CFG;

        if (empty($slug)) {
            return '';
        }

        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $apiurl = \report_adeptus_insights\api_config::get_backend_url();

            if (empty($apikey) || empty($apiurl)) {
                return $slug;
            }

            // Try wizard reports first.
            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            $curl->setHeader('Authorization: Bearer ' . $apikey);

            $options = [
                'CURLOPT_TIMEOUT' => 5,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ];

            $response = $curl->get($apiurl . '/wizard-reports/' . urlencode($slug), [], $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;

            if ($httpcode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['report']['name'])) {
                    return $data['report']['name'];
                }
                if (isset($data['report']['title'])) {
                    return $data['report']['title'];
                }
            }

            // Try AI reports.
            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            $curl->setHeader('Authorization: Bearer ' . $apikey);

            $response = $curl->get($apiurl . '/ai-reports/' . urlencode($slug), [], $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;

            if ($httpcode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['report']['name'])) {
                    return $data['report']['name'];
                }
                if (isset($data['report']['title'])) {
                    return $data['report']['title'];
                }
            }
        } catch (\Exception $e) {
            debugging('Failed to get report name: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $slug;
    }

    /**
     * Get roles that can receive alerts.
     *
     * @return array
     */
    private function get_alert_roles() {
        global $DB;

        $roles = [];

        // Get roles that typically have management responsibilities.
        $records = $DB->get_records('role', [], 'sortorder ASC', 'id, shortname, name');
        foreach ($records as $role) {
            $rolename = role_get_name($role);
            $roles[$role->id] = $rolename;
        }

        return $roles;
    }

    /**
     * Validate the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate multi-alert configuration (alerts_json is validated via JavaScript).
        // Server-side validation of JSON structure.
        if (!empty($data['config_alerts_enabled']) && $data['config_display_mode'] === 'kpi') {
            if (!empty($data['config_alerts_json'])) {
                $alerts = json_decode($data['config_alerts_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['config_alerts_json'] = 'Invalid alert configuration format';
                }
            }
        }

        return $errors;
    }
}
