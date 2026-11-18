import { Controller } from '@hotwired/stimulus';
import { snapshot } from '../form/snapshot.js';

/**
 * @typedef {object} Properties
 * @property {HTMLElement} statusTarget
 * @property {boolean} hasStatusTarget
 * @property {string} unsavedValue
 * @property {HTMLFormElement | null} form
 * @property {string} saved
 * @property {string} told
 * @property {boolean} sending
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * The bar of the actions of a form that edits something, which knows
 * whether the form holds changes not saved yet. While it does, its status
 * says so, where the bar said what is saved, and leaving the page asks
 * first, unless the form itself is being sent.
 */
export default class extends BaseController {
    static targets = ['status'];

    static values = { unsaved: String };

    connect() {
        this.form = this.element.closest('form');
        this.saved = snapshot(this.form);
        this.sending = false;

        if (this.hasStatusTarget) {
            this.told = String(this.statusTarget.textContent);
        }
    }

    check() {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = this.changed() ? this.unsavedValue : this.told;
        }
    }

    /** @param {SubmitEvent} event */
    send(event) {
        if (event.target === this.form) {
            this.sending = true;
        }
    }

    /** @param {BeforeUnloadEvent} event */
    leave(event) {
        if (!this.sending && this.changed()) {
            event.preventDefault();
        }
    }

    changed() {
        return snapshot(this.form) !== this.saved;
    }
}
