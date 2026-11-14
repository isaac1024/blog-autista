import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { termOption } from '../shared/options.js';
import { Search } from '../shared/search.js';

/**
 * A term as its search lists it, its name as the title of what is picked.
 *
 * @typedef {object} Term
 * @property {string} id
 * @property {string} title
 * @property {string} status
 * @property {boolean} published
 */

/**
 * @typedef {object} Labels
 * @property {string} offSite
 * @property {string} added
 * @property {string} removed
 * @property {string} repeated
 * @property {string} none
 * @property {string} result
 * @property {string} results
 * @property {string} error
 */

/**
 * @typedef {object} Properties
 * @property {AbortController} abortController
 * @property {Search<Term>} searches
 * @property {HTMLUListElement} listTarget
 * @property {HTMLLIElement[]} rowTargets
 * @property {HTMLTemplateElement} templateTarget
 * @property {HTMLElement} emptyTarget
 * @property {HTMLButtonElement} addTarget
 * @property {HTMLDialogElement} dialogTarget
 * @property {HTMLInputElement} searchTarget
 * @property {HTMLElement} searchStatusTarget
 * @property {HTMLUListElement} resultsTarget
 * @property {HTMLButtonElement} moreTarget
 * @property {HTMLElement} statusTarget
 * @property {string} searchUrlValue
 * @property {Labels} labelsValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties, HTMLElement>} */ (Controller);

/**
 * @template {Element} T
 * @param {ParentNode} root
 * @param {string} selector
 * @param {new () => T} type
 * @returns {T}
 */
const field = (root, selector, type) => {
    const element = root.querySelector(selector);

    if (!(element instanceof type)) {
        throw new TypeError(`Missing ${selector}`);
    }

    return element;
};

/**
 * @param {Event} event
 * @returns {HTMLLIElement|null}
 */
const eventRow = (event) => event.target instanceof Element ? event.target.closest('li') : null;

/*
 * Edits a list of terms, such as the tags of a post: adds terms searched by
 * name, each once, and takes them out with Remove. Each term is a form field
 * of its own, so the form posts them as they are listed.
 */
export default class extends BaseController {
    static targets = ['list', 'row', 'template', 'empty', 'add', 'dialog', 'search', 'searchStatus', 'results', 'more', 'status'];

    static values = {
        searchUrl: String,
        labels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        this.searches = new Search({
            url: new URL(this.searchUrlValue, window.location.href),
            param: 'name',
            key: 'terms',
            owner: this.abortController.signal,
            view: () => ({ input: this.searchTarget, results: this.resultsTarget, status: this.searchStatusTarget, more: this.moreTarget }),
            labels: () => this.labelsValue,
            row: (term) => termOption(term, () => this.add(term), this.labelsValue.offSite),
        });
        this.addTarget.hidden = false;
    }

    disconnect() {
        this.abortController.abort();
    }

    /** @param {Event} event */
    remove(event) {
        const row = eventRow(event);

        if (row === null) {
            return;
        }

        const next = row.nextElementSibling ?? row.previousElementSibling;
        const name = field(row, '[data-field="name"]', HTMLElement).textContent;
        row.remove();
        this.emptyTarget.hidden = this.rowTargets.length > 0;
        this.announce(this.labelsValue.removed.replace('%name%', name));
        // The focus stays in the list: on the next term, or else on the button that adds one.
        (next ? field(next, '[data-field="remove"]', HTMLButtonElement) : this.addTarget).focus();
        notifyForm(this.element);
    }

    /** @param {Term} term */
    add(term) {
        if (this.rowTargets.some((row) => row.dataset.termId === term.id)) {
            this.announce(this.labelsValue.repeated.replace('%name%', term.title));

            return;
        }

        const template = this.templateTarget.content.firstElementChild;

        if (!(template instanceof HTMLLIElement)) {
            throw new TypeError('Missing term row template');
        }

        const row = /** @type {HTMLLIElement} */ (template.cloneNode(true));
        const described = `${this.element.id}-${term.id}`;
        row.dataset.termId = term.id;
        field(row, '[data-field="id"]', HTMLInputElement).value = term.id;
        field(row, '[data-field="term"]', HTMLElement).id = described;
        field(row, '[data-field="name"]', HTMLElement).textContent = term.title;
        field(row, '[data-field="status"]', HTMLElement).textContent = term.published ? term.status : `${term.status} · ${this.labelsValue.offSite}`;
        const remove = field(row, '[data-field="remove"]', HTMLButtonElement);
        remove.setAttribute('aria-describedby', described);
        this.listTarget.append(row);
        this.emptyTarget.hidden = true;
        this.dialogTarget.close();
        this.announce(this.labelsValue.added.replace('%name%', term.title));
        remove.focus();
        notifyForm(this.element);
    }

    openDialog() {
        this.dialogTarget.showModal();
        this.searchTarget.focus();
        this.search();
    }

    /** @param {Event} [event] */
    search(event) {
        event?.preventDefault();
        this.searches.first();
    }

    more() {
        this.searches.next();
    }

    /** @param {string} message */
    announce(message) {
        this.statusTarget.textContent = message;
    }
}
