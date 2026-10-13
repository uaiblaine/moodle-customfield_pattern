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
 * A text input that asks for the mask module when it is rendered.
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_pattern\form;

use MoodleQuickForm_text;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/text.php');

/**
 * A text input that asks for the mask module when it is rendered.
 *
 * The module has to be required at RENDER time, not while the form is defined. A dynamic form
 * shown in a modal is defined first and rendered afterwards, inside
 * $PAGE->start_collecting_javascript_requirements() (core_form\external\dynamic_form::execute()),
 * which swaps in a fresh requirements manager: a js_call_amd() made during definition() goes to
 * the discarded one and the mask never starts. Rendering is the one moment both transports share.
 *
 * @package    customfield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class masked_text_element extends MoodleQuickForm_text {
    /** @var string The element type this class is registered under. */
    public const TYPE = 'customfield_pattern_text';

    /**
     * Register the element type with formslib, once per request.
     *
     * @return void
     */
    public static function register(): void {
        \MoodleQuickForm::registerElementType(self::TYPE, __FILE__, self::class);
    }

    /**
     * Export for the form templates, asking for the mask module first.
     *
     * @param renderer_base $output Renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output) {
        self::require_mask_js();
        return parent::export_for_template($output);
    }

    /**
     * Ask the current requirements manager for the mask module, once per manager.
     *
     * @return void
     */
    protected static function require_mask_js(): void {
        global $PAGE;
        if ($PAGE->requires->should_create_one_time_item_now('customfield_pattern_mask')) {
            $PAGE->requires->js_call_amd('customfield_pattern/mask', 'init');
        }
    }
}
