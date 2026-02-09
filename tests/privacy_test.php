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
 * Privacy provider tests for the Adeptus Insights block plugin.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use block_adeptus_insights\privacy\provider;

/**
 * Privacy provider test case for block_adeptus_insights.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_adeptus_insights\privacy\provider
 */
class privacy_test extends provider_testcase {

    /**
     * Test that the provider implements the required interfaces.
     */
    public function test_provider_implements_interfaces(): void {
        $this->assertTrue(
            is_a(
                provider::class,
                \core_privacy\local\metadata\provider::class,
                true
            )
        );
        $this->assertTrue(
            is_a(
                provider::class,
                \core_privacy\local\request\plugin\provider::class,
                true
            )
        );
        $this->assertTrue(
            is_a(
                provider::class,
                \core_privacy\local\request\core_userlist_provider::class,
                true
            )
        );
    }

    /**
     * Test that metadata is returned correctly.
     */
    public function test_get_metadata(): void {
        $collection = new collection('block_adeptus_insights');
        $collection = provider::get_metadata($collection);

        $items = $collection->get_collection();
        $this->assertNotEmpty($items);
    }

    /**
     * Test that get_contexts_for_userid returns an empty list.
     *
     * This block does not store personal user data.
     */
    public function test_get_contexts_for_userid_returns_empty(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * Test that get_users_in_context returns empty.
     *
     * This block does not store personal user data.
     */
    public function test_get_users_in_context_returns_empty(): void {
        $this->resetAfterTest();

        $context = \context_system::instance();
        $userlist = new userlist($context, 'block_adeptus_insights');
        provider::get_users_in_context($userlist);

        $this->assertEmpty($userlist->get_userids());
    }
}
