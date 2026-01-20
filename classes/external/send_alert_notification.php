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

namespace block_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

/**
 * External function to send alert notifications via Moodle's messaging system.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_alert_notification extends external_api {

    /**
     * Define parameters for execute function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blockinstanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'alerts' => new external_multiple_structure(
                new external_single_structure([
                    'alert_id' => new external_value(PARAM_INT, 'Backend alert ID', VALUE_DEFAULT, 0),
                    'alert_name' => new external_value(PARAM_TEXT, 'Alert name'),
                    'message' => new external_value(PARAM_RAW, 'Alert message'),
                    'severity' => new external_value(PARAM_ALPHA, 'Severity: warning, critical, or recovery', VALUE_DEFAULT, 'warning'),
                    'report_name' => new external_value(PARAM_TEXT, 'Report name', VALUE_DEFAULT, ''),
                    'report_slug' => new external_value(PARAM_RAW, 'Report slug', VALUE_DEFAULT, ''),
                    'current_value' => new external_value(PARAM_RAW, 'Current metric value', VALUE_DEFAULT, ''),
                    'threshold' => new external_value(PARAM_RAW, 'Threshold value', VALUE_DEFAULT, ''),
                    'notify_users' => new external_value(PARAM_RAW, 'JSON array of user objects to notify', VALUE_DEFAULT, '[]'),
                ]),
                'Array of triggered alerts'
            ),
        ]);
    }

    /**
     * Execute the send notification function.
     *
     * @param int $blockinstanceid Block instance ID
     * @param array $alerts Array of triggered alerts
     * @return array Result with success status
     */
    public static function execute(int $blockinstanceid, array $alerts): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/notification_manager.php');

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'blockinstanceid' => $blockinstanceid,
            'alerts' => $alerts,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);

        $results = [
            'success' => true,
            'sent_count' => 0,
            'skipped_count' => 0,
            'errors' => [],
        ];

        // Get local alert configurations for email settings.
        $localalerts = self::get_local_alert_configs($params['blockinstanceid']);

        foreach ($params['alerts'] as $alertdata) {
            $alertid = (int) ($alertdata['alert_id'] ?? 0);
            $reportslug = $alertdata['report_slug'] ?? '';
            $severity = $alertdata['severity'] ?? 'warning';

            // Check if alert was already triggered for this severity - each severity fires once.
            // This allows warning, critical, and recovery to each fire independently.
            if ($alertid > 0) {
                $alreadytriggered = \block_adeptus_insights\notification_manager::is_alert_triggered(
                    $params['blockinstanceid'],
                    $alertid,
                    $severity
                );

                if ($alreadytriggered) {
                    // Skip this alert - already triggered for this severity.
                    $results['skipped_count']++;
                    continue;
                }
            }

            // Parse notify_users JSON.
            $notifyusers = [];
            if (!empty($alertdata['notify_users'])) {
                $decoded = json_decode($alertdata['notify_users'], true);
                if (is_array($decoded)) {
                    $notifyusers = $decoded;
                }
            }

            // Find local config for this alert to get email settings.
            $localconfig = self::find_local_alert_config($localalerts, $alertid);

            // Prepare alert array for notification manager.
            $alert = [
                'alert_name' => $alertdata['alert_name'],
                'message' => $alertdata['message'],
                'severity' => $severity,
                'report_name' => $alertdata['report_name'],
                'current_value' => $alertdata['current_value'],
                'threshold' => $alertdata['threshold'],
                'notify_users' => !empty($localconfig['notify_users']) ? $localconfig['notify_users'] : $notifyusers,
                'notify_email' => $localconfig['notify_email'] ?? false,
                'notify_emails' => $localconfig['notify_emails'] ?? '',
            ];

            // Send notification.
            $sendresult = \block_adeptus_insights\notification_manager::send_configured_alert(
                $alert,
                $params['blockinstanceid']
            );

            $results['sent_count'] += $sendresult['sent_count'];

            if (!empty($sendresult['errors'])) {
                $results['errors'] = array_merge($results['errors'], $sendresult['errors']);
            }

            // Log/archive the alert as triggered for this severity (only if notification was actually sent).
            if ($alertid > 0 && $sendresult['sent_count'] > 0) {
                \block_adeptus_insights\notification_manager::log_alert_triggered(
                    $params['blockinstanceid'],
                    $alertid,
                    $severity,
                    $reportslug,
                    $alertdata['alert_name'],
                    $alertdata['current_value'],
                    $alertdata['threshold']
                );
            }
        }

        if (!empty($results['errors']) && $results['sent_count'] === 0) {
            $results['success'] = false;
        }

        return $results;
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether notifications were sent successfully'),
            'sent_count' => new external_value(PARAM_INT, 'Number of notifications sent'),
            'skipped_count' => new external_value(PARAM_INT, 'Number of alerts skipped (already triggered)'),
            'errors' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Error message'),
                'List of errors if any'
            ),
        ]);
    }

    /**
     * Get local alert configurations from block config.
     *
     * @param int $blockinstanceid Block instance ID
     * @return array Array of alert configurations
     */
    private static function get_local_alert_configs(int $blockinstanceid): array {
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
    private static function find_local_alert_config(array $localalerts, int $backendid): ?array {
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
}
