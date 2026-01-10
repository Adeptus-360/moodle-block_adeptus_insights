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

use block_adeptus_insights\alert_manager;

/**
 * Scheduled task to cleanup old alert history entries.
 *
 * This task runs daily to remove alert history entries older than
 * the configured retention period (default 180 days).
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_alert_history extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_alert_history', 'block_adeptus_insights');
    }

    /**
     * Execute the scheduled task.
     *
     * Removes alert history entries older than the retention period.
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/blocks/adeptus_insights/classes/alert_manager.php');

        mtrace('Starting alert history cleanup...');

        $deleted = alert_manager::cleanup_old_history();

        mtrace("Alert history cleanup complete. Deleted $deleted old entries.");
    }
}
