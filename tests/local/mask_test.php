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
 * Tests for the mask parser and applier
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(mask::class)]
final class mask_test extends \basic_testcase {
    /**
     * Values and the masks they are fitted into
     *
     * @return array
     */
    public static function apply_provider(): array {
        $cpf = ['999.999.999-99'];
        $cnpj = ['**.***.***/****-99'];
        $phone = ['(99) 9999-9999', '(99) 99999-9999'];
        return [
            'CPF typed with the literals' => ['529.982.247-25', $cpf, '529.982.247-25', '52998224725'],
            'CPF typed without the literals' => ['52998224725', $cpf, '529.982.247-25', '52998224725'],
            'CPF with spaces around and inside' => [' 529 982 247 25 ', $cpf, '529.982.247-25', '52998224725'],
            'CPF with a character the mask does not have' => ['529982247/25', $cpf, null, null],
            'CPF one digit short' => ['5299822472', $cpf, null, null],
            'CPF one digit long' => ['529982247255', $cpf, null, null],
            'CPF with a letter' => ['5299822472A', $cpf, null, null],
            'CNPJ letters are uppercased' => ['12abc34501de35', $cnpj, '12.ABC.345/01DE-35', '12ABC34501DE35'],
            'CNPJ typed with the literals' => ['12.ABC.345/01DE-35', $cnpj, '12.ABC.345/01DE-35', '12ABC34501DE35'],
            'CEP' => ['30130100', ['99999-999'], '30130-100', '30130100'],
            'landline picks the short alternative' => ['3132345678', $phone, '(31) 3234-5678', '3132345678'],
            'mobile picks the long alternative' => ['31987654321', $phone, '(31) 98765-4321', '31987654321'],
            'mobile typed with the literals' => ['(31) 98765-4321', $phone, '(31) 98765-4321', '31987654321'],
            'phone fitting no alternative' => ['319876543', $phone, null, null],
            'alphanumeric literal added' => ['12345', ['BR-99999'], 'BR-12345', '12345'],
            'alphanumeric literal typed' => ['br12345', ['BR-99999'], 'BR-12345', '12345'],
            'letter token keeps the case' => ['ab12', ['AA-99'], 'ab-12', 'ab12'],
            'digit in a letter token' => ['1b12', ['AA-99'], null, null],
            'non-ASCII letter' => ['éb12', ['AA-99'], null, null],
        ];
    }

    /**
     * Fitting a value into masks gives the masked and raw shapes, or null
     *
     * @param string $value
     * @param array $masks
     * @param string|null $masked
     * @param string|null $raw
     * @return void
     */
    #[DataProvider('apply_provider')]
    public function test_apply(string $value, array $masks, ?string $masked, ?string $raw): void {
        $expected = $masked === null ? null : ['masked' => $masked, 'raw' => $raw];
        $this->assertSame($expected, mask::apply($value, $masks));
    }

    /**
     * Mask settings and whether they are usable
     *
     * @return array
     */
    public static function is_valid_provider(): array {
        return [
            'two alternatives' => ['(99) 9999-9999|(99) 99999-9999', true],
            'letters and wildcards' => ['AA-**', true],
            'empty' => ['', false],
            'blank' => ['   ', false],
            'trailing separator' => ['99|', false],
            'no token' => ['abc', false],
            'alternative without a token' => ['999|--', false],
        ];
    }

    /**
     * A mask is usable only when every alternative has a token
     *
     * @param string $mask
     * @param bool $expected
     * @return void
     */
    #[DataProvider('is_valid_provider')]
    public function test_is_valid(string $mask, bool $expected): void {
        $this->assertSame($expected, mask::is_valid($mask));
    }

    /**
     * Splitting trims each alternative and drops empty ones
     *
     * @return void
     */
    public function test_split(): void {
        $this->assertSame(['999', 'AA'], mask::split(' 999 | AA '));
        $this->assertSame(['999'], mask::split('999||'));
        $this->assertSame([], mask::split(''));
    }

    /**
     * Lengths, token counts and the numeric keypad decision
     *
     * @return void
     */
    public function test_measures(): void {
        $phone = ['(99) 9999-9999', '(99) 99999-9999'];
        $this->assertSame(11, mask::token_count('(99) 99999-9999'));
        $this->assertSame(15, mask::max_length($phone));
        $this->assertSame(11, mask::max_token_count($phone));
        $this->assertTrue(mask::is_numeric($phone));
        $this->assertFalse(mask::is_numeric(['99', 'AA']));
        $this->assertFalse(mask::is_numeric(['**']));
        $this->assertFalse(mask::is_numeric([]));
    }
}
