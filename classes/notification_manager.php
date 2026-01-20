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
 * Notification manager for Adeptus Insights block.
 *
 * Handles sending alert notifications through Moodle's messaging system.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Notification manager class.
 *
 * Sends alert notifications through Moodle's native notification system,
 * which supports popup notifications, email, and mobile push notifications.
 */
class notification_manager {
    /**
     * Send an alert notification to specified users.
     *
     * @param string $alertname Name of the alert
     * @param string $message Alert message
     * @param string $severity Alert severity ('warning', 'critical', 'recovery')
     * @param array $userids Array of user IDs to notify (if empty, notifies admins)
     * @param array $context Additional context data
     * @return array Results with success status and message IDs
     */
    public static function send_alert_notification(
        string $alertname,
        string $message,
        string $severity = 'warning',
        array $userids = [],
        array $context = []
    ): array {
        global $DB, $CFG, $SITE;

        require_once($CFG->dirroot . '/lib/messagelib.php');

        $results = [
            'success' => true,
            'sent_count' => 0,
            'message_ids' => [],
            'errors' => [],
        ];

        // Determine message provider based on severity.
        $messageprovider = 'alertnotification';
        if ($severity === 'critical') {
            $messageprovider = 'criticalalert';
        } else if ($severity === 'recovery') {
            $messageprovider = 'alertrecovery';
        }

        // If no users specified, get site admins.
        if (empty($userids)) {
            $admins = get_admins();
            $userids = array_keys($admins);
        }

        // Build the notification content.
        $subject = self::build_subject($alertname);
        $fullmessage = self::build_full_message($alertname, $message, $severity, $context);
        $fullmessagehtml = self::build_html_message($alertname, $message, $severity, $context);
        $smallmessage = self::build_small_message($alertname, $message);

        // Get a system user as the sender.
        $fromuser = \core_user::get_noreply_user();

        foreach ($userids as $userid) {
            try {
                $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*');
                if (!$user) {
                    $results['errors'][] = "User $userid not found";
                    continue;
                }

                // Create the message object.
                $eventdata = new \core\message\message();
                $eventdata->component = 'block_adeptus_insights';
                $eventdata->name = $messageprovider;
                $eventdata->userfrom = $fromuser;
                $eventdata->userto = $user;
                $eventdata->subject = $subject;
                $eventdata->fullmessage = $fullmessage;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml = $fullmessagehtml;
                $eventdata->smallmessage = $smallmessage;
                $eventdata->notification = 1;

                // Set context URL if provided.
                if (!empty($context['url'])) {
                    $eventdata->contexturl = $context['url'];
                    $eventdata->contexturlname = get_string('view_metric', 'block_adeptus_insights');
                }

                // Send the message.
                $messageid = message_send($eventdata);

                if ($messageid) {
                    $results['sent_count']++;
                    $results['message_ids'][] = $messageid;
                } else {
                    $results['errors'][] = "Failed to send notification to user $userid";
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Error sending to user $userid: " . $e->getMessage();
            }
        }

        if (!empty($results['errors'])) {
            $results['success'] = $results['sent_count'] > 0;
        }

        return $results;
    }

    /**
     * Build notification subject line.
     *
     * @param string $alertname Alert name
     * @return string Subject line
     */
    private static function build_subject(string $alertname): string {
        return get_string('alert_notification_subject', 'block_adeptus_insights', $alertname);
    }

    /**
     * Build plain text message.
     *
     * @param string $alertname Alert name
     * @param string $message Alert message
     * @param string $severity Severity level
     * @param array $context Additional context
     * @return string Plain text message
     */
    private static function build_full_message(
        string $alertname,
        string $message,
        string $severity,
        array $context
    ): string {
        global $SITE;

        $lines = [];
        $lines[] = get_string('alert_notification_greeting', 'block_adeptus_insights');
        $lines[] = '';

        switch ($severity) {
            case 'critical':
                $lines[] = get_string('alert_critical_intro', 'block_adeptus_insights');
                break;
            case 'warning':
                $lines[] = get_string('alert_warning_intro', 'block_adeptus_insights');
                break;
            case 'recovery':
                $lines[] = get_string('alert_recovery_intro', 'block_adeptus_insights');
                break;
        }

        $lines[] = '';
        $lines[] = get_string('alert_name', 'block_adeptus_insights') . ': ' . $alertname;
        $lines[] = get_string('alert_message', 'block_adeptus_insights') . ': ' . $message;

        if (!empty($context['report_name'])) {
            $lines[] = get_string('alert_report', 'block_adeptus_insights') . ': ' . $context['report_name'];
        }

        if (!empty($context['current_value'])) {
            $lines[] = get_string('alert_current_value', 'block_adeptus_insights') . ': ' . $context['current_value'];
        }

        if (!empty($context['threshold'])) {
            $lines[] = get_string('alert_threshold', 'block_adeptus_insights') . ': ' . $context['threshold'];
        }

        if (!empty($context['url'])) {
            $lines[] = '';
            $lines[] = get_string('view_metric', 'block_adeptus_insights') . ': ' . $context['url'];
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = get_string('alert_notification_footer', 'block_adeptus_insights', $SITE->fullname);
        $lines[] = '';
        $lines[] = get_string('alert_powered_by', 'block_adeptus_insights');

        return implode("\n", $lines);
    }

    /**
     * Get the Adeptus Insights logo as base64 data URI.
     *
     * @return string|null Logo data URI or null if unavailable
     */
    private static function get_logo_base64(): ?string {
        global $CFG;

        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/branding_manager.php');
            $brandingmanager = new \report_adeptus_insights\branding_manager();

            if ($brandingmanager->is_branding_available()) {
                return $brandingmanager->get_logo_base64();
            }
        } catch (\Exception $e) {
            debugging('Failed to get branding logo: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return null;
    }

    /**
     * Build HTML message.
     *
     * @param string $alertname Alert name
     * @param string $message Alert message
     * @param string $severity Severity level
     * @param array $context Additional context
     * @return string HTML message
     */
    private static function build_html_message(
        string $alertname,
        string $message,
        string $severity,
        array $context
    ): string {
        global $SITE;

        // Set colors based on severity.
        $colors = [
            'critical' => ['bg' => '#f8d7da', 'border' => '#f5c6cb', 'text' => '#721c24', 'icon' => '&#9888;'],
            'warning' => ['bg' => '#fff3cd', 'border' => '#ffeeba', 'text' => '#856404', 'icon' => '&#9888;'],
            'recovery' => ['bg' => '#d4edda', 'border' => '#c3e6cb', 'text' => '#155724', 'icon' => '&#10003;'],
        ];
        $color = $colors[$severity] ?? $colors['warning'];

        // Get the logo.
        $logo = self::get_logo_base64();

        $html = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, ' .
            'Oxygen, Ubuntu, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff;">';

        // Header banner with logo.
        $html .= '<div style="background-color: #f8f9fa; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; border-bottom: 1px solid #e9ecef;">';
        if ($logo) {
            $html .= '<img src="' . $logo . '" alt="Adeptus Insights" style="max-height: 90px; width: auto;" />';
        } else {
            $html .= '<span style="color: #1a1a2e; font-size: 24px; font-weight: 600;">Adeptus Insights</span>';
        }
        $html .= '</div>';

        // Main content area.
        $html .= '<div style="padding: 30px; border: 1px solid #e9ecef; border-top: none;">';

        // Alert box with severity indicator.
        $html .= '<div style="background-color: ' . $color['bg'] . '; border: 1px solid ' .
            $color['border'] . '; border-radius: 8px; padding: 20px; margin-bottom: 25px;">';
        $html .= '<h2 style="color: ' . $color['text'] . '; margin: 0 0 10px 0; font-size: 18px;">';
        $html .= '<span style="margin-right: 8px;">' . $color['icon'] . '</span>';
        $html .= htmlspecialchars($alertname);
        $html .= '</h2>';
        $html .= '<p style="color: ' . $color['text'] .
            '; margin: 0; font-size: 14px; line-height: 1.5;">' . htmlspecialchars($message) . '</p>';
        $html .= '</div>';

        // Details section.
        if (!empty($context['report_name']) || !empty($context['current_value']) || !empty($context['threshold'])) {
            $html .= '<div style="background-color: #f8f9fa; border-radius: 8px; ' .
                'padding: 20px; margin-bottom: 25px;">';
            $html .= '<h3 style="margin: 0 0 15px 0; font-size: 14px; color: #495057; ' .
                'text-transform: uppercase; letter-spacing: 0.5px;">' .
                get_string('alert_details', 'block_adeptus_insights') . '</h3>';
            $html .= '<table style="width: 100%; font-size: 14px; color: #495057; ' .
                'border-collapse: collapse;">';

            if (!empty($context['report_name'])) {
                $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; ' .
                    'width: 40%;"><strong>' .
                    get_string('alert_report', 'block_adeptus_insights') . '</strong></td>';
                $html .= '<td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">' .
                    htmlspecialchars($context['report_name']) . '</td></tr>';
            }
            if (!empty($context['current_value'])) {
                $html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">' .
                    '<strong>' . get_string('alert_current_value', 'block_adeptus_insights') .
                    '</strong></td>';
                $html .= '<td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">' .
                    htmlspecialchars($context['current_value']) . '</td></tr>';
            }
            if (!empty($context['threshold'])) {
                $html .= '<tr><td style="padding: 8px 0;"><strong>' .
                    get_string('alert_threshold', 'block_adeptus_insights') . '</strong></td>';
                $html .= '<td style="padding: 8px 0;">' .
                    htmlspecialchars($context['threshold']) . '</td></tr>';
            }

            $html .= '</table>';
            $html .= '</div>';
        }

        // View Metric button if URL provided.
        if (!empty($context['url'])) {
            $html .= '<div style="text-align: center; margin-bottom: 25px;">';
            $html .= '<a href="' . htmlspecialchars($context['url']) .
                '" style="display: inline-block; background-color: #0f6cbf; color: #ffffff; ' .
                'padding: 12px 30px; border-radius: 6px; text-decoration: none; ' .
                'font-size: 14px; font-weight: 500;">';
            $html .= get_string('view_metric', 'block_adeptus_insights');
            $html .= '</a>';
            $html .= '</div>';
        }

        // Site footer.
        $html .= '<div style="border-top: 1px solid #e9ecef; padding-top: 20px; font-size: 12px; color: #6c757d; text-align: center; line-height: 1.6;">';
        $html .= get_string('alert_notification_footer', 'block_adeptus_insights', htmlspecialchars($SITE->fullname));
        $html .= '</div>';

        $html .= '</div>'; // End main content area.

        // Powered by footer.
        $html .= '<div style="background-color: #f8f9fa; padding: 15px 30px; border-radius: 0 0 8px 8px; border: 1px solid #e9ecef; border-top: none; text-align: center;">';
        $html .= '<span style="font-size: 11px; color: #6c757d;">';
        $html .= get_string('alert_powered_by', 'block_adeptus_insights');
        $html .= '</span>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Build small message for mobile/popup.
     *
     * @param string $alertname Alert name
     * @param string $message Alert message
     * @return string Small message
     */
    private static function build_small_message(string $alertname, string $message): string {
        return $alertname . ': ' . $message;
    }

    /**
     * Send notifications to configured alert recipients.
     *
     * Reads the alert configuration to determine who should receive notifications.
     *
     * @param array $alert Alert configuration from backend
     * @param int $blockinstanceid Block instance ID for context
     * @return array Send results
     */
    public static function send_configured_alert(array $alert, int $blockinstanceid = 0): array {
        global $DB, $CFG;

        // Determine severity from alert data.
        $severity = 'warning';
        if (!empty($alert['severity'])) {
            $severity = $alert['severity'];
        } else if (!empty($alert['is_critical']) || !empty($alert['status']) && $alert['status'] === 'critical') {
            $severity = 'critical';
        } else if (!empty($alert['is_recovery']) || !empty($alert['status']) && $alert['status'] === 'recovery') {
            $severity = 'recovery';
        }

        // Build context.
        $context = [
            'report_name' => $alert['report_name'] ?? '',
            'current_value' => $alert['current_value'] ?? $alert['value'] ?? '',
            'threshold' => $alert['threshold'] ?? $alert['threshold_value'] ?? '',
        ];

        // Get the page URL where the block is located.
        if ($blockinstanceid > 0) {
            $blockinstance = $DB->get_record('block_instances', ['id' => $blockinstanceid]);
            if ($blockinstance) {
                $parentcontext = \context::instance_by_id($blockinstance->parentcontextid, IGNORE_MISSING);
                if ($parentcontext) {
                    // Get the actual page URL based on context type.
                    $url = self::get_page_url_from_context($parentcontext, $blockinstance);
                    if ($url) {
                        $context['url'] = $url;
                    }
                }
            }
        }

        // Collect user IDs to notify.
        $userids = [];

        // Add configured users from alert.
        if (!empty($alert['notify_users']) && is_array($alert['notify_users'])) {
            foreach ($alert['notify_users'] as $user) {
                if (!empty($user['id'])) {
                    $userids[] = (int) $user['id'];
                }
            }
        }

        // If no users configured, fall back to admins.
        if (empty($userids)) {
            $admins = get_admins();
            $userids = array_keys($admins);
        }

        // Send Moodle notifications to users.
        $result = self::send_alert_notification(
            $alert['alert_name'] ?? $alert['name'] ?? 'Alert',
            $alert['message'] ?? '',
            $severity,
            $userids,
            $context
        );

        // Also send emails to additional email addresses if configured.
        if (!empty($alert['notify_email']) && !empty($alert['notify_emails'])) {
            $emailresult = self::send_email_to_addresses(
                $alert['notify_emails'],
                $alert['alert_name'] ?? $alert['name'] ?? 'Alert',
                $alert['message'] ?? '',
                $severity,
                $context
            );

            // Merge email results.
            $result['email_sent_count'] = $emailresult['sent_count'];
            $result['email_errors'] = $emailresult['errors'];
            if (!empty($emailresult['errors'])) {
                $result['errors'] = array_merge($result['errors'], $emailresult['errors']);
            }
        }

        return $result;
    }

    /**
     * Send alert emails to a list of email addresses.
     *
     * This sends emails directly to external email addresses (not Moodle users).
     *
     * @param string $emaillist Comma or newline separated list of email addresses
     * @param string $alertname Alert name
     * @param string $message Alert message
     * @param string $severity Alert severity
     * @param array $context Additional context
     * @return array Results with sent count and errors
     */
    public static function send_email_to_addresses(
        string $emaillist,
        string $alertname,
        string $message,
        string $severity,
        array $context = []
    ): array {
        global $CFG, $SITE;

        require_once($CFG->libdir . '/moodlelib.php');

        $results = [
            'sent_count' => 0,
            'errors' => [],
        ];

        // Parse email addresses (comma or newline separated).
        $emails = preg_split('/[\s,;]+/', $emaillist, -1, PREG_SPLIT_NO_EMPTY);
        $emails = array_map('trim', $emails);
        $emails = array_filter($emails, function ($email) {
            return validate_email($email);
        });

        if (empty($emails)) {
            return $results;
        }

        // Build email content.
        $subject = self::build_subject($alertname);
        $messagetext = self::build_full_message($alertname, $message, $severity, $context);
        $messagehtml = self::build_html_message($alertname, $message, $severity, $context);

        // Get sender.
        $fromuser = \core_user::get_noreply_user();

        foreach ($emails as $email) {
            try {
                // Create a minimal fake user object for the recipient.
                $touser = new \stdClass();
                $touser->id = -1;
                $touser->email = $email;
                $touser->firstname = '';
                $touser->lastname = '';
                $touser->firstnamephonetic = '';
                $touser->lastnamephonetic = '';
                $touser->middlename = '';
                $touser->alternatename = '';
                $touser->maildisplay = 1;
                $touser->mailformat = 1; // HTML format.
                $touser->deleted = 0;
                $touser->auth = 'manual';
                $touser->suspended = 0;
                $touser->emailstop = 0;

                // Send the email.
                $success = email_to_user(
                    $touser,
                    $fromuser,
                    $subject,
                    $messagetext,
                    $messagehtml
                );

                if ($success) {
                    $results['sent_count']++;
                } else {
                    $results['errors'][] = "Failed to send email to: $email";
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Error sending email to $email: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get the actual page URL from a context.
     *
     * @param \context $parentcontext The parent context of the block
     * @param \stdClass $blockinstance The block instance record
     * @return string|null The page URL or null
     */
    private static function get_page_url_from_context(\context $parentcontext, \stdClass $blockinstance): ?string {
        global $CFG;

        $contextlevel = $parentcontext->contextlevel;

        switch ($contextlevel) {
            case CONTEXT_SYSTEM:
                // Site-level block - likely on the dashboard or front page.
                if ($blockinstance->pagetypepattern === 'my-index' || $blockinstance->pagetypepattern === 'my-*') {
                    return $CFG->wwwroot . '/my/';
                }
                return $CFG->wwwroot . '/';

            case CONTEXT_COURSE:
                // Course page.
                $courseid = $parentcontext->instanceid;
                return $CFG->wwwroot . '/course/view.php?id=' . $courseid;

            case CONTEXT_MODULE:
                // Activity module page.
                $cmid = $parentcontext->instanceid;
                return $CFG->wwwroot . '/mod/view.php?id=' . $cmid;

            case CONTEXT_USER:
                // User dashboard.
                return $CFG->wwwroot . '/my/';

            default:
                // Fall back to trying the context's get_url method.
                try {
                    return $parentcontext->get_url()->out(false);
                } catch (\Exception $e) {
                    return $CFG->wwwroot . '/my/';
                }
        }
    }

    /**
     * Check if an alert has already been triggered (fired) for a specific severity.
     *
     * Alerts fire once per severity level (warning, critical, recovery) and are then archived.
     * This allows:
     * - Warning notification to fire once
     * - Critical notification to fire once (separately)
     * - Recovery notification to fire once (if enabled)
     *
     * @param int $blockinstanceid Block instance ID
     * @param int $alertid Backend alert ID
     * @param string $severity Alert severity: 'warning', 'critical', or 'recovery'
     * @return bool True if alert was already triggered for this severity (should NOT send), false if OK to send
     */
    public static function is_alert_triggered(int $blockinstanceid, int $alertid, string $severity = 'warning'): bool {
        global $DB;

        // Check if this alert has been triggered for this severity.
        return $DB->record_exists('block_adeptus_alert_log', [
            'blockinstanceid' => $blockinstanceid,
            'alert_id' => $alertid,
            'severity' => $severity,
        ]);
    }

    /**
     * Log a sent alert notification and handle alert cycle resets.
     *
     * Alert cycle logic:
     * - When warning/critical fires: clears recovery entry (so recovery can fire later)
     * - When recovery fires: clears warning/critical entries (so they can fire again)
     *
     * This creates a proper alert cycle where alerts can fire again after recovery.
     *
     * @param int $blockinstanceid Block instance ID
     * @param int $alertid Backend alert ID
     * @param string $severity Alert severity: 'warning', 'critical', or 'recovery'
     * @param string $reportslug Report slug
     * @param string $alertname Alert name
     * @param string $triggeredvalue Value that triggered the alert
     * @param string $thresholdvalue Threshold that was exceeded
     * @return int The ID of the created log record
     */
    public static function log_alert_triggered(
        int $blockinstanceid,
        int $alertid,
        string $severity,
        string $reportslug,
        string $alertname = '',
        string $triggeredvalue = '',
        string $thresholdvalue = ''
    ): int {
        global $DB;

        $now = time();

        // Handle alert cycle resets based on severity.
        if ($severity === 'recovery') {
            // Recovery fired - clear warning and critical so they can fire again.
            $DB->delete_records('block_adeptus_alert_log', [
                'blockinstanceid' => $blockinstanceid,
                'alert_id' => $alertid,
                'severity' => 'warning',
            ]);
            $DB->delete_records('block_adeptus_alert_log', [
                'blockinstanceid' => $blockinstanceid,
                'alert_id' => $alertid,
                'severity' => 'critical',
            ]);
        } else {
            // Warning or critical fired - clear recovery so it can fire again later.
            $DB->delete_records('block_adeptus_alert_log', [
                'blockinstanceid' => $blockinstanceid,
                'alert_id' => $alertid,
                'severity' => 'recovery',
            ]);
        }

        // Log this alert trigger.
        $record = new \stdClass();
        $record->blockinstanceid = $blockinstanceid;
        $record->alert_id = $alertid;
        $record->severity = $severity;
        $record->report_slug = $reportslug;
        $record->alert_name = $alertname;
        $record->triggered_value = $triggeredvalue;
        $record->threshold_value = $thresholdvalue;
        $record->timecreated = $now;

        return $DB->insert_record('block_adeptus_alert_log', $record);
    }

    /**
     * Clean up old triggered alert log entries.
     *
     * Called periodically to remove old entries for housekeeping.
     * Note: This removes historical records, so alerts could potentially
     * fire again if recreated. Use with caution.
     *
     * @param int $retentiondays Number of days to retain entries (default 365)
     * @return int Number of records deleted
     */
    public static function cleanup_old_logs(int $retentiondays = 365): int {
        global $DB;

        // Delete entries older than retention period.
        $cutoff = time() - ($retentiondays * 86400);

        return $DB->delete_records_select(
            'block_adeptus_alert_log',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
    }

    /**
     * Clear all alert logs for a specific block instance.
     *
     * Called when block is deleted or alerts are reconfigured.
     *
     * @param int $blockinstanceid Block instance ID
     * @return bool True on success
     */
    public static function clear_block_alert_logs(int $blockinstanceid): bool {
        global $DB;

        return $DB->delete_records('block_adeptus_alert_log', ['blockinstanceid' => $blockinstanceid]);
    }

    /**
     * Clear alert log for a specific alert.
     *
     * Called when an alert is deleted or modified.
     *
     * @param int $blockinstanceid Block instance ID
     * @param int $alertid Alert ID
     * @return bool True on success
     */
    public static function clear_alert_log(int $blockinstanceid, int $alertid): bool {
        global $DB;

        return $DB->delete_records('block_adeptus_alert_log', [
            'blockinstanceid' => $blockinstanceid,
            'alert_id' => $alertid,
        ]);
    }
}
