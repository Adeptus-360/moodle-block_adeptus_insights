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

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_block;
use block_adeptus_insights\kpi_history_manager;

/**
 * External function to get KPI history for trend and sparkline.
 *
 * @package    block_adeptus_insights
 * @copyright  2025 Adeptus Analytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_kpi_history extends external_api {

    /**
     * Define parameters for execute function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blockinstanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'reportslug' => new external_value(PARAM_TEXT, 'Report slug'),
            'limit' => new external_value(PARAM_INT, 'Maximum history entries to return', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute the get KPI history function.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param int $limit Maximum entries to return
     * @return array Result with history data
     */
    public static function execute(
        int $blockinstanceid,
        string $reportslug,
        int $limit = 10
    ): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'blockinstanceid' => $blockinstanceid,
            'reportslug' => $reportslug,
            'limit' => $limit,
        ]);

        // Cap limit to reasonable maximum.
        $params['limit'] = min($params['limit'], 50);

        // Verify block instance exists and get context.
        $blockinstance = $DB->get_record('block_instances', ['id' => $params['blockinstanceid']], '*', MUST_EXIST);
        $context = context_block::instance($params['blockinstanceid']);

        // Check capability.
        self::validate_context($context);
        require_capability('block/adeptus_insights:view', $context);

        // Get history.
        $history = kpi_history_manager::get_history(
            $params['blockinstanceid'],
            $params['reportslug'],
            $params['limit']
        );

        // Format history for response.
        $historydata = [];
        foreach ($history as $entry) {
            $historydata[] = [
                'id' => (int) $entry->id,
                'value' => (float) $entry->metric_value,
                'label' => $entry->metric_label ?? '',
                'row_count' => (int) ($entry->row_count ?? 0),
                'timestamp' => (int) $entry->timecreated,
            ];
        }

        // Get sparkline data (just values).
        $sparklinedata = array_map(function($h) {
            return $h['value'];
        }, $historydata);

        // Get statistics.
        $stats = kpi_history_manager::get_statistics(
            $params['blockinstanceid'],
            $params['reportslug']
        );

        return [
            'success' => true,
            'history' => $historydata,
            'sparkline' => $sparklinedata,
            'statistics' => [
                'count' => $stats['count'],
                'min' => $stats['min'] ?? 0,
                'max' => $stats['max'] ?? 0,
                'avg' => $stats['avg'] ?? 0,
                'first_recorded' => $stats['first_recorded'] ?? 0,
                'last_recorded' => $stats['last_recorded'] ?? 0,
            ],
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'history' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Record ID'),
                    'value' => new external_value(PARAM_FLOAT, 'Metric value'),
                    'label' => new external_value(PARAM_TEXT, 'Metric label'),
                    'row_count' => new external_value(PARAM_INT, 'Row count'),
                    'timestamp' => new external_value(PARAM_INT, 'Unix timestamp'),
                ]),
                'History entries (oldest first)'
            ),
            'sparkline' => new external_multiple_structure(
                new external_value(PARAM_FLOAT, 'Value'),
                'Sparkline data values'
            ),
            'statistics' => new external_single_structure([
                'count' => new external_value(PARAM_INT, 'Total history count'),
                'min' => new external_value(PARAM_FLOAT, 'Minimum value'),
                'max' => new external_value(PARAM_FLOAT, 'Maximum value'),
                'avg' => new external_value(PARAM_FLOAT, 'Average value'),
                'first_recorded' => new external_value(PARAM_INT, 'First recorded timestamp'),
                'last_recorded' => new external_value(PARAM_INT, 'Last recorded timestamp'),
            ]),
        ]);
    }
}
