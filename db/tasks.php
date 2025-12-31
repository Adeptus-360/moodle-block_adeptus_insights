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
 * Scheduled task definitions for Adeptus Insights block.
 *
 * @package    block_adeptus_insights
 * @copyright  2025 Adeptus Analytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    // Cleanup old KPI history entries (runs daily at 3 AM).
    [
        'classname' => 'block_adeptus_insights\task\cleanup_kpi_history',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Check alert thresholds (runs every 15 minutes).
    [
        'classname' => 'block_adeptus_insights\task\check_alert_thresholds',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Cleanup old alert history entries (runs daily at 4 AM).
    [
        'classname' => 'block_adeptus_insights\task\cleanup_alert_history',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '4',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
