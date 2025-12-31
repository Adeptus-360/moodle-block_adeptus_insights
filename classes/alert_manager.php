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

namespace block_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Enterprise-grade Alert Manager for KPI threshold monitoring.
 *
 * Handles alert configuration, evaluation, status tracking, and notification dispatch.
 * Implements the "Insurance Policy" concept for proactive educational intervention.
 *
 * @package    block_adeptus_insights
 * @copyright  2025 Adeptus Analytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class alert_manager {

    /** @var string Alert status - within acceptable range */
    const STATUS_OK = 'ok';

    /** @var string Alert status - approaching threshold */
    const STATUS_WARNING = 'warning';

    /** @var string Alert status - threshold exceeded */
    const STATUS_CRITICAL = 'critical';

    /** @var string Alert status - recovered from breach */
    const STATUS_RECOVERY = 'recovery';

    /** @var string Operator - value greater than threshold */
    const OP_GREATER_THAN = 'gt';

    /** @var string Operator - value less than threshold */
    const OP_LESS_THAN = 'lt';

    /** @var string Operator - value equals threshold */
    const OP_EQUALS = 'eq';

    /** @var string Operator - value greater than or equal */
    const OP_GREATER_EQUAL = 'gte';

    /** @var string Operator - value less than or equal */
    const OP_LESS_EQUAL = 'lte';

    /** @var string Operator - percentage change from baseline */
    const OP_CHANGE_PERCENT = 'change_pct';

    /** @var string Operator - percentage increase from baseline */
    const OP_INCREASE_PERCENT = 'increase_pct';

    /** @var string Operator - percentage decrease from baseline */
    const OP_DECREASE_PERCENT = 'decrease_pct';

    /** @var int Default cooldown between alerts (1 hour) */
    const DEFAULT_COOLDOWN_SECONDS = 3600;

    /** @var int Minimum time between alert checks (5 minutes) */
    const MIN_CHECK_INTERVAL = 300;

    /** @var int Maximum alerts to store in history per block/report */
    const MAX_HISTORY_PER_ALERT = 100;

    /** @var int Alert history retention period (180 days) */
    const HISTORY_RETENTION_DAYS = 180;

    /**
     * Get all available operators for threshold comparison.
     *
     * @return array Associative array of operator => display name
     */
    public static function get_operators(): array {
        return [
            self::OP_GREATER_THAN => get_string('alert_op_gt', 'block_adeptus_insights'),
            self::OP_LESS_THAN => get_string('alert_op_lt', 'block_adeptus_insights'),
            self::OP_EQUALS => get_string('alert_op_eq', 'block_adeptus_insights'),
            self::OP_GREATER_EQUAL => get_string('alert_op_gte', 'block_adeptus_insights'),
            self::OP_LESS_EQUAL => get_string('alert_op_lte', 'block_adeptus_insights'),
            self::OP_CHANGE_PERCENT => get_string('alert_op_change_pct', 'block_adeptus_insights'),
            self::OP_INCREASE_PERCENT => get_string('alert_op_increase_pct', 'block_adeptus_insights'),
            self::OP_DECREASE_PERCENT => get_string('alert_op_decrease_pct', 'block_adeptus_insights'),
        ];
    }

    /**
     * Get available check intervals.
     *
     * @return array Associative array of seconds => display name
     */
    public static function get_check_intervals(): array {
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
     * Create or update an alert configuration.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug to monitor
     * @param array $config Alert configuration
     * @return int|false Alert ID on success, false on failure
     */
    public static function save_alert(int $blockinstanceid, string $reportslug, array $config) {
        global $DB, $USER;

        $now = time();

        // Validate required fields.
        $required = ['operator', 'metric_field'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                debugging("Missing required field: $field", DEBUG_DEVELOPER);
                return false;
            }
        }

        // At least one threshold must be set.
        if (!isset($config['warning_value']) && !isset($config['critical_value'])) {
            debugging("At least one threshold (warning or critical) must be set", DEBUG_DEVELOPER);
            return false;
        }

        // Build the record.
        $record = new \stdClass();
        $record->blockinstanceid = $blockinstanceid;
        $record->report_slug = trim($reportslug);
        $record->metric_field = $config['metric_field'] ?? 'value';
        $record->operator = $config['operator'];
        $record->warning_value = $config['warning_value'] ?? null;
        $record->critical_value = $config['critical_value'] ?? null;
        $record->check_interval = max(self::MIN_CHECK_INTERVAL, $config['check_interval'] ?? 3600);
        $record->cooldown_seconds = $config['cooldown_seconds'] ?? self::DEFAULT_COOLDOWN_SECONDS;
        $record->baseline_value = $config['baseline_value'] ?? null;
        $record->baseline_period = $config['baseline_period'] ?? 'previous'; // 'previous', 'day', 'week', 'month'
        $record->notify_roles = json_encode($config['notify_roles'] ?? []);
        $record->notify_emails = $config['notify_emails'] ?? '';
        $record->notify_email = !empty($config['notify_email']) ? 1 : 0;
        $record->notify_message = !empty($config['notify_message']) ? 1 : 0;
        $record->notify_on_warning = !empty($config['notify_on_warning']) ? 1 : 0;
        $record->notify_on_critical = !empty($config['notify_on_critical']) ? 1 : 0;
        $record->notify_on_recovery = !empty($config['notify_on_recovery']) ? 1 : 0;
        $record->enabled = isset($config['enabled']) ? ($config['enabled'] ? 1 : 0) : 1;
        $record->alert_name = $config['alert_name'] ?? '';
        $record->alert_description = $config['alert_description'] ?? '';
        $record->timemodified = $now;
        $record->modifiedby = $USER->id;

        // Check if alert already exists.
        $existing = $DB->get_record('block_adeptus_alerts', [
            'blockinstanceid' => $blockinstanceid,
            'report_slug' => $record->report_slug,
            'metric_field' => $record->metric_field,
        ]);

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('block_adeptus_alerts', $record);
            return $record->id;
        } else {
            $record->current_status = self::STATUS_OK;
            $record->last_checked = null;
            $record->last_value = null;
            $record->last_alert_time = null;
            $record->timecreated = $now;
            $record->createdby = $USER->id;
            return $DB->insert_record('block_adeptus_alerts', $record);
        }
    }

    /**
     * Get all alerts for a block instance.
     *
     * @param int $blockinstanceid Block instance ID
     * @param bool $enabledonly Only return enabled alerts
     * @return array Array of alert records
     */
    public static function get_alerts(int $blockinstanceid, bool $enabledonly = false): array {
        global $DB;

        $params = ['blockinstanceid' => $blockinstanceid];
        $sql = "SELECT * FROM {block_adeptus_alerts} WHERE blockinstanceid = :blockinstanceid";

        if ($enabledonly) {
            $sql .= " AND enabled = 1";
        }

        $sql .= " ORDER BY report_slug, metric_field";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get a specific alert by ID.
     *
     * @param int $alertid Alert ID
     * @return object|false Alert record or false
     */
    public static function get_alert(int $alertid) {
        global $DB;
        return $DB->get_record('block_adeptus_alerts', ['id' => $alertid]);
    }

    /**
     * Delete an alert configuration.
     *
     * @param int $alertid Alert ID to delete
     * @return bool Success status
     */
    public static function delete_alert(int $alertid): bool {
        global $DB;

        // Delete associated history.
        $DB->delete_records('block_adeptus_alert_history', ['alertid' => $alertid]);

        // Delete the alert.
        return $DB->delete_records('block_adeptus_alerts', ['id' => $alertid]);
    }

    /**
     * Delete all alerts for a block instance.
     *
     * @param int $blockinstanceid Block instance ID
     * @return bool Success status
     */
    public static function delete_block_alerts(int $blockinstanceid): bool {
        global $DB;

        // Get all alert IDs for this block.
        $alerts = $DB->get_fieldset_select('block_adeptus_alerts', 'id', 'blockinstanceid = ?', [$blockinstanceid]);

        if (!empty($alerts)) {
            // Delete history for all alerts.
            list($insql, $params) = $DB->get_in_or_equal($alerts);
            $DB->delete_records_select('block_adeptus_alert_history', "alertid $insql", $params);
        }

        // Delete all alerts.
        return $DB->delete_records('block_adeptus_alerts', ['blockinstanceid' => $blockinstanceid]);
    }

    /**
     * Evaluate a metric value against an alert's thresholds.
     *
     * @param object $alert Alert record
     * @param float $value Current metric value
     * @param float|null $previousvalue Previous value for comparison operators
     * @return array Evaluation result with 'status', 'threshold_breached', 'details'
     */
    public static function evaluate_threshold($alert, float $value, ?float $previousvalue = null): array {
        $result = [
            'status' => self::STATUS_OK,
            'threshold_breached' => null,
            'threshold_type' => null,
            'details' => '',
            'value' => $value,
            'previous_value' => $previousvalue,
        ];

        // For percentage-based operators, we need a baseline.
        if (in_array($alert->operator, [self::OP_CHANGE_PERCENT, self::OP_INCREASE_PERCENT, self::OP_DECREASE_PERCENT])) {
            $baseline = $previousvalue ?? $alert->baseline_value;
            if ($baseline === null || abs($baseline) < 0.0001) {
                // No baseline available, can't evaluate percentage change.
                $result['details'] = 'No baseline value available for percentage comparison';
                return $result;
            }

            $percentchange = (($value - $baseline) / abs($baseline)) * 100;
            $result['percent_change'] = $percentchange;

            // Check critical first, then warning.
            if ($alert->critical_value !== null) {
                if (self::check_percentage_condition($alert->operator, $percentchange, $alert->critical_value)) {
                    $result['status'] = self::STATUS_CRITICAL;
                    $result['threshold_breached'] = $alert->critical_value;
                    $result['threshold_type'] = 'critical';
                    $result['details'] = sprintf('%.1f%% change exceeds critical threshold of %.1f%%',
                        $percentchange, $alert->critical_value);
                    return $result;
                }
            }

            if ($alert->warning_value !== null) {
                if (self::check_percentage_condition($alert->operator, $percentchange, $alert->warning_value)) {
                    $result['status'] = self::STATUS_WARNING;
                    $result['threshold_breached'] = $alert->warning_value;
                    $result['threshold_type'] = 'warning';
                    $result['details'] = sprintf('%.1f%% change exceeds warning threshold of %.1f%%',
                        $percentchange, $alert->warning_value);
                    return $result;
                }
            }

            return $result;
        }

        // Standard comparison operators.
        // Check critical first (more severe).
        if ($alert->critical_value !== null) {
            if (self::check_condition($alert->operator, $value, $alert->critical_value)) {
                $result['status'] = self::STATUS_CRITICAL;
                $result['threshold_breached'] = $alert->critical_value;
                $result['threshold_type'] = 'critical';
                $result['details'] = self::format_threshold_message($alert->operator, $value, $alert->critical_value);
                return $result;
            }
        }

        // Then check warning.
        if ($alert->warning_value !== null) {
            if (self::check_condition($alert->operator, $value, $alert->warning_value)) {
                $result['status'] = self::STATUS_WARNING;
                $result['threshold_breached'] = $alert->warning_value;
                $result['threshold_type'] = 'warning';
                $result['details'] = self::format_threshold_message($alert->operator, $value, $alert->warning_value);
                return $result;
            }
        }

        return $result;
    }

    /**
     * Check if a condition is met for standard operators.
     *
     * @param string $operator The comparison operator
     * @param float $value Current value
     * @param float $threshold Threshold to compare against
     * @return bool True if condition is met (threshold breached)
     */
    private static function check_condition(string $operator, float $value, float $threshold): bool {
        switch ($operator) {
            case self::OP_GREATER_THAN:
                return $value > $threshold;
            case self::OP_LESS_THAN:
                return $value < $threshold;
            case self::OP_EQUALS:
                return abs($value - $threshold) < 0.0001;
            case self::OP_GREATER_EQUAL:
                return $value >= $threshold;
            case self::OP_LESS_EQUAL:
                return $value <= $threshold;
            default:
                return false;
        }
    }

    /**
     * Check if a percentage-based condition is met.
     *
     * @param string $operator The percentage operator
     * @param float $percentchange The calculated percentage change
     * @param float $threshold The threshold percentage
     * @return bool True if condition is met
     */
    private static function check_percentage_condition(string $operator, float $percentchange, float $threshold): bool {
        switch ($operator) {
            case self::OP_CHANGE_PERCENT:
                return abs($percentchange) >= $threshold;
            case self::OP_INCREASE_PERCENT:
                return $percentchange >= $threshold;
            case self::OP_DECREASE_PERCENT:
                return $percentchange <= -$threshold;
            default:
                return false;
        }
    }

    /**
     * Format a human-readable threshold breach message.
     *
     * @param string $operator The operator used
     * @param float $value The current value
     * @param float $threshold The threshold value
     * @return string Formatted message
     */
    private static function format_threshold_message(string $operator, float $value, float $threshold): string {
        $opnames = [
            self::OP_GREATER_THAN => 'exceeds',
            self::OP_LESS_THAN => 'is below',
            self::OP_EQUALS => 'equals',
            self::OP_GREATER_EQUAL => 'is at or above',
            self::OP_LESS_EQUAL => 'is at or below',
        ];

        $opname = $opnames[$operator] ?? 'meets condition of';
        return sprintf('Value %.2f %s threshold %.2f', $value, $opname, $threshold);
    }

    /**
     * Process an alert check - evaluate and potentially trigger notifications.
     *
     * @param object $alert Alert record
     * @param float $value Current metric value
     * @param float|null $previousvalue Previous value for comparison
     * @param array $options Additional options (force_notify, skip_cooldown)
     * @return array Processing result
     */
    public static function process_alert($alert, float $value, ?float $previousvalue = null, array $options = []): array {
        global $DB, $USER;

        $now = time();
        $evaluation = self::evaluate_threshold($alert, $value, $previousvalue);
        $newstatus = $evaluation['status'];
        $oldstatus = $alert->current_status;

        $result = [
            'alert_id' => $alert->id,
            'old_status' => $oldstatus,
            'new_status' => $newstatus,
            'status_changed' => ($oldstatus !== $newstatus),
            'evaluation' => $evaluation,
            'notification_sent' => false,
            'history_id' => null,
        ];

        // Check for recovery.
        if ($oldstatus !== self::STATUS_OK && $newstatus === self::STATUS_OK) {
            $newstatus = self::STATUS_RECOVERY;
            $result['new_status'] = $newstatus;
            $result['status_changed'] = true;
        }

        // Update alert record.
        $alert->last_checked = $now;
        $alert->last_value = $value;
        $alert->current_status = ($newstatus === self::STATUS_RECOVERY) ? self::STATUS_OK : $newstatus;

        // Log to history if status changed or this is a breach continuation.
        if ($result['status_changed'] || $newstatus !== self::STATUS_OK) {
            $historyid = self::log_alert_event($alert, $newstatus, $oldstatus, $value, $evaluation);
            $result['history_id'] = $historyid;
        }

        // Check if we should send notifications.
        $shouldnotify = false;
        $skipcooldown = !empty($options['skip_cooldown']);
        $forcenotify = !empty($options['force_notify']);

        if ($result['status_changed'] || $forcenotify) {
            // Check cooldown unless bypassed.
            $cooldownok = $skipcooldown ||
                ($alert->last_alert_time === null) ||
                (($now - $alert->last_alert_time) >= $alert->cooldown_seconds);

            if ($cooldownok) {
                // Check notification preferences.
                if ($newstatus === self::STATUS_CRITICAL && $alert->notify_on_critical) {
                    $shouldnotify = true;
                } else if ($newstatus === self::STATUS_WARNING && $alert->notify_on_warning) {
                    $shouldnotify = true;
                } else if ($newstatus === self::STATUS_RECOVERY && $alert->notify_on_recovery) {
                    $shouldnotify = true;
                } else if ($forcenotify) {
                    $shouldnotify = true;
                }
            }
        }

        // Send notifications if needed.
        if ($shouldnotify) {
            $notificationresult = self::send_alert_notifications($alert, $newstatus, $evaluation);
            $result['notification_sent'] = $notificationresult['success'];
            $result['notifications_count'] = $notificationresult['count'];
            $alert->last_alert_time = $now;
        }

        // Save updated alert record.
        $alert->timemodified = $now;
        $DB->update_record('block_adeptus_alerts', $alert);

        return $result;
    }

    /**
     * Log an alert event to history.
     *
     * @param object $alert Alert record
     * @param string $newstatus New status
     * @param string $oldstatus Previous status
     * @param float $value Current metric value
     * @param array $evaluation Evaluation details
     * @return int History record ID
     */
    private static function log_alert_event($alert, string $newstatus, string $oldstatus,
            float $value, array $evaluation): int {
        global $DB;

        $record = new \stdClass();
        $record->alertid = $alert->id;
        $record->blockinstanceid = $alert->blockinstanceid;
        $record->report_slug = $alert->report_slug;
        $record->previous_status = $oldstatus;
        $record->new_status = $newstatus;
        $record->metric_value = $value;
        $record->threshold_value = $evaluation['threshold_breached'];
        $record->threshold_type = $evaluation['threshold_type'];
        $record->evaluation_details = json_encode($evaluation);
        $record->notified = 0;
        $record->timecreated = time();

        $historyid = $DB->insert_record('block_adeptus_alert_history', $record);

        // Clean up excess history entries.
        self::cleanup_excess_history($alert->id);

        return $historyid;
    }

    /**
     * Send alert notifications to configured recipients.
     *
     * @param object $alert Alert record
     * @param string $status Current alert status
     * @param array $evaluation Evaluation details
     * @return array Result with 'success' and 'count'
     */
    public static function send_alert_notifications($alert, string $status, array $evaluation): array {
        global $DB, $CFG;

        $result = ['success' => false, 'count' => 0, 'errors' => []];

        // Get block instance for context.
        $blockinstance = $DB->get_record('block_instances', ['id' => $alert->blockinstanceid]);
        if (!$blockinstance) {
            $result['errors'][] = 'Block instance not found';
            return $result;
        }

        $context = \context_block::instance($alert->blockinstanceid);

        // Build notification message.
        $messagedata = self::build_notification_message($alert, $status, $evaluation);

        $sendsuccess = 0;

        // Send Moodle messages to users with configured roles.
        if ($alert->notify_message) {
            $recipients = self::get_alert_recipients($alert, $context);
            foreach ($recipients as $user) {
                try {
                    $messagesent = self::send_moodle_message($user, $messagedata, $alert);
                    if ($messagesent) {
                        $sendsuccess++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to message user {$user->id}: " . $e->getMessage();
                }
            }
        }

        // Send emails to configured email addresses.
        if ($alert->notify_email && !empty($alert->notify_emails)) {
            $emails = self::parse_email_addresses($alert->notify_emails);
            foreach ($emails as $email) {
                try {
                    $emailsent = self::send_direct_email($email, $messagedata, $alert);
                    if ($emailsent) {
                        $sendsuccess++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to email {$email}: " . $e->getMessage();
                }
            }
        }

        // Update history to mark as notified.
        if ($sendsuccess > 0) {
            $result['success'] = true;
            $result['count'] = $sendsuccess;

            // Mark recent history as notified.
            $DB->execute(
                "UPDATE {block_adeptus_alert_history}
                 SET notified = 1
                 WHERE alertid = ? AND notified = 0
                 ORDER BY timecreated DESC LIMIT 1",
                [$alert->id]
            );
        }

        return $result;
    }

    /**
     * Parse email addresses from a string (one per line or comma-separated).
     *
     * @param string $emailstring Email addresses string
     * @return array Valid email addresses
     */
    private static function parse_email_addresses(string $emailstring): array {
        $emails = [];

        // Split by newlines, commas, or semicolons.
        $parts = preg_split('/[\n,;]+/', $emailstring);

        foreach ($parts as $part) {
            $email = trim($part);
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_unique($emails);
    }

    /**
     * Send email directly to an email address (not a Moodle user).
     *
     * @param string $email Email address
     * @param array $messagedata Message data
     * @param object $alert Alert record
     * @return bool Success status
     */
    private static function send_direct_email(string $email, array $messagedata, $alert): bool {
        global $CFG, $SITE;

        // Create a dummy user object for email_to_user().
        $recipient = new \stdClass();
        $recipient->id = -1;
        $recipient->email = $email;
        $recipient->firstname = '';
        $recipient->lastname = '';
        $recipient->maildisplay = 1;
        $recipient->mailformat = 1; // HTML format.
        $recipient->auth = 'manual';
        $recipient->suspended = 0;
        $recipient->deleted = 0;

        $noreplyuser = \core_user::get_noreply_user();

        try {
            $result = email_to_user(
                $recipient,
                $noreplyuser,
                $messagedata['subject'],
                $messagedata['body'],
                $messagedata['html']
            );
            return $result;
        } catch (\Exception $e) {
            debugging('Failed to send direct email to ' . $email . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Build the notification message content.
     *
     * @param object $alert Alert record
     * @param string $status Alert status
     * @param array $evaluation Evaluation details
     * @return array Message data with subject, body, etc.
     */
    private static function build_notification_message($alert, string $status, array $evaluation): array {
        global $CFG, $SITE;

        $statuslabels = [
            self::STATUS_OK => get_string('alert_status_ok', 'block_adeptus_insights'),
            self::STATUS_WARNING => get_string('alert_status_warning', 'block_adeptus_insights'),
            self::STATUS_CRITICAL => get_string('alert_status_critical', 'block_adeptus_insights'),
            self::STATUS_RECOVERY => get_string('alert_status_recovery', 'block_adeptus_insights'),
        ];

        $statusemojis = [
            self::STATUS_OK => '✅',
            self::STATUS_WARNING => '⚠️',
            self::STATUS_CRITICAL => '🚨',
            self::STATUS_RECOVERY => '✅',
        ];

        $alertname = !empty($alert->alert_name) ? $alert->alert_name : $alert->report_slug;

        // Subject line.
        $subject = sprintf('[%s] %s: %s',
            $SITE->shortname,
            $statusemojis[$status] ?? '📊',
            get_string('alert_notification_subject', 'block_adeptus_insights', [
                'status' => $statuslabels[$status] ?? $status,
                'alert' => $alertname,
            ])
        );

        // Build message body.
        $body = get_string('alert_notification_intro', 'block_adeptus_insights', [
            'alert' => $alertname,
            'status' => $statuslabels[$status] ?? $status,
        ]);

        $body .= "\n\n";
        $body .= get_string('alert_notification_details', 'block_adeptus_insights', [
            'report' => $alert->report_slug,
            'value' => number_format($evaluation['value'], 2),
            'threshold' => $evaluation['threshold_breached'] !== null
                ? number_format($evaluation['threshold_breached'], 2)
                : 'N/A',
            'details' => $evaluation['details'],
        ]);

        if (!empty($alert->alert_description)) {
            $body .= "\n\n" . $alert->alert_description;
        }

        // Add link to view the block/report.
        $viewurl = new \moodle_url('/my/', ['blockid' => $alert->blockinstanceid]);
        $body .= "\n\n" . get_string('alert_notification_action', 'block_adeptus_insights', $viewurl->out());

        // HTML version.
        $htmlbody = self::build_html_notification($alert, $status, $evaluation, $statuslabels, $statusemojis);

        return [
            'subject' => $subject,
            'body' => $body,
            'html' => $htmlbody,
            'status' => $status,
            'alert_name' => $alertname,
            'value' => $evaluation['value'],
        ];
    }

    /**
     * Build HTML version of notification.
     *
     * @param object $alert Alert record
     * @param string $status Alert status
     * @param array $evaluation Evaluation details
     * @param array $statuslabels Status labels
     * @param array $statusemojis Status emojis
     * @return string HTML content
     */
    private static function build_html_notification($alert, string $status, array $evaluation,
            array $statuslabels, array $statusemojis): string {
        global $CFG, $SITE;

        $statuscolors = [
            self::STATUS_OK => '#28a745',
            self::STATUS_WARNING => '#ffc107',
            self::STATUS_CRITICAL => '#dc3545',
            self::STATUS_RECOVERY => '#17a2b8',
        ];

        $color = $statuscolors[$status] ?? '#6c757d';
        $emoji = $statusemojis[$status] ?? '📊';
        $statuslabel = $statuslabels[$status] ?? $status;
        $alertname = !empty($alert->alert_name) ? $alert->alert_name : $alert->report_slug;

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$color}; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #e9ecef; }
        .metric-box { background: white; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid {$color}; }
        .metric-value { font-size: 28px; font-weight: bold; color: {$color}; }
        .metric-label { color: #6c757d; font-size: 14px; }
        .details { margin: 15px 0; padding: 10px; background: #fff; border-radius: 4px; }
        .footer { padding: 15px 20px; background: #e9ecef; border-radius: 0 0 8px 8px; font-size: 12px; color: #6c757d; }
        .btn { display: inline-block; padding: 10px 20px; background: {$color}; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$emoji} Alert: {$statuslabel}</h1>
        </div>
        <div class="content">
            <p>The alert <strong>{$alertname}</strong> has triggered.</p>

            <div class="metric-box">
                <div class="metric-value">{$evaluation['value']}</div>
                <div class="metric-label">{$alert->metric_field} for {$alert->report_slug}</div>
            </div>

            <div class="details">
                <strong>Details:</strong><br>
                {$evaluation['details']}
            </div>

            <a href="{$CFG->wwwroot}/my/" class="btn">View Dashboard</a>
        </div>
        <div class="footer">
            This alert was sent from {$SITE->fullname} ({$CFG->wwwroot})
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Get recipients for an alert based on configured roles.
     *
     * @param object $alert Alert record
     * @param \context $context Block context
     * @return array Array of user objects
     */
    private static function get_alert_recipients($alert, \context $context): array {
        global $DB;

        $recipients = [];
        $roleids = json_decode($alert->notify_roles, true) ?? [];

        if (empty($roleids)) {
            // Default to managers if no roles configured.
            $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
            if ($managerrole) {
                $roleids = [$managerrole->id];
            }
        }

        foreach ($roleids as $roleid) {
            // Get users with this role in the block's context or parent contexts.
            $users = get_role_users($roleid, $context, true, 'u.*', null, true);
            foreach ($users as $user) {
                if (!isset($recipients[$user->id])) {
                    // Check if user has capability to receive alerts.
                    if (has_capability('block/adeptus_insights:receivealerts', $context, $user)) {
                        $recipients[$user->id] = $user;
                    }
                }
            }
        }

        return array_values($recipients);
    }

    /**
     * Send a Moodle message notification.
     *
     * @param object $user Recipient user
     * @param array $messagedata Message data
     * @param object $alert Alert record
     * @return bool Success status
     */
    private static function send_moodle_message($user, array $messagedata, $alert): bool {
        global $USER;

        $message = new \core\message\message();
        $message->component = 'block_adeptus_insights';
        $message->name = 'alertnotification';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $messagedata['subject'];
        $message->fullmessage = $messagedata['body'];
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $messagedata['html'];
        $message->smallmessage = substr($messagedata['subject'], 0, 100);
        $message->notification = 1;
        $message->contexturl = new \moodle_url('/my/');
        $message->contexturlname = get_string('dashboard');

        try {
            $messageid = message_send($message);
            return !empty($messageid);
        } catch (\Exception $e) {
            debugging('Failed to send Moodle message: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Send an email notification.
     *
     * @param object $user Recipient user
     * @param array $messagedata Message data
     * @param object $alert Alert record
     * @return bool Success status
     */
    private static function send_email_notification($user, array $messagedata, $alert): bool {
        global $CFG;

        $noreplyuser = \core_user::get_noreply_user();

        try {
            $result = email_to_user(
                $user,
                $noreplyuser,
                $messagedata['subject'],
                $messagedata['body'],
                $messagedata['html']
            );
            return $result;
        } catch (\Exception $e) {
            debugging('Failed to send email: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Get alert history for a specific alert.
     *
     * @param int $alertid Alert ID
     * @param int $limit Maximum records to return
     * @return array Array of history records
     */
    public static function get_alert_history(int $alertid, int $limit = 50): array {
        global $DB;

        return $DB->get_records('block_adeptus_alert_history',
            ['alertid' => $alertid],
            'timecreated DESC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Get recent alert history for a block instance.
     *
     * @param int $blockinstanceid Block instance ID
     * @param int $limit Maximum records to return
     * @return array Array of history records with alert info
     */
    public static function get_block_alert_history(int $blockinstanceid, int $limit = 50): array {
        global $DB;

        $sql = "SELECT h.*, a.alert_name, a.report_slug as alert_report
                FROM {block_adeptus_alert_history} h
                JOIN {block_adeptus_alerts} a ON h.alertid = a.id
                WHERE h.blockinstanceid = :blockinstanceid
                ORDER BY h.timecreated DESC";

        return $DB->get_records_sql($sql, ['blockinstanceid' => $blockinstanceid], 0, $limit);
    }

    /**
     * Get current alert status summary for a block instance.
     *
     * @param int $blockinstanceid Block instance ID
     * @return array Status summary with counts and active alerts
     */
    public static function get_block_alert_status(int $blockinstanceid): array {
        global $DB;

        $alerts = self::get_alerts($blockinstanceid, true);

        $summary = [
            'total' => count($alerts),
            'ok' => 0,
            'warning' => 0,
            'critical' => 0,
            'active_alerts' => [],
            'highest_severity' => self::STATUS_OK,
        ];

        foreach ($alerts as $alert) {
            switch ($alert->current_status) {
                case self::STATUS_WARNING:
                    $summary['warning']++;
                    $summary['active_alerts'][] = $alert;
                    if ($summary['highest_severity'] === self::STATUS_OK) {
                        $summary['highest_severity'] = self::STATUS_WARNING;
                    }
                    break;
                case self::STATUS_CRITICAL:
                    $summary['critical']++;
                    $summary['active_alerts'][] = $alert;
                    $summary['highest_severity'] = self::STATUS_CRITICAL;
                    break;
                default:
                    $summary['ok']++;
            }
        }

        return $summary;
    }

    /**
     * Clean up excess history entries for an alert.
     *
     * @param int $alertid Alert ID
     */
    private static function cleanup_excess_history(int $alertid): void {
        global $DB;

        $count = $DB->count_records('block_adeptus_alert_history', ['alertid' => $alertid]);

        if ($count > self::MAX_HISTORY_PER_ALERT) {
            // Get IDs to keep (most recent).
            $keepids = $DB->get_fieldset_sql(
                "SELECT id FROM {block_adeptus_alert_history}
                 WHERE alertid = ?
                 ORDER BY timecreated DESC
                 LIMIT ?",
                [$alertid, self::MAX_HISTORY_PER_ALERT]
            );

            if (!empty($keepids)) {
                list($insql, $params) = $DB->get_in_or_equal($keepids, SQL_PARAMS_NAMED, 'id', false);
                $params['alertid'] = $alertid;
                $DB->delete_records_select('block_adeptus_alert_history',
                    "alertid = :alertid AND id $insql", $params);
            }
        }
    }

    /**
     * Clean up old history entries across all alerts.
     *
     * @return int Number of records deleted
     */
    public static function cleanup_old_history(): int {
        global $DB;

        $cutoff = time() - (self::HISTORY_RETENTION_DAYS * 86400);

        return $DB->delete_records_select(
            'block_adeptus_alert_history',
            'timecreated < ?',
            [$cutoff]
        );
    }

    /**
     * Check if an alert needs to be evaluated based on check interval.
     *
     * @param object $alert Alert record
     * @return bool True if alert should be checked
     */
    public static function should_check_alert($alert): bool {
        if (!$alert->enabled) {
            return false;
        }

        if ($alert->last_checked === null) {
            return true;
        }

        $elapsed = time() - $alert->last_checked;
        return $elapsed >= $alert->check_interval;
    }

    /**
     * Process all due alerts for a block instance.
     *
     * Called from scheduled task or real-time when KPI values are saved.
     *
     * @param int $blockinstanceid Block instance ID
     * @param array $kpivalues Associative array of report_slug => value
     * @return array Processing results
     */
    public static function process_block_alerts(int $blockinstanceid, array $kpivalues): array {
        $results = [];
        $alerts = self::get_alerts($blockinstanceid, true);

        foreach ($alerts as $alert) {
            // Check if this alert's report has a value.
            if (!isset($kpivalues[$alert->report_slug])) {
                continue;
            }

            // Check if it's time to evaluate this alert.
            if (!self::should_check_alert($alert)) {
                continue;
            }

            $value = $kpivalues[$alert->report_slug];

            // Get previous value for comparison operators.
            $previousvalue = null;
            if (in_array($alert->operator,
                    [self::OP_CHANGE_PERCENT, self::OP_INCREASE_PERCENT, self::OP_DECREASE_PERCENT])) {
                $previousvalue = kpi_history_manager::get_previous_value($blockinstanceid, $alert->report_slug);
            }

            $results[$alert->id] = self::process_alert($alert, $value, $previousvalue);
        }

        return $results;
    }
}
