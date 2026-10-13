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

namespace customfield_pattern\local;

/**
 * Normalisation and validation of one field's values, a pure function of its configuration
 *
 * Built from a configdata array, so the field configuration form can validate the default
 * value against settings that are not saved yet, and a consumer without a customfield_data
 * row can validate input against a saved field ({@see \customfield_pattern\field_controller}).
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pattern {
    /** @var string No mask: the value is free text, checked only by length and regex. */
    public const PRESET_NONE = 'none';
    /** @var string Brazilian individual taxpayer number. */
    public const PRESET_CPF = 'cpf';
    /** @var string Brazilian company taxpayer number, alphanumeric since July 2026. */
    public const PRESET_CNPJ = 'cnpj';
    /** @var string Brazilian postal code. */
    public const PRESET_CEP = 'cep';
    /** @var string Brazilian phone number with area code, landline or mobile. */
    public const PRESET_PHONE_BR = 'phone_br';
    /** @var string The mask comes from the field's `mask` setting. */
    public const PRESET_CUSTOM = 'custom';

    /** @var string Store the value with the mask's literals. */
    public const STORE_MASKED = 'masked';
    /** @var string Store only the characters typed into the mask's tokens. */
    public const STORE_RAW = 'raw';

    /** @var int Longest regular expression body a configuration may hold. */
    public const REGEX_MAX_LENGTH = 512;

    /**
     * @var int pcre.backtrack_limit (PCRE2's match limit) while a regex runs, a tenth of PHP's default: a
     *          plain expression on a value of a few hundred characters stays far below it, a
     *          catastrophic one stops within milliseconds.
     */
    public const REGEX_BACKTRACK_LIMIT = 100000;

    /**
     * @var string Value a regex is tried on when it is configured ({@see self::regex_survives_probe()}):
     *             the shape that makes nested quantifiers such as (a+)+$ backtrack exponentially.
     */
    private const REGEX_PROBE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa!';

    /** @var array Mask of every preset that has a fixed one. */
    private const PRESET_MASKS = [
        self::PRESET_CPF => '999.999.999-99',
        self::PRESET_CNPJ => '**.***.***/****-99',
        self::PRESET_CEP => '99999-999',
        self::PRESET_PHONE_BR => '(99) 9999-9999|(99) 99999-9999',
    ];

    /** @var string The preset, one of the PRESET_* constants. */
    private string $preset;

    /** @var array The alternative masks in force, empty when there is none. */
    private array $masks;

    /** @var string Regular expression body, '' when there is none. */
    private string $regex;

    /** @var string Admin-set message for a value the regex rejects, '' for the generic one. */
    private string $regexerror;

    /** @var string One of the STORE_* constants. */
    private string $storeformat;

    /** @var int Maximum length of the stored value, 0 for no limit. */
    private int $maxlength;

    /** @var \context|null Context the admin-set error message is formatted in. */
    private ?\context $context;

    /**
     * Constructor
     *
     * Unknown preset or store format values read as the defaults (no mask, masked storage), so
     * a configuration written by a later version degrades instead of failing.
     *
     * @param array $configdata the field's configdata (preset, mask, regex, regexerror,
     *      storeformat, maxlength); missing keys read as empty
     * @param \context|null $context context to format the admin-set error message in;
     *      system when null
     */
    public function __construct(array $configdata, ?\context $context = null) {
        $preset = (string) ($configdata['preset'] ?? self::PRESET_NONE);
        $this->preset = in_array($preset, self::presets(), true) ? $preset : self::PRESET_NONE;

        if ($this->preset === self::PRESET_CUSTOM) {
            $custom = (string) ($configdata['mask'] ?? '');
            $this->masks = mask::is_valid($custom) ? mask::split($custom) : [];
        } else if (isset(self::PRESET_MASKS[$this->preset])) {
            $this->masks = mask::split(self::PRESET_MASKS[$this->preset]);
        } else {
            $this->masks = [];
        }

        $this->regex = trim((string) ($configdata['regex'] ?? ''));
        $this->regexerror = trim((string) ($configdata['regexerror'] ?? ''));
        $this->storeformat = ($configdata['storeformat'] ?? '') === self::STORE_RAW ? self::STORE_RAW : self::STORE_MASKED;
        $this->maxlength = max(0, (int) ($configdata['maxlength'] ?? 0));
        $this->context = $context;
    }

    /**
     * Every preset, in the order the configuration form lists them
     *
     * @return array list of PRESET_* values
     */
    public static function presets(): array {
        return [
            self::PRESET_NONE,
            self::PRESET_CPF,
            self::PRESET_CNPJ,
            self::PRESET_CEP,
            self::PRESET_PHONE_BR,
            self::PRESET_CUSTOM,
        ];
    }

    /**
     * The alternative masks in force
     *
     * @return array list of masks; empty when the preset is none, or custom with an unusable mask
     */
    public function get_masks(): array {
        return $this->masks;
    }

    /**
     * The storage format in force
     *
     * @return string one of the STORE_* constants
     */
    public function get_storeformat(): string {
        return $this->storeformat;
    }

    /**
     * The value as it is stored: trimmed, fitted into the mask, masked or raw
     *
     * A value no mask alternative fits comes back trimmed and otherwise untouched: this method
     * is total, and {@see self::validate()} is what rejects such a value.
     *
     * @param string $raw the value as typed
     * @return string '' for empty input
     */
    public function normalise(string $raw): string {
        $value = trim($raw);
        if ($value === '' || !$this->masks) {
            return $value;
        }
        $fitted = mask::apply($value, $this->masks);
        if ($fitted === null) {
            return $value;
        }
        return $this->storeformat === self::STORE_RAW ? $fitted['raw'] : $fitted['masked'];
    }

    /**
     * Validate a typed value: mask, check digits, length, then regex
     *
     * Empty input is valid here; whether the field is required, and whether the value is
     * unique, are the caller's checks.
     *
     * @param string $raw the value as typed
     * @return string|null null when valid, else the error message, safe to output as HTML like
     *      the result of get_string()
     */
    public function validate(string $raw): ?string {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $normalised = $value;
        if ($this->masks) {
            $fitted = mask::apply($value, $this->masks);
            if ($fitted === null) {
                return get_string('errormask', 'customfield_pattern', s(implode(' | ', $this->masks)));
            }
            if ($this->preset === self::PRESET_CPF && !checkdigit::cpf($fitted['raw'])) {
                return get_string('errorcpf', 'customfield_pattern');
            }
            if ($this->preset === self::PRESET_CNPJ && !checkdigit::cnpj($fitted['raw'])) {
                return get_string('errorcnpj', 'customfield_pattern');
            }
            $normalised = $this->storeformat === self::STORE_RAW ? $fitted['raw'] : $fitted['masked'];
        }

        if ($this->maxlength > 0 && \core_text::strlen($normalised) > $this->maxlength) {
            return get_string('errormaxlength', 'customfield_pattern', $this->maxlength);
        }

        $matches = $this->regex === '' ? true : self::regex_matches($this->regex, $normalised);
        if ($matches === null) {
            // Not a "no": the expression aborted (match limit, invalid UTF-8) or no longer compiles.
            return get_string('errorregexunchecked', 'customfield_pattern');
        }
        if ($matches === false) {
            if ($this->regexerror === '') {
                return get_string('errorregex', 'customfield_pattern');
            }
            return format_string($this->regexerror, true, ['context' => $this->context ?? \context_system::instance()]);
        }

        return null;
    }

    /**
     * A stored value as it is shown: a raw value gets its mask back, anything else is unchanged
     *
     * @param string $stored the value as stored
     * @return string plain text, not escaped
     */
    public function format_for_display(string $stored): string {
        if ($stored === '' || $this->storeformat !== self::STORE_RAW || !$this->masks) {
            return $stored;
        }
        $fitted = mask::apply($stored, $this->masks);
        // A value stored under an earlier mask may not fit the current one: show it as stored.
        return $fitted === null ? $stored : $fitted['masked'];
    }

    /**
     * Whether a regular expression body compiles
     *
     * @param string $body the expression without delimiters or anchors
     * @return bool
     */
    public static function regex_compiles(string $body): bool {
        return self::regex_matches($body, '') !== null;
    }

    /**
     * Whether a regular expression body runs to an answer on a probe value within the match limit
     *
     * Only a smoke test: it catches the classic catastrophic shapes (nested quantifiers such as
     * `(a+)+$`) on a string of letters followed by a symbol, not every expression that can blow up
     * on some other input. Whatever it misses is still bounded when a value is validated
     * ({@see self::regex_matches()}), where it ends as "could not be checked".
     *
     * @param string $body the expression without delimiters or anchors, already known to compile
     * @return bool
     */
    public static function regex_survives_probe(string $body): bool {
        return self::regex_matches($body, self::REGEX_PROBE) !== null;
    }

    /**
     * Match a whole value against a regular expression body
     *
     * The body is anchored as `\A(?:body)\z` with the `u` flag, and the match must also cover
     * the whole value: a body such as `a)|(b` closes the group early and would otherwise
     * match any value that starts with `a`. The delimiter is chr(1), a character a text
     * setting cannot carry, so a `/` or `#` in the body needs no escaping. The compile warning
     * of a bad body is swallowed and reported as null; so is a runtime failure (match limit,
     * invalid UTF-8), which callers report as "could not be checked", never as no match.
     *
     * The expression is admin-set and runs on user input, so the run is bounded: the match limit
     * is lowered to {@see self::REGEX_BACKTRACK_LIMIT} and the JIT is off for this one call (the
     * JIT has its own stack limit and makes the bound depend on the build), both restored after.
     *
     * @param string $body the expression without delimiters or anchors
     * @param string $value the whole value to match
     * @return bool|null whether it matches; null when the body does not compile or run
     */
    private static function regex_matches(string $body, string $value): ?bool {
        $delimiter = chr(1);
        $expression = $delimiter . '\\A(?:' . $body . ')\\z' . $delimiter . 'u';
        $matches = [];
        $backtracklimit = ini_get('pcre.backtrack_limit');
        $jit = ini_get('pcre.jit');
        set_error_handler(static function (): bool {
            return true;
        });
        try {
            ini_set('pcre.backtrack_limit', (string) self::REGEX_BACKTRACK_LIMIT);
            ini_set('pcre.jit', '0');
            $result = preg_match($expression, $value, $matches);
        } finally {
            ini_set('pcre.backtrack_limit', (string) $backtracklimit);
            ini_set('pcre.jit', (string) $jit);
            restore_error_handler();
        }
        if ($result === false) {
            return null;
        }
        return $result === 1 && $matches[0] === $value;
    }
}
