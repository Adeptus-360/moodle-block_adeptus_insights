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
 * @copyright  2025 Adeptus Analytics
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
$string['configdisplaymode'] = 'Display mode';
$string['configdisplaymode_desc'] = 'Choose how reports are displayed in the block.';
$string['displaymode_embedded'] = 'Embedded report (chart & table)';
$string['displaymode_kpi'] = 'KPI cards';
$string['displaymode_links'] = 'Report links';
$string['displaymode_tabs'] = 'Tabbed reports';

// Configuration form - Report source.
$string['configreportsource'] = 'Report source';
$string['configreportsource_desc'] = 'Choose which reports to display.';
$string['reportsource_all'] = 'All reports';
$string['reportsource_wizard'] = 'Wizard reports only';
$string['reportsource_ai'] = 'AI-generated reports only';
$string['reportsource_category'] = 'Reports from category';
$string['reportsource_manual'] = 'Manually selected reports';

$string['configselectedreports'] = 'Selected reports';
$string['configselectedreports_desc'] = 'Choose specific reports to display. Hold Ctrl/Cmd to select multiple.';
$string['configselectedcategory'] = 'Report category';
$string['configselectedcategory_desc'] = 'Show reports from this category.';

// Configuration form - Display options.
$string['configshowchart'] = 'Show chart';
$string['configshowchart_desc'] = 'Display the chart visualization.';
$string['configshowtable'] = 'Show table';
$string['configshowtable_desc'] = 'Display the data table.';
$string['configchartheight'] = 'Chart height';
$string['configchartheight_desc'] = 'Height of the chart in pixels.';
$string['configtablemaxrows'] = 'Max table rows';
$string['configtablemaxrows_desc'] = 'Maximum rows to display before pagination.';
$string['configcompactmode'] = 'Compact mode';
$string['configcompactmode_desc'] = 'Use reduced padding and margins.';
$string['configshowheader'] = 'Show header';
$string['configshowheader_desc'] = 'Display the block header.';
$string['configshowfooter'] = 'Show footer';
$string['configshowfooter_desc'] = 'Display the "View all reports" link.';

// Configuration form - Behavior.
$string['configclickaction'] = 'Click action';
$string['configclickaction_desc'] = 'What happens when a report is clicked.';
$string['clickaction_modal'] = 'Open in modal popup';
$string['clickaction_newtab'] = 'Open in new tab';
$string['clickaction_expand'] = 'Expand inline';

$string['configautorefresh'] = 'Auto-refresh interval';
$string['configautorefresh_desc'] = 'Automatically refresh report data.';
$string['autorefresh_never'] = 'Never';
$string['autorefresh_5m'] = 'Every 5 minutes';
$string['autorefresh_15m'] = 'Every 15 minutes';
$string['autorefresh_30m'] = 'Every 30 minutes';
$string['autorefresh_1h'] = 'Every hour';

$string['configshowrefreshbutton'] = 'Show refresh button';
$string['configshowrefreshbutton_desc'] = 'Display manual refresh button.';
$string['configshowexport'] = 'Show export buttons';
$string['configshowexport_desc'] = 'Display quick export buttons.';
$string['configshowtimestamp'] = 'Show timestamp';
$string['configshowtimestamp_desc'] = 'Display "Last updated" timestamp.';

// Configuration form - Context.
$string['configcontextfilter'] = 'Context filter';
$string['configcontextfilter_desc'] = 'How to filter reports based on page context.';
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
$string['configkpicolumns'] = 'KPI columns';
$string['configkpicolumns_desc'] = 'Number of columns for KPI card layout.';
$string['configmaxlinkitems'] = 'Max link items';
$string['configmaxlinkitems_desc'] = 'Maximum number of report links to display.';

// Block content.
$string['viewallreports'] = 'View all reports';
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
$string['privacy:metadata'] = 'The Adeptus Insights block does not store any personal data itself. All report data is managed by the parent Adeptus Insights report plugin.';

// Admin settings.
$string['settings'] = 'Adeptus Insights Block Settings';
$string['settings_general'] = 'General Settings';
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
