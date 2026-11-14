import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { Lookups, lookupText } from '../shared/lookup.js';
import { pageOption } from '../shared/options.js';
import { Search } from '../shared/search.js';

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
 * @property {Lookups<Page>} pages
 * @property {Search<Page>} searches
 * @property {HTMLInputElement} inputTarget
 * @property {HTMLElement} titleTarget
 * @property {HTMLElement} stateTarget
 * @property {HTMLButtonElement} chooseTarget
 * @property {HTMLDialogElement} dialogTarget
 * @property {HTMLInputElement} searchTarget
 * @property {HTMLElement} searchStatusTarget
 * @property {HTMLUListElement} resultsTarget
 * @property {HTMLButtonElement} moreTarget
 * @property {HTMLElement} statusTarget
 * @property {string} searchUrlValue
 * @property {string} lookupUrlValue
 * @property {Labels} labelsValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * Edits one page: shows the one chosen, as the CMS knows it now, and picks
 * another searched by title, a page of results at a time. Where the page stands for the site is said in
 * words.
 */
export default class extends BaseController {
    static targets = ['input', 'title', 'state', 'choose', 'dialog', 'search', 'searchStatus', 'results', 'more', 'status'];

    static values = {
        searchUrl: String,
        lookupUrl: String,
        labels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        const owner = this.abortController;
        this.pages = /** @type {Lookups<Page>} */ (new Lookups(new URL(this.lookupUrlValue, window.location.href), 'pages', owner.signal, () => this.draw()));
        this.searches = new Search({
            url: new URL(this.searchUrlValue, window.location.href),
            param: 'title',
            key: 'pages',
            owner: owner.signal,
            view: () => ({ input: this.searchTarget, results: this.resultsTarget, status: this.searchStatusTarget, more: this.moreTarget }),
            labels: () => this.labelsValue,
            row: (page) => pageOption(page, () => this.choose(page), this.labelsValue.offSite),
        });
        this.chooseTarget.hidden = false;
        this.draw();

        if (this.inputTarget.value !== '') {
            this.pages.ask([this.inputTarget.value]);
        }
    }

    disconnect() {
        this.abortController.abort();
    }

    /** What the field holds: no page, one being looked up, missing, unknown, or the page itself. */
    draw() {
        const { name, standing } = this.inputTarget.value === ''
            ? { name: this.labelsValue.empty, standing: '' }
            : lookupText(this.pages.of(this.inputTarget.value), ({ title }) => title, this.labelsValue);

        this.titleTarget.textContent = name;
        this.stateTarget.textContent = standing;
    }

    /** @param {Page} page */
    choose(page) {
        this.inputTarget.value = page.id;
        this.pages.know(page);
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
    search(event) {
        event?.preventDefault();
        this.searches.first();
    }

    more() {
        this.searches.next();
    }
}
