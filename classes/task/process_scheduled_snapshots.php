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
 * Scheduled task for processing KPI snapshots.
 *
 * This task runs periodically to execute scheduled report snapshots
 * and post the results to the backend API for history tracking.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights\task;

/**
 * Process scheduled snapshots task.
 *
 * Runs every 5 minutes to check for and execute due snapshot schedules.
 */
class process_scheduled_snapshots extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_process_snapshots', 'block_adeptus_insights');
    }

    /**
     * Execute the task.
     *
     * Finds all due snapshot schedules and executes them.
     */
    public function execute() {
        global $CFG;

        mtrace('Adeptus Insights: Processing scheduled snapshots...');

        // Load the scheduler manager.
        require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/snapshot_scheduler.php');
        $scheduler = new \block_adeptus_insights\snapshot_scheduler();

        // Check if snapshots feature is enabled.
        if (!$scheduler->is_snapshots_enabled()) {
            mtrace('  Snapshots feature is not enabled for this installation.');
            return;
        }

        // Get all due schedules.
        $dueschedules = $scheduler->get_due_schedules();
        $count = count($dueschedules);

        if ($count === 0) {
            mtrace('  No scheduled snapshots due.');
            return;
        }

        mtrace("  Found {$count} scheduled snapshot(s) due for execution.");

        $successcount = 0;
        $failcount = 0;

        foreach ($dueschedules as $schedule) {
            mtrace("  Processing schedule ID {$schedule->id} for report '{$schedule->report_slug}'...");

            $success = $scheduler->execute_snapshot($schedule);

            if ($success) {
                $successcount++;
            } else {
                $failcount++;
            }
        }

        mtrace("Adeptus Insights: Completed - {$successcount} successful, {$failcount} failed.");
    }
}
