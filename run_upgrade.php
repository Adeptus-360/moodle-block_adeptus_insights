<?php
// Direct upgrade script for Adeptus Insights block.
// Run with: php blocks/adeptus_insights/run_upgrade.php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->libdir . '/adminlib.php');

$dbman = $DB->get_manager();

echo "Adeptus Insights Block - Direct Upgrade Script\n";
echo "===============================================\n\n";

// Get current version.
$currentversion = $DB->get_field('config_plugins', 'value', [
    'plugin' => 'block_adeptus_insights',
    'name' => 'version'
]);

echo "Current version: " . ($currentversion ?: 'Not installed') . "\n";
echo "Target version: 2025123002\n\n";

// Check if alerts table exists.
$alertstable = new xmldb_table('block_adeptus_alerts');
if ($dbman->table_exists($alertstable)) {
    echo "Table block_adeptus_alerts already exists.\n";
} else {
    echo "Creating table block_adeptus_alerts...\n";

    $table = new xmldb_table('block_adeptus_alerts');
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

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);
    $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
    $table->add_key('modifiedby', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);

    $table->add_index('block_report', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'report_slug']);
    $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['current_status']);
    $table->add_index('enabled_check', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'last_checked']);

    $dbman->create_table($table);
    echo "  Created successfully!\n";
}

// Check if alert history table exists.
$historytable = new xmldb_table('block_adeptus_alert_history');
if ($dbman->table_exists($historytable)) {
    echo "Table block_adeptus_alert_history already exists.\n";
} else {
    echo "Creating table block_adeptus_alert_history...\n";

    $table = new xmldb_table('block_adeptus_alert_history');
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

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('alertid', XMLDB_KEY_FOREIGN, ['alertid'], 'block_adeptus_alerts', ['id']);
    $table->add_key('blockinstanceid', XMLDB_KEY_FOREIGN, ['blockinstanceid'], 'block_instances', ['id']);

    $table->add_index('alert_time', XMLDB_INDEX_NOTUNIQUE, ['alertid', 'timecreated']);
    $table->add_index('block_time', XMLDB_INDEX_NOTUNIQUE, ['blockinstanceid', 'timecreated']);
    $table->add_index('status_time', XMLDB_INDEX_NOTUNIQUE, ['new_status', 'timecreated']);
    $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

    $dbman->create_table($table);
    echo "  Created successfully!\n";
}

// Update plugin version.
echo "\nUpdating plugin version to 2025123002...\n";
set_config('version', 2025123002, 'block_adeptus_insights');
echo "  Done!\n";

// Purge caches.
echo "\nPurging caches...\n";
purge_all_caches();
echo "  Done!\n";

echo "\n===============================================\n";
echo "Upgrade complete!\n";
echo "===============================================\n";
