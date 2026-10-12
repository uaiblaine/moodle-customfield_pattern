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

namespace customfield_pattern\privacy;

use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the privacy provider
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The plugin counts as compliant
     *
     * Core's own compliance test sweeps every component but is not in the plugin's testsuite,
     * which is all moodle-plugin-ci runs, so the check is repeated here
     * ({@see \core_privacy\manager::component_is_compliant()}).
     *
     * @return void
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('customfield_pattern'));
    }

    /**
     * A raw-stored value is exported as it is shown, with its mask
     *
     * @return void
     */
    public function test_export_customfield_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category();
        $field = $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'pattern',
            'configdata' => ['preset' => 'cnpj', 'storeformat' => 'raw'],
        ]);
        $data = $generator->add_instance_data($field, (int) $course->id, '12.ABC.345/01DE-35');
        $this->assertSame('12ABC34501DE35', $data->get_value());

        $subcontext = ['Custom fields data', (string) $data->get('id')];
        writer::reset();
        provider::export_customfield_data($data, (object) ['id' => $data->get('id')], $subcontext);

        $exported = writer::with_context($data->get_context())->get_data($subcontext);
        $this->assertSame('12.ABC.345/01DE-35', $exported->charvalue);
    }
}
