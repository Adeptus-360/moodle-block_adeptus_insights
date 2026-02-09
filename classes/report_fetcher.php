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
 * Report fetcher class for Adeptus Insights block.
 *
 * Handles fetching reports from the backend API via the parent plugin.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Report fetcher class.
 *
 * Provides methods to fetch reports from the Adeptus Insights backend API.
 */
class report_fetcher {
    /** @var \report_adeptus_insights\installation_manager */
    private $installationmanager;

    /** @var string */
    private $apiurl;

    /** @var string */
    private $apikey;

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;

        // Load parent plugin classes.
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

        $this->installation_manager = new \report_adeptus_insights\installation_manager();
        $this->api_url = \report_adeptus_insights\api_config::get_backend_url();
        $this->api_key = $this->installation_manager->get_api_key();
    }

    /**
     * Check if API is available.
     *
     * @return bool
     */
    public function is_available() {
        return !empty($this->api_key) && !empty($this->api_url);
    }

    /**
     * Fetch all available reports.
     *
     * @param string $source Filter by source: 'all', 'wizard', 'ai'
     * @param string $category Filter by category slug
     * @return array
     */
    public function fetch_reports($source = 'all', $category = '') {
        $reports = [];

        if ($source === 'all' || $source === 'wizard') {
            $wizardreports = $this->fetch_wizard_reports();
            foreach ($wizardreports as $report) {
                $report['source'] = 'wizard';
                $reports[] = $report;
            }
        }

        if ($source === 'all' || $source === 'ai') {
            $aireports = $this->fetch_ai_reports();
            foreach ($aireports as $report) {
                $report['source'] = 'ai';
                $reports[] = $report;
            }
        }

        // Filter by category if specified.
        if (!empty($category)) {
            $reports = array_filter($reports, function ($report) use ($category) {
                $reportcategory = $report['category_info']['slug'] ?? '';
                return $reportcategory === $category;
            });
            $reports = array_values($reports);
        }

        // Sort by name.
        usort($reports, function ($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $reports;
    }

    /**
     * Fetch wizard reports from the backend.
     *
     * @param int $userid Optional user ID filter
     * @return array
     */
    public function fetch_wizard_reports($userid = null) {
        global $USER;

        if (!$this->is_available()) {
            return [];
        }

        $url = $this->api_url . '/wizard-reports';
        if ($userid) {
            $url .= '?user_id=' . intval($userid);
        }

        $response = $this->make_api_request($url);

        if ($response && isset($response['success']) && $response['success']) {
            return $response['reports'] ?? [];
        }

        return [];
    }

    /**
     * Fetch AI-generated reports from the backend.
     *
     * @param int $userid Optional user ID filter
     * @return array
     */
    public function fetch_ai_reports($userid = null) {
        global $USER;

        if (!$this->is_available()) {
            return [];
        }

        $url = $this->api_url . '/ai-reports';
        if ($userid) {
            $url .= '?user_id=' . intval($userid);
        }

        $response = $this->make_api_request($url);

        if ($response && isset($response['success']) && $response['success']) {
            return $response['reports'] ?? [];
        }

        return [];
    }

    /**
     * Fetch a single report by slug.
     *
     * @param string $slug Report slug
     * @param string $source Report source: 'wizard' or 'ai'
     * @return array|null
     */
    public function fetch_report($slug, $source = 'wizard') {
        if (!$this->is_available()) {
            return null;
        }

        $endpoint = $source === 'ai' ? '/ai-reports/' : '/wizard-reports/';
        $url = $this->api_url . $endpoint . urlencode($slug);

        $response = $this->make_api_request($url);

        if ($response && isset($response['success']) && $response['success']) {
            return $response['report'] ?? null;
        }

        return null;
    }

    /**
     * Fetch report categories.
     *
     * @return array
     */
    public function fetch_categories() {
        if (!$this->is_available()) {
            return [];
        }

        $url = $this->api_url . '/reports/categories';
        $response = $this->make_api_request($url);

        if ($response && isset($response['success']) && $response['success']) {
            return $response['data'] ?? [];
        }

        return [];
    }

    /**
     * Fetch report definitions (wizard templates).
     *
     * @return array
     */
    public function fetch_report_definitions() {
        if (!$this->is_available()) {
            return [];
        }

        $url = $this->api_url . '/reports/definitions';
        $response = $this->make_api_request($url, 'GET', [], ['X-API-Key' => $this->api_key]);

        if ($response && isset($response['success']) && $response['success']) {
            return $response['data'] ?? [];
        }

        return [];
    }

    /**
     * Make an API request.
     *
     * @param string $url API URL
     * @param string $method HTTP method
     * @param array $data Request body data
     * @param array $extraheaders Additional headers
     * @return array|null
     */
    private function make_api_request($url, $method = 'GET', $data = [], $extraheaders = []) {
        $curl = new \curl();

        // Set headers.
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('Authorization: Bearer ' . $this->api_key);

        // Add extra headers.
        foreach ($extraheaders as $key => $value) {
            $curl->setHeader($key . ': ' . $value);
        }

        // Set options.
        $options = [
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        // Make the request based on method.
        if ($method === 'POST') {
            $response = $curl->post($url, json_encode($data), $options);
        } else if ($method === 'GET') {
            $response = $curl->get($url, [], $options);
        } else {
            // For other methods (PUT, DELETE, etc.).
            $options['CURLOPT_CUSTOMREQUEST'] = $method;
            if (!empty($data)) {
                $response = $curl->post($url, json_encode($data), $options);
            } else {
                $response = $curl->get($url, [], $options);
            }
        }

        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;
        $error = $curl->get_errno() ? $curl->error : '';

        if ($error || $httpcode < 200 || $httpcode >= 300) {
            debugging("API request failed: $url, HTTP $httpcode, Error: $error", DEBUG_DEVELOPER);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Get reports formatted for display.
     *
     * @param array $config Block configuration
     * @return array
     */
    public function get_reports_for_display($config) {
        $source = $config['report_source'] ?? 'all';
        $category = $config['selected_category'] ?? '';
        $selectedreports = $config['selected_reports'] ?? [];
        $maxitems = $config['max_link_items'] ?? 10;

        // Fetch reports based on source.
        $reports = $this->fetch_reports($source, $category);

        // Filter to selected reports if manual selection.
        if ($source === 'manual' && !empty($selectedreports)) {
            $selectedslugs = is_array($selectedreports) ? $selectedreports : [$selectedreports];
            $reports = array_filter($reports, function ($report) use ($selectedslugs) {
                return in_array($report['slug'], $selectedslugs);
            });
            $reports = array_values($reports);
        }

        // Limit number of reports.
        if ($maxitems > 0 && count($reports) > $maxitems) {
            $reports = array_slice($reports, 0, $maxitems);
        }

        // Format for display.
        return array_map(function ($report) {
            return [
                'slug' => $report['slug'],
                'name' => $report['name'],
                'description' => $report['description'] ?? $report['name'],
                'source' => $report['source'],
                'source_label' => $report['source'] === 'wizard' ? 'Wizard' : 'AI',
                'source_class' => $report['source'] === 'wizard' ? 'badge-wizard' : 'badge-ai',
                'category' => $report['category_info']['name'] ?? $report['category'] ?? 'General',
                'category_color' => $report['category_info']['color'] ?? '#6c757d',
                'category_slug' => $report['category_info']['slug'] ?? 'general',
                'created_at' => $report['created_at'] ?? '',
                'formatted_date' => $report['formatted_date'] ?? '',
            ];
        }, $reports);
    }
}
