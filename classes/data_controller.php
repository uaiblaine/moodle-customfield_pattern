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

/**
 * Data controller of the pattern custom field: instance form element, validation and storage
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_controller extends \core_customfield\data_controller {
    /**
     * Return the name of the field where the information is stored
     *
     * @return string
     */
    public function datafield(): string {
        return 'charvalue';
    }

    /**
     * Add the text element, with the mask for the client-side formatter, to the instance form
     *
     * With a mask the element's maxlength is the longest masked value, whatever the storage
     * format: the user always types into the masked shape, and the configuration form already
     * keeps the setting at or above what the mask stores.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function instance_form_definition(\MoodleQuickForm $mform) {
        $field = $this->get_pattern_field();
        $elementname = $this->get_form_element_name();
        $masks = $field->get_masks();

        $attributes = ['size' => (int) $field->get_configdata_property('displaysize')];
        if ($masks) {
            $attributes['maxlength'] = mask::max_length($masks);
            $attributes['placeholder'] = $masks[0];
            $attributes['data-customfield-pattern-mask'] = implode(mask::ALTERNATIVE_SEPARATOR, $masks);
            if (mask::is_numeric($masks)) {
                $attributes['inputmode'] = 'numeric';
            }
            $this->require_mask_js();
        } else if ((int) $field->get_configdata_property('maxlength') > 0) {
            $attributes['maxlength'] = (int) $field->get_configdata_property('maxlength');
        }

        $mform->addElement('text', $elementname, $field->get_formatted_name(), $attributes);
        $mform->setType($elementname, PARAM_TEXT);
        $default = (string) $this->get_default_value();
        if ($default !== '') {
            $mform->setDefault($elementname, $field->format_for_display($default));
        }
        if ($field->get_configdata_property('required')) {
            $mform->addRule($elementname, null, 'required', null, 'client');
        }
    }

    /**
     * Put the stored value in the form in its displayed (masked) shape
     *
     * @param \stdClass $instance the instance that has custom fields
     * @return void
     */
    public function instance_form_before_set_data(\stdClass $instance) {
        $instance->{$this->get_form_element_name()} = $this->get_pattern_field()->format_for_display((string) $this->get_value());
    }

    /**
     * Validate the submitted value; uniqueness is checked on the value as it would be stored
     *
     * @param array $data
     * @param array $files
     * @return array errors keyed by element name
     */
    public function instance_form_validation(array $data, array $files): array {
        $elementname = $this->get_form_element_name();
        $value = (string) ($data[$elementname] ?? '');
        if (trim($value) === '') {
            return parent::instance_form_validation($data, $files);
        }

        $field = $this->get_pattern_field();
        $errors = [];
        $error = $field->validate_value($value);
        if ($error !== null) {
            $errors[$elementname] = $error;
        }

        // The stored values are normalised, so the uniqueness check must compare normalised values.
        $data[$elementname] = $field->normalise_value($value);
        return $errors + parent::instance_form_validation($data, $files);
    }

    /**
     * Save the submitted value normalised: trimmed, fitted into the mask, masked or raw
     *
     * @param \stdClass $datanew data coming from the form
     * @return void
     */
    public function instance_form_save(\stdClass $datanew) {
        $elementname = $this->get_form_element_name();
        if (property_exists($datanew, $elementname)) {
            // A copy: the handler passes the same object to every field of the instance.
            $datanew = clone $datanew;
            $datanew->{$elementname} = $this->get_pattern_field()->normalise_value((string) $datanew->{$elementname});
        }
        parent::instance_form_save($datanew);
    }

    /**
     * Returns the default value as it would be stored in the database
     *
     * @return string
     */
    public function get_default_value() {
        return $this->get_pattern_field()->normalise_value((string) $this->get_field()->get_configdata_property('defaultvalue'));
    }

    /**
     * Returns the value in a human-readable format, the mask restored and the text escaped
     *
     * @return string|null value or null if empty
     */
    public function export_value() {
        $value = (string) $this->get_value();
        if ($this->is_empty($value)) {
            return null;
        }
        return format_string($this->get_pattern_field()->format_for_display($value), true, ['context' => $this->get_context()]);
    }

    /**
     * The field controller, typed as this plugin's
     *
     * @return field_controller
     */
    private function get_pattern_field(): field_controller {
        // The return type turns a foreign controller into a TypeError instead of an undefined method.
        return $this->get_field();
    }

    /**
     * Load the client-side formatter once per page
     *
     * The module binds by event delegation on the document, so one call also serves elements a
     * modal dynamic form renders later.
     *
     * @return void
     */
    private function require_mask_js(): void {
        global $PAGE;
        if ($PAGE->requires->should_create_one_time_item_now('customfield_pattern_mask')) {
            $PAGE->requires->js_call_amd('customfield_pattern/mask', 'init');
        }
    }
}
