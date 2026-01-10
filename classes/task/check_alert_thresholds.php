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

namespace block_adeptus_insights\task;

defined('MOODLE_INTERNAL') || die();

use block_adeptus_insights\alert_manager;
use block_adeptus_insights\kpi_history_manager;

/**
 * Scheduled task to check alert thresholds for all enabled alerts.
 *
 * This task runs periodically to evaluate KPI values against configured
 * thresholds and trigger notifications when thresholds are breached.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_alert_thresholds extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_check_alert_thresholds', 'block_adeptus_insights');
    }

    /**
     * Execute the scheduled task.
     *
     * Queries all enabled alerts that are due for checking, fetches the latest
     * KPI values, and evaluates them against thresholds.
     */
    public function execute() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/alert_manager.php');
        require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/kpi_history_manager.php');

        mtrace('Starting alert threshold check...');

        $now = time();
        $processedcount = 0;
        $triggeredcount = 0;
        $errorcount = 0;

        // Get all enabled alerts that need checking.
        // An alert needs checking if: enabled=1 AND (last_checked IS NULL OR now - last_checked >= check_interval)
        $sql = "SELECT a.*
                FROM {block_adeptus_alerts} a
                JOIN {block_instances} bi ON a.blockinstanceid = bi.id
                WHERE a.enabled = 1
                  AND (a.last_checked IS NULL OR (:now - a.last_checked) >= a.check_interval)
                ORDER BY a.blockinstanceid, a.report_slug";

        $alerts = $DB->get_records_sql($sql, ['now' => $now]);

        if (empty($alerts)) {
            mtrace('No alerts due for checking.');
            return;
        }

        mtrace('Found ' . count($alerts) . ' alerts to check.');

        // Group alerts by block instance for efficient processing.
        $alertsbyblock = [];
        foreach ($alerts as $alert) {
            if (!isset($alertsbyblock[$alert->blockinstanceid])) {
                $alertsbyblock[$alert->blockinstanceid] = [];
            }
            $alertsbyblock[$alert->blockinstanceid][$alert->report_slug] = $alert;
        }

        // Process each block's alerts.
        foreach ($alertsbyblock as $blockinstanceid => $blockalerts) {
            try {
                // Verify block still exists.
                $blockinstance = $DB->get_record('block_instances', ['id' => $blockinstanceid]);
                if (!$blockinstance) {
                    mtrace("  Block $blockinstanceid no longer exists, cleaning up alerts...");
                    alert_manager::delete_block_alerts($blockinstanceid);
                    continue;
                }

                mtrace("  Processing block $blockinstanceid with " . count($blockalerts) . " alerts...");

                // Get KPI values for all reports in this block.
                $kpivalues = $this->get_current_kpi_values($blockinstanceid, array_keys($blockalerts));

                foreach ($blockalerts as $reportslug => $alert) {
                    $processedcount++;

                    if (!isset($kpivalues[$reportslug])) {
                        mtrace("    - $reportslug: No KPI value available, skipping.");
                        continue;
                    }

                    $currentvalue = $kpivalues[$reportslug];

                    // Get previous value for percentage-based operators.
                    $previousvalue = null;
                    if (in_array($alert->operator,
                            [alert_manager::OP_CHANGE_PERCENT, alert_manager::OP_INCREASE_PERCENT,
                             alert_manager::OP_DECREASE_PERCENT])) {
                        $previousvalue = $this->get_previous_value($blockinstanceid, $reportslug);
                    }

                    // Process the alert.
                    $result = alert_manager::process_alert($alert, $currentvalue, $previousvalue);

                    if ($result['status_changed']) {
                        $triggeredcount++;
                        mtrace("    - $reportslug: Status changed from {$result['old_status']} to {$result['new_status']}");
                        if ($result['notification_sent']) {
                            mtrace("      Notifications sent: {$result['notifications_count']}");
                        }
                    } else {
                        mtrace("    - $reportslug: Status unchanged ({$result['new_status']})");
                    }
                }

            } catch (\Exception $e) {
                $errorcount++;
                mtrace("  ERROR processing block $blockinstanceid: " . $e->getMessage());
                debugging('Alert check error for block ' . $blockinstanceid . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        mtrace("Alert check complete. Processed: $processedcount, Triggered: $triggeredcount, Errors: $errorcount");
    }

    /**
     * Get current KPI values for specified reports in a block.
     *
     * Uses the most recent entry from kpi_history for each report.
     *
     * @param int $blockinstanceid Block instance ID
     * @param array $reportslugs Array of report slugs to fetch
     * @return array Associative array of report_slug => value
     */
    private function get_current_kpi_values(int $blockinstanceid, array $reportslugs): array {
        global $DB;

        $values = [];

        foreach ($reportslugs as $slug) {
            $lastentry = kpi_history_manager::get_last_entry($blockinstanceid, $slug);
            if ($lastentry) {
                $values[$slug] = (float)$lastentry->metric_value;
            }
        }

        return $values;
    }

    /**
     * Get previous KPI value for percentage calculations.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @return float|null Previous value or null if not available
     */
    private function get_previous_value(int $blockinstanceid, string $reportslug): ?float {
        global $DB;

        // Get the second most recent entry.
        $sql = "SELECT metric_value
                FROM {block_adeptus_kpi_history}
                WHERE blockinstanceid = :blockinstanceid
                  AND report_slug = :report_slug
                ORDER BY timecreated DESC
                LIMIT 1 OFFSET 1";

        $result = $DB->get_field_sql($sql, [
            'blockinstanceid' => $blockinstanceid,
            'report_slug' => $reportslug,
        ]);

        return $result !== false ? (float)$result : null;
    }
}
