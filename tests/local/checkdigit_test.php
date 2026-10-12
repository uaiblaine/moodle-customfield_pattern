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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the CPF and CNPJ check digit algorithms
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(checkdigit::class)]
final class checkdigit_test extends \basic_testcase {
    /**
     * CPF digits and whether they are valid
     *
     * @return array
     */
    public static function cpf_provider(): array {
        return [
            'valid' => ['52998224725', true],
            'valid, second example' => ['12345678909', true],
            'repeated digits pass the arithmetic but are never issued' => ['11111111111', false],
            'wrong second check digit' => ['52998224724', false],
            'wrong first check digit' => ['52998224735', false],
            'too short' => ['5299822472', false],
            'with punctuation' => ['529.982.247-25', false],
        ];
    }

    /**
     * CPF check digits
     *
     * @param string $digits
     * @param bool $expected
     * @return void
     */
    #[DataProvider('cpf_provider')]
    public function test_cpf(string $digits, bool $expected): void {
        $this->assertSame($expected, checkdigit::cpf($digits));
    }

    /**
     * CNPJ characters and whether they are valid
     *
     * @return array
     */
    public static function cnpj_provider(): array {
        return [
            'numeric' => ['11222333000181', true],
            'alphanumeric, the Receita Federal example' => ['12ABC34501DE35', true],
            'alphanumeric, lowercase input' => ['12abc34501de35', true],
            'alphanumeric, computed with the algorithm' => ['1A2B3C4D000179', true],
            'numeric, wrong second check digit' => ['11222333000182', false],
            'alphanumeric, wrong second check digit' => ['12ABC34501DE36', false],
            'alphanumeric, wrong first check digit' => ['12ABC34501DE45', false],
            'letter in a check digit position' => ['12ABC34501DEA5', false],
            'repeated characters pass the arithmetic but are never issued' => ['00000000000000', false],
            'too short' => ['1122233300018', false],
            'with punctuation' => ['11.222.333/0001-81', false],
        ];
    }

    /**
     * CNPJ check digits, numeric and alphanumeric
     *
     * @param string $value
     * @param bool $expected
     * @return void
     */
    #[DataProvider('cnpj_provider')]
    public function test_cnpj(string $value, bool $expected): void {
        $this->assertSame($expected, checkdigit::cnpj($value));
    }
}
