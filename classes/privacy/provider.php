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
 * Privacy provider for the Adeptus Insights block.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider implementation for block_adeptus_insights.
 *
 * This plugin stores block configuration data and alert logs associated with
 * block instances, not individual users. No personal user data is collected
 * or stored by this plugin.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{
    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // This plugin does not store any personal user data.
        // The tables store block instance configuration and system logs only.
        $collection->add_database_table(
            'block_adeptus_insights_snap_sched',
            [
                'blockinstanceid' => 'privacy:metadata:block_adeptus_insights_snap_sched:blockinstanceid',
                'report_slug' => 'privacy:metadata:block_adeptus_insights_snap_sched:report_slug',
                'interval_seconds' => 'privacy:metadata:block_adeptus_insights_snap_sched:interval_seconds',
            ],
            'privacy:metadata:block_adeptus_insights_snap_sched'
        );

        $collection->add_database_table(
            'block_adeptus_insights_alert_log',
            [
                'blockinstanceid' => 'privacy:metadata:block_adeptus_insights_alert_log:blockinstanceid',
                'alert_id' => 'privacy:metadata:block_adeptus_insights_alert_log:alert_id',
                'severity' => 'privacy:metadata:block_adeptus_insights_alert_log:severity',
            ],
            'privacy:metadata:block_adeptus_insights_alert_log'
        );

        // External system for backend API.
        $collection->add_external_location_link(
            'adeptus_backend',
            [
                'report_data' => 'privacy:metadata:adeptus_backend:report_data',
            ],
            'privacy:metadata:adeptus_backend'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * This plugin does not store personal user data, so this returns an empty contextlist.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Get the list of users who have data within a context.
     *
     * This plugin does not store personal user data.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) {
        // This plugin does not store personal user data.
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * This plugin does not store personal user data.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // This plugin does not store personal user data.
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * This plugin does not store personal user data.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // This plugin does not store personal user data.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * This plugin does not store personal user data.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // This plugin does not store personal user data.
    }

    /**
     * Delete multiple users within a single context.
     *
     * This plugin does not store personal user data.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // This plugin does not store personal user data.
    }
}
