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
 * Upgrade script for the Adeptus Insights block.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the block_adeptus_insights plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_block_adeptus_insights_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Upgrade to version with KPI history table.
    if ($oldversion < 2025123000) {

        // Define table block_adeptus_kpi_history to be created.
        $table = new xmldb_table('block_adeptus_kpi_history');

        // Adding fields to table block_adeptus_kpi_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_slug', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_source', XMLDB_TYPE_CHAR, '50', null, null, null, 'wizard');
        $table->add_field('metric_value', XMLDB_TYPE_NUMBER, '15, 4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('metric_label', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('row_count', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('context_type', XMLDB_TYPE_CHAR, '50', null, null, null, 'site');
        $table->add_field('context_id', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_adeptus_kpi_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table block_adeptus_kpi_history.
        $table->add_index('block_report_time', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'report_slug', 'timecreated']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('context', XMLDB_INDEX_NOTUNIQUE, ['context_type', 'context_id']);

        // Conditionally launch create table for block_adeptus_kpi_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adeptus_insights savepoint reached.
        upgrade_block_savepoint(true, 2025123000, 'adeptus_insights');
    }

    // Upgrade to version with alert system tables.
    if ($oldversion < 2025123001) {

        // Define table block_adeptus_alerts to be created.
        $table = new xmldb_table('block_adeptus_alerts');

        // Adding fields to table block_adeptus_alerts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_slug', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('metric_field', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'value');
        $table->add_field('alert_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('alert_description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('operator', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('warning_value', XMLDB_TYPE_NUMBER, '15, 4', null, null, null, null);
        $table->add_field('critical_value', XMLDB_TYPE_NUMBER, '15, 4', null, null, null, null);
        $table->add_field('baseline_value', XMLDB_TYPE_NUMBER, '15, 4', null, null, null, null);
        $table->add_field('baseline_period', XMLDB_TYPE_CHAR, '20', null, null, null, 'previous');
        $table->add_field('check_interval', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3600');
        $table->add_field('cooldown_seconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3600');
        $table->add_field('current_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'ok');
        $table->add_field('last_checked', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('last_value', XMLDB_TYPE_NUMBER, '15, 4', null, null, null, null);
        $table->add_field('last_alert_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('notify_roles', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('notify_email', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('notify_message', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('notify_on_warning', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('notify_on_critical', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('notify_on_recovery', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table block_adeptus_alerts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_key('modifiedby', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);

        // Adding indexes to table block_adeptus_alerts.
        $table->add_index('block_report', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'report_slug']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['current_status']);
        $table->add_index('enabled_check', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'last_checked']);

        // Conditionally launch create table for block_adeptus_alerts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_adeptus_alert_history to be created.
        $table = new xmldb_table('block_adeptus_alert_history');

        // Adding fields to table block_adeptus_alert_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('alertid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_slug', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('previous_status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('new_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('metric_value', XMLDB_TYPE_NUMBER, '15, 4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('threshold_value', XMLDB_TYPE_NUMBER, '15, 4', null, null, null, null);
        $table->add_field('threshold_type', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('evaluation_details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('notified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_adeptus_alert_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('alertid', XMLDB_KEY_FOREIGN, ['alertid'], 'block_adeptus_alerts', ['id']);
        $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);

        // Adding indexes to table block_adeptus_alert_history.
        $table->add_index('alert_time', XMLDB_INDEX_NOTUNIQUE, ['alertid', 'timecreated']);
        $table->add_index('block_time', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'timecreated']);
        $table->add_index('status_time', XMLDB_INDEX_NOTUNIQUE, ['new_status', 'timecreated']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Conditionally launch create table for block_adeptus_alert_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adeptus_insights savepoint reached.
        upgrade_block_savepoint(true, 2025123001, 'adeptus_insights');
    }

    // Add notify_emails field to alerts table.
    if ($oldversion < 2025123003) {

        // Define field notify_emails to be added to block_adeptus_alerts.
        $table = new xmldb_table('block_adeptus_alerts');
        $field = new xmldb_field('notify_emails', XMLDB_TYPE_TEXT, null, null, null, null, null, 'notify_roles');

        // Conditionally launch add field notify_emails.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Adeptus_insights savepoint reached.
        upgrade_block_savepoint(true, 2025123003, 'adeptus_insights');
    }

    return true;
}
