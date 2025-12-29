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
 * Block instance configuration form for Adeptus Insights.
 *
 * @package    block_adeptus_insights
 * @copyright  2025 Adeptus Analytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing Adeptus Insights block instances.
 */
class block_adeptus_insights_edit_form extends block_edit_form {

    /**
     * Define the form fields.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG, $DB;

        // =====================================
        // GENERAL SETTINGS
        // =====================================
        $mform->addElement('header', 'config_header_general', get_string('settings_general', 'block_adeptus_insights'));

        // Block title.
        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_adeptus_insights'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->addHelpButton('config_title', 'configtitle', 'block_adeptus_insights');

        // Display mode.
        $displaymodes = [
            'embedded' => get_string('displaymode_embedded', 'block_adeptus_insights'),
            'kpi' => get_string('displaymode_kpi', 'block_adeptus_insights'),
            'links' => get_string('displaymode_links', 'block_adeptus_insights'),
            'tabs' => get_string('displaymode_tabs', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_display_mode', get_string('configdisplaymode', 'block_adeptus_insights'), $displaymodes);
        $mform->setDefault('config_display_mode', 'links');
        $mform->addHelpButton('config_display_mode', 'configdisplaymode', 'block_adeptus_insights');

        // =====================================
        // REPORT SOURCE SETTINGS
        // =====================================
        $mform->addElement('header', 'config_header_source', get_string('configreportsource', 'block_adeptus_insights'));

        // Report source.
        $reportsources = [
            'all' => get_string('reportsource_all', 'block_adeptus_insights'),
            'wizard' => get_string('reportsource_wizard', 'block_adeptus_insights'),
            'ai' => get_string('reportsource_ai', 'block_adeptus_insights'),
            'category' => get_string('reportsource_category', 'block_adeptus_insights'),
            'manual' => get_string('reportsource_manual', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_report_source', get_string('configreportsource', 'block_adeptus_insights'), $reportsources);
        $mform->setDefault('config_report_source', 'all');
        $mform->addHelpButton('config_report_source', 'configreportsource', 'block_adeptus_insights');

        // Report category filter (available for all sources except manual).
        $reportcategories = $this->get_report_category_options();
        $mform->addElement('select', 'config_report_category', get_string('configselectedcategory', 'block_adeptus_insights'), $reportcategories);
        $mform->setDefault('config_report_category', '');
        $mform->addHelpButton('config_report_category', 'configselectedcategory', 'block_adeptus_insights');
        $mform->hideIf('config_report_category', 'config_report_source', 'eq', 'manual');

        // Selected reports (for manual selection).
        // This will be populated via JavaScript from the API.
        $mform->addElement('textarea', 'config_selected_reports_json', get_string('configselectedreports', 'block_adeptus_insights'));
        $mform->setType('config_selected_reports_json', PARAM_RAW);
        $mform->hideIf('config_selected_reports_json', 'config_report_source', 'neq', 'manual');

        // =====================================
        // DISPLAY OPTIONS
        // =====================================
        $mform->addElement('header', 'config_header_display', get_string('configshowchart', 'block_adeptus_insights'));

        // Show chart.
        $mform->addElement('advcheckbox', 'config_show_chart', get_string('configshowchart', 'block_adeptus_insights'));
        $mform->setDefault('config_show_chart', 1);

        // Show table.
        $mform->addElement('advcheckbox', 'config_show_table', get_string('configshowtable', 'block_adeptus_insights'));
        $mform->setDefault('config_show_table', 1);

        // Chart height.
        $chartheights = [];
        for ($h = 150; $h <= 400; $h += 50) {
            $chartheights[$h] = $h . 'px';
        }
        $mform->addElement('select', 'config_chart_height', get_string('configchartheight', 'block_adeptus_insights'), $chartheights);
        $mform->setDefault('config_chart_height', 250);

        // Max table rows.
        $maxrows = [5 => '5', 10 => '10', 25 => '25', 50 => '50'];
        $mform->addElement('select', 'config_table_max_rows', get_string('configtablemaxrows', 'block_adeptus_insights'), $maxrows);
        $mform->setDefault('config_table_max_rows', 10);

        // Compact mode.
        $mform->addElement('advcheckbox', 'config_compact_mode', get_string('configcompactmode', 'block_adeptus_insights'));
        $mform->setDefault('config_compact_mode', 0);

        // Show footer.
        $mform->addElement('advcheckbox', 'config_show_footer', get_string('configshowfooter', 'block_adeptus_insights'));
        $mform->setDefault('config_show_footer', 1);

        // Show category badges.
        $mform->addElement('advcheckbox', 'config_show_category_badges', get_string('configshowcategorybadges', 'block_adeptus_insights'));
        $mform->setDefault('config_show_category_badges', 1);

        // KPI columns (for KPI mode).
        $kpicolumns = [1 => '1', 2 => '2', 3 => '3', 4 => '4'];
        $mform->addElement('select', 'config_kpi_columns', get_string('configkpicolumns', 'block_adeptus_insights'), $kpicolumns);
        $mform->setDefault('config_kpi_columns', 2);
        $mform->hideIf('config_kpi_columns', 'config_display_mode', 'neq', 'kpi');

        // Max link items (for links mode).
        $maxitems = [5 => '5', 10 => '10', 15 => '15', 20 => '20'];
        $mform->addElement('select', 'config_max_link_items', get_string('configmaxlinkitems', 'block_adeptus_insights'), $maxitems);
        $mform->setDefault('config_max_link_items', 10);
        $mform->hideIf('config_max_link_items', 'config_display_mode', 'neq', 'links');

        // =====================================
        // BEHAVIOR SETTINGS
        // =====================================
        $mform->addElement('header', 'config_header_behavior', get_string('configclickaction', 'block_adeptus_insights'));

        // Click action.
        $clickactions = [
            'modal' => get_string('clickaction_modal', 'block_adeptus_insights'),
            'newtab' => get_string('clickaction_newtab', 'block_adeptus_insights'),
            'expand' => get_string('clickaction_expand', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_click_action', get_string('configclickaction', 'block_adeptus_insights'), $clickactions);
        $mform->setDefault('config_click_action', 'modal');

        // Auto-refresh.
        $refreshintervals = [
            'never' => get_string('autorefresh_never', 'block_adeptus_insights'),
            '5m' => get_string('autorefresh_5m', 'block_adeptus_insights'),
            '15m' => get_string('autorefresh_15m', 'block_adeptus_insights'),
            '30m' => get_string('autorefresh_30m', 'block_adeptus_insights'),
            '1h' => get_string('autorefresh_1h', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_auto_refresh', get_string('configautorefresh', 'block_adeptus_insights'), $refreshintervals);
        $mform->setDefault('config_auto_refresh', 'never');

        // Show refresh button.
        $mform->addElement('advcheckbox', 'config_show_refresh_button', get_string('configshowrefreshbutton', 'block_adeptus_insights'));
        $mform->setDefault('config_show_refresh_button', 1);

        // Show export.
        $mform->addElement('advcheckbox', 'config_show_export', get_string('configshowexport', 'block_adeptus_insights'));
        $mform->setDefault('config_show_export', 1);

        // Show timestamp.
        $mform->addElement('advcheckbox', 'config_show_timestamp', get_string('configshowtimestamp', 'block_adeptus_insights'));
        $mform->setDefault('config_show_timestamp', 1);

        // =====================================
        // CONTEXT FILTERING
        // =====================================
        $mform->addElement('header', 'config_header_context', get_string('configcontextfilter', 'block_adeptus_insights'));

        // Context filter mode.
        $contextmodes = [
            'auto' => get_string('contextfilter_auto', 'block_adeptus_insights'),
            'course' => get_string('configcontextcourse', 'block_adeptus_insights'),
            'category' => get_string('configcontextcategory', 'block_adeptus_insights'),
            'none' => get_string('contextfilter_none', 'block_adeptus_insights'),
        ];
        $mform->addElement('select', 'config_context_filter', get_string('configcontextfilter', 'block_adeptus_insights'), $contextmodes);
        $mform->setDefault('config_context_filter', 'auto');
        $mform->addHelpButton('config_context_filter', 'configcontextfilter', 'block_adeptus_insights');

        // Course selector (for manual course context).
        $courses = $this->get_course_options();
        $mform->addElement('autocomplete', 'config_context_course', get_string('configcontextcourse', 'block_adeptus_insights'), $courses);
        $mform->hideIf('config_context_course', 'config_context_filter', 'neq', 'course');

        // Category selector (for manual category context).
        $categories = $this->get_category_options();
        $mform->addElement('autocomplete', 'config_context_category', get_string('configcontextcategory', 'block_adeptus_insights'), $categories);
        $mform->hideIf('config_context_category', 'config_context_filter', 'neq', 'category');
    }

    /**
     * Get course options for the autocomplete.
     *
     * @return array
     */
    private function get_course_options() {
        global $DB;

        $courses = ['' => get_string('selectreport', 'block_adeptus_insights')];

        $records = $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, fullname, shortname');
        foreach ($records as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $courses[$course->id] = format_string($course->fullname) . ' (' . $course->shortname . ')';
        }

        return $courses;
    }

    /**
     * Get category options for the autocomplete.
     *
     * @return array
     */
    private function get_category_options() {
        global $DB;

        $categories = ['' => get_string('selectreport', 'block_adeptus_insights')];

        $records = $DB->get_records('course_categories', ['visible' => 1], 'name ASC', 'id, name');
        foreach ($records as $category) {
            $categories[$category->id] = format_string($category->name);
        }

        return $categories;
    }

    /**
     * Get report category options from the backend API.
     *
     * @return array
     */
    private function get_report_category_options() {
        global $CFG;

        $categories = ['' => get_string('reportsource_all', 'block_adeptus_insights')];

        // Try to fetch categories from the parent plugin.
        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $apiurl = \report_adeptus_insights\api_config::get_backend_url();

            if (!empty($apikey) && !empty($apiurl)) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiurl . '/reports/categories');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $apikey,
                ]);

                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpcode === 200 && $response) {
                    $data = json_decode($response, true);
                    if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                        foreach ($data['data'] as $cat) {
                            $categories[$cat['slug']] = $cat['name'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            debugging('Failed to fetch report categories: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $categories;
    }

    /**
     * Validate the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate that at least chart or table is shown for embedded mode.
        if (isset($data['config_display_mode']) && $data['config_display_mode'] === 'embedded') {
            if (empty($data['config_show_chart']) && empty($data['config_show_table'])) {
                $errors['config_show_chart'] = get_string('errorloadingreport', 'block_adeptus_insights');
            }
        }

        return $errors;
    }
}
