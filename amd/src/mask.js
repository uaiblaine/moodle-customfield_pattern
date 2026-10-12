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
 * Formats a pattern custom field while the user types.
 *
 * Inputs opt in with a data-customfield-pattern-mask attribute holding the masks, separated by |.
 * The listener sits on the document, so inputs a modal dynamic form renders later are covered too.
 * This is a typing aid only: it drops what does not fit, while the server-side
 * customfield_pattern\local\mask::apply() is strict and decides what is stored. Keep the token
 * classes of the two in step.
 *
 * @module     customfield_pattern/mask
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTOR = '[data-customfield-pattern-mask]';
const SEPARATOR = '|';

/** @type {boolean} Whether the document listener is in place. */
let initialised = false;

/**
 * Whether a mask character is a token.
 *
 * @param {string} char One mask character.
 * @returns {boolean}
 */
const isToken = (char) => char === '9' || char === 'A' || char === '*';

/**
 * Whether a typed character fits a token.
 *
 * @param {string} token One of 9, A and *.
 * @param {string} char One typed character.
 * @returns {boolean}
 */
const fits = (token, char) => {
    if (token === '9') {
        return /^[0-9]$/.test(char);
    }
    if (token === 'A') {
        return /^[A-Za-z]$/.test(char);
    }
    return /^[0-9A-Za-z]$/.test(char);
};

/**
 * Whether a character can fill a token at all (an ASCII letter or digit).
 *
 * @param {string} char One character.
 * @returns {boolean}
 */
const isTyped = (char) => /^[0-9A-Za-z]$/.test(char);

/**
 * Number of tokens in a mask.
 *
 * @param {string} mask One alternative mask.
 * @returns {number}
 */
const tokenCount = (mask) => Array.from(mask).filter(isToken).length;

/**
 * Pick the alternative for the number of characters typed: the shortest one they fit in.
 *
 * @param {string[]} masks The alternatives.
 * @param {number} count Number of letters and digits typed.
 * @returns {string}
 */
const chooseMask = (masks, count) => {
    const sorted = masks.slice().sort((a, b) => tokenCount(a) - tokenCount(b));
    const fitting = sorted.find((mask) => tokenCount(mask) >= count);
    return fitting === undefined ? sorted[sorted.length - 1] : fitting;
};

/**
 * Format typed characters into a mask, leniently.
 *
 * Characters that do not fit their token are dropped, literals are written only while more
 * typed characters follow, and whatever exceeds the mask is cut.
 *
 * @param {string[]} chars The typed letters and digits, in order.
 * @param {string} mask One alternative mask.
 * @returns {string}
 */
const format = (chars, mask) => {
    let output = '';
    let position = 0;
    for (const maskChar of Array.from(mask)) {
        if (position >= chars.length) {
            break;
        }
        if (isToken(maskChar)) {
            while (position < chars.length && !fits(maskChar, chars[position])) {
                position++;
            }
            if (position >= chars.length) {
                break;
            }
            output += maskChar === '*' ? chars[position].toUpperCase() : chars[position];
            position++;
        } else {
            output += maskChar;
            if (chars[position].toUpperCase() === maskChar.toUpperCase()) {
                // An alphanumeric literal typed along with the data.
                position++;
            }
        }
    }
    return output;
};

/**
 * Caret position just after the given number of letters and digits.
 *
 * @param {string} value The formatted value.
 * @param {number} count Letters and digits that precede the caret.
 * @returns {number}
 */
const caretAfter = (value, count) => {
    if (count === 0) {
        return 0;
    }
    let seen = 0;
    for (let index = 0; index < value.length; index++) {
        if (isTyped(value[index])) {
            seen++;
            if (seen === count) {
                return index + 1;
            }
        }
    }
    return value.length;
};

/**
 * Reformat one input after the user typed, deleted or pasted into it.
 *
 * @param {HTMLInputElement} input The input.
 */
const applyMask = (input) => {
    const masks = input.dataset.customfieldPatternMask
        .split(SEPARATOR)
        .map((mask) => mask.trim())
        .filter((mask) => mask !== '');
    if (masks.length === 0) {
        return;
    }
    const oldValue = input.value;
    const caret = input.selectionStart === null ? oldValue.length : input.selectionStart;
    const caretAtEnd = caret >= oldValue.length;
    const typedBeforeCaret = Array.from(oldValue.slice(0, caret)).filter(isTyped).length;

    const chars = Array.from(oldValue).filter(isTyped);
    const newValue = format(chars, chooseMask(masks, chars.length));
    if (newValue === oldValue) {
        return;
    }
    input.value = newValue;

    if (document.activeElement === input) {
        const position = caretAtEnd ? newValue.length : caretAfter(newValue, typedBeforeCaret);
        input.setSelectionRange(position, position);
    }
};

/**
 * Handle an input event anywhere in the document.
 *
 * @param {InputEvent} event The event.
 */
const handleInput = (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches(SELECTOR) || event.isComposing) {
        return;
    }
    applyMask(input);
};

/**
 * Start formatting every masked input, present and future. Safe to call more than once.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('input', handleInput);
};
