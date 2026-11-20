import { Controller } from '@hotwired/stimulus';
import { snapshot } from '../form/snapshot.js';

/**
 * @typedef {object} Properties
 * @property {HTMLElement} statusTarget
 * @property {boolean} hasStatusTarget
 * @property {string} unsavedValue
 * @property {boolean} keptValue
 * @property {HTMLFormElement | null} form
 * @property {string} saved
 * @property {string} told
 * @property {boolean} sending
 * @property {ReturnType<typeof setInterval>} keeping
 */

/*
 * How often a form holding changes reminds the server of its session:
 * well within the 8 hours an idle session lasts.
 */
const KEEP_ALIVE = 5 * 60 * 1000;

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * The bar of the actions of a form that edits something, which knows
 * whether the form holds changes not saved yet. While it does, its status
 * says so, where the bar said what is saved, and leaving the page asks
 * first, unless the form itself is being sent. A form that came back with
 * what was sent and refused holds changes from the start. Meanwhile, the
 * page asks the server for itself now and then, so the session does not
 * expire under an editor still writing, which would lose what they send.
 */
export default class extends BaseController {
    static targets = ['status'];

    static values = { unsaved: String, kept: Boolean };

    connect() {
        this.form = this.element.closest('form');
        this.saved = snapshot(this.form);
        this.sending = false;

        if (this.hasStatusTarget) {
            this.told = String(this.statusTarget.textContent);
        }

        this.check();
        this.keeping = setInterval(() => { this.keepAlive(); }, KEEP_ALIVE);
    }

    disconnect() {
        clearInterval(this.keeping);
    }

    keepAlive() {
        if (!this.sending && this.changed()) {
            fetch(window.location.href, { method: 'HEAD', cache: 'no-store' }).catch(() => {});
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

    /*
     * A form that holds what was sent and refused differs from what is
     * saved, whatever it holds.
     */
    changed() {
        return this.keptValue || snapshot(this.form) !== this.saved;
    }
}
