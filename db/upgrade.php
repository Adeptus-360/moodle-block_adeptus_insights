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

/**
 * Upgrade the block_adeptus_insights plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_block_adeptus_insights_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add snapshot scheduling table (version 2026011900).
    if ($oldversion < 2026011900) {
        // Define table block_adeptus_snap_sched to be created.
        $table = new xmldb_table('block_adeptus_snap_sched');

        // Adding fields to table.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_slug', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_source', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'wizard');
        $table->add_field('interval_seconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '86400');
        $table->add_field('last_snapshot_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('next_snapshot_due', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('last_row_count', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);

        // Adding indexes to table.
        $table->add_index('report_slug', XMLDB_INDEX_NOTUNIQUE, ['report_slug']);
        $table->add_index('next_due_active', XMLDB_INDEX_NOTUNIQUE, ['next_snapshot_due', 'is_active']);
        $table->add_index('block_report', XMLDB_INDEX_UNIQUE, ['blockinstanceid', 'report_slug']);

        // Conditionally launch create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Block_adeptus_insights savepoint reached.
        upgrade_block_savepoint(true, 2026011900, 'adeptus_insights');
    }

    // Add alert log table for notification cooldown tracking (version 2026011903).
    if ($oldversion < 2026011903) {
        // Define table block_adeptus_alert_log to be created.
        $table = new xmldb_table('block_adeptus_alert_log');

        // Adding fields to table.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('alert_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_slug', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('alert_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('triggered_value', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('threshold_value', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('cooldown_seconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3600');
        $table->add_field('cooldown_expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);

        // Adding indexes to table.
        $table->add_index('alert_cooldown', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'alert_id', 'cooldown_expires']);
        $table->add_index('block_alert', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'alert_id']);

        // Conditionally launch create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Block_adeptus_insights savepoint reached.
        upgrade_block_savepoint(true, 2026011903, 'adeptus_insights');
    }

    // Update alert log table: add severity, remove cooldown fields (version 2026011904).
    if ($oldversion < 2026011904) {
        $table = new xmldb_table('block_adeptus_alert_log');

        // Add severity field if it doesn't exist.
        $field = new xmldb_field('severity', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'warning', 'alert_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop old indexes before modifying.
        $index = new xmldb_index('alert_cooldown', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'alert_id', 'cooldown_expires']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Drop cooldown_seconds field (no longer needed in fire-once model).
        $field = new xmldb_field('cooldown_seconds');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Drop cooldown_expires field (no longer needed in fire-once model).
        $field = new xmldb_field('cooldown_expires');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Add new unique index for fire-once per severity.
        $index = new xmldb_index('block_alert_severity', XMLDB_INDEX_UNIQUE, ['blockinstanceid', 'alert_id', 'severity']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Block_adeptus_insights savepoint reached.
        upgrade_block_savepoint(true, 2026011904, 'adeptus_insights');
    }

    return true;
}
