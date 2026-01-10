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
use context_block;
use block_adeptus_insights\kpi_history_manager;

/**
 * External function to save KPI history value.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_kpi_history extends external_api {
    /**
     * Define parameters for execute function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blockinstanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'reportslug' => new external_value(PARAM_TEXT, 'Report slug'),
            'value' => new external_value(PARAM_FLOAT, 'KPI metric value'),
            'source' => new external_value(PARAM_ALPHA, 'Report source (wizard/ai)', VALUE_DEFAULT, 'wizard'),
            'label' => new external_value(PARAM_TEXT, 'Metric label', VALUE_DEFAULT, ''),
            'rowcount' => new external_value(PARAM_INT, 'Number of data rows', VALUE_DEFAULT, 0),
            'contexttype' => new external_value(PARAM_ALPHA, 'Context type', VALUE_DEFAULT, 'site'),
            'contextid' => new external_value(PARAM_INT, 'Context ID', VALUE_DEFAULT, 0),
            'interval' => new external_value(PARAM_INT, 'Minimum seconds between saves', VALUE_DEFAULT, 3600),
        ]);
    }

    /**
     * Execute the save KPI history function.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param float $value KPI metric value
     * @param string $source Report source
     * @param string $label Metric label
     * @param int $rowcount Number of data rows
     * @param string $contexttype Context type
     * @param int $contextid Context ID
     * @param int $interval Minimum seconds between saves
     * @return array Result with success status and data
     */
    public static function execute(
        int $blockinstanceid,
        string $reportslug,
        float $value,
        string $source = 'wizard',
        string $label = '',
        int $rowcount = 0,
        string $contexttype = 'site',
        int $contextid = 0,
        int $interval = 3600
    ): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'blockinstanceid' => $blockinstanceid,
            'reportslug' => $reportslug,
            'value' => $value,
            'source' => $source,
            'label' => $label,
            'rowcount' => $rowcount,
            'contexttype' => $contexttype,
            'contextid' => $contextid,
            'interval' => $interval,
        ]);

        // Verify block instance exists and get context.
        $blockinstance = $DB->get_record('block_instances', ['id' => $params['blockinstanceid']], '*', MUST_EXIST);
        $context = context_block::instance($params['blockinstanceid']);

        // Check capability.
        self::validate_context($context);
        require_capability('block/adeptus_insights:view', $context);

        // Save the KPI value.
        $result = kpi_history_manager::save_value(
            $params['blockinstanceid'],
            $params['reportslug'],
            $params['value'],
            [
                'source' => $params['source'],
                'label' => $params['label'],
                'row_count' => $params['rowcount'],
                'context_type' => $params['contexttype'],
                'context_id' => $params['contextid'],
                'interval' => $params['interval'],
            ]
        );

        // Get updated history for response.
        $history = kpi_history_manager::get_history($params['blockinstanceid'], $params['reportslug']);
        $trend = kpi_history_manager::calculate_trend(
            $params['blockinstanceid'],
            $params['reportslug'],
            $params['value']
        );

        return [
            'success' => true,
            'saved' => ($result !== false),
            'recordid' => is_int($result) ? $result : 0,
            'trend_direction' => $trend['direction'],
            'trend_percentage' => $trend['percentage'],
            'history_count' => count($history),
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
            'saved' => new external_value(PARAM_BOOL, 'Whether a new record was saved'),
            'recordid' => new external_value(PARAM_INT, 'Record ID if saved'),
            'trend_direction' => new external_value(PARAM_ALPHA, 'Trend direction (up/down/neutral)'),
            'trend_percentage' => new external_value(PARAM_FLOAT, 'Trend percentage change'),
            'history_count' => new external_value(PARAM_INT, 'Total history entries'),
        ]);
    }
}
