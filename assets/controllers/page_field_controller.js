import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { countText } from '../shared/count_text.js';
import { fetchJson } from '../shared/http.js';
import { LatestRequest } from '../shared/latest_request.js';
import { LOADING, found, lookupIn, lookupText } from '../shared/lookup.js';

/**
 * @typedef {object} Page
 * @property {string} id
 * @property {string} title
 * @property {string} status
 * @property {boolean} published
 */

/**
 * @typedef {object} Labels
 * @property {string} empty
 * @property {string} loading
 * @property {string} missing
 * @property {string} unknown
 * @property {string} offSite
 * @property {string} chosen
 * @property {string} none
 * @property {string} result
 * @property {string} results
 * @property {string} error
 */

/**
 * @typedef {object} Properties
 * @property {AbortController} abortController
 * @property {import('../shared/lookup.js').Lookup<Page>} page
 * @property {LatestRequest} searches
 * @property {HTMLInputElement} inputTarget
 * @property {HTMLElement} titleTarget
 * @property {HTMLElement} stateTarget
 * @property {HTMLButtonElement} chooseTarget
 * @property {HTMLDialogElement} dialogTarget
 * @property {HTMLInputElement} searchTarget
 * @property {HTMLElement} searchStatusTarget
 * @property {HTMLUListElement} resultsTarget
 * @property {HTMLElement} statusTarget
 * @property {string} searchUrlValue
 * @property {string} lookupUrlValue
 * @property {Labels} labelsValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * Edits one page: shows the one chosen, as the CMS knows it now, and picks
 * another searched by title. Where the page stands for the site is said in
 * words.
 */
export default class extends BaseController {
    static targets = ['input', 'title', 'state', 'choose', 'dialog', 'search', 'searchStatus', 'results', 'status'];

    static values = {
        searchUrl: String,
        lookupUrl: String,
        labels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        this.searches = new LatestRequest(this.abortController.signal);
        this.chooseTarget.hidden = false;
        this.page = LOADING;
        this.draw();

        if (this.inputTarget.value !== '') {
            this.lookUp(this.inputTarget.value);
        }
    }

    disconnect() {
        this.abortController.abort();
    }

    /** @param {string} id */
    async lookUp(id) {
        const { signal } = this.abortController;
        const url = new URL(this.lookupUrlValue, window.location.href);
        url.searchParams.set('ids', id);

        /** @type {Page[]|null} */
        let pages = null;

        try {
            pages = (/** @type {{pages: Page[]}} */ (await fetchJson(url, signal))).pages;
        } catch {
            // Told as unknown: the page may well exist.
        }

        // The field was disconnected meanwhile: this answer is for no one.
        if (signal.aborted) {
            return;
        }

        this.page = lookupIn(pages, id);
        this.draw();
    }

    /** What the field holds: no page, one being looked up, missing, unknown, or the page itself. */
    draw() {
        const { name, standing } = this.inputTarget.value === ''
            ? { name: this.labelsValue.empty, standing: '' }
            : lookupText(this.page, (page) => page.title, this.labelsValue);

        this.titleTarget.textContent = name;
        this.stateTarget.textContent = standing;
    }

    /** @param {Page} page */
    choose(page) {
        this.inputTarget.value = page.id;
        this.page = found(page);
        this.dialogTarget.close();
        this.draw();
        this.statusTarget.textContent = this.labelsValue.chosen.replace('%title%', page.title);
        this.chooseTarget.focus();
        notifyForm(this.inputTarget);
    }

    openDialog() {
        this.dialogTarget.showModal();
        this.searchTarget.focus();
        this.search();
    }

    /** @param {Event} [event] */
    async search(event) {
        event?.preventDefault();

        const signal = this.searches.next();
        const url = new URL(this.searchUrlValue, window.location.href);
        url.searchParams.set('title', this.searchTarget.value);
        /** @type {Page[]|null} */
        let pages = null;

        try {
            pages = (/** @type {{pages: Page[]}} */ (await fetchJson(url, signal))).pages;
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out, or the field was disconnected meanwhile.
        if (signal.aborted) {
            return;
        }

        if (pages === null) {
            this.searchStatusTarget.textContent = this.labelsValue.error;

            return;
        }

        this.resultsTarget.replaceChildren(...pages.map((page) => this.result(page)));
        this.searchStatusTarget.textContent = countText(pages.length, this.labelsValue);
    }

    /**
     * One page to pick: the whole row is its button, named by its title and status.
     *
     * @param {Page} page
     */
    result(page) {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-text-page-option';
        button.addEventListener('click', () => this.choose(page));

        const text = document.createElement('span');
        text.className = 'rich-text-page-option-text';
        const title = document.createElement('span');
        title.className = 'font-bold';
        title.textContent = page.title;
        const status = document.createElement('span');
        status.className = 'rich-text-page-status';
        status.textContent = page.status;
        text.append(title, status);

        button.append(text);
        item.append(button);

        return item;
    }

}
