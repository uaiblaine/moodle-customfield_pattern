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

use customfield_pattern\local\mask;
use customfield_pattern\local\pattern;

/**
 * Public API of the pattern rules, for components that keep values outside customfield_data
 *
 * Every method is a pure function of a configuration array and its argument; nothing reads a
 * custom field record. The field type itself goes through the same functions
 * ({@see field_controller}, {@see data_controller}), so a consumer and a course custom field
 * with the same configuration accept, store and show the same values.
 *
 * The configuration array has these keys, every one optional, every value a string:
 *
 * - `preset`: `none` (the default), `cpf`, `cnpj`, `cep`, `phone_br` or `custom` ({@see self::presets()});
 * - `mask`: the mask when `preset` is `custom`, alternatives separated by `|`; ignored otherwise;
 * - `regex`: a regular expression body the whole stored value must match, anchored automatically;
 * - `regexerror`: the message shown when the regex rejects a value, formatted with format_string();
 * - `storeformat`: `masked` (the default) or `raw`, the shape {@see self::normalise()} returns;
 * - `maxlength`: the longest stored value, as an integer string; absent or 0 for no limit.
 *
 * Unknown keys are ignored, so a consumer may keep other settings beside these. An unknown
 * `preset` or `storeformat` reads as the default everywhere but in {@see self::config_errors()},
 * which reports it: a configuration written by a later version degrades instead of failing.
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api {
    /**
     * Version of the configuration shape
     *
     * Raised only when an existing key changes meaning or is removed, never for a new optional
     * key: a consumer that stored configurations under one version can check this constant
     * before handing them back.
     *
     * @var int
     */
    public const CONFIG_VERSION = 1;

    /**
     * The problems of a configuration, one message per setting
     *
     * Checks that the preset and the storage format are known, that a custom preset has a valid
     * mask, that the regex compiles and, when `maxlength` is given, that it can hold the longest
     * value the mask stores. Whether `maxlength` itself is in range is the caller's rule.
     *
     * @param array $config the configuration (see the class description)
     * @return array setting name ('preset', 'mask', 'regex', 'storeformat' or 'maxlength') =>
     *      error message, safe to output as HTML like the result of get_string(); empty when valid
     */
    public static function config_errors(array $config): array {
        $errors = [];

        $preset = self::setting($config, 'preset');
        if ($preset !== '' && !in_array($preset, pattern::presets(), true)) {
            $errors['preset'] = get_string('errorconfigpreset', 'customfield_pattern');
        }

        if ($preset === pattern::PRESET_CUSTOM) {
            $custom = trim(self::setting($config, 'mask'));
            if ($custom === '') {
                $errors['mask'] = get_string('errorconfigmaskrequired', 'customfield_pattern');
            } else if (!mask::is_valid($custom)) {
                $errors['mask'] = get_string('errorconfigmask', 'customfield_pattern');
            }
        }

        $regex = trim(self::setting($config, 'regex'));
        if ($regex !== '' && !pattern::regex_compiles($regex)) {
            $errors['regex'] = get_string('errorconfigregex', 'customfield_pattern');
        }

        $storeformat = self::setting($config, 'storeformat');
        if ($storeformat !== '' && !in_array($storeformat, [pattern::STORE_MASKED, pattern::STORE_RAW], true)) {
            $errors['storeformat'] = get_string('errorconfigstoreformat', 'customfield_pattern');
        }

        $maxlength = (int) self::setting($config, 'maxlength');
        $pattern = new pattern(self::clean($config));
        $masks = $pattern->get_masks();
        if ($maxlength > 0 && $masks) {
            if ($pattern->get_storeformat() === pattern::STORE_RAW) {
                $needed = mask::max_token_count($masks);
            } else {
                $needed = mask::max_length($masks);
            }
            if ($maxlength < $needed) {
                $errors['maxlength'] = get_string('errorconfigmaxlenmask', 'customfield_pattern', $needed);
            }
        }

        return $errors;
    }

    /**
     * Validate a typed value: mask, CPF/CNPJ check digits, maximum length, then regex
     *
     * Empty input is valid: whether a value is required, and whether it is unique, are the
     * caller's checks (uniqueness on the {@see self::normalise()}d value).
     *
     * @param array $config the configuration (see the class description)
     * @param string $value the value as typed
     * @param \context|null $context context to format `regexerror` in; system when null
     * @return string|null null when valid, else the localised error message, safe to output as
     *      HTML like the result of get_string()
     */
    public static function validate(array $config, string $value, ?\context $context = null): ?string {
        return (new pattern(self::clean($config), $context))->validate($value);
    }

    /**
     * The value as it is stored: trimmed, fitted into the mask, masked or raw per `storeformat`
     *
     * Uppercases the characters typed into `*` tokens. A value no mask alternative fits comes
     * back trimmed and otherwise untouched: this function is total, {@see self::validate()} is
     * what rejects such a value. Normalising a normalised value returns it unchanged.
     *
     * @param array $config the configuration (see the class description)
     * @param string $raw the value as typed
     * @return string '' for empty input
     */
    public static function normalise(array $config, string $raw): string {
        return (new pattern(self::clean($config)))->normalise($raw);
    }

    /**
     * A stored value as it is shown: a raw-stored value gets its mask back
     *
     * A value that does not fit the current mask (stored under an earlier configuration) is
     * returned as stored.
     *
     * @param array $config the configuration (see the class description)
     * @param string $stored the value as stored
     * @return string plain text, NOT escaped: the caller escapes it
     */
    public static function format(array $config, string $stored): string {
        return (new pattern(self::clean($config)))->format_for_display($stored);
    }

    /**
     * Every preset with its localised name, in the order a settings form lists them
     *
     * @return array preset value => plain-text name
     */
    public static function presets(): array {
        $names = [
            pattern::PRESET_NONE => 'presetnone',
            pattern::PRESET_CPF => 'presetcpf',
            pattern::PRESET_CNPJ => 'presetcnpj',
            pattern::PRESET_CEP => 'presetcep',
            pattern::PRESET_PHONE_BR => 'presetphonebr',
            pattern::PRESET_CUSTOM => 'presetcustom',
        ];
        $presets = [];
        foreach (pattern::presets() as $preset) {
            $presets[$preset] = get_string($names[$preset], 'customfield_pattern');
        }
        return $presets;
    }

    /**
     * The alternative masks in force
     *
     * @param array $config the configuration (see the class description)
     * @return array list of masks; empty when the preset is none, or custom with an unusable mask
     */
    public static function masks(array $config): array {
        return (new pattern(self::clean($config)))->get_masks();
    }

    /**
     * Attributes of the text input a value is typed into
     *
     * With a mask: `data-customfield-pattern-mask` (the masks, `|`-separated, which the
     * formatter of {@see self::js_init()} reads), `placeholder` (the first mask), `maxlength`
     * (the longest masked value, whatever the storage format, because the user types the masked
     * shape) and, when every token is a digit, `inputmode="numeric"`. Without a mask, only
     * `maxlength` when the configuration sets one.
     *
     * @param array $config the configuration (see the class description)
     * @return array attribute name => value, unescaped (an HTML writer or template escapes them)
     */
    public static function input_attributes(array $config): array {
        $masks = self::masks($config);
        if (!$masks) {
            $maxlength = (int) self::setting($config, 'maxlength');
            return $maxlength > 0 ? ['maxlength' => $maxlength] : [];
        }

        $attributes = [
            'maxlength' => mask::max_length($masks),
            'placeholder' => $masks[0],
            'data-customfield-pattern-mask' => implode(mask::ALTERNATIVE_SEPARATOR, $masks),
        ];
        if (mask::is_numeric($masks)) {
            $attributes['inputmode'] = 'numeric';
        }
        return $attributes;
    }

    /**
     * Load the typing formatter on the current page, once per JavaScript requirements manager
     *
     * The module listens on the document, so inputs added to the page afterwards, by a modal
     * form or by the consumer's own JavaScript, are formatted too. A form shown in a modal is
     * rendered inside a fresh requirements manager: call this while rendering, not while
     * defining the form ({@see form\masked_text_element}).
     *
     * @return void
     */
    public static function js_init(): void {
        global $PAGE;
        if ($PAGE->requires->should_create_one_time_item_now('customfield_pattern_mask')) {
            $PAGE->requires->js_call_amd('customfield_pattern/mask', 'init');
        }
    }

    /**
     * The configuration without the values that are not scalars, which no setting takes
     *
     * @param array $config
     * @return array
     */
    private static function clean(array $config): array {
        return array_filter($config, 'is_scalar');
    }

    /**
     * One setting of a configuration as a string, '' when absent or not a scalar
     *
     * @param array $config
     * @param string $name
     * @return string
     */
    private static function setting(array $config, string $name): string {
        $value = $config[$name] ?? '';
        return is_scalar($value) ? (string) $value : '';
    }
}
