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
 * Snapshot scheduler manager for Adeptus Insights block.
 *
 * Handles scheduling and execution of automated KPI snapshots.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Snapshot scheduler manager class.
 *
 * Manages automated snapshot scheduling for KPI history tracking.
 * Reports are registered on first execution and then executed by cron
 * at the configured interval.
 */
class snapshot_scheduler {
    /** @var string Table name for snapshot schedules */
    const TABLE = 'block_adeptus_snap_sched';

    /** @var string Backend API URL */
    private $backendurl;

    /** @var string API key for backend authentication */
    private $apikey;

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;

        require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

        $installationmanager = new \report_adeptus_insights\installation_manager();
        $this->apikey = $installationmanager->get_api_key();
        $this->backendurl = \report_adeptus_insights\api_config::get_backend_url();
    }

    /**
     * Register a report for scheduled snapshots.
     *
     * Called from JavaScript on first report execution to set up
     * the schedule for subsequent cron-based snapshots.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report identifier
     * @param string $reportsource Report source ('wizard' or 'ai')
     * @param int $intervalseconds Snapshot interval in seconds
     * @param int $initialrowcount Row count from initial execution
     * @return bool True on success
     */
    public function register_report($blockinstanceid, $reportslug, $reportsource, $intervalseconds, $initialrowcount = 0) {
        global $DB;

        $now = time();

        // Check if schedule already exists for this block-report combination.
        $existing = $DB->get_record(self::TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'report_slug' => $reportslug,
        ]);

        if ($existing) {
            // Update existing schedule.
            $existing->interval_seconds = $intervalseconds;
            $existing->last_snapshot_time = $now;
            $existing->next_snapshot_due = $now + $intervalseconds;
            $existing->last_row_count = $initialrowcount;
            $existing->is_active = 1;
            $existing->timemodified = $now;

            $DB->update_record(self::TABLE, $existing);
            return true;
        }

        // Create new schedule.
        $record = new \stdClass();
        $record->blockinstanceid = $blockinstanceid;
        $record->report_slug = $reportslug;
        $record->report_source = $reportsource;
        $record->interval_seconds = $intervalseconds;
        $record->last_snapshot_time = $now;
        $record->next_snapshot_due = $now + $intervalseconds;
        $record->last_row_count = $initialrowcount;
        $record->is_active = 1;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $DB->insert_record(self::TABLE, $record);
        return true;
    }

    /**
     * Get all schedules that are due for execution.
     *
     * @return array Array of schedule records
     */
    public function get_due_schedules() {
        global $DB;

        $now = time();

        return $DB->get_records_select(
            self::TABLE,
            'is_active = 1 AND next_snapshot_due <= ?',
            [$now],
            'next_snapshot_due ASC',
            '*',
            0,
            100 // Process max 100 per cron run.
        );
    }

    /**
     * Execute a scheduled snapshot.
     *
     * @param \stdClass $schedule Schedule record
     * @return bool True on success
     */
    public function execute_snapshot($schedule) {
        global $DB, $CFG;

        $starttime = microtime(true);

        try {
            // Verify block instance still exists.
            $blockinstance = $DB->get_record('block_instances', ['id' => $schedule->blockinstanceid]);
            if (!$blockinstance) {
                // Block was deleted, deactivate schedule.
                $this->deactivate_schedule($schedule->id);
                mtrace("  Schedule {$schedule->id}: Block instance {$schedule->blockinstanceid} no longer exists, deactivated.");
                return false;
            }

            // Execute the report based on source type.
            if ($schedule->report_source === 'wizard') {
                $rowcount = $this->execute_wizard_report($schedule->report_slug);
            } else {
                $rowcount = $this->execute_ai_report($schedule->report_slug);
            }

            if ($rowcount === false) {
                mtrace("  Schedule {$schedule->id}: Failed to execute report {$schedule->report_slug}");
                return false;
            }

            $executiontime = (int) ((microtime(true) - $starttime) * 1000);

            // Post snapshot to backend and handle any triggered alerts.
            $success = $this->post_snapshot_to_backend(
                $schedule->report_slug,
                $rowcount,
                $executiontime,
                $schedule->report_source,
                $schedule->blockinstanceid
            );

            if ($success) {
                // Update schedule with next due time.
                $now = time();
                $schedule->last_snapshot_time = $now;
                $schedule->next_snapshot_due = $now + $schedule->interval_seconds;
                $schedule->last_row_count = $rowcount;
                $schedule->timemodified = $now;

                $DB->update_record(self::TABLE, $schedule);

                mtrace("  Schedule {$schedule->id}: Snapshot posted - {$rowcount} rows in {$executiontime}ms");
                return true;
            }

            mtrace("  Schedule {$schedule->id}: Failed to post snapshot to backend");
            return false;

        } catch (\Exception $e) {
            mtrace("  Schedule {$schedule->id}: Error - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a wizard report and return row count.
     *
     * @param string $reportslug Report slug
     * @return int|false Row count or false on failure
     */
    private function execute_wizard_report($reportslug) {
        global $DB, $CFG;

        // Fetch wizard report from backend by slug.
        $url = $this->backendurl . '/wizard-reports/' . urlencode($reportslug);
        $response = $this->make_api_request($url, 'GET');

        if (!$response || !$response['success'] || empty($response['report'])) {
            mtrace("    Failed to fetch wizard report {$reportslug} from backend");
            return false;
        }

        $report = $response['report'];
        $templateid = $report['report_template_id'] ?? $report['name'] ?? null;

        if (empty($templateid)) {
            mtrace("    Wizard report {$reportslug} has no template ID");
            return false;
        }

        // Fetch the report definition (SQL) using the template ID.
        $definition = $this->fetch_report_definition($templateid);
        if (!$definition || empty($definition['sqlquery'])) {
            mtrace("    Could not find report definition for template: {$templateid}");
            return false;
        }

        // Validate report compatibility.
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/report_validator.php');
        $validation = \report_adeptus_insights\report_validator::validate_report($definition);

        if (!$validation['valid']) {
            mtrace("    Report {$reportslug} incompatible: " . $validation['reason']);
            return false;
        }

        // Execute SQL query.
        $sql = $definition['sqlquery'];

        // Add safety limit if not present.
        if (!preg_match('/\bLIMIT\s+(\d+|:\w+|\?)/i', $sql)) {
            $sql = rtrim(rtrim($sql), ';') . ' LIMIT 100000';
        }

        try {
            $results = $DB->get_records_sql($sql);
            return $this->extract_metric_value($results);
        } catch (\dml_exception $e) {
            mtrace("    SQL error for {$reportslug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute an AI report and return row count.
     *
     * @param string $reportslug Report slug
     * @return int|false Row count or false on failure
     */
    private function execute_ai_report($reportslug) {
        global $DB, $CFG;

        // Fetch AI report from backend (includes SQL).
        $url = $this->backendurl . '/ai-reports/' . urlencode($reportslug);
        $response = $this->make_api_request($url, 'GET');

        if (!$response || !$response['success'] || empty($response['report'])) {
            return false;
        }

        $report = $response['report'];
        $sql = $report['sql_query'] ?? $report['sql'] ?? $report['generated_sql'] ?? null;

        if (empty($sql)) {
            mtrace("    AI report {$reportslug} has no SQL query");
            return false;
        }

        // Add safety limit if not present.
        if (!preg_match('/\bLIMIT\s+(\d+|:\w+|\?)/i', $sql)) {
            $sql = rtrim(rtrim($sql), ';') . ' LIMIT 100000';
        }

        try {
            $results = $DB->get_records_sql($sql);
            return $this->extract_metric_value($results);
        } catch (\dml_exception $e) {
            mtrace("    SQL error for AI report {$reportslug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract the metric value from query results.
     *
     * For KPI reports using aggregate functions (COUNT, SUM, etc.),
     * extracts the actual value from the first row's first numeric column.
     * For reports returning multiple rows, returns the row count.
     *
     * @param array $results Query results
     * @return int The metric value
     */
    private function extract_metric_value($results) {
        $rowcount = count($results);

        // If no results, return 0.
        if ($rowcount === 0) {
            return 0;
        }

        // If single row, likely a KPI with aggregate function - extract the value.
        if ($rowcount === 1) {
            $firstrow = reset($results);
            $values = (array) $firstrow;

            // Look for the first numeric value in the row.
            foreach ($values as $key => $value) {
                // Skip the 'id' column that Moodle adds as the key.
                if ($key === 'id') {
                    continue;
                }
                // Return the first numeric value we find.
                if (is_numeric($value)) {
                    return (int) $value;
                }
            }

            // If we have an 'id' and it looks like a count, use it.
            if (isset($values['id']) && is_numeric($values['id'])) {
                return (int) $values['id'];
            }
        }

        // For multi-row results, return the row count.
        return $rowcount;
    }

    /**
     * Fetch wizard report definition from backend.
     *
     * @param string $reportslug Report slug/name
     * @return array|null Report definition or null
     */
    private function fetch_report_definition($reportslug) {
        $url = $this->backendurl . '/reports/definitions';
        $response = $this->make_api_request($url, 'GET', [], ['X-API-Key' => $this->apikey]);

        if (!$response || !$response['success'] || empty($response['data'])) {
            return null;
        }

        // Find the report by name/slug.
        foreach ($response['data'] as $report) {
            $name = trim($report['name'] ?? '');
            if ($name === trim($reportslug)) {
                return $report;
            }
        }

        return null;
    }

    /**
     * Post snapshot to backend API and handle triggered alerts.
     *
     * @param string $reportslug Report slug
     * @param int $rowcount Number of rows (current metric value)
     * @param int $executiontimems Execution time in milliseconds
     * @param string $reportsource Report source ('wizard' or 'ai')
     * @param int $blockinstanceid Block instance ID for alert tracking
     * @return bool True on success
     */
    private function post_snapshot_to_backend($reportslug, $rowcount, $executiontimems, $reportsource = 'wizard', $blockinstanceid = 0) {
        global $CFG;

        // Use the correct endpoint based on report source.
        $endpoint = ($reportsource === 'ai') ? '/ai-reports/' : '/wizard-reports/';
        $url = $this->backendurl . $endpoint . urlencode($reportslug) . '/snapshots';

        $data = [
            'row_count' => $rowcount,
            'execution_time_ms' => $executiontimems,
            'source' => 'cron',
        ];

        $response = $this->make_api_request($url, 'POST', $data);

        if (!$response || empty($response['success'])) {
            return false;
        }

        // Handle any triggered alerts from the backend response.
        if (!empty($response['alerts']) && !empty($response['alerts']['triggered']) && $blockinstanceid > 0) {
            $this->process_triggered_alerts(
                $response['alerts']['triggered'],
                $blockinstanceid,
                $reportslug,
                $rowcount
            );
        }

        return true;
    }

    /**
     * Process triggered alerts from backend response.
     *
     * Sends notifications via Moodle's messaging system for alerts
     * that haven't already been triggered.
     *
     * @param array $triggeredalerts Array of triggered alert objects from backend
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param int $currentvalue Current metric value
     */
    private function process_triggered_alerts($triggeredalerts, $blockinstanceid, $reportslug, $currentvalue) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/notification_manager.php');

        // Get local alert configurations from block config for email settings.
        $localalerts = $this->get_local_alert_configs($blockinstanceid);

        $sentcount = 0;
        $skippedcount = 0;

        foreach ($triggeredalerts as $alertdata) {
            $alertid = (int) ($alertdata['id'] ?? $alertdata['alert_id'] ?? 0);
            $severity = $alertdata['severity'] ?? 'warning';

            // Check if alert was already triggered for this severity.
            if ($alertid > 0) {
                $alreadytriggered = notification_manager::is_alert_triggered(
                    $blockinstanceid,
                    $alertid,
                    $severity
                );

                if ($alreadytriggered) {
                    $skippedcount++;
                    continue;
                }
            }

            // Find local config for this alert to get email settings.
            $localconfig = $this->find_local_alert_config($localalerts, $alertid);

            // Skip alerts that aren't in the local block config.
            // This filters out old/orphaned backend alerts that were deleted from this block.
            if ($alertid > 0 && $localconfig === null) {
                $skippedcount++;
                continue;
            }

            // Prepare alert data for notification manager.
            $alert = [
                'alert_name' => $alertdata['alert_name'] ?? $alertdata['name'] ?? 'Alert',
                'message' => $alertdata['message'] ?? '',
                'severity' => $severity,
                'report_name' => $alertdata['report_name'] ?? '',
                'current_value' => $alertdata['current_value'] ?? $alertdata['value'] ?? $currentvalue,
                'threshold' => $alertdata['threshold'] ?? $alertdata['threshold_value'] ?? '',
                'notify_users' => $localconfig['notify_users'] ?? $alertdata['notify_users'] ?? [],
                'notify_email' => $localconfig['notify_email'] ?? false,
                'notify_emails' => $localconfig['notify_emails'] ?? '',
            ];

            // Send notification.
            $sendresult = notification_manager::send_configured_alert($alert, $blockinstanceid)

            if ($sendresult['sent_count'] > 0) {
                $sentcount += $sendresult['sent_count'];

                // Log/archive the alert as triggered.
                if ($alertid > 0) {
                    notification_manager::log_alert_triggered(
                        $blockinstanceid,
                        $alertid,
                        $severity,
                        $reportslug,
                        $alert['alert_name'],
                        (string) $currentvalue,
                        (string) $alert['threshold']
                    );
                }

                mtrace("    Alert '{$alert['alert_name']}' ({$severity}) triggered - {$sendresult['sent_count']} notification(s) sent");
            }
        }

        if ($skippedcount > 0) {
            mtrace("    {$skippedcount} alert(s) skipped (already triggered)");
        }
    }

    /**
     * Get local alert configurations from block config.
     *
     * @param int $blockinstanceid Block instance ID
     * @return array Array of alert configurations
     */
    private function get_local_alert_configs($blockinstanceid) {
        global $DB;

        try {
            $block = $DB->get_record('block_instances', ['id' => $blockinstanceid]);
            if (!$block || empty($block->configdata)) {
                return [];
            }

            $config = unserialize(base64_decode($block->configdata));
            if (empty($config->alerts_json)) {
                return [];
            }

            $alerts = json_decode($config->alerts_json, true);
            return is_array($alerts) ? $alerts : [];
        } catch (\Exception $e) {
            debugging('Failed to get local alert configs: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Find local alert config by backend ID.
     *
     * @param array $localalerts Array of local alert configurations
     * @param int $backendid Backend alert ID
     * @return array|null Alert config or null if not found
     */
    private function find_local_alert_config($localalerts, $backendid) {
        if (empty($localalerts) || $backendid <= 0) {
            return null;
        }

        foreach ($localalerts as $alert) {
            if (isset($alert['backend_id']) && (int) $alert['backend_id'] === $backendid) {
                return $alert;
            }
        }

        return null;
    }

    /**
     * Deactivate a schedule.
     *
     * @param int $scheduleid Schedule ID
     */
    public function deactivate_schedule($scheduleid) {
        global $DB;

        $DB->set_field(self::TABLE, 'is_active', 0, ['id' => $scheduleid]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $scheduleid]);
    }

    /**
     * Delete schedules for a block instance.
     *
     * Called when block is deleted.
     *
     * @param int $blockinstanceid Block instance ID
     */
    public function delete_schedules_for_block($blockinstanceid) {
        global $DB;

        $DB->delete_records(self::TABLE, ['blockinstanceid' => $blockinstanceid]);
    }

    /**
     * Make an API request.
     *
     * @param string $url API URL
     * @param string $method HTTP method
     * @param array $data Request body data
     * @param array $extraheaders Additional headers
     * @return array|null Response data or null
     */
    private function make_api_request($url, $method = 'GET', $data = [], $extraheaders = []) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ];

        foreach ($extraheaders as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpcode < 200 || $httpcode >= 300) {
            debugging("API request failed: $url, HTTP $httpcode, Error: $error", DEBUG_DEVELOPER);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Get schedule for a specific block and report.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @return \stdClass|null Schedule record or null
     */
    public function get_schedule($blockinstanceid, $reportslug) {
        global $DB;

        return $DB->get_record(self::TABLE, [
            'blockinstanceid' => $blockinstanceid,
            'report_slug' => $reportslug,
        ]);
    }

    /**
     * Check if snapshots feature is enabled.
     *
     * @return bool True if snapshots are enabled
     */
    public function is_snapshots_enabled() {
        global $CFG;

        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            $installationmanager = new \report_adeptus_insights\installation_manager();
            return $installationmanager->is_feature_enabled('snapshots');
        } catch (\Exception $e) {
            return false;
        }
    }
}
