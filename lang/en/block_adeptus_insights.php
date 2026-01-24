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
 * Language strings for the Adeptus Insights block.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name and general.
$string['pluginname'] = 'Adeptus Insights';
$string['adeptus_insights:addinstance'] = 'Add a new Adeptus Insights block';
$string['adeptus_insights:myaddinstance'] = 'Add a new Adeptus Insights block to Dashboard';
$string['adeptus_insights:view'] = 'View Adeptus Insights block content';
$string['adeptus_insights:configurealerts'] = 'Configure alert thresholds';
$string['adeptus_insights:receivealerts'] = 'Receive alert notifications';

// Block titles.
$string['quickstats'] = 'Quick Stats';
$string['availablereports'] = 'Available Reports';
$string['insightsreports'] = 'Insights Reports';

// Configuration form - General.
$string['configtitle'] = 'Block title';
$string['configtitle_desc'] = 'Leave empty to use the default title based on display mode.';
$string['configtitle_help'] = 'Enter a custom title for this block instance, or leave empty to use the default title.

**Default titles by display mode:**
- **Embedded**: The name of the selected report
- **KPI Cards**: "Quick Stats"
- **Report Links**: "Available Reports"
- **Tabbed Reports**: "Insights Reports"

A custom title allows you to provide context-specific naming, such as "Course Performance" or "Student Engagement Metrics".';
$string['configdisplaymode'] = 'Display mode';
$string['configdisplaymode_desc'] = 'Choose how reports are displayed in the block.';
$string['configdisplaymode_help'] = 'Select how reports are presented in the block:

**Embedded Report**
Displays a single report with its chart and/or data table directly in the block. Best for dashboards where you want to highlight one specific metric.

**KPI Cards**
Shows multiple metrics as compact cards with values, trend indicators, and sparkline charts. Ideal for executive dashboards and quick overviews of key performance indicators.

**Report Links**
Displays a list of clickable report names. Users click to view full reports. Best when you want to provide quick access to multiple reports without taking up dashboard space.

**Tabbed Reports**
Shows multiple reports in a tabbed interface where users can switch between them. Good for grouping related reports together in a single block.';
$string['displaymode_embedded'] = 'Embedded report (chart & table)';
$string['displaymode_kpi'] = 'KPI cards';
$string['displaymode_links'] = 'Report links';
$string['displaymode_tabs'] = 'Tabbed reports';

// Configuration form - Report filtering.
$string['allcategories'] = 'All Categories';
$string['configselectedcategory'] = 'Report category';
$string['configselectedcategory_desc'] = 'Filter reports by category.';
$string['configselectedcategory_help'] = 'Filter which reports appear in this block by their category.

**Common categories include:**
- **Enrollment**: Student registration and course enrollment data
- **Completion**: Course and activity completion statistics
- **Engagement**: User activity and interaction metrics
- **Performance**: Grades, scores, and assessment results
- **Custom**: Categories created for your organisation

Select "All Categories" to show all available reports without filtering.';

// Configuration form - Display options.
$string['configchartheight'] = 'Chart height';
$string['configchartheight_desc'] = 'Height of the chart in pixels.';

// Configuration form - Data Refresh.
$string['settings_datarefresh'] = 'Data Refresh';
$string['configautorefresh'] = 'Auto-refresh interval';
$string['configautorefresh_desc'] = 'Automatically refresh report data.';
$string['autorefresh_never'] = 'Never';
$string['autorefresh_5m'] = 'Every 5 minutes';
$string['autorefresh_15m'] = 'Every 15 minutes';
$string['autorefresh_30m'] = 'Every 30 minutes';
$string['autorefresh_1h'] = 'Every hour';

$string['configshowrefreshbutton'] = 'Show refresh button';
$string['configshowrefreshbutton_desc'] = 'Display manual refresh button.';
$string['configshowtimestamp'] = 'Show timestamp';
$string['configshowtimestamp_desc'] = 'Display "Last updated" timestamp.';

// Configuration form - Context.
$string['configcontextfilter'] = 'Context filter';
$string['configcontextfilter_desc'] = 'How to filter reports based on page context.';
$string['configcontextfilter_help'] = 'Control how report data is filtered based on context:

**Auto-detect from page**
Automatically filters data based on where the block is placed. If the block is on a course page, it shows data for that course. If on the site homepage, it shows site-wide data. This is the recommended setting for most use cases.

**Filter by course**
Always filter data for a specific course, regardless of where the block is placed. Use this when you want a block on the dashboard to show data for a particular course.

**Filter by category**
Filter data for all courses within a specific course category. Useful for department or faculty-level dashboards.

**No filtering (site-wide)**
Always show site-wide data regardless of where the block is placed. Use this for executive dashboards that need a complete overview.';
$string['contextfilter_auto'] = 'Auto-detect from page';
$string['contextfilter_manual'] = 'Manual selection';
$string['contextfilter_none'] = 'No filtering (site-wide)';

$string['configcontextcourse'] = 'Filter by course';
$string['configcontextcourse_desc'] = 'Show only data for this course.';
$string['configcontextcategory'] = 'Filter by category';
$string['configcontextcategory_desc'] = 'Show only data for this category.';

// Configuration form - Appearance.
$string['configshowcategorybadges'] = 'Show category badges';
$string['configshowcategorybadges_desc'] = 'Display category color badges on reports.';
$string['configkpicolumns'] = 'Number of KPI cards';
$string['configkpicolumns_desc'] = 'Number of KPI cards to display (1-4).';
$string['configkpireports'] = 'KPI reports';
$string['configkpireports_desc'] = 'Select which reports to display as KPI cards. Drag to reorder.';
$string['configtabsreports'] = 'Tab reports';
$string['configtabsreports_desc'] = 'Select which reports to display as tabs. Drag to reorder.';
$string['configmaxlinkitems'] = 'Max link items';
$string['configmaxlinkitems_desc'] = 'Maximum number of report links to display.';
$string['addreport'] = 'Add report';
$string['removereport'] = 'Remove report';
$string['noreportsselected'] = 'No reports selected. Click "Add report" to get started.';
$string['selectareport'] = 'Select a report';

// Block content.
$string['viewfullreport'] = 'View full report';
$string['refresh'] = 'Refresh';
$string['export'] = 'Export';
$string['exportcsv'] = 'Export CSV';
$string['exportpdf'] = 'Export PDF';
$string['lastupdated'] = 'Last updated';
$string['lastupdatedago'] = 'Last updated {$a} ago';
$string['loading'] = 'Loading...';
$string['noreports'] = 'No reports available';
$string['noreportsincategory'] = 'No reports in this category';
$string['selectreport'] = 'Select a report';
$string['showing'] = 'Showing {$a->count} of {$a->total}';
$string['prev'] = 'Prev';
$string['next'] = 'Next';
$string['page'] = 'Page';
$string['of'] = 'of';
$string['showingxofy'] = 'Showing {$a->start}-{$a->end} of {$a->total}';

// Context labels.
$string['sitelevel'] = 'Site-wide';
$string['courselevel'] = 'Course: {$a}';
$string['categorylevel'] = 'Category: {$a}';
$string['filteringby'] = 'Filtering by: {$a}';

// Report sources.
$string['wizardreport'] = 'Wizard';
$string['aireport'] = 'AI';

// Error messages.
$string['parentpluginmissing'] = 'The Adeptus Insights report plugin is required but not installed.';
$string['nopermission'] = 'You do not have permission to view this content.';
$string['errorloadingreports'] = 'Error loading reports. Please try again.';
$string['errorloadingreport'] = 'Error loading report data.';
$string['reportnotfound'] = 'Report not found.';

// Modal.
$string['closemodal'] = 'Close';
$string['reportdetails'] = 'Report Details';

// Accessibility.
$string['aria_refreshblock'] = 'Refresh block data';
$string['aria_exportcsv'] = 'Export data as CSV';
$string['aria_exportpdf'] = 'Export data as PDF';
$string['aria_openreport'] = 'Open report: {$a}';
$string['aria_closereport'] = 'Close report';

// Privacy.
$string['privacy:metadata'] = 'The Adeptus Insights block stores KPI history and alert configuration data.';
$string['privacy:metadata:block_adeptus_kpi_history'] = 'Historical KPI values for trend indicators and sparklines.';
$string['privacy:metadata:block_adeptus_kpi_history:userid'] = 'The ID of the user who triggered the KPI capture.';
$string['privacy:metadata:block_adeptus_kpi_history:blockinstanceid'] = 'The block instance ID.';
$string['privacy:metadata:block_adeptus_kpi_history:report_slug'] = 'The report identifier.';
$string['privacy:metadata:block_adeptus_kpi_history:metric_value'] = 'The recorded metric value.';
$string['privacy:metadata:block_adeptus_kpi_history:timecreated'] = 'When the value was captured.';
$string['privacy:metadata:block_adeptus_alerts'] = 'Alert threshold configurations for KPI monitoring.';
$string['privacy:metadata:block_adeptus_alerts:createdby'] = 'The ID of the user who created the alert.';
$string['privacy:metadata:block_adeptus_alerts:modifiedby'] = 'The ID of the user who last modified the alert.';
$string['privacy:metadata:block_adeptus_alerts:blockinstanceid'] = 'The block instance ID.';
$string['privacy:metadata:block_adeptus_alerts:alert_name'] = 'The name of the alert.';
$string['privacy:metadata:block_adeptus_alerts:timecreated'] = 'When the alert was created.';

// Admin settings.
$string['settings'] = 'Adeptus Insights Block Settings';
$string['settings_general'] = 'General Settings';
$string['settings_display'] = 'Display Options';
$string['enablealerts'] = 'Enable alerts';
$string['enablealerts_desc'] = 'Allow alert thresholds to be configured on blocks.';
$string['defaultrefreshinterval'] = 'Default refresh interval';
$string['defaultrefreshinterval_desc'] = 'Default auto-refresh interval for new blocks.';
$string['maxreportsperblock'] = 'Max reports per block';
$string['maxreportsperblock_desc'] = 'Maximum number of reports that can be displayed in a single block.';
$string['cacheduration'] = 'Cache duration';
$string['cacheduration_desc'] = 'How long to cache report data (in seconds).';
$string['defaultchartheight'] = 'Default chart height';
$string['defaultchartheight_desc'] = 'Default height for charts in pixels.';

// KPI History and Trends.
$string['task_process_snapshots'] = 'Process scheduled KPI snapshots';
$string['task_cleanup_kpi_history'] = 'Cleanup old KPI history entries';
$string['kpi_trend_up'] = 'Trending up';
$string['kpi_trend_down'] = 'Trending down';
$string['kpi_trend_neutral'] = 'No change';
$string['kpi_change_percentage'] = '{$a}% change';
$string['kpi_no_history'] = 'No historical data';
$string['kpi_history_points'] = '{$a} data points';
$string['kpi_first_recorded'] = 'First recorded: {$a}';
$string['kpi_last_recorded'] = 'Last recorded: {$a}';
$string['kpi_min_value'] = 'Min: {$a}';
$string['kpi_max_value'] = 'Max: {$a}';
$string['kpi_avg_value'] = 'Avg: {$a}';

// KPI History Interval Settings.
$string['configkpihistoryinterval'] = 'History save frequency';
$string['configkpihistoryinterval_help'] = 'How often to save a new KPI history data point for trend tracking and sparklines. More frequent saves provide more detailed trend data but use more storage. Less frequent saves are better for long-term trends.';
$string['kpi_interval_1h'] = 'Every hour';
$string['kpi_interval_6h'] = 'Every 6 hours';
$string['kpi_interval_12h'] = 'Every 12 hours';
$string['kpi_interval_1d'] = 'Every day';
$string['kpi_interval_3d'] = 'Every 3 days';
$string['kpi_interval_1w'] = 'Every week';
$string['kpi_interval_1m'] = 'Every month';

// Baseline Period Settings.
$string['configbaselineperiod'] = 'Baseline period';
$string['configbaselineperiod_help'] = 'The baseline period determines the reference point for calculating overall trend growth. The "overall" trend shows how much the metric has changed compared to this baseline.

**Options:**
- **All time**: Compare against the very first recorded value
- **Start of month**: Compare against the first value from this month
- **Start of week**: Compare against the first value from this week (Monday)
- **Rolling 30 days**: Compare against the value from 30 days ago
- **Rolling 7 days**: Compare against the value from 7 days ago';
$string['baseline_all_time'] = 'All time (first snapshot)';
$string['baseline_month_start'] = 'Start of this month';
$string['baseline_week_start'] = 'Start of this week';
$string['baseline_rolling_30d'] = 'Rolling 30 days';
$string['baseline_rolling_7d'] = 'Rolling 7 days';

// Alert System - Configuration Headers.
$string['config_header_alerts'] = 'Alert Configuration';
$string['config_header_alerts_desc'] = 'Configure alert thresholds to receive notifications when KPI metrics exceed defined limits.';
$string['config_header_alerts_help'] = 'The Alert System provides proactive monitoring for your KPI metrics. Configure thresholds for the metrics that matter most to your organisation, and receive notifications when they require attention.

**Example use cases:**
- Get notified when enrollment numbers reach a target milestone
- Monitor course completion rates for quality assurance
- Track engagement metrics to identify successful content
- Receive alerts when key performance indicators need review

**Configuration options:**
- **Report to monitor**: Choose which KPI/report metric to track
- **Threshold conditions**: Set warning and critical levels
- **Check frequency**: How often to evaluate the metric
- **Notification preferences**: Email, Moodle messages, or both
- **Cooldown period**: Prevent repeated notifications

You can configure multiple alerts per block, each monitoring a different metric with its own thresholds and notification settings.';

// Alert System - Scheduled Tasks.
$string['task_check_alert_thresholds'] = 'Check KPI alert thresholds';
$string['task_cleanup_alert_history'] = 'Cleanup old alert history entries';

// Alert System - Message Providers.
$string['messageprovider:alertnotification'] = 'KPI alert notifications';
$string['messageprovider:criticalalert'] = 'Critical KPI alerts';
$string['messageprovider:alertrecovery'] = 'Alert recovery notifications';

// Alert System - Status Labels.
$string['alert_status_ok'] = 'OK';
$string['alert_status_warning'] = 'Warning';
$string['alert_status_critical'] = 'Critical';
$string['alert_status_recovery'] = 'Recovered';

// Alert System - Operators.
$string['alert_op_gt'] = 'Greater than';
$string['alert_op_lt'] = 'Less than';
$string['alert_op_eq'] = 'Equals';
$string['alert_op_gte'] = 'Greater than or equal to';
$string['alert_op_lte'] = 'Less than or equal to';
$string['alert_op_change_pct'] = 'Change by percentage';
$string['alert_op_increase_pct'] = 'Increase by percentage';
$string['alert_op_decrease_pct'] = 'Decrease by percentage';

// Alert System - Check Intervals.
$string['alert_interval_5m'] = 'Every 5 minutes';
$string['alert_interval_15m'] = 'Every 15 minutes';
$string['alert_interval_30m'] = 'Every 30 minutes';
$string['alert_interval_1h'] = 'Every hour';
$string['alert_interval_2h'] = 'Every 2 hours';
$string['alert_interval_4h'] = 'Every 4 hours';
$string['alert_interval_8h'] = 'Every 8 hours';
$string['alert_interval_24h'] = 'Every 24 hours';

// Alert System - Configuration Form.
$string['config_alerts_enabled'] = 'Enable alerts for this block';
$string['config_alerts_enabled_desc'] = 'When enabled, you can configure threshold alerts for KPI metrics.';
$string['config_alerts_enabled_help'] = 'Enable the alert system to receive proactive notifications when your KPI metrics reach important thresholds.

**How it works:**
- Configure warning and critical thresholds for any monitored report
- The system checks metrics at your defined intervals (e.g., hourly, daily)
- When a threshold is crossed, notifications are sent via Moodle messaging and/or email

**Alert types:**
- **Warning** (amber): Metric has reached the warning threshold
- **Critical** (red): Metric has reached the critical threshold
- **Recovery** (green): Metric has returned to normal levels

**Best practices:**
- Choose thresholds that align with your organisation\'s goals and KPIs
- Use warning thresholds for early awareness, critical for urgent attention
- Configure appropriate cooldown periods to control notification frequency';
$string['config_add_alert'] = 'Add alert';
$string['config_remove_alert'] = 'Remove alert';
$string['config_alert_report'] = 'Report to monitor';
$string['config_alert_report_desc'] = 'Select which report/KPI to monitor for this alert.';
$string['config_alert_report_placeholder'] = 'Search reports by name, category, or type...';
$string['config_alert_name'] = 'Alert name';
$string['config_alert_name_desc'] = 'A friendly name for this alert (optional).';
$string['config_alert_description'] = 'Alert description';
$string['config_alert_description_desc'] = 'Additional context included in notifications (optional).';
$string['config_alert_operator'] = 'Condition';
$string['config_alert_operator_desc'] = 'When to trigger the alert.';
$string['config_alert_warning_value'] = 'Warning threshold';
$string['config_alert_warning_value_desc'] = 'Value that triggers a warning alert.';
$string['config_alert_critical_value'] = 'Critical threshold';
$string['config_alert_critical_value_desc'] = 'Value that triggers a critical alert.';
$string['config_alert_check_interval'] = 'Check frequency';
$string['config_alert_check_interval_desc'] = 'How often to check the threshold.';
$string['config_alert_cooldown'] = 'Notification cooldown';
$string['config_alert_cooldown_desc'] = 'Minimum time between repeated notifications for the same alert.';
$string['config_alert_notify_warning'] = 'Notify on warning';
$string['config_alert_notify_warning_desc'] = 'Send notification when warning threshold is reached.';
$string['config_alert_notify_critical'] = 'Notify on critical';
$string['config_alert_notify_critical_desc'] = 'Send notification when critical threshold is reached.';
$string['config_alert_notify_recovery'] = 'Notify on recovery';
$string['config_alert_notify_recovery_desc'] = 'Send notification when alert status returns to OK.';
$string['config_alert_notify_email'] = 'Send email notifications';
$string['config_alert_notify_email_desc'] = 'Send alerts to the email addresses specified below.';
$string['config_alert_notify_email_addresses'] = 'Email recipients';
$string['config_alert_notify_email_addresses_desc'] = 'Enter email addresses to receive alerts (one per line). Can include external stakeholders who don\'t have Moodle accounts.';
$string['config_alert_notify_email_addresses_placeholder'] = 'admin@example.com
manager@example.com
stakeholder@company.com';
$string['config_alert_notify_roles'] = 'Moodle message recipients';
$string['config_alert_notify_roles_desc'] = 'Select roles to receive Moodle notifications. Users must have accounts on this site.';
$string['config_alert_role_filter'] = 'Filter by role';
$string['config_alert_role_filter_all'] = 'All users';
$string['config_alert_role_filter_desc'] = 'Optionally filter the user list by role to find recipients more easily.';
$string['config_alert_notify_users'] = 'Select recipients';
$string['config_alert_notify_users_desc'] = 'Search and select specific users to receive notifications for this alert.';
$string['config_alert_user_search_placeholder'] = 'Search users by name or email...';
$string['config_alert_no_users_selected'] = 'No recipients selected';
$string['config_alert_users_loading'] = 'Loading users...';
$string['config_no_alerts'] = 'No alerts configured. Click "Add alert" to create one.';
$string['config_alerts_list'] = 'Configured Alerts';
$string['config_alert_name_placeholder'] = 'e.g., Low Engagement Alert';
$string['config_alert_notify_on'] = 'Notify on';
$string['no_alerts_configured'] = 'No alerts configured yet. Click "Add Alert" to create your first alert.';
$string['edit_alert'] = 'Edit alert';
$string['delete_alert'] = 'Delete alert';
$string['alert_delete_confirm'] = 'Are you sure you want to delete this alert?';
$string['add_new_alert'] = 'Add New Alert';
$string['alert_report_label'] = 'Report: {$a}';
$string['alert_check_every'] = 'Check: {$a}';
$string['alert_selected_report'] = 'Selected: {$a}';
$string['alert_for_report'] = 'Alert for: {$a}';
$string['alert_thresholds'] = 'Warning: {$a->warning}, Critical: {$a->critical}';
$string['alert_threshold_warning_only'] = 'Warning: {$a}';
$string['alert_threshold_critical_only'] = 'Critical: {$a}';
$string['alert_check_every'] = 'Check every {$a}';
$string['alert_status_badge_ok'] = 'OK';
$string['alert_status_badge_warning'] = 'Warning';
$string['alert_status_badge_critical'] = 'Critical';
$string['alert_enabled'] = 'Enabled';
$string['alert_disabled'] = 'Disabled';
$string['alert_validation_report_required'] = 'Please select a report to monitor.';
$string['alert_validation_threshold_required'] = 'At least one threshold (warning or critical) is required.';

// Alert System - Notification Messages.
$string['alert_notification_subject'] = '{$a->status}: {$a->alert}';
$string['alert_notification_intro'] = 'The alert "{$a->alert}" has changed status to {$a->status}.';
$string['alert_notification_details'] = 'Report: {$a->report}
Current value: {$a->value}
Threshold: {$a->threshold}
Details: {$a->details}';
$string['alert_notification_action'] = 'View dashboard: {$a}';

// Alert System - Visual Indicators.
$string['alerts_badge_warning'] = '{$a} warning';
$string['alerts_badge_warnings'] = '{$a} warnings';
$string['alerts_badge_critical'] = '{$a} critical';
$string['alerts_badge_criticals'] = '{$a} critical alerts';
$string['alert_indicator_warning'] = 'Warning: approaching threshold';
$string['alert_indicator_critical'] = 'Critical: threshold exceeded';
$string['alert_indicator_ok'] = 'Status OK';
$string['view_alert_details'] = 'View alert details';
$string['dismiss_alert'] = 'Dismiss alert';
$string['acknowledge_alert'] = 'Acknowledge';

// Alert System - History.
$string['alert_history'] = 'Alert History';
$string['alert_history_empty'] = 'No alert history available.';
$string['alert_triggered_at'] = 'Triggered {$a}';
$string['alert_value_was'] = 'Value was {$a->value} (threshold: {$a->threshold})';
$string['alert_status_changed'] = 'Status changed from {$a->from} to {$a->to}';

// Alert System - Info Panel Descriptions.
$string['insurance_policy_title'] = 'Proactive Monitoring';
$string['insurance_policy_desc'] = 'Set up alerts to stay informed when your KPIs reach important milestones or require attention.';
$string['insurance_example_inactive'] = 'Monitor user activity levels across your courses';
$string['insurance_example_submissions'] = 'Track submission rates and completion progress';
$string['insurance_example_grades'] = 'Watch for changes in grade distributions';
$string['insurance_example_engagement'] = 'Detect shifts in engagement patterns';

// Alert System - Cooldown Options.
$string['cooldown_15m'] = '15 minutes';
$string['cooldown_30m'] = '30 minutes';
$string['cooldown_1h'] = '1 hour';
$string['cooldown_2h'] = '2 hours';
$string['cooldown_4h'] = '4 hours';
$string['cooldown_8h'] = '8 hours';
$string['cooldown_24h'] = '24 hours';
$string['cooldown_48h'] = '48 hours';

// Feature Permissions.
$string['feature_locked'] = 'Feature Not Available';
$string['alerts_upgrade_required'] = 'Alert configuration is available on Pro and Enterprise plans. Upgrade your subscription to enable proactive monitoring and automated notifications.';
$string['upgrade_to_unlock'] = 'Upgrade to Unlock';

// Alert Notifications.
$string['alert_notification_subject'] = 'Adeptus Insights Alert: {$a}';
$string['alert_notification_greeting'] = 'Hello,';
$string['alert_critical_intro'] = 'A critical alert has been triggered that requires your immediate attention.';
$string['alert_warning_intro'] = 'A warning alert has been triggered that may require your attention.';
$string['alert_recovery_intro'] = 'Good news! A previously triggered alert has now recovered to normal levels.';
$string['alert_name'] = 'Alert';
$string['alert_message'] = 'Message';
$string['alert_report'] = 'Report';
$string['alert_current_value'] = 'Current Value';
$string['alert_threshold'] = 'Threshold';
$string['alert_details'] = 'Alert Details';
$string['alert_notification_footer'] = 'This notification was sent from {$a}. You can manage your notification preferences in your profile settings.';
$string['view_dashboard'] = 'View Dashboard';
$string['view_metric'] = 'View Metric';
$string['viewdetails'] = 'View Details';
$string['alert_powered_by'] = 'This alert was powered by Adeptus 360 - www.adeptus360.com';
$string['messageprovider:alertnotification'] = 'KPI alert notifications';
$string['messageprovider:criticalalert'] = 'Critical KPI alert notifications';
$string['messageprovider:alertrecovery'] = 'Alert recovery notifications';

// KPI Modal.
$string['daterange'] = 'Date Range';
$string['last7days'] = 'Last 7 days';
$string['last30days'] = 'Last 30 days';
$string['last90days'] = 'Last 90 days';
$string['alltime'] = 'All time';
$string['minimum'] = 'Minimum';
$string['maximum'] = 'Maximum';
$string['average'] = 'Average';
$string['datapoints'] = 'data points';
$string['errorloadingkpi'] = 'Error loading KPI data.';

// Alert Configuration notices.
$string['alerts_kpi_only_notice'] = 'These settings are only applicable when using KPI Cards display mode. Please select "KPI cards" from the Display Mode option above to configure alerts and snapshot settings.';

// UI Elements - Dropdowns and Filters.
$string['searchcategories'] = 'Search categories...';
$string['aria_searchcategories'] = 'Search categories';
$string['nocategoriesfound'] = 'No categories found';
$string['searchreports'] = 'Search reports...';
$string['aria_searchreports'] = 'Search reports';
$string['noreportsfound'] = 'No reports found';
$string['loadingreport'] = 'Loading report...';
$string['loadingreportselector'] = 'Loading report selector...';

// UI Elements - Table and Chart.
$string['table'] = 'Table';
$string['chart'] = 'Chart';
$string['rows'] = 'rows';
$string['charttype'] = 'Type';
$string['charttype_bar'] = 'Bar';
$string['charttype_line'] = 'Line';
$string['charttype_pie'] = 'Pie';
$string['charttype_doughnut'] = 'Doughnut';
$string['charttype_bar_full'] = 'Bar Chart';
$string['charttype_line_full'] = 'Line Chart';
$string['charttype_pie_full'] = 'Pie Chart';
$string['charttype_doughnut_full'] = 'Doughnut Chart';
$string['xaxis'] = 'X-Axis';
$string['yaxis'] = 'Y-Axis';
$string['xaxis_labels'] = 'X-Axis (Labels)';
$string['yaxis_values'] = 'Y-Axis (Values)';

// UI Elements - Export.
$string['exportformat_pdf'] = 'PDF';
$string['exportformat_csv'] = 'CSV';
$string['exportformat_json'] = 'JSON';
$string['exportas_pdf'] = 'Export as PDF';
$string['exportas_csv'] = 'Export as CSV';
$string['exportas_json'] = 'Export as JSON';

// Edit Form - Section Headers.
$string['email_notifications_header'] = 'Email Notifications';
$string['moodle_notifications_header'] = 'Moodle Message Notifications';

// External API Messages.
$string['error_block_not_found'] = 'Block instance not found';
$string['error_invalid_report_source'] = 'Invalid report source';
$string['error_snapshots_disabled'] = 'Snapshots feature is not enabled for this installation';
$string['snapshot_registered_success'] = 'Snapshot schedule registered successfully';
$string['snapshot_registered_failed'] = 'Failed to register snapshot schedule';

// JavaScript Strings - Error Messages.
$string['js_error_auth_failed'] = 'Authentication failed';
$string['js_error_could_not_auth'] = 'Could not authenticate';
$string['js_error_no_auth_token'] = 'No authentication token';
$string['js_error_report_not_found'] = 'Report not found';
$string['js_error_connection'] = 'Connection error';
$string['js_error_failed_load_report'] = 'Failed to load report';
$string['js_error_failed_execute_report'] = 'Failed to execute report';
$string['js_error_failed_to_load'] = 'Failed to load';
$string['js_error_query_failed'] = 'Query execution failed';
$string['error_exception'] = 'Error: {$a}';

// JavaScript Strings - Export Messages.
$string['js_export_no_data'] = 'No data to export';
$string['js_export_not_available'] = 'Export not available';
$string['js_export_verify_error'] = 'Unable to verify export eligibility. Please try again.';
$string['js_export_coming_soon'] = 'Export to {$a} coming soon';
$string['js_export_title'] = '{$a} Export';
$string['js_export_not_on_plan'] = '{$a} export is not available on your current plan.';
$string['js_ok'] = 'OK';

// JavaScript Strings - UI Labels.
$string['js_no_reports'] = '-- No Reports --';
$string['js_showing_x_of_y'] = 'Showing {$a->start}-{$a->end} of {$a->total}';
$string['js_page_x_of_y'] = 'Page {$a->current} of {$a->total}';
$string['js_updated'] = 'Updated: {$a}';
$string['js_last_updated'] = 'Last updated: {$a}';
$string['js_last_run'] = 'Last run: {$a}';
$string['js_no_change'] = 'No change';
$string['js_unknown'] = 'Unknown';
$string['js_just_now'] = 'Just now';
$string['js_vs_previous_up'] = '+{$a} vs previous';
$string['js_vs_previous_down'] = '-{$a} vs previous';
$string['js_vs_previous'] = '{$a} vs previous';
$string['js_value'] = 'Value: {$a}';
$string['js_selected'] = 'Selected: {$a}';
$string['js_min_ago'] = '{$a} min ago';
$string['js_hour_ago'] = '{$a} hour ago';
$string['js_hours_ago'] = '{$a} hours ago';
$string['js_day_ago'] = '{$a} day ago';
$string['js_days_ago'] = '{$a} days ago';

// JavaScript Strings - Alert Messages.
$string['js_alert_created'] = 'Alert created successfully.';
$string['js_alert_updated'] = 'Alert updated successfully.';
$string['js_alert_deleted'] = 'Alert deleted successfully.';
$string['js_alert_failed_create'] = 'Failed to create alert.';
$string['js_alert_failed_update'] = 'Failed to update alert.';
$string['js_alert_failed_delete'] = 'Failed to delete alert from server.';
$string['js_alert_no_reports'] = 'No reports configured in this block. Add reports in the KPI or Tabs settings first.';
$string['js_alert_select_report'] = 'Please select a report to monitor.';
$string['js_alert_enter_threshold'] = 'Please enter a threshold value.';
$string['js_no_thresholds_set'] = 'No thresholds set';
$string['js_failed_load_reports'] = 'Failed to load reports';

// Privacy API strings.
$string['privacy:metadata:block_adeptus_insights_snap_sched'] = 'Stores snapshot scheduling configuration for KPI metrics. This is administrative data associated with block instances, not individual users.';
$string['privacy:metadata:block_adeptus_insights_snap_sched:blockinstanceid'] = 'The ID of the block instance.';
$string['privacy:metadata:block_adeptus_insights_snap_sched:report_slug'] = 'The identifier of the report being tracked.';
$string['privacy:metadata:block_adeptus_insights_snap_sched:interval_seconds'] = 'How often snapshots are taken.';
$string['privacy:metadata:block_adeptus_insights_alert_log'] = 'Stores a log of triggered alert notifications. This is system data associated with block instances, not individual users.';
$string['privacy:metadata:block_adeptus_insights_alert_log:blockinstanceid'] = 'The ID of the block instance.';
$string['privacy:metadata:block_adeptus_insights_alert_log:alert_id'] = 'The backend alert identifier.';
$string['privacy:metadata:block_adeptus_insights_alert_log:severity'] = 'The severity level of the alert.';
$string['privacy:metadata:adeptus_backend'] = 'Report data is sent to the Adeptus 360 backend API for processing and trend analysis. No personal user data is transmitted.';
$string['privacy:metadata:adeptus_backend:report_data'] = 'Aggregated report metrics such as row counts and execution times.';
