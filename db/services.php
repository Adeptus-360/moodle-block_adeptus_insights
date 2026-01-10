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
    // Save KPI value to history.
    'block_adeptus_insights_save_kpi_history' => [
        'classname'     => 'block_adeptus_insights\external\save_kpi_history',
        'methodname'    => 'execute',
        'description'   => 'Save a KPI value to history for trend tracking',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/adeptus_insights:view',
    ],

    // Get KPI history for trend and sparkline.
    'block_adeptus_insights_get_kpi_history' => [
        'classname'     => 'block_adeptus_insights\external\get_kpi_history',
        'methodname'    => 'execute',
        'description'   => 'Get KPI history for trend calculation and sparkline',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/adeptus_insights:view',
    ],

    // Search reports for autocomplete.
    'block_adeptus_insights_search_reports' => [
        'classname'     => 'block_adeptus_insights\external\search_reports',
        'methodname'    => 'execute',
        'description'   => 'Search available reports for alert configuration autocomplete',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/adeptus_insights:addinstance',
    ],
];
