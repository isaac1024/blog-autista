import { Controller } from '@hotwired/stimulus';

/**
 * @typedef {object} Properties
 * @property {string} saved
 * @property {HTMLButtonElement} buttonTarget
 * @property {HTMLElement} hintTarget
 * @property {string} formValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * Keeps a button from acting while a form holds changes not saved yet, such
 * as publishing a page while the editor has newer changes than the ones
 * saved, and says why next to it. The button stays focusable, so whoever
 * reaches it hears why it waits.
 */
export default class extends BaseController {
    static targets = ['button', 'hint'];

    static values = { form: String };

    connect() {
        this.saved = this.snapshot();
        this.check();
    }

    check() {
        const waiting = this.snapshot() !== this.saved;

        this.buttonTarget.setAttribute('aria-disabled', String(waiting));
        this.hintTarget.hidden = !waiting;
    }

    /** @param {Event} event */
    hold(event) {
        if (this.buttonTarget.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
        }
    }

    /*
     * The form's fields as it would submit them, those joining it from
     * outside included.
     */
    snapshot() {
        const form = document.getElementById(this.formValue);
        // Without the form there is nothing to save.
        const fields = form instanceof HTMLFormElement ? [...new FormData(form)].map(([name, value]) => [name, String(value)]) : [];

        return new URLSearchParams(fields).toString();
    }
}
