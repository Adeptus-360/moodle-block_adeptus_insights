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

namespace block_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

/**
 * External function to search reports for alert configuration autocomplete.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_reports extends external_api {
    /** @var int Cache duration in seconds */
    const CACHE_DURATION = 300; // 5 minutes

    /** @var array Cached reports */
    private static $reportscache = null;

    /** @var int Cache timestamp */
    private static $cachetimestamp = 0;

    /**
     * Define parameters for execute function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'Search query string'),
        ]);
    }

    /**
     * Execute the search reports function.
     *
     * @param string $query Search query
     * @return array Result with matching reports
     */
    public static function execute(string $query): array {
        global $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);

        // Get all reports (cached).
        $allreports = self::get_all_reports();

        // Filter reports by query.
        $query = trim(strtolower($params['query']));
        $results = [];

        foreach ($allreports as $report) {
            $name = strtolower($report['name']);
            $slug = strtolower($report['slug']);
            $category = strtolower($report['category'] ?? '');

            // Match if query is empty (return all) or matches name, slug, or category.
            if (
                empty($query) ||
                strpos($name, $query) !== false ||
                strpos($slug, $query) !== false ||
                strpos($category, $query) !== false
            ) {
                // Format label with category and source badge.
                $sourcebadge = $report['source'] === 'ai' ? '[AI]' : '[Wizard]';
                $categoryinfo = !empty($report['category']) ? ' (' . $report['category'] . ')' : '';

                $results[] = [
                    'slug' => $report['slug'],
                    'label' => $report['name'] . $categoryinfo . ' ' . $sourcebadge,
                    'name' => $report['name'],
                    'source' => $report['source'],
                    'category' => $report['category'] ?? '',
                ];
            }

            // Limit results for performance.
            if (count($results) >= 50) {
                break;
            }
        }

        // Sort results by relevance (exact matches first, then alphabetically).
        usort($results, function ($a, $b) use ($query) {
            $aname = strtolower($a['name']);
            $bname = strtolower($b['name']);

            // Exact match at start of name comes first.
            $astart = strpos($aname, $query) === 0;
            $bstart = strpos($bname, $query) === 0;

            if ($astart && !$bstart) {
                return -1;
            }
            if ($bstart && !$astart) {
                return 1;
            }

            // Then sort alphabetically.
            return strcmp($a['name'], $b['name']);
        });

        return [
            'reports' => $results,
        ];
    }

    /**
     * Get all reports from the API (with caching).
     *
     * @return array All available reports
     */
    private static function get_all_reports(): array {
        global $CFG;

        // Check cache.
        if (self::$reportscache !== null && (time() - self::$cachetimestamp) < self::CACHE_DURATION) {
            return self::$reportscache;
        }

        $allreports = [];

        try {
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
            require_once($CFG->dirroot . '/report/adeptus_insights/classes/api_config.php');

            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $apiurl = \report_adeptus_insights\api_config::get_backend_url();

            if (empty($apikey) || empty($apiurl)) {
                return [];
            }

            // Fetch wizard reports.
            $wizardreports = self::fetch_reports_from_api($apiurl . '/wizard-reports', $apikey);
            foreach ($wizardreports as $report) {
                $allreports[] = [
                    'slug' => $report['slug'] ?? '',
                    'name' => $report['name'] ?? $report['title'] ?? $report['display_name'] ?? $report['slug'] ?? 'Unnamed',
                    'source' => 'wizard',
                    'category' => $report['category_info']['name'] ?? $report['category'] ?? '',
                ];
            }

            // Fetch AI reports.
            $aireports = self::fetch_reports_from_api($apiurl . '/ai-reports', $apikey);
            foreach ($aireports as $report) {
                $allreports[] = [
                    'slug' => $report['slug'] ?? '',
                    'name' => $report['name'] ?? $report['title'] ?? $report['display_name'] ?? $report['slug'] ?? 'Unnamed',
                    'source' => 'ai',
                    'category' => $report['category_info']['name'] ?? $report['category'] ?? '',
                ];
            }

            // Sort all reports alphabetically by name.
            usort($allreports, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            // Update cache.
            self::$reportscache = $allreports;
            self::$cachetimestamp = time();
        } catch (\Exception $e) {
            debugging('Failed to fetch reports for search: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        return $allreports;
    }

    /**
     * Fetch reports from an API endpoint.
     *
     * @param string $url API URL
     * @param string $apikey API key
     * @return array Reports data
     */
    private static function fetch_reports_from_api(string $url, string $apikey): array {
        $curl = new \curl();

        // Set headers.
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('Authorization: Bearer ' . $apikey);

        // Set options and make request.
        $options = [
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $response = $curl->get($url, [], $options);

        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode !== 200 || empty($response)) {
            return [];
        }

        $data = json_decode($response, true);

        // Handle different API response formats.
        if (isset($data['reports'])) {
            return $data['reports'];
        }
        if (isset($data['data'])) {
            return $data['data'];
        }
        if (is_array($data) && !isset($data['success'])) {
            return $data;
        }

        return [];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reports' => new external_multiple_structure(
                new external_single_structure([
                    'slug' => new external_value(PARAM_TEXT, 'Report slug identifier'),
                    'label' => new external_value(PARAM_RAW, 'Display label for autocomplete'),
                    'name' => new external_value(PARAM_TEXT, 'Report name'),
                    'source' => new external_value(PARAM_ALPHA, 'Report source (wizard or ai)'),
                    'category' => new external_value(PARAM_TEXT, 'Report category'),
                ]),
                'Matching reports'
            ),
        ]);
    }
}
