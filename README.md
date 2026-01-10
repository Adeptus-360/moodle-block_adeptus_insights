# Adeptus Insights Block

Dashboard block companion for the Adeptus Insights report plugin.

## Description

The Adeptus Insights Block allows you to embed reports, KPI cards, and quick access links from the Adeptus Insights report plugin directly onto Moodle dashboards and course pages. Display key metrics at a glance with customizable display modes, automatic refresh, and threshold alerts.

### Key Features

- **Multiple Display Modes**: Embedded reports, KPI cards, report links, or tabbed reports
- **KPI Cards**: Compact metric displays with trend indicators and sparkline charts
- **Automatic Refresh**: Configurable auto-refresh intervals (5 min to 1 hour)
- **Alert System**: Set warning and critical thresholds with email and Moodle notifications
- **Context Filtering**: Auto-detect course/category context or set manually
- **Chart & Table Views**: Display charts, data tables, or both
- **Export Options**: Quick export buttons for CSV and PDF

### Display Modes

1. **Embedded Report**: Full chart and data table in the block
2. **KPI Cards**: Multiple metrics as compact cards with trends
3. **Report Links**: Clickable list of report names
4. **Tabbed Reports**: Multiple reports in a tabbed interface

## Requirements

- **Moodle**: Version 4.1 or higher (2022112800)
- **PHP**: Version 7.4 or higher (8.1+ recommended)
- **Adeptus Insights Report Plugin**: Required dependency

## Installation

### Method 1: Upload via Moodle Admin

1. Download the plugin ZIP file
2. Log in to your Moodle site as an administrator
3. Go to **Site administration > Plugins > Install plugins**
4. Upload the ZIP file and follow the installation prompts

### Method 2: Manual Installation

1. Download and extract the plugin
2. Copy the `adeptus_insights` folder to `/path/to/moodle/blocks/`
3. Log in as administrator and visit the notifications page
4. Follow the installation prompts

## Configuration

### Adding the Block

1. Turn on editing mode on any page
2. Click "Add a block" in the block drawer
3. Select "Adeptus Insights"
4. Configure the block settings

### Block Settings

- **Display Mode**: Choose how reports are presented
- **Report Source**: Filter by wizard reports, AI reports, category, or manual selection
- **Context Filter**: Auto-detect from page or manually select course/category
- **Display Options**: Show/hide chart, table, header, footer
- **Behavior**: Click action, auto-refresh interval, export buttons
- **Alerts**: Configure threshold alerts with notifications

## Capabilities

The plugin defines the following capabilities:

- `block/adeptus_insights:addinstance` - Add block to a page
- `block/adeptus_insights:myaddinstance` - Add block to dashboard
- `block/adeptus_insights:view` - View block content
- `block/adeptus_insights:configurealerts` - Configure alert thresholds
- `block/adeptus_insights:receivealerts` - Receive alert notifications

## Alert System

Configure proactive monitoring for KPI metrics:

1. Enable alerts in block configuration
2. Select a report to monitor
3. Set warning and critical thresholds
4. Configure notification preferences (email and/or Moodle messages)
5. Set check frequency and cooldown periods

Alert status levels:
- **OK** (green): Metric is within normal range
- **Warning** (amber): Metric has reached warning threshold
- **Critical** (red): Metric has exceeded critical threshold

## Privacy

This block stores KPI history data and alert configurations linked to block instances and users. All data is managed locally on your Moodle server. See the parent Adeptus Insights report plugin for complete privacy information.

## Support

For technical support and documentation:

- **Website**: [www.adeptus360.com](https://www.adeptus360.com)
- **Email**: info@adeptus360.com

## License

This plugin is licensed under the GNU General Public License v3 or later.

See [http://www.gnu.org/copyleft/gpl.html](http://www.gnu.org/copyleft/gpl.html) for details.

## Author

**Adeptus 360**

- Website: [www.adeptus360.com](https://www.adeptus360.com)
- Email: info@adeptus360.com

## Version

- **Current Version**: 1.0.0
- **Moodle Compatibility**: 4.1+
- **Maturity**: Stable
