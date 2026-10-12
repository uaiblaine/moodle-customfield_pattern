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

use core_customfield_generator;
use customfield_pattern\local\pattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the field controller: value API and configuration form
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(field_controller::class)]
#[CoversClass(pattern::class)]
final class field_controller_test extends \advanced_testcase {
    /**
     * A field controller built without a category, as a consumer without customfield_data does
     *
     * @param array $configdata
     * @return field_controller
     */
    private function make_field(array $configdata): field_controller {
        return new field_controller(0, (object) [
            'type' => 'pattern',
            'shortname' => 'pattern',
            'name' => 'Pattern',
            'configdata' => json_encode($configdata),
        ]);
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
     * Configurations, typed values, and the expected stored value and error
     *
     * Each error is a lang string identifier, or a literal message prefixed with '='.
     *
     * @return array
     */
    public static function value_provider(): array {
        $cpf = ['preset' => 'cpf'];
        $cnpj = ['preset' => 'cnpj'];
        $phone = ['preset' => 'phone_br'];
        $custom = ['preset' => 'custom', 'mask' => 'AA-99|AAA-999'];
        $regex = [
            'preset' => 'custom',
            'mask' => '*****',
            'regex' => '[A-Z]{2}[0-9]{3}',
            'regexerror' => 'Two letters, three digits',
        ];
        return [
            'empty is valid and stays empty' => [$cpf, '  ', '', null],
            'valid CPF' => [$cpf, '52998224725', '529.982.247-25', null],
            'CPF of repeated digits' => [$cpf, '111.111.111-11', '111.111.111-11', 'errorcpf'],
            'CPF with a wrong check digit' => [$cpf, '529.982.247-24', '529.982.247-24', 'errorcpf'],
            'CPF of the wrong length' => [$cpf, '5299822472', '5299822472', 'errormask'],
            'numeric CNPJ' => [$cnpj, '11222333000181', '11.222.333/0001-81', null],
            'alphanumeric CNPJ, official example' => [$cnpj, '12.abc.345/01de-35', '12.ABC.345/01DE-35', null],
            'alphanumeric CNPJ, computed' => [$cnpj, '1A2B3C4D000179', '1A.2B3.C4D/0001-79', null],
            'CNPJ with a wrong check digit' => [$cnpj, '11.222.333/0001-82', '11.222.333/0001-82', 'errorcnpj'],
            'CEP' => [['preset' => 'cep'], '30130100', '30130-100', null],
            'landline' => [$phone, '3132345678', '(31) 3234-5678', null],
            'mobile' => [$phone, '(31) 98765-4321', '(31) 98765-4321', null],
            'phone of nine digits' => [$phone, '319876543', '319876543', 'errormask'],
            'custom mask, short alternative' => [$custom, 'ab12', 'ab-12', null],
            'custom mask, long alternative' => [$custom, 'abc123', 'abc-123', null],
            'custom mask, no alternative fits' => [$custom, 'ab1', 'ab1', 'errormask'],
            'regex passes on the normalised value' => [$regex, 'ab123', 'AB123', null],
            'regex fails with the custom message' => [$regex, 'abc12', 'ABC12', '=Two letters, three digits'],
            'regex fails with the generic message' => [['regex' => '[0-9]+'], 'abc', 'abc', 'errorregex'],
            'no mask, trimmed' => [[], '  free text ', 'free text', null],
            'maximum length' => [['maxlength' => 3], 'abcd', 'abcd', 'errormaxlength'],
            'maximum length counts the raw value' => [
                $cpf + ['storeformat' => 'raw', 'maxlength' => 11],
                '529.982.247-25',
                '52998224725',
                null,
            ],
        ];
    }

    /**
     * normalise_value() and validate_value() without a category or a data row
     *
     * @param array $configdata
     * @param string $typed
     * @param string $stored
     * @param string|null $error
     * @return void
     */
    #[DataProvider('value_provider')]
    public function test_normalise_and_validate(array $configdata, string $typed, string $stored, ?string $error): void {
        $field = $this->make_field($configdata);
        $this->assertSame($stored, $field->normalise_value($typed));

        $actual = $field->validate_value($typed);
        if ($error === null) {
            $this->assertNull($actual);
        } else if (str_starts_with($error, '=')) {
            $this->assertSame(substr($error, 1), $actual);
        } else {
            $a = null;
            if ($error === 'errormask') {
                $a = implode(' | ', $field->get_masks());
            } else if ($error === 'errormaxlength') {
                $a = $configdata['maxlength'];
            }
            $this->assertSame(get_string($error, 'customfield_pattern', $a), $actual);
        }
    }

    /**
     * The mask error names the expected format
     *
     * @return void
     */
    public function test_mask_error_names_the_format(): void {
        $field = $this->make_field(['preset' => 'phone_br']);
        $this->assertSame(
            get_string('errormask', 'customfield_pattern', '(99) 9999-9999 | (99) 99999-9999'),
            $field->validate_value('123')
        );
    }

    /**
     * Raw storage strips the literals; display puts them back
     *
     * @return void
     */
    public function test_raw_storage_and_display(): void {
        $raw = $this->make_field(['preset' => 'cnpj', 'storeformat' => 'raw']);
        $this->assertSame('12ABC34501DE35', $raw->normalise_value('12.abc.345/01de-35'));
        $this->assertSame('12.ABC.345/01DE-35', $raw->format_for_display('12ABC34501DE35'));
        // A value stored under another mask is shown as stored.
        $this->assertSame('legacy value', $raw->format_for_display('legacy value'));
        $this->assertSame('', $raw->format_for_display(''));

        // Masked storage: display never rewrites the stored value, even a raw one.
        $masked = $this->make_field(['preset' => 'cnpj', 'storeformat' => 'masked']);
        $this->assertSame('12.ABC.345/01DE-35', $masked->normalise_value('12abc34501de35'));
        $this->assertSame('12ABC34501DE35', $masked->format_for_display('12ABC34501DE35'));
    }

    /**
     * A broken configuration degrades: an unknown preset or an invalid custom mask means no mask
     *
     * @return void
     */
    public function test_unusable_configuration_means_no_mask(): void {
        $this->assertSame([], $this->make_field(['preset' => 'nonsense'])->get_masks());
        $this->assertSame([], $this->make_field(['preset' => 'custom', 'mask' => 'abc'])->get_masks());
        $this->assertSame(['99-99'], $this->make_field(['preset' => 'custom', 'mask' => '99-99'])->get_masks());
    }

    /**
     * Valid configuration data for the validation tests
     *
     * @param array $overrides
     * @return array
     */
    private static function config(array $overrides = []): array {
        return ['configdata' => $overrides + [
            'defaultvalue' => '',
            'displaysize' => 50,
            'maxlength' => 1333,
            'preset' => 'custom',
            'mask' => '999-AAA',
            'regex' => '',
            'regexerror' => '',
            'storeformat' => 'masked',
        ]];
    }

    /**
     * Configurations and the configuration form element expected to carry an error
     *
     * @return array
     */
    public static function config_validation_provider(): array {
        return [
            'valid' => [[], null, null],
            'custom preset without a mask' => [['mask' => ''], 'configdata[mask]', 'errorconfigmaskrequired'],
            'custom mask without a token' => [['mask' => '---'], 'configdata[mask]', 'errorconfigmask'],
            'custom mask with an empty alternative' => [['mask' => '999|'], 'configdata[mask]', 'errorconfigmask'],
            'mask not checked for another preset' => [['preset' => 'cpf', 'mask' => ''], null, null],
            'regex that does not compile' => [['regex' => '([0-9]'], 'configdata[regex]', 'errorconfigregex'],
            'regex with a slash compiles' => [['regex' => '[0-9]{3}/[A-Z]+'], null, null],
            'maximum length below the masked value' => [['maxlength' => 6], 'configdata[maxlength]', 'errorconfigmaxlenmask'],
            'maximum length fits the raw value' => [['maxlength' => 6, 'storeformat' => 'raw'], null, null],
            'maximum length out of range' => [['maxlength' => 2000], 'configdata[maxlength]', 'errorconfigmaxlen'],
            'display size out of range' => [['displaysize' => 0], 'configdata[displaysize]', 'errorconfigdisplaysize'],
            'default value that fails the mask' => [['defaultvalue' => 'abc'], 'configdata[defaultvalue]', 'errormask'],
            'default value that fits' => [['defaultvalue' => '123abc'], null, null],
        ];
    }

    /**
     * config_form_validation() flags each broken setting on its own element
     *
     * @param array $overrides
     * @param string|null $element
     * @param string|null $identifier
     * @return void
     */
    #[DataProvider('config_validation_provider')]
    public function test_config_form_validation(array $overrides, ?string $element, ?string $identifier): void {
        $this->resetAfterTest();
        $category = $this->get_generator()->create_category();
        $field = \core_customfield\field_controller::create(0, (object) ['type' => 'pattern'], $category);

        $errors = $field->config_form_validation(self::config($overrides));
        if ($element === null) {
            $this->assertSame([], $errors);
            return;
        }
        $this->assertSame([$element], array_keys($errors));
        if ($identifier === 'errormask') {
            $this->assertSame(get_string('errormask', 'customfield_pattern', '999-AAA'), $errors[$element]);
        } else if ($identifier === 'errorconfigmaxlenmask') {
            $this->assertSame(get_string($identifier, 'customfield_pattern', 7), $errors[$element]);
        } else {
            $this->assertSame(get_string($identifier, 'customfield_pattern'), $errors[$element]);
        }
    }

    /**
     * The configuration form builds, validates and saves a pattern field
     *
     * @return void
     */
    public function test_config_form_submission(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->get_generator()->create_category();
        $field = $this->get_generator()->create_field([
            'categoryid' => $category->get('id'),
            'shortname' => 'cnpj',
            'type' => 'pattern',
            'configdata' => self::config(['preset' => 'cnpj', 'storeformat' => 'raw'])['configdata'],
        ]);

        $submitdata = (array) $field->to_record();
        $submitdata['configdata'] = $field->get('configdata');
        $submitdata['configdata']['regex'] = '[0-9A-Z]{14}';

        $submitdata = \core_customfield\field_config_form::mock_ajax_submit($submitdata);
        $form = new \core_customfield\field_config_form(null, null, 'post', '', null, true, $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $saved = \core_customfield\field_controller::create((int) $field->get('id'));
        $this->assertSame('[0-9A-Z]{14}', $saved->get_configdata_property('regex'));
        $this->assertSame('raw', $saved->get_configdata_property('storeformat'));

        // Control: the same form rejects a regex that does not compile.
        $submitdata['configdata']['regex'] = '([0-9]';
        $submitdata = \core_customfield\field_config_form::mock_ajax_submit($submitdata);
        $form = new \core_customfield\field_config_form(null, null, 'post', '', null, true, $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertFalse($form->is_validated());
    }

    /**
     * Values from an external source (the upload course tool) are normalised
     *
     * @return void
     */
    public function test_parse_value(): void {
        $this->assertSame('30130-100', $this->make_field(['preset' => 'cep'])->parse_value('30130100'));
    }
}
