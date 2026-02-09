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
 * Basic unit tests for the Adeptus Insights block plugin.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Basic test case for block_adeptus_insights plugin.
 *
 * @package    block_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_adeptus_insights
 */
class block_test extends \advanced_testcase {

    /**
     * Test that the block version file is valid.
     */
    public function test_version_file_exists(): void {
        global $CFG;
        $versionfile = $CFG->dirroot . '/blocks/adeptus_insights/version.php';
        $this->assertFileExists($versionfile);
    }

    /**
     * Test that the block component name is correctly set.
     */
    public function test_block_component(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/blocks/adeptus_insights/version.php');
        $this->assertEquals('block_adeptus_insights', $plugin->component);
    }

    /**
     * Test that the block depends on the report plugin.
     */
    public function test_block_dependencies(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/blocks/adeptus_insights/version.php');
        $this->assertArrayHasKey('report_adeptus_insights', $plugin->dependencies);
    }

    /**
     * Test that the block capabilities are defined.
     */
    public function test_capabilities_defined(): void {
        $capabilities = get_all_capabilities();
        $capnames = array_column($capabilities, 'name');

        $this->assertContains('block/adeptus_insights:addinstance', $capnames);
        $this->assertContains('block/adeptus_insights:myaddinstance', $capnames);
        $this->assertContains('block/adeptus_insights:view', $capnames);
    }

    /**
     * Test that the block class exists and can be referenced.
     */
    public function test_block_class_exists(): void {
        $this->assertTrue(class_exists('\block_adeptus_insights'));
    }

    /**
     * Test that language strings are loadable.
     */
    public function test_language_strings_exist(): void {
        $pluginname = get_string('pluginname', 'block_adeptus_insights');
        $this->assertNotEmpty($pluginname);
        $this->assertEquals('Adeptus Insights', $pluginname);
    }

    /**
     * Test that the privacy provider implements required interfaces.
     */
    public function test_privacy_provider(): void {
        $this->assertTrue(
            is_a(
                privacy\provider::class,
                \core_privacy\local\metadata\provider::class,
                true
            )
        );
    }

    /**
     * Test that message providers are defined.
     */
    public function test_message_providers_defined(): void {
        global $CFG;
        $messageproviders = [];
        require($CFG->dirroot . '/blocks/adeptus_insights/db/messages.php');
        $this->assertArrayHasKey('alertnotification', $messageproviders);
        $this->assertArrayHasKey('criticalalert', $messageproviders);
        $this->assertArrayHasKey('alertrecovery', $messageproviders);
    }
}
