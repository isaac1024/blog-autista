import { Controller } from '@hotwired/stimulus';

/*
 * A text area that wraps a long single line, such as a title, so all of it
 * shows: Enter adds no line break, pasted breaks become spaces, and the field
 * grows with its text where the browser can't size it to its content itself.
 */
export default class extends Controller {
    connect() {
        this.resize();
    }

    preventBreak(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    }

    flatten() {
        const { value, selectionStart } = this.element;
        const flat = value.replace(/[\r\n]+/g, ' ');

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

        this.element.style.height = 'auto';
        this.element.style.height = `${this.element.scrollHeight}px`;
    }
}
