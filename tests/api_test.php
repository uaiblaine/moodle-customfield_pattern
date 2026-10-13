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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the public API used by consumers that keep values outside customfield_data
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(api::class)]
final class api_test extends \advanced_testcase {
    /**
     * The configuration version is an integer a consumer can store
     *
     * @return void
     */
    public function test_config_version(): void {
        $this->assertSame(1, api::CONFIG_VERSION);
    }

    /**
     * Configurations and the settings expected to carry an error
     *
     * @return array
     */
    public static function config_errors_provider(): array {
        return [
            'empty configuration' => [[], []],
            'every preset' => [['preset' => 'cpf'], []],
            'unknown keys are ignored' => [['preset' => 'cep', 'displaysize' => 'x', 'other' => ['a']], []],
            'unknown preset' => [['preset' => 'nonsense'], ['preset' => 'errorconfigpreset']],
            'custom without a mask' => [['preset' => 'custom', 'mask' => ' '], ['mask' => 'errorconfigmaskrequired']],
            'custom with a mask without tokens' => [['preset' => 'custom', 'mask' => '--'], ['mask' => 'errorconfigmask']],
            'mask ignored for a preset' => [['preset' => 'cpf', 'mask' => '--'], []],
            'regex that does not compile' => [['regex' => '([0-9]'], ['regex' => 'errorconfigregex']],
            'unknown storage format' => [['storeformat' => 'hashed'], ['storeformat' => 'errorconfigstoreformat']],
            'maximum length below the masked value' => [['preset' => 'cpf', 'maxlength' => '13'], ['maxlength' => 14]],
            'maximum length fits the masked value' => [['preset' => 'cpf', 'maxlength' => '14'], []],
            'maximum length below the raw value' => [
                ['preset' => 'cpf', 'storeformat' => 'raw', 'maxlength' => '10'],
                ['maxlength' => 11],
            ],
            'maximum length fits the raw value' => [['preset' => 'cpf', 'storeformat' => 'raw', 'maxlength' => '11'], []],
            'several problems at once' => [
                ['preset' => 'custom', 'mask' => '', 'regex' => '(', 'storeformat' => 'x'],
                ['mask' => 'errorconfigmaskrequired', 'regex' => 'errorconfigregex', 'storeformat' => 'errorconfigstoreformat'],
            ],
        ];
    }

    /**
     * config_errors() reports each broken setting under its name
     *
     * @param array $config
     * @param array $expected setting => lang string identifier, or the needed length for maxlength
     * @return void
     */
    #[DataProvider('config_errors_provider')]
    public function test_config_errors(array $config, array $expected): void {
        $strings = [];
        foreach ($expected as $setting => $identifier) {
            $strings[$setting] = is_int($identifier)
                ? get_string('errorconfigmaxlenmask', 'customfield_pattern', $identifier)
                : get_string($identifier, 'customfield_pattern');
        }
        $this->assertSame($strings, api::config_errors($config));
    }

    /**
     * validate(), normalise() and format() over the same configuration
     *
     * @return void
     */
    public function test_value_functions(): void {
        $masked = ['preset' => 'cpf'];
        $raw = ['preset' => 'cpf', 'storeformat' => 'raw'];

        $this->assertNull(api::validate($masked, '529.982.247-25'));
        $this->assertNull(api::validate($masked, '  '));
        $this->assertSame(get_string('errorcpf', 'customfield_pattern'), api::validate($masked, '529.982.247-24'));
        $this->assertSame(
            get_string('errormask', 'customfield_pattern', '999.999.999-99'),
            api::validate($masked, '5299822472')
        );

        $this->assertSame('529.982.247-25', api::normalise($masked, ' 52998224725 '));
        $this->assertSame('52998224725', api::normalise($raw, '529.982.247-25'));
        $this->assertSame('52998224725', api::normalise($raw, api::normalise($raw, '529.982.247-25')), 'Idempotent');
        $this->assertSame('abc', api::normalise($raw, ' abc '), 'A value that does not fit comes back trimmed');

        $this->assertSame('529.982.247-25', api::format($raw, '52998224725'));
        $this->assertSame('529.982.247-25', api::format($masked, '529.982.247-25'));
        $this->assertSame('123', api::format($raw, '123'), 'A value that does not fit is shown as stored');
        $this->assertSame('<b>', api::format([], '<b>'), 'Plain text: the caller escapes');
    }

    /**
     * The regex error message is formatted, in the given context or the system one
     *
     * @return void
     */
    public function test_validate_regex_message(): void {
        $this->resetAfterTest();
        $config = ['regex' => '[a-z]+', 'regexerror' => 'Lower case & nothing else'];

        $this->assertNull(api::validate($config, 'abc'));
        $this->assertSame('Lower case &amp; nothing else', api::validate($config, 'ABC'));
        $this->assertSame(
            'Lower case &amp; nothing else',
            api::validate($config, 'ABC', \context_course::instance(SITEID))
        );
        $this->assertSame(get_string('errorregex', 'customfield_pattern'), api::validate(['regex' => '[a-z]+'], 'ABC'));
    }

    /**
     * A setting that is not a scalar reads as absent instead of failing
     *
     * @return void
     */
    public function test_non_scalar_settings_read_as_absent(): void {
        $config = ['preset' => ['cpf'], 'storeformat' => (object) [], 'regex' => ['x']];

        $this->assertSame([], api::masks($config));
        $this->assertSame('abc', api::normalise($config, 'abc'));
        $this->assertNull(api::validate($config, 'abc'));
        $this->assertSame([], api::config_errors($config));
    }

    /**
     * Every preset has a name, in the settings form's order
     *
     * @return void
     */
    public function test_presets(): void {
        $presets = api::presets();

        $this->assertSame(['none', 'cpf', 'cnpj', 'cep', 'phone_br', 'custom'], array_keys($presets));
        $this->assertSame(get_string('presetphonebr', 'customfield_pattern'), $presets['phone_br']);
    }

    /**
     * The masks of a preset, of a custom mask and of nothing
     *
     * @return void
     */
    public function test_masks(): void {
        $this->assertSame(['(99) 9999-9999', '(99) 99999-9999'], api::masks(['preset' => 'phone_br']));
        $this->assertSame(['AA-99', 'AAA-999'], api::masks(['preset' => 'custom', 'mask' => 'AA-99 | AAA-999']));
        $this->assertSame([], api::masks(['preset' => 'custom', 'mask' => '--']));
        $this->assertSame([], api::masks([]));
    }

    /**
     * The input attributes the formatter and the browser read
     *
     * @return void
     */
    public function test_input_attributes(): void {
        $this->assertSame([
            'maxlength' => 14,
            'placeholder' => '999.999.999-99',
            'data-customfield-pattern-mask' => '999.999.999-99',
            'inputmode' => 'numeric',
        ], api::input_attributes(['preset' => 'cpf', 'storeformat' => 'raw']));

        $cnpj = api::input_attributes(['preset' => 'cnpj']);
        $this->assertSame('**.***.***/****-99', $cnpj['data-customfield-pattern-mask']);
        $this->assertArrayNotHasKey('inputmode', $cnpj);

        $this->assertSame(15, api::input_attributes(['preset' => 'phone_br'])['maxlength']);

        // Control: without a mask there is no mask attribute, only a configured maximum length.
        $this->assertSame(['maxlength' => 5], api::input_attributes(['regex' => 'x', 'maxlength' => '5']));
        $this->assertSame([], api::input_attributes([]));
    }

    /**
     * js_init() asks for the formatter once per requirements manager
     *
     * @return void
     */
    public function test_js_init(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');

        api::js_init();
        api::js_init();
        $amdjscode = (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);
        $this->assertSame(1, substr_count(implode('', $amdjscode), "require(['customfield_pattern/mask']"));

        // A modal form renders inside a fresh manager, which must get the module again; collecting
        // starts only once the theme is set up, as core_form\external\dynamic_form::execute() has it.
        $PAGE->initialise_theme_and_output();
        $PAGE->start_collecting_javascript_requirements();
        api::js_init();
        $this->assertStringContainsString('customfield_pattern/mask', $PAGE->requires->get_end_code());
    }

    /**
     * The field controller's value API answers as the API does for its configuration
     *
     * @return void
     */
    public function test_field_controller_delegates(): void {
        $config = ['preset' => 'cnpj', 'storeformat' => 'raw'];
        $field = new field_controller(0, (object) [
            'type' => 'pattern',
            'shortname' => 'pattern',
            'name' => 'Pattern',
            'configdata' => json_encode($config),
        ]);

        $this->assertSame($config, $field->get_config());
        $this->assertSame(api::normalise($config, '12.abc.345/01de-35'), $field->normalise_value('12.abc.345/01de-35'));
        $this->assertSame(api::validate($config, '12ABC34501DE34'), $field->validate_value('12ABC34501DE34'));
        $this->assertSame(api::format($config, '12ABC34501DE35'), $field->format_for_display('12ABC34501DE35'));
        $this->assertSame(api::masks($config), $field->get_masks());
    }

    /**
     * config_errors() caps the regex length and refuses one that cannot answer on the probe value
     *
     * @return void
     */
    public function test_config_errors_bound_the_regex(): void {
        $this->assertSame([], api::config_errors(['regex' => str_repeat('a', 512)]));
        $this->assertSame([], api::config_errors(['regex' => '[A-Z]{3}-[0-9]{6}|(ab|cd)+']));
        $this->assertSame(
            ['regex' => get_string('errorconfigregexlength', 'customfield_pattern', 512)],
            api::config_errors(['regex' => str_repeat('a', 513)])
        );
        // Characters, not bytes: 512 two-byte letters fit.
        $this->assertSame([], api::config_errors(['regex' => str_repeat('é', 512)]));
        $this->assertSame(
            ['regex' => get_string('errorconfigregexslow', 'customfield_pattern')],
            api::config_errors(['regex' => '(a+)+$'])
        );
    }

    /**
     * A catastrophic regex stops at the lowered match limit and reports that it could not check
     *
     * The site's own limit is raised first, so only the limit validate() sets keeps the run short;
     * both settings are back to the site's afterwards.
     *
     * @return void
     */
    public function test_catastrophic_regex_is_bounded(): void {
        $sitelimit = ini_get('pcre.backtrack_limit');
        $sitejit = ini_get('pcre.jit');
        ini_set('pcre.backtrack_limit', '2000000000');
        try {
            $start = microtime(true);
            $error = api::validate(['regex' => '(a+)+$'], str_repeat('a', 40) . '!');
            $elapsed = microtime(true) - $start;

            $this->assertSame(get_string('errorregexunchecked', 'customfield_pattern'), $error);
            $this->assertLessThan(1.0, $elapsed, 'The run is bounded by the lowered match limit');
            $this->assertSame('2000000000', ini_get('pcre.backtrack_limit'), 'The limit is restored');
            $this->assertSame($sitejit, ini_get('pcre.jit'), 'The JIT setting is restored');
        } finally {
            ini_set('pcre.backtrack_limit', (string) $sitelimit);
        }
    }

    /**
     * Plain expressions on long values still answer yes or no
     *
     * @return void
     */
    public function test_plain_regex_on_a_long_value(): void {
        $long = str_repeat('Lorem ipsum ', 110);

        $this->assertNull(api::validate(['regex' => '[A-Za-z ]+'], $long));
        $this->assertNull(api::validate(['regex' => '.*ipsum.*'], $long));
        $this->assertSame(get_string('errorregex', 'customfield_pattern'), api::validate(['regex' => '[a-z ]+'], $long));
    }
}
