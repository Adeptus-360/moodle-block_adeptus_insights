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
 * KPI History Manager - handles storage and retrieval of KPI historical values.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kpi_history_manager {
    /** @var int Default minimum seconds between data points to avoid duplicates */
    const DEFAULT_INTERVAL_SECONDS = 3600; // 1 hour

    /** @var int Maximum data points to keep per KPI */
    const MAX_HISTORY_POINTS = 30;

    /** @var int Days to retain history data */
    const RETENTION_DAYS = 90;

    /**
     * Save a KPI value to history.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param float $value The KPI metric value
     * @param array $options Additional options (source, label, row_count, context_type, context_id, interval)
     * @return bool|int Record ID on success, false if skipped (too recent) or failed
     */
    public static function save_value(int $blockinstanceid, string $reportslug, float $value, array $options = []) {
        global $DB, $USER;

        // Get configured interval or use default.
        $interval = $options['interval'] ?? self::DEFAULT_INTERVAL_SECONDS;

        // Check if we should save (not too recent based on configured interval).
        $lastentry = self::get_last_entry($blockinstanceid, $reportslug);
        if ($lastentry) {
            $timesince = time() - $lastentry->timecreated;
            if ($timesince < $interval) {
                // Too recent, skip saving but return the last entry ID.
                return false;
            }
        }

        $record = new \stdClass();
        $record->blockinstanceid = $blockinstanceid;
        $record->report_slug = $reportslug;
        $record->report_source = $options['source'] ?? 'wizard';
        $record->metric_value = $value;
        $record->metric_label = $options['label'] ?? null;
        $record->row_count = $options['row_count'] ?? 0;
        $record->context_type = $options['context_type'] ?? 'site';
        $record->context_id = $options['context_id'] ?? 0;
        $record->userid = $USER->id;
        $record->timecreated = time();

        try {
            $id = $DB->insert_record('block_adeptus_kpi_history', $record);

            // Cleanup old entries for this KPI if we have too many.
            self::cleanup_excess_entries($blockinstanceid, $reportslug);

            return $id;
        } catch (\Exception $e) {
            debugging('Failed to save KPI history: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Get the last entry for a specific KPI.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @return object|false The last entry or false if none
     */
    public static function get_last_entry(int $blockinstanceid, string $reportslug) {
        global $DB;

        return $DB->get_record_sql(
            'SELECT * FROM {block_adeptus_kpi_history}
             WHERE blockinstanceid = ? AND report_slug = ?
             ORDER BY timecreated DESC
             LIMIT 1',
            [$blockinstanceid, $reportslug]
        );
    }

    /**
     * Get the previous (second most recent) value for a KPI.
     *
     * Used for percentage-based alert calculations that compare current vs previous.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @return float|null Previous value or null if not available
     */
    public static function get_previous_value(int $blockinstanceid, string $reportslug): ?float {
        global $DB;

        $result = $DB->get_field_sql(
            'SELECT metric_value FROM {block_adeptus_kpi_history}
             WHERE blockinstanceid = ? AND report_slug = ?
             ORDER BY timecreated DESC
             LIMIT 1 OFFSET 1',
            [$blockinstanceid, $reportslug]
        );

        return $result !== false ? (float)$result : null;
    }

    /**
     * Get KPI history for trend calculation and sparklines.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param int $limit Maximum number of entries to return
     * @return array Array of history entries (oldest first)
     */
    public static function get_history(int $blockinstanceid, string $reportslug, int $limit = 10): array {
        global $DB;

        $records = $DB->get_records_sql(
            'SELECT id, metric_value, metric_label, row_count, timecreated
             FROM {block_adeptus_kpi_history}
             WHERE blockinstanceid = ? AND report_slug = ?
             ORDER BY timecreated DESC
             LIMIT ?',
            [$blockinstanceid, $reportslug, $limit]
        );

        // Return in chronological order (oldest first) for sparkline rendering.
        return array_reverse(array_values($records));
    }

    /**
     * Calculate trend data from history.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param float $currentvalue Current KPI value
     * @return array Trend data with direction, percentage, and previous value
     */
    public static function calculate_trend(int $blockinstanceid, string $reportslug, float $currentvalue): array {
        $history = self::get_history($blockinstanceid, $reportslug, 2);

        $trend = [
            'direction' => 'neutral',
            'percentage' => 0,
            'previous_value' => null,
            'has_history' => false,
        ];

        if (empty($history)) {
            return $trend;
        }

        // Get the previous value (last in history).
        $previousentry = end($history);
        $previousvalue = (float) $previousentry->metric_value;
        $trend['previous_value'] = $previousvalue;
        $trend['has_history'] = true;

        // Calculate percentage change.
        if ($previousvalue != 0) {
            $change = (($currentvalue - $previousvalue) / abs($previousvalue)) * 100;
        } else if ($currentvalue > 0) {
            $change = 100;
        } else {
            $change = 0;
        }

        $trend['percentage'] = round($change, 1);

        // Determine direction (threshold of 0.5% to avoid noise).
        if ($change > 0.5) {
            $trend['direction'] = 'up';
        } else if ($change < -0.5) {
            $trend['direction'] = 'down';
        } else {
            $trend['direction'] = 'neutral';
        }

        return $trend;
    }

    /**
     * Get sparkline data for a KPI.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @param int $points Number of data points for sparkline
     * @return array Array of values for sparkline chart
     */
    public static function get_sparkline_data(int $blockinstanceid, string $reportslug, int $points = 10): array {
        $history = self::get_history($blockinstanceid, $reportslug, $points);

        return array_map(function ($entry) {
            return (float) $entry->metric_value;
        }, $history);
    }

    /**
     * Cleanup excess entries for a specific KPI (keep only MAX_HISTORY_POINTS).
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     */
    private static function cleanup_excess_entries(int $blockinstanceid, string $reportslug): void {
        global $DB;

        // Count entries.
        $count = $DB->count_records('block_adeptus_kpi_history', [
            'blockinstanceid' => $blockinstanceid,
            'report_slug' => $reportslug,
        ]);

        if ($count <= self::MAX_HISTORY_POINTS) {
            return;
        }

        // Get IDs of entries to keep (newest ones).
        $keepids = $DB->get_fieldset_sql(
            'SELECT id FROM {block_adeptus_kpi_history}
             WHERE blockinstanceid = ? AND report_slug = ?
             ORDER BY timecreated DESC
             LIMIT ?',
            [$blockinstanceid, $reportslug, self::MAX_HISTORY_POINTS]
        );

        if (empty($keepids)) {
            return;
        }

        // Delete older entries.
        [$insql, $params] = $DB->get_in_or_equal($keepids, SQL_PARAMS_NAMED, 'keep', false);
        $params['blockid'] = $blockinstanceid;
        $params['slug'] = $reportslug;

        $DB->delete_records_select(
            'block_adeptus_kpi_history',
            "blockinstanceid = :blockid AND report_slug = :slug AND id $insql",
            $params
        );
    }

    /**
     * Cleanup old history entries across all KPIs (retention policy).
     *
     * @return int Number of records deleted
     */
    public static function cleanup_old_entries(): int {
        global $DB;

        $cutoff = time() - (self::RETENTION_DAYS * 24 * 60 * 60);

        $count = $DB->count_records_select(
            'block_adeptus_kpi_history',
            'timecreated < ?',
            [$cutoff]
        );

        if ($count > 0) {
            $DB->delete_records_select(
                'block_adeptus_kpi_history',
                'timecreated < ?',
                [$cutoff]
            );
        }

        return $count;
    }

    /**
     * Delete all history for a specific block instance (when block is deleted).
     *
     * @param int $blockinstanceid Block instance ID
     * @return bool Success
     */
    public static function delete_block_history(int $blockinstanceid): bool {
        global $DB;

        return $DB->delete_records('block_adeptus_kpi_history', [
            'blockinstanceid' => $blockinstanceid,
        ]);
    }

    /**
     * Get summary statistics for a KPI.
     *
     * @param int $blockinstanceid Block instance ID
     * @param string $reportslug Report slug
     * @return array Statistics (min, max, avg, count, first_recorded, last_recorded)
     */
    public static function get_statistics(int $blockinstanceid, string $reportslug): array {
        global $DB;

        $stats = $DB->get_record_sql(
            'SELECT
                COUNT(*) as count,
                MIN(metric_value) as min_value,
                MAX(metric_value) as max_value,
                AVG(metric_value) as avg_value,
                MIN(timecreated) as first_recorded,
                MAX(timecreated) as last_recorded
             FROM {block_adeptus_kpi_history}
             WHERE blockinstanceid = ? AND report_slug = ?',
            [$blockinstanceid, $reportslug]
        );

        return [
            'count' => (int) $stats->count,
            'min' => $stats->min_value !== null ? (float) $stats->min_value : null,
            'max' => $stats->max_value !== null ? (float) $stats->max_value : null,
            'avg' => $stats->avg_value !== null ? round((float) $stats->avg_value, 2) : null,
            'first_recorded' => $stats->first_recorded ? (int) $stats->first_recorded : null,
            'last_recorded' => $stats->last_recorded ? (int) $stats->last_recorded : null,
        ];
    }
}
