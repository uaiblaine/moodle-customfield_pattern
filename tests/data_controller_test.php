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

namespace customfield_pattern;

use core_customfield\category_controller;
use core_customfield_generator;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the data controller, through the course custom field handler
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(data_controller::class)]
final class data_controller_test extends \advanced_testcase {
    /** @var category_controller The course custom field category holding the fields. */
    private category_controller $category;

    /** @var field_controller[] Fields keyed by shortname. */
    private array $fields = [];

    /**
     * Create a CPF, a raw-stored CNPJ, a unique phone and a custom mask field with a regex
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->get_generator();
        $this->category = $generator->create_category();
        $configs = [
            'cpf' => ['preset' => 'cpf'],
            'cnpj' => ['preset' => 'cnpj', 'storeformat' => 'raw'],
            'phone' => ['preset' => 'phone_br', 'uniquevalues' => 1],
            'code' => [
                'preset' => 'custom',
                'mask' => 'AA-99|AAA-999',
                'regex' => '[A-Z].*',
                'regexerror' => 'Start with a capital letter',
            ],
            'free' => ['maxlength' => 5, 'defaultvalue' => ' abc '],
        ];
        foreach ($configs as $shortname => $config) {
            $this->fields[$shortname] = $generator->create_field([
                'categoryid' => $this->category->get('id'),
                'shortname' => $shortname,
                'type' => 'pattern',
                'configdata' => $config + ['displaysize' => 30, 'maxlength' => 100],
            ]);
        }
    }

    /**
     * Get the custom field generator
     *
     * @return core_customfield_generator
     */
    private function get_generator(): core_customfield_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_customfield');
    }

    /**
     * The data controller of one field for one course, read fresh from the handler
     *
     * @param int $courseid
     * @param string $shortname
     * @return data_controller
     */
    private function get_data(int $courseid, string $shortname): data_controller {
        $handler = $this->category->get_handler();
        foreach ($handler->get_instance_data($courseid, true) as $data) {
            if ($data->get_field()->get('shortname') === $shortname) {
                return $data;
            }
        }
        $this->fail("No data controller for {$shortname}");
    }

    /**
     * Saving through the course handler stores the normalised value and exports it masked
     *
     * @return void
     */
    public function test_handler_save_stores_normalised_values(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();

        $this->category->get_handler()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_cpf' => ' 52998224725 ',
            'customfield_cnpj' => '12.abc.345/01de-35',
            'customfield_phone' => '31987654321',
            'customfield_code' => 'abc123',
        ]);

        $stored = $DB->get_records_menu('customfield_data', ['instanceid' => $course->id], '', 'fieldid, charvalue');
        $this->assertSame('529.982.247-25', $stored[$this->fields['cpf']->get('id')]);
        $this->assertSame('12ABC34501DE35', $stored[$this->fields['cnpj']->get('id')]);
        $this->assertSame('(31) 98765-4321', $stored[$this->fields['phone']->get('id')]);
        $this->assertSame('abc-123', $stored[$this->fields['code']->get('id')]);

        $this->assertSame('12ABC34501DE35', $this->get_data((int) $course->id, 'cnpj')->get_value());
        $this->assertSame('12.ABC.345/01DE-35', $this->get_data((int) $course->id, 'cnpj')->export_value());
        $this->assertSame('529.982.247-25', $this->get_data((int) $course->id, 'cpf')->export_value());
    }

    /**
     * The handler's validation reports each rule on its element; valid values report nothing
     *
     * @return void
     */
    public function test_handler_validation(): void {
        $course = $this->getDataGenerator()->create_course();
        $handler = $this->category->get_handler();

        $errors = $handler->instance_form_validation([
            'id' => $course->id,
            'customfield_cpf' => '111.111.111-11',
            'customfield_cnpj' => '11.222.333/0001-82',
            'customfield_phone' => '319876543',
            'customfield_code' => 'ab12',
            'customfield_free' => 'abcdef',
        ], []);
        // Equals, not same: the handler's field order among equal sort orders is not part of the contract.
        $this->assertEquals([
            'customfield_cpf' => get_string('errorcpf', 'customfield_pattern'),
            'customfield_cnpj' => get_string('errorcnpj', 'customfield_pattern'),
            'customfield_phone' => get_string('errormask', 'customfield_pattern', '(99) 9999-9999 | (99) 99999-9999'),
            'customfield_code' => 'Start with a capital letter',
            'customfield_free' => get_string('errormaxlength', 'customfield_pattern', 5),
        ], $errors);

        // Control: valid values, typed in any accepted shape, and empty values pass.
        $errors = $handler->instance_form_validation([
            'id' => $course->id,
            'customfield_cpf' => '52998224725',
            'customfield_cnpj' => '12.ABC.345/01DE-35',
            'customfield_phone' => '(31) 3234-5678',
            'customfield_code' => 'AB12',
            'customfield_free' => '',
        ], []);
        $this->assertSame([], $errors);
    }

    /**
     * Uniqueness compares the value as it would be stored, not as it was typed
     *
     * @return void
     */
    public function test_uniqueness_compares_normalised_values(): void {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $handler = $this->category->get_handler();
        $handler->instance_form_save((object) ['id' => $course1->id, 'customfield_phone' => '(31) 98765-4321']);

        $errors = $handler->instance_form_validation(['id' => $course2->id, 'customfield_phone' => '31987654321'], []);
        $this->assertSame(['customfield_phone' => get_string('erroruniquevalues', 'core_customfield')], $errors);

        // Control: another number is unique.
        $errors = $handler->instance_form_validation(['id' => $course2->id, 'customfield_phone' => '31987654320'], []);
        $this->assertSame([], $errors);
    }

    /**
     * The form element carries the mask for the formatter, which is loaded once per page
     *
     * @return void
     */
    public function test_instance_form_definition(): void {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/formslib.php');
        $course = $this->getDataGenerator()->create_course();
        $mform = new \MoodleQuickForm('test', 'post', '');

        foreach (['cpf', 'cnpj', 'phone', 'free'] as $shortname) {
            $this->get_data((int) $course->id, $shortname)->instance_form_definition($mform);
        }

        $cpf = $mform->getElement('customfield_cpf');
        $this->assertSame('999.999.999-99', $cpf->getAttribute('data-customfield-pattern-mask'));
        $this->assertSame('999.999.999-99', $cpf->getAttribute('placeholder'));
        $this->assertSame('numeric', $cpf->getAttribute('inputmode'));
        $this->assertEquals(14, $cpf->getAttribute('maxlength'));

        $cnpj = $mform->getElement('customfield_cnpj');
        $this->assertSame('**.***.***/****-99', $cnpj->getAttribute('data-customfield-pattern-mask'));
        $this->assertNull($cnpj->getAttribute('inputmode'));

        $phone = $mform->getElement('customfield_phone');
        $this->assertSame('(99) 9999-9999|(99) 99999-9999', $phone->getAttribute('data-customfield-pattern-mask'));
        $this->assertEquals(15, $phone->getAttribute('maxlength'));

        // Control: a field without a mask has no mask attribute and keeps its maximum length.
        $free = $mform->getElement('customfield_free');
        $this->assertNull($free->getAttribute('data-customfield-pattern-mask'));
        $this->assertEquals(5, $free->getAttribute('maxlength'));

        $amdjscode = (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);
        $calls = 0;
        foreach ($amdjscode as $code) {
            $calls += substr_count($code, "require(['customfield_pattern/mask']");
        }
        $this->assertSame(1, $calls);
    }

    /**
     * A raw-stored value goes into the form, and out of the default, in its masked shape
     *
     * @return void
     */
    public function test_form_data_and_default(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->get_generator()->add_instance_data($this->fields['cnpj'], (int) $course->id, '12abc34501de35');

        $instance = new \stdClass();
        $this->get_data((int) $course->id, 'cnpj')->instance_form_before_set_data($instance);
        $this->assertSame('12.ABC.345/01DE-35', $instance->customfield_cnpj);

        // The default value is stored normalised (trimmed) and shown when there is no data row.
        $free = $this->get_data((int) $course->id, 'free');
        $this->assertSame('abc', $free->get_default_value());
        $this->assertSame('abc', $free->export_value());
    }

    /**
     * An empty value is saved empty and exports as null
     *
     * @return void
     */
    public function test_empty_value(): void {
        $course = $this->getDataGenerator()->create_course();
        $data = $this->get_generator()->add_instance_data($this->fields['cpf'], (int) $course->id, '   ');
        $this->assertSame('', $data->get_value());
        $this->assertNull($data->export_value());
    }
}
