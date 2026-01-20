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
 * External function to get users filtered by role for alert recipient selection.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

/**
 * External function to get users by role for alert configuration.
 */
class get_users_by_role extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'roleid' => new external_value(PARAM_INT, 'Role ID to filter by (0 for all users)', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_TEXT, 'Search query for user name or email', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * Get users filtered by role.
     *
     * @param int $roleid Role ID to filter by (0 for all)
     * @param string $search Search query
     * @param int $limit Maximum results
     * @return array Users data
     */
    public static function execute($roleid = 0, $search = '', $limit = 50) {
        global $DB, $CFG;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'roleid' => $roleid,
            'search' => $search,
            'limit' => $limit,
        ]);

        $roleid = $params['roleid'];
        $search = trim($params['search']);
        $limit = min($params['limit'], 100); // Cap at 100.

        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);

        // Require view capability.
        require_capability('block/adeptus_insights:addinstance', $context);

        $users = [];

        // Build the query.
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                FROM {user} u";

        $where = ["u.deleted = 0", "u.suspended = 0", "u.confirmed = 1"];
        $sqlparams = [];

        // Filter by role if specified.
        if ($roleid > 0) {
            $sql .= " JOIN {role_assignments} ra ON ra.userid = u.id";
            $where[] = "ra.roleid = :roleid";
            $sqlparams['roleid'] = $roleid;
        }

        // Search filter.
        if (!empty($search)) {
            $searchlike = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = "(" . $DB->sql_like('u.firstname', ':search1', false) .
                       " OR " . $DB->sql_like('u.lastname', ':search2', false) .
                       " OR " . $DB->sql_like('u.email', ':search3', false) .
                       " OR " . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'), ':search4', false) . ")";
            $sqlparams['search1'] = $searchlike;
            $sqlparams['search2'] = $searchlike;
            $sqlparams['search3'] = $searchlike;
            $sqlparams['search4'] = $searchlike;
        }

        $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY u.lastname, u.firstname";

        $records = $DB->get_records_sql($sql, $sqlparams, 0, $limit);

        foreach ($records as $user) {
            // Get user picture URL.
            $userpicture = new \user_picture($user);
            $userpicture->size = 35;

            $users[] = [
                'id' => (int) $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'fullname' => fullname($user),
                'email' => $user->email,
                'profileimageurl' => $userpicture->get_url($GLOBALS['PAGE'])->out(false),
            ];
        }

        return [
            'users' => $users,
            'count' => count($users),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'firstname' => new external_value(PARAM_TEXT, 'First name'),
                    'lastname' => new external_value(PARAM_TEXT, 'Last name'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'email' => new external_value(PARAM_TEXT, 'Email address'),
                    'profileimageurl' => new external_value(PARAM_URL, 'Profile image URL'),
                ])
            ),
            'count' => new external_value(PARAM_INT, 'Number of users returned'),
        ]);
    }
}
