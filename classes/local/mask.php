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
 * Input mask parsing and application, as pure functions
 *
 * A mask is a string of tokens and literals: `9` is a digit, `A` an ASCII letter, `*` an ASCII
 * letter or digit (uppercased), and any other character is a literal copied into the value.
 * `|` separates alternative masks; the first alternative the input fits wins. There is no
 * escape, so `9`, `A`, `*` and `|` cannot be literals.
 *
 * The client-side twin is amd/src/mask.js: it applies the same tokens while the user types,
 * but leniently (it drops what does not fit), while {@see self::apply()} is strict and is
 * what the server stores and validates.
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mask {
    /** @var string Separator between alternative masks. */
    public const ALTERNATIVE_SEPARATOR = '|';

    /**
     * Split a mask setting into its alternatives
     *
     * Leading and trailing whitespace of each alternative is dropped: a space typed around `|`
     * is never meant as a literal. Empty alternatives are dropped too; {@see self::is_valid()}
     * is what rejects them in the configuration form.
     *
     * @param string $mask the mask setting, alternatives separated by `|`
     * @return array list of non-empty alternative masks (strings)
     */
    public static function split(string $mask): array {
        $alternatives = [];
        foreach (explode(self::ALTERNATIVE_SEPARATOR, $mask) as $alternative) {
            $alternative = trim($alternative);
            if ($alternative !== '') {
                $alternatives[] = $alternative;
            }
        }
        return $alternatives;
    }

    /**
     * Whether a mask setting is usable: every alternative is non-empty and has a token
     *
     * @param string $mask the mask setting, alternatives separated by `|`
     * @return bool
     */
    public static function is_valid(string $mask): bool {
        if (trim($mask) === '') {
            return false;
        }
        foreach (explode(self::ALTERNATIVE_SEPARATOR, $mask) as $alternative) {
            if (self::token_count(trim($alternative)) === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether a mask character is a token (a placeholder for typed input)
     *
     * @param string $char one character of a mask
     * @return bool
     */
    public static function is_token(string $char): bool {
        return $char === '9' || $char === 'A' || $char === '*';
    }

    /**
     * Number of tokens in one alternative mask
     *
     * @param string $alternative one alternative mask
     * @return int
     */
    public static function token_count(string $alternative): int {
        $count = 0;
        foreach (self::chars($alternative) as $char) {
            if (self::is_token($char)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Length, in characters, of the longest value any alternative produces
     *
     * @param array $alternatives list of alternative masks
     * @return int
     */
    public static function max_length(array $alternatives): int {
        $max = 0;
        foreach ($alternatives as $alternative) {
            $max = max($max, \core_text::strlen($alternative));
        }
        return $max;
    }

    /**
     * Largest number of tokens of any alternative, the length of the longest raw value
     *
     * @param array $alternatives list of alternative masks
     * @return int
     */
    public static function max_token_count(array $alternatives): int {
        $max = 0;
        foreach ($alternatives as $alternative) {
            $max = max($max, self::token_count($alternative));
        }
        return $max;
    }

    /**
     * Whether every token of every alternative is a digit, so a numeric keypad suffices
     *
     * @param array $alternatives list of alternative masks
     * @return bool
     */
    public static function is_numeric(array $alternatives): bool {
        if (!$alternatives) {
            return false;
        }
        foreach ($alternatives as $alternative) {
            foreach (self::chars($alternative) as $char) {
                if ($char === 'A' || $char === '*') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Fit a typed value into the first alternative it matches
     *
     * The input may be typed with or without the literals. Whitespace and any non-alphanumeric
     * literal of any alternative are dropped before matching; an alphanumeric literal is
     * consumed only where the mask has it. Every other character must fill a token of the
     * right class, all tokens must be filled and nothing may be left over.
     *
     * @param string $value the typed value
     * @param array $alternatives list of alternative masks
     * @return array|null null when no alternative fits, else ['masked' => the value with the
     *      literals, 'raw' => the token characters only]
     */
    public static function apply(string $value, array $alternatives): ?array {
        $droppable = [];
        foreach ($alternatives as $alternative) {
            foreach (self::chars($alternative) as $char) {
                if (!self::is_token($char) && !self::is_alphanumeric($char)) {
                    $droppable[$char] = true;
                }
            }
        }

        $chars = [];
        foreach (self::chars($value) as $char) {
            if (self::is_alphanumeric($char)) {
                $chars[] = $char;
            } else if (!isset($droppable[$char]) && !preg_match('/^\s$/u', $char)) {
                return null;
            }
        }

        foreach ($alternatives as $alternative) {
            $result = self::apply_one($chars, $alternative);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Fit pre-filtered input characters into one alternative
     *
     * @param array $chars the input's ASCII letters and digits, in order
     * @param string $alternative one alternative mask
     * @return array|null see {@see self::apply()}
     */
    private static function apply_one(array $chars, string $alternative): ?array {
        $count = count($chars);
        $position = 0;
        $masked = '';
        $raw = '';
        foreach (self::chars($alternative) as $maskchar) {
            if (self::is_token($maskchar)) {
                if ($position >= $count || !self::fits($maskchar, $chars[$position])) {
                    return null;
                }
                $char = $maskchar === '*' ? strtoupper($chars[$position]) : $chars[$position];
                $masked .= $char;
                $raw .= $char;
                $position++;
            } else {
                $masked .= $maskchar;
                if ($position < $count && strcasecmp($chars[$position], $maskchar) === 0) {
                    // An alphanumeric literal the user typed along with the data.
                    $position++;
                }
            }
        }
        if ($position !== $count) {
            return null;
        }
        return ['masked' => $masked, 'raw' => $raw];
    }

    /**
     * Whether a typed character fits a token
     *
     * @param string $token one of `9`, `A`, `*`
     * @param string $char one typed character
     * @return bool
     */
    private static function fits(string $token, string $char): bool {
        switch ($token) {
            case '9':
                return (bool) preg_match('/^[0-9]$/', $char);
            case 'A':
                return (bool) preg_match('/^[A-Za-z]$/', $char);
            default:
                return self::is_alphanumeric($char);
        }
    }

    /**
     * Whether a character is an ASCII letter or digit, the only characters a token takes
     *
     * @param string $char one character
     * @return bool
     */
    private static function is_alphanumeric(string $char): bool {
        return (bool) preg_match('/^[0-9A-Za-z]$/', $char);
    }

    /**
     * Split a UTF-8 string into characters
     *
     * @param string $value
     * @return array list of one-character strings
     */
    private static function chars(string $value): array {
        if ($value === '') {
            return [];
        }
        return mb_str_split($value, 1, 'UTF-8');
    }
}
