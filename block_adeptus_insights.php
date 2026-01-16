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
 * Main block class for Adeptus Insights.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adeptus Insights block class.
 *
 * Displays reports from the Adeptus Insights report plugin directly
 * on dashboards, course pages, and other Moodle locations.
 */
class block_adeptus_insights extends block_base {
    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_adeptus_insights');
    }

    /**
     * Allow multiple instances of this block on the same page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Allow the block to have a configuration page.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Define where this block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'all' => true,
            'site-index' => true,
            'course-view' => true,
            'my' => true,
            'course-category' => true,
        ];
    }

    /**
     * Customize the block title based on configuration.
     */
    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        } else if (!empty($this->config->display_mode)) {
            // Set a contextual title based on display mode
            switch ($this->config->display_mode) {
                case 'embedded':
                    if (!empty($this->config->selected_reports)) {
                        $reports = $this->config->selected_reports;
                        if (is_array($reports) && count($reports) === 1) {
                            // Single report - use its name as title
                            $this->title = get_string('pluginname', 'block_adeptus_insights');
                        }
                    }
                    break;
                case 'kpi':
                    $this->title = get_string('quickstats', 'block_adeptus_insights');
                    break;
                case 'links':
                    $this->title = get_string('availablereports', 'block_adeptus_insights');
                    break;
            }
        }
    }

    /**
     * Generate the block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Check if parent plugin is available
        if (!$this->is_parent_plugin_available()) {
            $this->content->text = $this->render_error_state('parentpluginmissing');
            return $this->content;
        }

        // Check capability
        if (!has_capability('report/adeptus_insights:view', $this->context)) {
            $this->content->text = $this->render_error_state('nopermission');
            return $this->content;
        }

        // Ensure config is initialized (handles unconfigured blocks).
        if (!isset($this->config) || !is_object($this->config)) {
            $this->config = new stdClass();
        }

        // Get display mode from config (default to 'links')
        $displaymode = $this->config->display_mode ?? 'links';

        // Build context data for templates
        $contextdata = $this->build_context_data();

        // Render based on display mode
        switch ($displaymode) {
            case 'embedded':
                $this->content->text = $this->render_embedded_mode($contextdata);
                break;
            case 'kpi':
                $this->content->text = $this->render_kpi_mode($contextdata);
                break;
            case 'tabs':
                $this->content->text = $this->render_tabbed_mode($contextdata);
                break;
            case 'links':
            default:
                $this->content->text = $this->render_links_mode($contextdata);
                break;
        }

        // Initialize JavaScript for the block (must be done in get_content to ensure instance is available)
        $this->init_block_javascript();

        return $this->content;
    }

    /**
     * Initialize JavaScript for the block.
     */
    private function init_block_javascript() {
        global $CFG;

        // Get API key from parent plugin to pass directly to JS.
        $apikey = '';
        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
        } catch (\Exception $e) {
            debugging('Failed to get API key: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $jsparams = [
            'blockid' => $this->instance->id,
            'contextid' => $this->context->id,
            'config' => $this->get_js_config(),
            'apiKey' => $apikey,
            'isAdmin' => is_siteadmin(),
        ];

        $this->page->requires->js_call_amd(
            'block_adeptus_insights/block',
            'init',
            [$jsparams]
        );

        // Also load edit_form module if user can edit (for modal config).
        // This ensures the module is available when the block settings modal opens.
        if ($this->page->user_is_editing()) {
            $this->page->requires->js_call_amd(
                'block_adeptus_insights/edit_form',
                'init',
                [['apiKey' => $apikey]]
            );
        }
    }

    /**
     * Load required JavaScript for the block.
     */
    public function get_required_javascript() {
        parent::get_required_javascript();
        // JS initialization moved to init_block_javascript() called from get_content()
    }

    /**
     * Check if the parent Adeptus Insights report plugin is available.
     *
     * @return bool
     */
    private function is_parent_plugin_available() {
        global $CFG;
        return file_exists($CFG->dirroot . '/report/adeptus_insights/version.php');
    }

    /**
     * Build context data for template rendering.
     *
     * @return array
     */
    private function build_context_data() {
        global $COURSE;

        $data = [
            'blockid' => $this->instance->id,
            'contextid' => $this->context->id,
            'wwwroot' => (new moodle_url('/'))->out(false),
            'sesskey' => sesskey(),
            'userid' => $this->page->context->instanceid ?? 0,
        ];

        // Add context filtering data
        $contextfilter = $this->get_context_filter();
        $data['context_type'] = $contextfilter['type'];
        $data['context_id'] = $contextfilter['id'];
        $data['context_name'] = $contextfilter['name'];

        // Add course info if on course page
        if ($COURSE && $COURSE->id > 1) {
            $data['courseid'] = $COURSE->id;
            $data['coursename'] = format_string($COURSE->fullname);
        }

        // Add configuration
        $data['display_mode'] = $this->config->display_mode ?? 'links';
        $data['report_category'] = $this->config->report_category ?? '';

        // Validate and sanitize numeric CSS values to prevent injection.
        $chartheight = (int) ($this->config->chart_height ?? 250);
        $data['chart_height'] = max(100, min(800, $chartheight)); // Clamp between 100-800px.

        $data['click_action'] = 'modal'; // Always use modal to keep users on the page.
        $data['auto_refresh'] = $this->config->auto_refresh ?? 'never';
        $data['show_refresh_button'] = $this->config->show_refresh_button ?? true;
        $data['show_timestamp'] = $this->config->show_timestamp ?? true;

        return $data;
    }

    /**
     * Get context filter based on current page location.
     *
     * @return array
     */
    private function get_context_filter() {
        global $COURSE;

        $filter = [
            'type' => 'site',
            'id' => 0,
            'name' => get_string('sitelevel', 'block_adeptus_insights'),
        ];

        // Check if manual context override is set
        if (!empty($this->config->context_filter) && $this->config->context_filter !== 'auto') {
            if ($this->config->context_filter === 'course' && !empty($this->config->context_course)) {
                $filter['type'] = 'course';
                $filter['id'] = $this->config->context_course;
                // Get course name
                global $DB;
                $course = $DB->get_record('course', ['id' => $this->config->context_course], 'fullname');
                $filter['name'] = $course ? format_string($course->fullname) : '';
            } else if ($this->config->context_filter === 'category' && !empty($this->config->context_category)) {
                $filter['type'] = 'category';
                $filter['id'] = $this->config->context_category;
                // Get category name
                global $DB;
                $category = $DB->get_record('course_categories', ['id' => $this->config->context_category], 'name');
                $filter['name'] = $category ? format_string($category->name) : '';
            }
            return $filter;
        }

        // Auto-detect context.
        $pagecontext = $this->page->context;

        if ($pagecontext->contextlevel == CONTEXT_COURSE && $COURSE->id > 1) {
            $filter['type'] = 'course';
            $filter['id'] = $COURSE->id;
            $filter['name'] = format_string($COURSE->fullname);
        } else if ($pagecontext->contextlevel == CONTEXT_COURSECAT) {
            $filter['type'] = 'category';
            $filter['id'] = $pagecontext->instanceid;
            global $DB;
            $category = $DB->get_record('course_categories', ['id' => $pagecontext->instanceid], 'name');
            $filter['name'] = $category ? format_string($category->name) : '';
        }

        return $filter;
    }

    /**
     * Get JavaScript configuration.
     *
     * @return array
     */
    private function get_js_config() {
        global $CFG;

        // Parse selected reports for KPI mode.
        $kpiselectedreports = [];
        if (!empty($this->config->kpi_selected_reports)) {
            $decoded = json_decode($this->config->kpi_selected_reports, true);
            if (is_array($decoded)) {
                $kpiselectedreports = $decoded;
            }
        }

        // Parse selected reports for Tabs mode.
        $tabsselectedreports = [];
        if (!empty($this->config->tabs_selected_reports)) {
            $decoded = json_decode($this->config->tabs_selected_reports, true);
            if (is_array($decoded)) {
                $tabsselectedreports = $decoded;
            }
        }

        // Get alert status for this block.
        $alertstatus = $this->get_alert_status();

        // Get backend URL from parent plugin config.
        $backendurl = 'https://backend.adeptus360.com/api/v1'; // Default fallback.
        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');
            $backendurl = \report_adeptus_insights\api_config::get_backend_url();
        } catch (\Exception $e) {
            debugging('Failed to get backend URL: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return [
            'backendUrl' => $backendurl,
            'displayMode' => $this->config->display_mode ?? 'links',
            'reportCategory' => $this->config->report_category ?? '',
            'kpiSelectedReports' => $kpiselectedreports,
            'tabsSelectedReports' => $tabsselectedreports,
            'kpiColumns' => $this->config->kpi_columns ?? 2,
            'kpiHistoryInterval' => (int) ($this->config->kpi_history_interval ?? 3600),
            'chartHeight' => $this->config->chart_height ?? 250,
            'clickAction' => 'modal', // Always use modal to keep users on the page.
            'autoRefresh' => $this->config->auto_refresh ?? 'never',
            'showRefreshButton' => $this->config->show_refresh_button ?? true,
            'showTimestamp' => $this->config->show_timestamp ?? true,
            'maxLinkItems' => $this->config->max_link_items ?? 10,
            'contextType' => $this->get_context_filter()['type'],
            'contextId' => $this->get_context_filter()['id'],
            'alertsEnabled' => !empty($this->config->alerts_enabled),
            'alertStatus' => $alertstatus,
        ];
    }

    /**
     * Get current alert status for this block instance.
     *
     * @return array Alert status summary
     */
    private function get_alert_status() {
        global $CFG;

        $status = [
            'hasAlerts' => false,
            'warningCount' => 0,
            'criticalCount' => 0,
            'highestSeverity' => 'ok',
            'activeAlerts' => [],
        ];

        if (empty($this->config->alerts_enabled) || $this->config->display_mode !== 'kpi') {
            return $status;
        }

        try {
            require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/alert_manager.php');

            $summary = \block_adeptus_insights\alert_manager::get_block_alert_status($this->instance->id);

            $status['hasAlerts'] = ($summary['warning'] > 0 || $summary['critical'] > 0);
            $status['warningCount'] = $summary['warning'];
            $status['criticalCount'] = $summary['critical'];
            $status['highestSeverity'] = $summary['highest_severity'];

            // Include active alert details for display.
            foreach ($summary['active_alerts'] as $alert) {
                $status['activeAlerts'][] = [
                    'id' => $alert->id,
                    'name' => $alert->alert_name ?: $alert->report_slug,
                    'status' => $alert->current_status,
                    'value' => $alert->last_value,
                    'reportSlug' => $alert->report_slug,
                ];
            }
        } catch (\Exception $e) {
            debugging('Failed to get alert status: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $status;
    }

    /**
     * Render embedded report mode.
     *
     * @param array $data Context data
     * @return string HTML output
     */
    private function render_embedded_mode($data) {
        global $OUTPUT;

        $data['is_embedded'] = true;
        $data['loading'] = true;

        // Pass selected reports JSON for embedded mode (when using manual selection).
        $selectedreports = [];
        if (!empty($this->config->selected_reports_json)) {
            $decoded = json_decode($this->config->selected_reports_json, true);
            if (is_array($decoded)) {
                $selectedreports = $decoded;
            }
        }
        $data['selected_reports_json'] = htmlspecialchars(json_encode($selectedreports), ENT_QUOTES, 'UTF-8');

        return $OUTPUT->render_from_template('block_adeptus_insights/embedded_report', $data);
    }

    /**
     * Render KPI cards mode.
     *
     * @param array $data Context data
     * @return string HTML output
     */
    private function render_kpi_mode($data) {
        global $OUTPUT;

        $data['is_kpi'] = true;
        $data['loading'] = true;

        // Validate kpi_columns to be between 1-4.
        $kpicolumns = (int) ($this->config->kpi_columns ?? 2);
        $data['kpi_columns'] = max(1, min(4, $kpicolumns));

        // Pass selected reports JSON for the template data attribute.
        $selectedreports = [];
        if (!empty($this->config->kpi_selected_reports)) {
            $decoded = json_decode($this->config->kpi_selected_reports, true);
            if (is_array($decoded)) {
                $selectedreports = $decoded;
            }
        }
        $data['selected_reports_json'] = htmlspecialchars(json_encode($selectedreports), ENT_QUOTES, 'UTF-8');

        return $OUTPUT->render_from_template('block_adeptus_insights/kpi_grid', $data);
    }

    /**
     * Render tabbed reports mode.
     *
     * @param array $data Context data
     * @return string HTML output
     */
    private function render_tabbed_mode($data) {
        global $OUTPUT;

        $data['is_tabbed'] = true;
        $data['loading'] = true;

        // Pass selected reports JSON for the template data attribute.
        $selectedreports = [];
        if (!empty($this->config->tabs_selected_reports)) {
            $decoded = json_decode($this->config->tabs_selected_reports, true);
            if (is_array($decoded)) {
                $selectedreports = $decoded;
            }
        }
        $data['selected_reports_json'] = htmlspecialchars(json_encode($selectedreports), ENT_QUOTES, 'UTF-8');

        return $OUTPUT->render_from_template('block_adeptus_insights/report_tabs', $data);
    }

    /**
     * Render report links mode.
     *
     * @param array $data Context data
     * @return string HTML output
     */
    private function render_links_mode($data) {
        global $OUTPUT;

        $data['is_links'] = true;
        $data['loading'] = true;
        $data['max_items'] = $this->config->max_link_items ?? 10;
        $data['show_category_badges'] = $this->config->show_category_badges ?? true;

        return $OUTPUT->render_from_template('block_adeptus_insights/report_list', $data);
    }

    /**
     * Render an error state.
     *
     * @param string $errorkey Language string key
     * @return string HTML output
     */
    private function render_error_state($errorkey) {
        global $OUTPUT;

        return $OUTPUT->render_from_template('block_adeptus_insights/error_state', [
            'message' => get_string($errorkey, 'block_adeptus_insights'),
        ]);
    }

    /**
     * Serialize and store block configuration.
     *
     * @param stdClass $data
     * @param bool $nolongerused
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $CFG;

        // Handle multi-select fields which come as arrays.
        if (isset($data->selected_reports) && is_array($data->selected_reports)) {
            $data->selected_reports = array_filter($data->selected_reports);
        }

        // Save multi-alert configuration to database.
        if (!empty($data->alerts_enabled) && !empty($data->alerts_json)) {
            $this->save_alerts_from_json($data->alerts_json);
        } else if (empty($data->alerts_enabled)) {
            // Alerts disabled - optionally disable all alerts in database.
            $this->disable_all_alerts();
        }

        parent::instance_config_save($data, $nolongerused);
    }

    /**
     * Save multiple alert configurations from JSON data.
     *
     * @param string $alertsjson JSON-encoded array of alert configurations
     */
    private function save_alerts_from_json($alertsjson) {
        global $CFG, $DB;

        try {
            require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/alert_manager.php');

            $alerts = json_decode($alertsjson, true);
            if (!is_array($alerts)) {
                return;
            }

            // Get existing alert IDs for this block.
            $existingids = $DB->get_fieldset_select(
                'block_adeptus_alerts',
                'id',
                'blockinstanceid = ?',
                [$this->instance->id]
            );
            $processedids = [];

            // Save or update each alert.
            foreach ($alerts as $alertdata) {
                if (empty($alertdata['report_slug'])) {
                    continue;
                }

                $alertconfig = [
                    'operator' => $alertdata['operator'] ?? 'gt',
                    'metric_field' => 'value',
                    'warning_value' => isset($alertdata['warning_value']) && $alertdata['warning_value'] !== ''
                        ? (float)$alertdata['warning_value'] : null,
                    'critical_value' => isset($alertdata['critical_value']) && $alertdata['critical_value'] !== ''
                        ? (float)$alertdata['critical_value'] : null,
                    'check_interval' => (int)($alertdata['check_interval'] ?? 3600),
                    'cooldown_seconds' => (int)($alertdata['cooldown_seconds'] ?? 3600),
                    'alert_name' => $alertdata['alert_name'] ?? '',
                    'notify_on_warning' => !empty($alertdata['notify_on_warning']),
                    'notify_on_critical' => !empty($alertdata['notify_on_critical']),
                    'notify_on_recovery' => !empty($alertdata['notify_on_recovery']),
                    'notify_email' => !empty($alertdata['notify_email']),
                    'notify_message' => true,
                    'notify_roles' => $alertdata['notify_roles'] ?? [],
                    'enabled' => isset($alertdata['enabled']) ? (bool)$alertdata['enabled'] : true,
                ];

                // If alert has an ID, it's an existing alert.
                if (!empty($alertdata['id'])) {
                    $alertconfig['id'] = (int)$alertdata['id'];
                    $processedids[] = (int)$alertdata['id'];
                }

                // Save the alert (creates or updates).
                $savedid = \block_adeptus_insights\alert_manager::save_alert(
                    $this->instance->id,
                    $alertdata['report_slug'],
                    $alertconfig
                );

                if ($savedid && empty($alertdata['id'])) {
                    $processedids[] = $savedid;
                }
            }

            // Delete alerts that were removed (not in the processed list).
            $todeleteids = array_diff($existingids, $processedids);
            foreach ($todeleteids as $deleteid) {
                \block_adeptus_insights\alert_manager::delete_alert($deleteid);
            }
        } catch (\Exception $e) {
            debugging('Failed to save alert configurations: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Disable all alerts for this block instance.
     */
    private function disable_all_alerts() {
        global $DB;

        try {
            $DB->set_field('block_adeptus_alerts', 'enabled', 0, [
                'blockinstanceid' => $this->instance->id,
            ]);
        } catch (\Exception $e) {
            debugging('Failed to disable alerts: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Clean up when block instance is deleted.
     *
     * @return bool
     */
    public function instance_delete() {
        global $CFG;

        // Delete all alerts and history for this block instance.
        try {
            require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/alert_manager.php');
            \block_adeptus_insights\alert_manager::delete_block_alerts($this->instance->id);
        } catch (\Exception $e) {
            debugging('Failed to delete block alerts: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Delete KPI history for this block instance.
        try {
            require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/kpi_history_manager.php');
            \block_adeptus_insights\kpi_history_manager::delete_block_history($this->instance->id);
        } catch (\Exception $e) {
            debugging('Failed to delete block KPI history: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return true;
    }
}
