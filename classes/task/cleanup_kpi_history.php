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

namespace block_adeptus_insights\task;

defined('MOODLE_INTERNAL') || die();

use block_adeptus_insights\kpi_history_manager;

/**
 * Scheduled task to cleanup old KPI history entries.
 *
 * @package    block_adeptus_insights
 * @copyright  2025 Adeptus Analytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_kpi_history extends \core\task\scheduled_task {

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_kpi_history', 'block_adeptus_insights');
    }

    /**
     * Execute the task.
     *
     * This task removes KPI history entries older than the retention period
     * (default 90 days) to keep the database clean.
     */
    public function execute(): void {
        $deleted = kpi_history_manager::cleanup_old_entries();

        if ($deleted > 0) {
            mtrace("Cleaned up {$deleted} old KPI history entries.");
        } else {
            mtrace("No old KPI history entries to clean up.");
        }
    }
}
