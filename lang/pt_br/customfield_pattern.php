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
 * Strings for component 'customfield_pattern', language 'pt_br'
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['displaysize'] = 'Tamanho do campo no formulário';
$string['errorcnpj'] = 'Este não é um CNPJ válido.';
$string['errorconfigdisplaysize'] = 'O tamanho do campo no formulário deve estar entre 1 e 200 caracteres.';
$string['errorconfigmask'] = 'Cada alternativa da máscara, separada por |, deve conter pelo menos um dos símbolos 9, A ou *.';
$string['errorconfigmaskrequired'] = 'Uma máscara personalizada é obrigatória quando o formato é Máscara personalizada.';
$string['errorconfigmaxlen'] = 'O número máximo de caracteres permitidos deve estar entre 1 e 1333.';
$string['errorconfigmaxlenmask'] = 'O número máximo de caracteres deve ser pelo menos {$a}, o tamanho do maior valor que a máscara armazena.';
$string['errorconfigpreset'] = 'Este formato não é conhecido.';
$string['errorconfigregex'] = 'Esta expressão regular não é válida.';
$string['errorconfigstoreformat'] = 'Este formato de armazenamento não é conhecido.';
$string['errorcpf'] = 'Este não é um CPF válido.';
$string['errormask'] = 'O valor não corresponde ao formato esperado: {$a}.';
$string['errormaxlength'] = 'O número máximo de caracteres permitidos neste campo é {$a}.';
$string['errorregex'] = 'O valor não está no formato esperado.';
$string['mask'] = 'Máscara personalizada';
$string['mask_help'] = 'O formato do valor. Na máscara:

* 9 representa um dígito;
* A representa uma letra;
* \\* representa uma letra ou um dígito (letras são convertidas para maiúsculas);
* qualquer outro caractere é copiado para o valor como está.

Separe máscaras alternativas com |, por exemplo (99) 9999-9999|(99) 99999-9999: é usada a alternativa que corresponde ao número de caracteres digitados.';
$string['maxlength'] = 'Número máximo de caracteres';
$string['pluginname'] = 'Padrão';
$string['preset'] = 'Formato';
$string['preset_help'] = 'Um formato pronto, ou Máscara personalizada para definir o seu. CPF e CNPJ também conferem os dígitos verificadores; CNPJ aceita os números alfanuméricos emitidos desde julho de 2026.';
$string['presetcep'] = 'CEP';
$string['presetcnpj'] = 'CNPJ';
$string['presetcpf'] = 'CPF';
$string['presetcustom'] = 'Máscara personalizada';
$string['presetnone'] = 'Nenhum';
$string['presetphonebr'] = 'Telefone (Brasil)';
$string['privacy:metadata'] = 'O plugin de campo do tipo Padrão não armazena dados pessoais; ele usa tabelas definidas no núcleo.';
$string['regex'] = 'Expressão regular';
$string['regex_help'] = 'Opcional. Uma expressão regular à qual o valor inteiro deve corresponder, escrita sem delimitadores: ela é ancorada nas duas pontas automaticamente e conferida contra o valor como é armazenado (depois de aplicada a máscara). Por exemplo, [0-9]{5}-[0-9]{3}.';
$string['regexerror'] = 'Mensagem de erro da expressão regular';
$string['regexerror_help'] = 'A mensagem exibida quando o valor não corresponde à expressão regular. Se vazia, é exibida uma mensagem genérica.';
$string['specificsettings'] = 'Configurações do campo Padrão';
$string['storeformat'] = 'Armazenar como';
$string['storeformat_help'] = 'Se o valor é armazenado com os caracteres que a máscara acrescenta (por exemplo 529.982.247-25) ou só com os caracteres digitados (52998224725). Ele é sempre exibido com a máscara.';
$string['storeformatmasked'] = 'Formatado, com os caracteres da máscara';
$string['storeformatraw'] = 'Só os caracteres digitados';
