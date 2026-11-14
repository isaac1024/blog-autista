import { Controller } from '@hotwired/stimulus';
import { flatten } from '../form/single_line.js';

const BaseController = /** @type {import('./controller_types.js').ControllerWith<object, HTMLTextAreaElement>} */ (Controller);

/*
 * A text area that wraps a long single line, such as a title, so all of it
 * shows: Enter adds no line break, pasted breaks become spaces, and the field
 * grows with its text where the browser can't size it to its content itself.
 */
export default class extends BaseController {
    connect() {
        this.resize();
    }

    /** @param {KeyboardEvent} event */
    preventBreak(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    }

    flatten() {
        const { value, selectionStart } = this.element;
        const flat = flatten(value);

        if (flat !== value) {
            this.element.value = flat;
            this.element.setSelectionRange(selectionStart, selectionStart);
        }

        this.resize();
    }

    resize() {
        if (CSS.supports('field-sizing', 'content')) {
            return;
        }

        // Measured at the height its one row gives it, so it shrinks too.
        this.element.style.removeProperty('height');
        this.element.style.height = `${this.element.scrollHeight}px`;
    }
}
