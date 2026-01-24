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
 * External function for registering snapshot schedules.
 *
 * Called from JavaScript when a report is first executed to register
 * the report for automated cron-based snapshot execution.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * External function for registering snapshot schedules.
 */
class register_snapshot_schedule extends external_api {
    /**
     * Define parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'blockinstanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'reportslug' => new external_value(PARAM_TEXT, 'Report slug/identifier'),
            'reportsource' => new external_value(PARAM_ALPHA, 'Report source: wizard or ai'),
            'intervalseconds' => new external_value(PARAM_INT, 'Snapshot interval in seconds'),
            'rowcount' => new external_value(PARAM_INT, 'Initial row count from first execution', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param string $reportsource Report source
     * @param int $intervalseconds Interval in seconds
     * @param int $rowcount Initial row count
     * @return array Result
     */
    public static function execute($blockinstanceid, $reportslug, $reportsource, $intervalseconds, $rowcount = 0) {
        global $CFG, $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'blockinstanceid' => $blockinstanceid,
            'reportslug' => $reportslug,
            'reportsource' => $reportsource,
            'intervalseconds' => $intervalseconds,
            'rowcount' => $rowcount,
        ]);

        // Verify block instance exists.
        $blockinstance = $DB->get_record('block_instances', ['id' => $params['blockinstanceid']]);
        if (!$blockinstance) {
            return [
                'success' => false,
                'message' => get_string('error_block_not_found', 'block_adeptus_insights'),
            ];
        }

        // Get block context and verify capability.
        $context = \context_block::instance($params['blockinstanceid']);
        self::validate_context($context);

        // Require capability to edit the block.
        require_capability('block/adeptus_insights:addinstance', $context);

        // Validate source.
        if (!in_array($params['reportsource'], ['wizard', 'ai'])) {
            return [
                'success' => false,
                'message' => get_string('error_invalid_report_source', 'block_adeptus_insights'),
            ];
        }

        // Validate interval (minimum 5 minutes, maximum 1 week).
        $intervalseconds = max(300, min(604800, $params['intervalseconds']));

        // Load scheduler and register.
        require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/snapshot_scheduler.php');
        $scheduler = new \block_adeptus_insights\snapshot_scheduler();

        // Check if snapshots are enabled.
        if (!$scheduler->is_snapshots_enabled()) {
            return [
                'success' => false,
                'message' => get_string('error_snapshots_disabled', 'block_adeptus_insights'),
            ];
        }

        try {
            $success = $scheduler->register_report(
                $params['blockinstanceid'],
                $params['reportslug'],
                $params['reportsource'],
                $intervalseconds,
                $params['rowcount']
            );

            if ($success) {
                return [
                    'success' => true,
                    'message' => get_string('snapshot_registered_success', 'block_adeptus_insights'),
                ];
            }

            return [
                'success' => false,
                'message' => get_string('snapshot_registered_failed', 'block_adeptus_insights'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Define return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether registration succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
