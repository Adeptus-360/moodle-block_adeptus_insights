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
 * External services definitions for Adeptus Insights block.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // Search reports for autocomplete (used in alert configuration UI).
    'block_adeptus_insights_search_reports' => [
        'classname'     => 'block_adeptus_insights\external\search_reports',
        'methodname'    => 'execute',
        'description'   => 'Search available reports for alert configuration autocomplete',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/adeptus_insights:addinstance',
    ],

    // Get users by role for alert recipient selection.
    'block_adeptus_insights_get_users_by_role' => [
        'classname'     => 'block_adeptus_insights\external\get_users_by_role',
        'methodname'    => 'execute',
        'description'   => 'Get users filtered by role for alert notification recipient selection',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/adeptus_insights:addinstance',
    ],

    // Register snapshot schedule for cron-based KPI history.
    'block_adeptus_insights_register_snapshot_schedule' => [
        'classname'     => 'block_adeptus_insights\external\register_snapshot_schedule',
        'methodname'    => 'execute',
        'description'   => 'Register a report for scheduled snapshot execution via cron',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/adeptus_insights:addinstance',
    ],

    // Send alert notifications via Moodle's messaging system.
    'block_adeptus_insights_send_alert_notification' => [
        'classname'     => 'block_adeptus_insights\external\send_alert_notification',
        'methodname'    => 'execute',
        'description'   => 'Send alert notifications to users via Moodle messaging system',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/adeptus_insights:addinstance',
    ],
];
