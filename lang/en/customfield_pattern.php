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

/**
 * Strings for component 'customfield_pattern', language 'en'
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['displaysize'] = 'Form input size';
$string['errorcnpj'] = 'This is not a valid CNPJ.';
$string['errorconfigdisplaysize'] = 'The form input size must be between 1 and 200 characters.';
$string['errorconfigmask'] = 'Every alternative of the mask, separated by |, must contain at least one of the tokens 9, A or *.';
$string['errorconfigmaskrequired'] = 'A custom mask is required when the format is Custom mask.';
$string['errorconfigmaxlen'] = 'The maximum number of characters allowed must be between 1 and 1333.';
$string['errorconfigmaxlenmask'] = 'The maximum number of characters must be at least {$a}, the length of the longest value the mask stores.';
$string['errorconfigpreset'] = 'This format is not known.';
$string['errorconfigregex'] = 'This regular expression is not valid.';
$string['errorconfigstoreformat'] = 'This storage format is not known.';
$string['errorcpf'] = 'This is not a valid CPF.';
$string['errormask'] = 'The value does not match the expected format: {$a}.';
$string['errormaxlength'] = 'The maximum number of characters allowed in this field is {$a}.';
$string['errorregex'] = 'The value is not in the expected format.';
$string['mask'] = 'Custom mask';
$string['mask_help'] = 'The shape of the value. In the mask:

* 9 stands for a digit;
* A stands for a letter;
* \\* stands for a letter or a digit (letters are converted to upper case);
* any other character is copied into the value as it is.

Separate alternative masks with |, for example (99) 9999-9999|(99) 99999-9999: the alternative that fits the number of characters typed is used.';
$string['maxlength'] = 'Maximum number of characters';
$string['pluginname'] = 'Pattern';
$string['preset'] = 'Format';
$string['preset_help'] = 'A ready-made format, or Custom mask to define your own. CPF and CNPJ also check the verification digits; CNPJ accepts the alphanumeric numbers issued since July 2026.';
$string['presetcep'] = 'CEP';
$string['presetcnpj'] = 'CNPJ';
$string['presetcpf'] = 'CPF';
$string['presetcustom'] = 'Custom mask';
$string['presetnone'] = 'None';
$string['presetphonebr'] = 'Phone (Brazil)';
$string['privacy:metadata'] = 'The Pattern field type plugin doesn\'t store any personal data; it uses tables defined in core.';
$string['regex'] = 'Regular expression';
$string['regex_help'] = 'Optional. A regular expression the whole value must match, written without delimiters: it is anchored at both ends automatically and checked against the value as it is stored (after the mask is applied). For example, [0-9]{5}-[0-9]{3}.';
$string['regexerror'] = 'Regular expression error message';
$string['regexerror_help'] = 'The message shown when the value does not match the regular expression. When empty, a generic message is shown.';
$string['specificsettings'] = 'Pattern field settings';
$string['storeformat'] = 'Store as';
$string['storeformat_help'] = 'Whether the value is stored with the characters the mask adds (for example 529.982.247-25) or with only the characters typed (52998224725). It is always shown with the mask.';
$string['storeformatmasked'] = 'Formatted, with the mask characters';
$string['storeformatraw'] = 'Typed characters only';
