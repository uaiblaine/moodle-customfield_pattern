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
 * Field controller of the pattern custom field: configuration form and value API
 *
 * {@see self::normalise_value()}, {@see self::validate_value()} and
 * {@see self::format_for_display()} are the public API for consumers that keep values outside
 * customfield_data (enrol_apply's application questions): they read only the field's
 * configuration and their argument.
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_controller extends \core_customfield\field_controller {
    /** @var string Plugin type. */
    public const TYPE = 'pattern';

    /** @var int Largest value the charvalue column takes, as customfield_text allows. */
    private const MAX_LENGTH = 1333;

    /**
     * Add the settings of a pattern field to the configuration form
     *
     * The handler may call this method's siblings after it and remove elements, so nothing
     * here or in {@see self::config_form_validation()} assumes an element is still present.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        $mform->addElement('header', 'header_specificsettings', get_string('specificsettings', 'customfield_pattern'));
        $mform->setExpanded('header_specificsettings', true);

        $mform->addElement('text', 'configdata[defaultvalue]', get_string('defaultvalue', 'core_customfield'), ['size' => 50]);
        $mform->setType('configdata[defaultvalue]', PARAM_TEXT);

        $mform->addElement('text', 'configdata[displaysize]', get_string('displaysize', 'customfield_pattern'), ['size' => 6]);
        $mform->setType('configdata[displaysize]', PARAM_INT);
        if (!$this->get_configdata_property('displaysize')) {
            $mform->setDefault('configdata[displaysize]', 50);
        }
        $mform->addRule('configdata[displaysize]', null, 'numeric', null, 'client');

        $mform->addElement('text', 'configdata[maxlength]', get_string('maxlength', 'customfield_pattern'), ['size' => 6]);
        $mform->setType('configdata[maxlength]', PARAM_INT);
        if (!$this->get_configdata_property('maxlength')) {
            $mform->setDefault('configdata[maxlength]', self::MAX_LENGTH);
        }
        $mform->addRule('configdata[maxlength]', null, 'numeric', null, 'client');

        $presets = [
            pattern::PRESET_NONE => get_string('presetnone', 'customfield_pattern'),
            pattern::PRESET_CPF => get_string('presetcpf', 'customfield_pattern'),
            pattern::PRESET_CNPJ => get_string('presetcnpj', 'customfield_pattern'),
            pattern::PRESET_CEP => get_string('presetcep', 'customfield_pattern'),
            pattern::PRESET_PHONE_BR => get_string('presetphonebr', 'customfield_pattern'),
            pattern::PRESET_CUSTOM => get_string('presetcustom', 'customfield_pattern'),
        ];
        $mform->addElement('select', 'configdata[preset]', get_string('preset', 'customfield_pattern'), $presets);
        $mform->addHelpButton('configdata[preset]', 'preset', 'customfield_pattern');
        // Only for a new field: a default named 'configdata[preset]' outranks the nested configdata
        // set_data() loads, so an unconditional one showed every saved field as "None".
        if ($this->get_configdata_property('preset') === null) {
            $mform->setDefault('configdata[preset]', pattern::PRESET_NONE);
        }

        $mform->addElement('text', 'configdata[mask]', get_string('mask', 'customfield_pattern'), ['size' => 50]);
        $mform->setType('configdata[mask]', PARAM_TEXT);
        $mform->addHelpButton('configdata[mask]', 'mask', 'customfield_pattern');
        $mform->hideIf('configdata[mask]', 'configdata[preset]', 'neq', pattern::PRESET_CUSTOM);

        // Raw, not PARAM_TEXT: tag stripping would mangle a body such as [^<>]+.
        $mform->addElement('text', 'configdata[regex]', get_string('regex', 'customfield_pattern'), ['size' => 50]);
        $mform->setType('configdata[regex]', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('configdata[regex]', 'regex', 'customfield_pattern');

        $mform->addElement('text', 'configdata[regexerror]', get_string('regexerror', 'customfield_pattern'), ['size' => 50]);
        $mform->setType('configdata[regexerror]', PARAM_TEXT);
        $mform->addHelpButton('configdata[regexerror]', 'regexerror', 'customfield_pattern');
        $mform->hideIf('configdata[regexerror]', 'configdata[regex]', 'eq', '');

        $storeformats = [
            pattern::STORE_MASKED => get_string('storeformatmasked', 'customfield_pattern'),
            pattern::STORE_RAW => get_string('storeformatraw', 'customfield_pattern'),
        ];
        $mform->addElement('select', 'configdata[storeformat]', get_string('storeformat', 'customfield_pattern'), $storeformats);
        $mform->addHelpButton('configdata[storeformat]', 'storeformat', 'customfield_pattern');
        if ($this->get_configdata_property('storeformat') === null) {
            $mform->setDefault('configdata[storeformat]', pattern::STORE_MASKED);
        }
        $mform->hideIf('configdata[storeformat]', 'configdata[preset]', 'eq', pattern::PRESET_NONE);
    }

    /**
     * Validate the data on the field configuration form
     *
     * @param array $data from the add/edit field form
     * @param array $files
     * @return array associative array of error messages
     */
    public function config_form_validation(array $data, $files = []): array {
        $errors = parent::config_form_validation($data, $files);
        $config = $data['configdata'] ?? [];

        $maxlength = (int) ($config['maxlength'] ?? 0);
        if ($maxlength < 1 || $maxlength > self::MAX_LENGTH) {
            $errors['configdata[maxlength]'] = get_string('errorconfigmaxlen', 'customfield_pattern');
        }

        $displaysize = (int) ($config['displaysize'] ?? 0);
        if ($displaysize < 1 || $displaysize > 200) {
            $errors['configdata[displaysize]'] = get_string('errorconfigdisplaysize', 'customfield_pattern');
        }

        if (($config['preset'] ?? '') === pattern::PRESET_CUSTOM) {
            $custom = trim((string) ($config['mask'] ?? ''));
            if ($custom === '') {
                $errors['configdata[mask]'] = get_string('errorconfigmaskrequired', 'customfield_pattern');
            } else if (!mask::is_valid($custom)) {
                $errors['configdata[mask]'] = get_string('errorconfigmask', 'customfield_pattern');
            }
        }

        $regex = trim((string) ($config['regex'] ?? ''));
        if ($regex !== '' && !pattern::regex_compiles($regex)) {
            $errors['configdata[regex]'] = get_string('errorconfigregex', 'customfield_pattern');
        }

        $pattern = new pattern($config, $this->get_configuration_context());
        $masks = $pattern->get_masks();
        if ($masks && !isset($errors['configdata[maxlength]'])) {
            if ($pattern->get_storeformat() === pattern::STORE_RAW) {
                $needed = mask::max_token_count($masks);
            } else {
                $needed = mask::max_length($masks);
            }
            if ($maxlength < $needed) {
                $errors['configdata[maxlength]'] = get_string('errorconfigmaxlenmask', 'customfield_pattern', $needed);
            }
        }

        // The default value obeys the same rules as typed input, once the rules themselves are valid.
        $default = (string) ($config['defaultvalue'] ?? '');
        if (!$errors && $default !== '') {
            $error = $pattern->validate($default);
            if ($error !== null) {
                $errors['configdata[defaultvalue]'] = $error;
            }
        }

        return $errors;
    }

    /**
     * Pre-process a value from an external source (for example the upload course tool)
     *
     * @param string $value
     * @return string the value as it would be stored
     */
    public function parse_value(string $value) {
        return $this->normalise_value($value);
    }

    /**
     * The value as it is stored: trimmed, fitted into the mask, masked or raw per the settings
     *
     * Uppercases the characters typed into `*` tokens (all CNPJ letters). A value no mask
     * alternative fits comes back trimmed and otherwise untouched; {@see self::validate_value()}
     * rejects it.
     *
     * @param string $raw the value as typed
     * @return string '' for empty input
     */
    public function normalise_value(string $raw): string {
        return $this->get_pattern()->normalise($raw);
    }

    /**
     * Validate a typed value: mask, CPF/CNPJ check digits, maximum length, then regex
     *
     * Empty input is valid: whether the field is required, and uniqueness, are the caller's
     * checks.
     *
     * @param string $raw the value as typed
     * @return string|null null when valid, else the localised error message, safe to output as
     *      HTML like the result of get_string()
     */
    public function validate_value(string $raw): ?string {
        return $this->get_pattern()->validate($raw);
    }

    /**
     * A stored value as it is shown: a raw-stored value gets its mask back
     *
     * @param string $stored the value as stored
     * @return string plain text, not escaped: callers escape it
     */
    public function format_for_display(string $stored): string {
        return $this->get_pattern()->format_for_display($stored);
    }

    /**
     * The alternative masks in force
     *
     * @return array list of masks; empty when the field has no mask
     */
    public function get_masks(): array {
        return $this->get_pattern()->get_masks();
    }

    /**
     * Does this custom field type support being used as part of the block_myoverview
     * custom field grouping?
     *
     * @return bool
     */
    public function supports_course_grouping(): bool {
        return true;
    }

    /**
     * Format the stored values used for block_myoverview grouping
     *
     * @param array $values the stored values that need formatting
     * @return array formatted values keyed by stored value
     */
    public function course_grouping_format_values($values): array {
        $ret = [];
        foreach ($values as $value) {
            $ret[$value] = format_string($this->format_for_display((string) $value));
        }
        $ret[BLOCK_MYOVERVIEW_CUSTOMFIELD_EMPTY] = get_string('nocustomvalue', 'block_myoverview', $this->get_formatted_name());
        return $ret;
    }

    /**
     * The value rules of this field's saved configuration
     *
     * @return pattern
     */
    private function get_pattern(): pattern {
        return new pattern((array) $this->get('configdata'), $this->get_configuration_context());
    }

    /**
     * Context to format the admin-set regex error message in
     *
     * A controller built with `new` instead of {@see \core_customfield\field_controller::create()}
     * has no category, hence no handler; it falls back to the system context.
     *
     * @return \context
     */
    private function get_configuration_context(): \context {
        if ($this->category === null) {
            return \context_system::instance();
        }
        return $this->get_handler()->get_configuration_context();
    }
}
