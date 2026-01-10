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
 * Privacy Subsystem implementation for block_adeptus_insights.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_adeptus_insights.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // KPI history table - stores historical KPI values.
        $collection->add_database_table(
            'block_adeptus_kpi_history',
            [
                'userid' => 'privacy:metadata:block_adeptus_kpi_history:userid',
                'blockinstanceid' => 'privacy:metadata:block_adeptus_kpi_history:blockinstanceid',
                'report_slug' => 'privacy:metadata:block_adeptus_kpi_history:report_slug',
                'metric_value' => 'privacy:metadata:block_adeptus_kpi_history:metric_value',
                'timecreated' => 'privacy:metadata:block_adeptus_kpi_history:timecreated',
            ],
            'privacy:metadata:block_adeptus_kpi_history'
        );

        // Alerts table - stores alert configurations.
        $collection->add_database_table(
            'block_adeptus_alerts',
            [
                'createdby' => 'privacy:metadata:block_adeptus_alerts:createdby',
                'modifiedby' => 'privacy:metadata:block_adeptus_alerts:modifiedby',
                'blockinstanceid' => 'privacy:metadata:block_adeptus_alerts:blockinstanceid',
                'alert_name' => 'privacy:metadata:block_adeptus_alerts:alert_name',
                'timecreated' => 'privacy:metadata:block_adeptus_alerts:timecreated',
            ],
            'privacy:metadata:block_adeptus_alerts'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Check KPI history table.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {block_instances} bi ON bi.id = ctx.instanceid AND ctx.contextlevel = :contextlevel1
                  JOIN {block_adeptus_kpi_history} h ON h.blockinstanceid = bi.id
                 WHERE h.userid = :userid1";

        $params = [
            'userid1' => $userid,
            'contextlevel1' => CONTEXT_BLOCK,
        ];

        $contextlist->add_from_sql($sql, $params);

        // Check alerts table (createdby).
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {block_instances} bi ON bi.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {block_adeptus_alerts} a ON a.blockinstanceid = bi.id
                 WHERE a.createdby = :userid";

        $params = [
            'userid' => $userid,
            'contextlevel' => CONTEXT_BLOCK,
        ];

        $contextlist->add_from_sql($sql, $params);

        // Check alerts table (modifiedby).
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {block_instances} bi ON bi.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {block_adeptus_alerts} a ON a.blockinstanceid = bi.id
                 WHERE a.modifiedby = :userid";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }

        // Get users from KPI history.
        $sql = "SELECT DISTINCT h.userid
                  FROM {block_adeptus_kpi_history} h
                  JOIN {block_instances} bi ON bi.id = h.blockinstanceid
                 WHERE bi.id = :blockinstanceid";

        $params = ['blockinstanceid' => $context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);

        // Get users from alerts (createdby).
        $sql = "SELECT DISTINCT a.createdby as userid
                  FROM {block_adeptus_alerts} a
                  JOIN {block_instances} bi ON bi.id = a.blockinstanceid
                 WHERE bi.id = :blockinstanceid AND a.createdby IS NOT NULL";

        $userlist->add_from_sql('userid', $sql, $params);

        // Get users from alerts (modifiedby).
        $sql = "SELECT DISTINCT a.modifiedby as userid
                  FROM {block_adeptus_alerts} a
                  JOIN {block_instances} bi ON bi.id = a.blockinstanceid
                 WHERE bi.id = :blockinstanceid AND a.modifiedby IS NOT NULL";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_BLOCK) {
                continue;
            }

            // Export KPI history.
            $history = $DB->get_records('block_adeptus_kpi_history', [
                'blockinstanceid' => $context->instanceid,
                'userid' => $userid,
            ]);

            if ($history) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_adeptus_insights'), 'KPI History'],
                    (object) ['history' => array_values($history)]
                );
            }

            // Export alerts created by user.
            $alerts = $DB->get_records_select('block_adeptus_alerts',
                'blockinstanceid = :blockid AND (createdby = :userid1 OR modifiedby = :userid2)',
                ['blockid' => $context->instanceid, 'userid1' => $userid, 'userid2' => $userid]
            );

            if ($alerts) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_adeptus_insights'), 'Alerts'],
                    (object) ['alerts' => array_values($alerts)]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }

        $blockinstanceid = $context->instanceid;

        // Delete alert history first (foreign key constraint).
        $alertids = $DB->get_fieldset_select('block_adeptus_alerts', 'id',
            'blockinstanceid = :blockid', ['blockid' => $blockinstanceid]);

        if ($alertids) {
            list($insql, $inparams) = $DB->get_in_or_equal($alertids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('block_adeptus_alert_history', "alertid $insql", $inparams);
        }

        // Delete alerts.
        $DB->delete_records('block_adeptus_alerts', ['blockinstanceid' => $blockinstanceid]);

        // Delete KPI history.
        $DB->delete_records('block_adeptus_kpi_history', ['blockinstanceid' => $blockinstanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_BLOCK) {
                continue;
            }

            $blockinstanceid = $context->instanceid;

            // Delete KPI history for this user.
            $DB->delete_records('block_adeptus_kpi_history', [
                'blockinstanceid' => $blockinstanceid,
                'userid' => $userid,
            ]);

            // Anonymize alerts created/modified by this user (don't delete as others may use them).
            $DB->set_field_select('block_adeptus_alerts', 'createdby', null,
                'blockinstanceid = :blockid AND createdby = :userid',
                ['blockid' => $blockinstanceid, 'userid' => $userid]);

            $DB->set_field_select('block_adeptus_alerts', 'modifiedby', null,
                'blockinstanceid = :blockid AND modifiedby = :userid',
                ['blockid' => $blockinstanceid, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $blockinstanceid = $context->instanceid;
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete KPI history for these users.
        $params = array_merge(['blockid' => $blockinstanceid], $inparams);
        $DB->delete_records_select('block_adeptus_kpi_history',
            "blockinstanceid = :blockid AND userid $insql", $params);

        // Anonymize alerts.
        $DB->set_field_select('block_adeptus_alerts', 'createdby', null,
            "blockinstanceid = :blockid AND createdby $insql", $params);

        $DB->set_field_select('block_adeptus_alerts', 'modifiedby', null,
            "blockinstanceid = :blockid AND modifiedby $insql", $params);
    }
}
