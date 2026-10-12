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
 * Check digit algorithms of Brazilian registry numbers, as pure functions
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checkdigit {
    /** @var array Weights of the second CNPJ check digit; the first uses the last twelve. */
    private const CNPJ_WEIGHTS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Whether eleven digits form a valid CPF
     *
     * Each check digit is the mod-11 sum of the digits before it weighted from 10 (or 11) down
     * to 2, times 10, with a result of 10 read as 0. Eleven repeated digits pass that
     * arithmetic and are rejected because the Receita Federal never issues them.
     *
     * @param string $digits the eleven digits, without punctuation
     * @return bool
     */
    public static function cpf(string $digits): bool {
        if (!preg_match('/^[0-9]{11}$/', $digits) || preg_match('/^(.)\1{10}$/', $digits)) {
            return false;
        }
        for ($length = 9; $length < 11; $length++) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }
            if ((int) $digits[$length] !== ($sum * 10) % 11 % 10) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether fourteen characters form a valid CNPJ, numeric or alphanumeric
     *
     * Since July 2026 (IN RFB 2.229/2024) the first twelve positions take `0-9` and `A-Z`; the
     * last two stay numeric check digits. Each character is valued as its ASCII code minus 48,
     * so a digit keeps its value and `A` is 17, and the classic mod-11 algorithm runs
     * unchanged: weights 5..2, 9..2 (then 6..2, 9..2), remainder below 2 gives 0, else 11
     * minus the remainder. Numeric CNPJs therefore stay valid under the same code. Fourteen
     * repeated characters are rejected as never issued.
     *
     * @param string $value the fourteen characters, without punctuation; letters in any case
     * @return bool
     */
    public static function cnpj(string $value): bool {
        $value = strtoupper($value);
        if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $value) || preg_match('/^(.)\1{13}$/', $value)) {
            return false;
        }
        for ($length = 12; $length < 14; $length++) {
            $weights = array_slice(self::CNPJ_WEIGHTS, 13 - $length);
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (ord($value[$i]) - 48) * $weights[$i];
            }
            $remainder = $sum % 11;
            $expected = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int) $value[$length] !== $expected) {
                return false;
            }
        }
        return true;
    }
}
