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

defined('MOODLE_INTERNAL') || die();

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

        // Initialize the report selector JavaScript.
        $this->init_report_selector_js();

        // =====================================
        // GENERAL SETTINGS
        // =====================================
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
        $mform->addElement('select', 'config_display_mode', get_string('configdisplaymode', 'block_adeptus_insights'), $displaymodes);
        $mform->setDefault('config_display_mode', 'links');
        $mform->addHelpButton('config_display_mode', 'configdisplaymode', 'block_adeptus_insights');

        // =====================================
        // REPORT SOURCE SETTINGS
        // =====================================
        $mform->addElement('header', 'config_header_source', get_string('configreportsource', 'block_adeptus_insights'));

        // Report source.
        $reportsources = [
            'all' => get_string('reportsource_all', 'block_adeptus_insights'),
            'wizard' => get_string('reportsource_wizard', 'block_adeptus_insights'),
            'ai' => get_string('reportsource_ai', 'block_adeptus_insights'),
            'category' => get_string('reportsource_category', 'block_adeptus_insights'),
            'manual' => get_string('reportsource_manual', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_report_source', get_string('configreportsource', 'block_adeptus_insights'), $reportsources);
        $mform->setDefault('config_report_source', 'all');
        $mform->addHelpButton('config_report_source', 'configreportsource', 'block_adeptus_insights');

        // Report category filter (available for all sources except manual).
        $reportcategories = $this->get_report_category_options();
        $mform->addElement('select', 'config_report_category', get_string('configselectedcategory', 'block_adeptus_insights'), $reportcategories);
        $mform->setDefault('config_report_category', '');
        $mform->addHelpButton('config_report_category', 'configselectedcategory', 'block_adeptus_insights');
        $mform->hideIf('config_report_category', 'config_report_source', 'eq', 'manual');

        // Selected reports (for manual selection).
        // Hidden textarea to store the JSON data.
        $mform->addElement(
            'textarea',
            'config_selected_reports_json',
            get_string('configselectedreports', 'block_adeptus_insights'),
            ['rows' => 4, 'class' => 'manual-reports-selector d-none']
        );
        $mform->setType('config_selected_reports_json', PARAM_RAW);
        $mform->hideIf('config_selected_reports_json', 'config_report_source', 'neq', 'manual');

        // Manual report selector UI (rendered via JavaScript).
        $mform->addElement('html', '<div id="manual-report-selector-container" class="manual-report-selector-ui mb-3" style="display:none;"></div>');

        // =====================================
        // DISPLAY OPTIONS
        // =====================================
        $mform->addElement('header', 'config_header_display', get_string('configshowchart', 'block_adeptus_insights'));

        // Show chart.
        $mform->addElement('advcheckbox', 'config_show_chart', get_string('configshowchart', 'block_adeptus_insights'));
        $mform->setDefault('config_show_chart', 1);

        // Show table.
        $mform->addElement('advcheckbox', 'config_show_table', get_string('configshowtable', 'block_adeptus_insights'));
        $mform->setDefault('config_show_table', 1);

        // Chart height.
        $chartheights = [];
        for ($h = 150; $h <= 400; $h += 50) {
            $chartheights[$h] = $h . 'px';
        }
        $mform->addElement('select', 'config_chart_height', get_string('configchartheight', 'block_adeptus_insights'), $chartheights);
        $mform->setDefault('config_chart_height', 250);

        // Max table rows.
        $maxrows = [5 => '5', 10 => '10', 25 => '25', 50 => '50'];
        $mform->addElement('select', 'config_table_max_rows', get_string('configtablemaxrows', 'block_adeptus_insights'), $maxrows);
        $mform->setDefault('config_table_max_rows', 10);

        // Compact mode.
        $mform->addElement('advcheckbox', 'config_compact_mode', get_string('configcompactmode', 'block_adeptus_insights'));
        $mform->setDefault('config_compact_mode', 0);

        // Show footer.
        $mform->addElement('advcheckbox', 'config_show_footer', get_string('configshowfooter', 'block_adeptus_insights'));
        $mform->setDefault('config_show_footer', 1);

        // Show category badges.
        $mform->addElement('advcheckbox', 'config_show_category_badges', get_string('configshowcategorybadges', 'block_adeptus_insights'));
        $mform->setDefault('config_show_category_badges', 1);

        // KPI columns (for KPI mode).
        $kpicolumns = [1 => '1', 2 => '2', 3 => '3', 4 => '4'];
        $mform->addElement('select', 'config_kpi_columns', get_string('configkpicolumns', 'block_adeptus_insights'), $kpicolumns);
        $mform->setDefault('config_kpi_columns', 2);
        $mform->hideIf('config_kpi_columns', 'config_display_mode', 'neq', 'kpi');

        // KPI history save interval (for KPI mode trend tracking).
        $historyintervals = [
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
            $historyintervals
        );
        $mform->setDefault('config_kpi_history_interval', 3600);
        $mform->addHelpButton('config_kpi_history_interval', 'configkpihistoryinterval', 'block_adeptus_insights');
        $mform->hideIf('config_kpi_history_interval', 'config_display_mode', 'neq', 'kpi');

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

        // KPI selected reports (stored as JSON).
        $mform->addElement(
            'textarea',
            'config_kpi_selected_reports',
            get_string('configkpireports', 'block_adeptus_insights'),
            ['rows' => 4, 'class' => 'kpi-reports-selector d-none']
        );
        $mform->setType('config_kpi_selected_reports', PARAM_RAW);
        $mform->hideIf('config_kpi_selected_reports', 'config_display_mode', 'neq', 'kpi');

        // KPI report selector UI (rendered via JavaScript).
        $mform->addElement('html', '<div id="kpi-report-selector-container" class="kpi-report-selector-ui mb-3" style="display:none;"></div>');

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

        // Tabs selected reports (stored as JSON).
        $mform->addElement(
            'textarea',
            'config_tabs_selected_reports',
            get_string('configtabsreports', 'block_adeptus_insights'),
            ['rows' => 4, 'class' => 'tabs-reports-selector d-none']
        );
        $mform->setType('config_tabs_selected_reports', PARAM_RAW);
        $mform->hideIf('config_tabs_selected_reports', 'config_display_mode', 'neq', 'tabs');

        // Tabs report selector UI (rendered via JavaScript).
        $mform->addElement('html', '<div id="tabs-report-selector-container" class="tabs-report-selector-ui mb-3" style="display:none;"></div>');

        // Max link items (for links mode).
        $maxitems = [5 => '5', 10 => '10', 15 => '15', 20 => '20'];
        $mform->addElement('select', 'config_max_link_items', get_string('configmaxlinkitems', 'block_adeptus_insights'), $maxitems);
        $mform->setDefault('config_max_link_items', 10);
        $mform->hideIf('config_max_link_items', 'config_display_mode', 'neq', 'links');

        // =====================================
        // BEHAVIOR SETTINGS
        // =====================================
        $mform->addElement('header', 'config_header_behavior', get_string('configclickaction', 'block_adeptus_insights'));

        // Click action.
        $clickactions = [
            'modal' => get_string('clickaction_modal', 'block_adeptus_insights'),
            'newtab' => get_string('clickaction_newtab', 'block_adeptus_insights'),
            'expand' => get_string('clickaction_expand', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_click_action', get_string('configclickaction', 'block_adeptus_insights'), $clickactions);
        $mform->setDefault('config_click_action', 'modal');

        // Auto-refresh.
        $refreshintervals = [
            'never' => get_string('autorefresh_never', 'block_adeptus_insights'),
            '5m' => get_string('autorefresh_5m', 'block_adeptus_insights'),
            '15m' => get_string('autorefresh_15m', 'block_adeptus_insights'),
            '30m' => get_string('autorefresh_30m', 'block_adeptus_insights'),
            '1h' => get_string('autorefresh_1h', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_auto_refresh', get_string('configautorefresh', 'block_adeptus_insights'), $refreshintervals);
        $mform->setDefault('config_auto_refresh', 'never');

        // Show refresh button.
        $mform->addElement('advcheckbox', 'config_show_refresh_button', get_string('configshowrefreshbutton', 'block_adeptus_insights'));
        $mform->setDefault('config_show_refresh_button', 1);

        // Show export.
        $mform->addElement('advcheckbox', 'config_show_export', get_string('configshowexport', 'block_adeptus_insights'));
        $mform->setDefault('config_show_export', 1);

        // Show timestamp.
        $mform->addElement('advcheckbox', 'config_show_timestamp', get_string('configshowtimestamp', 'block_adeptus_insights'));
        $mform->setDefault('config_show_timestamp', 1);

        // =====================================
        // CONTEXT FILTERING
        // =====================================
        $mform->addElement('header', 'config_header_context', get_string('configcontextfilter', 'block_adeptus_insights'));

        // Context filter mode.
        $contextmodes = [
            'auto' => get_string('contextfilter_auto', 'block_adeptus_insights'),
            'course' => get_string('configcontextcourse', 'block_adeptus_insights'),
            'category' => get_string('configcontextcategory', 'block_adeptus_insights'),
            'none' => get_string('contextfilter_none', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_context_filter', get_string('configcontextfilter', 'block_adeptus_insights'), $contextmodes);
        $mform->setDefault('config_context_filter', 'auto');
        $mform->addHelpButton('config_context_filter', 'configcontextfilter', 'block_adeptus_insights');

        // Course selector (for manual course context).
        $courses = $this->get_course_options();
        $mform->addElement('autocomplete', 'config_context_course', get_string('configcontextcourse', 'block_adeptus_insights'), $courses);
        $mform->hideIf('config_context_course', 'config_context_filter', 'neq', 'course');

        // Category selector (for manual category context).
        $categories = $this->get_category_options();
        $mform->addElement('autocomplete', 'config_context_category', get_string('configcontextcategory', 'block_adeptus_insights'), $categories);
        $mform->hideIf('config_context_category', 'config_context_filter', 'neq', 'category');

        // =====================================
        // ALERT CONFIGURATION (for KPI mode)
        // =====================================
        $mform->addElement('header', 'config_header_alerts', get_string('config_header_alerts', 'block_adeptus_insights'));
        $mform->addHelpButton('config_header_alerts', 'config_header_alerts', 'block_adeptus_insights');

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
                <div id="alert-edit-panel" class="alert-edit-panel card mb-3" style="display:none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold alert-panel-title">' . get_string('config_add_alert', 'block_adeptus_insights') . '</span>
                        <button type="button" class="close alert-panel-close" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="alert-edit-report">' . get_string('config_alert_report', 'block_adeptus_insights') . ' <span class="text-danger">*</span></label>
                            <input type="text" id="alert-edit-report-search" class="form-control"
                                   placeholder="' . get_string('config_alert_report_placeholder', 'block_adeptus_insights') . '">
                            <div id="alert-report-dropdown" class="alert-report-dropdown position-absolute bg-white border rounded shadow-sm"
                                 style="display:none; z-index:1050; max-height:250px; overflow-y:auto;"></div>
                            <input type="hidden" id="alert-edit-report" value="">
                            <small class="form-text text-muted" id="alert-edit-report-display"></small>
                        </div>

                        <div class="form-group">
                            <label for="alert-edit-name">' .
                                get_string('config_alert_name', 'block_adeptus_insights') . '</label>
                            <input type="text" id="alert-edit-name" class="form-control"
                                placeholder="' . get_string('config_alert_name_placeholder', 'block_adeptus_insights') . '">
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="alert-edit-operator">' . get_string('config_alert_operator', 'block_adeptus_insights') . '</label>
                                    <select id="alert-edit-operator" class="form-control"></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="alert-edit-warning">' . get_string('config_alert_warning_value', 'block_adeptus_insights') . '</label>
                                    <input type="number" step="any" id="alert-edit-warning" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="alert-edit-critical">' . get_string('config_alert_critical_value', 'block_adeptus_insights') . '</label>
                                    <input type="number" step="any" id="alert-edit-critical" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="alert-edit-interval">' . get_string('config_alert_check_interval', 'block_adeptus_insights') . '</label>
                                    <select id="alert-edit-interval" class="form-control"></select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="alert-edit-cooldown">' . get_string('config_alert_cooldown', 'block_adeptus_insights') . '</label>
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
                        <h6 class="text-muted mb-3"><i class="fa fa-envelope mr-1"></i> Email Notifications</h6>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="alert-edit-notify-email">
                                <label class="custom-control-label" for="alert-edit-notify-email">' . get_string('config_alert_notify_email', 'block_adeptus_insights') . '</label>
                            </div>
                            <small class="form-text text-muted">' . get_string('config_alert_notify_email_desc', 'block_adeptus_insights') . '</small>
                        </div>

                        <div class="form-group" id="email-addresses-group" style="display: none;">
                            <label for="alert-edit-email-addresses">' .
                                get_string('config_alert_notify_email_addresses', 'block_adeptus_insights') . '</label>
                            <textarea id="alert-edit-email-addresses" class="form-control" rows="3"
                                placeholder="' . get_string('config_alert_notify_email_addresses_placeholder', 'block_adeptus_insights') . '"></textarea>
                            <small class="form-text text-muted">' .
                                get_string('config_alert_notify_email_addresses_desc', 'block_adeptus_insights') . '</small>
                        </div>

                        <hr class="my-3">
                        <h6 class="text-muted mb-3"><i class="fa fa-comment mr-1"></i> Moodle Message Notifications</h6>

                        <div class="form-group">
                            <label for="alert-edit-notify-roles">' . get_string('config_alert_notify_roles', 'block_adeptus_insights') . '</label>
                            <select id="alert-edit-notify-roles" class="form-control" multiple size="4"></select>
                            <small class="form-text text-muted">' . get_string('config_alert_notify_roles_desc', 'block_adeptus_insights') . '</small>
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
    }

    /**
     * Get existing alerts for the current block instance.
     *
     * @return array Array of alert configurations
     */
    private function get_existing_alerts() {
        global $DB, $CFG;

        $blockinstanceid = $this->block->instance->id ?? 0;
        if (empty($blockinstanceid)) {
            return [];
        }

        try {
            $alerts = $DB->get_records('block_adeptus_alerts', ['blockinstanceid' => $blockinstanceid], 'id ASC');
            $result = [];

            foreach ($alerts as $alert) {
                $result[] = [
                    'id' => $alert->id,
                    'report_slug' => $alert->report_slug,
                    'report_name' => self::get_report_display_name($alert->report_slug),
                    'alert_name' => $alert->alert_name,
                    'operator' => $alert->operator,
                    'warning_value' => $alert->warning_value,
                    'critical_value' => $alert->critical_value,
                    'check_interval' => $alert->check_interval,
                    'cooldown_seconds' => $alert->cooldown_seconds,
                    'notify_on_warning' => (bool) $alert->notify_on_warning,
                    'notify_on_critical' => (bool) $alert->notify_on_critical,
                    'notify_on_recovery' => (bool) $alert->notify_on_recovery,
                    'notify_email' => (bool) $alert->notify_email,
                    'notify_roles' => json_decode($alert->notify_roles, true) ?: [],
                    'enabled' => (bool) $alert->enabled,
                    'current_status' => $alert->current_status,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            debugging('Failed to get existing alerts: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
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
     * Initialize the JavaScript for the report selector UI.
     */
    private function init_report_selector_js() {
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

        $categories = ['' => get_string('reportsource_all', 'block_adeptus_insights')];

        // Try to fetch categories from the parent plugin.
        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $apiurl = \report_adeptus_insights\api_config::get_backend_url();

            if (!empty($apikey) && !empty($apiurl)) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiurl . '/reports/categories');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $apikey,
                ]);

                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

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
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiurl . '/reports/definitions');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-API-Key: ' . $apikey,
                ]);

                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

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
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiurl . '/wizard-reports/' . urlencode($slug));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apikey,
            ]);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

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
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiurl . '/ai-reports/' . urlencode($slug));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apikey,
            ]);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

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

        // Validate that at least chart or table is shown for embedded mode.
        if (isset($data['config_display_mode']) && $data['config_display_mode'] === 'embedded') {
            if (empty($data['config_show_chart']) && empty($data['config_show_table'])) {
                $errors['config_show_chart'] = get_string('errorloadingreport', 'block_adeptus_insights');
            }
        }

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
