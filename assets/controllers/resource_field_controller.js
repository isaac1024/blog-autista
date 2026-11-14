import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { Lookups, lookupText } from '../shared/lookup.js';
import { pageOption } from '../shared/options.js';
import { Search } from '../shared/search.js';

/**
 * A resource of the CMS to pick, such as a page or a post, as the endpoints
 * of the field answer it.
 *
 * @typedef {object} Resource
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
 * @property {Lookups<Resource>} resources
 * @property {Search<Resource>} searches
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
 * @property {string} keyValue
 * @property {string} paramValue
 * @property {Labels} labelsValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * Edits one resource of a kind, such as a page or a post: shows the one
 * chosen, as the CMS knows it now, and picks another searched by its name,
 * a page of results at a time. Where it stands for the site is said in
 * words. The endpoints answer the resources under the key, and the search
 * takes the name under the parameter.
 */
export default class extends BaseController {
    static targets = ['input', 'title', 'state', 'choose', 'dialog', 'search', 'searchStatus', 'results', 'more', 'status'];

    static values = {
        searchUrl: String,
        lookupUrl: String,
        key: String,
        param: String,
        labels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        const owner = this.abortController;
        this.resources = /** @type {Lookups<Resource>} */ (new Lookups(new URL(this.lookupUrlValue, window.location.href), this.keyValue, owner.signal, () => this.draw()));
        this.searches = new Search({
            url: new URL(this.searchUrlValue, window.location.href),
            param: this.paramValue,
            key: this.keyValue,
            owner: owner.signal,
            view: () => ({ input: this.searchTarget, results: this.resultsTarget, status: this.searchStatusTarget, more: this.moreTarget }),
            labels: () => this.labelsValue,
            row: (resource) => pageOption(resource, () => this.choose(resource), this.labelsValue.offSite),
        });
        this.chooseTarget.hidden = false;
        this.draw();

        if (this.inputTarget.value !== '') {
            this.resources.ask([this.inputTarget.value]);
        }
    }

    disconnect() {
        this.abortController.abort();
    }

    /** What the field holds: none, one being looked up, missing, unknown, or the resource itself. */
    draw() {
        const { name, standing } = this.inputTarget.value === ''
            ? { name: this.labelsValue.empty, standing: '' }
            : lookupText(this.resources.of(this.inputTarget.value), ({ title }) => title, this.labelsValue);

        this.titleTarget.textContent = name;
        this.stateTarget.textContent = standing;
    }

    /** @param {Resource} resource */
    choose(resource) {
        this.inputTarget.value = resource.id;
        this.resources.know(resource);
        this.dialogTarget.close();
        this.draw();
        this.statusTarget.textContent = this.labelsValue.chosen.replace('%title%', resource.title);
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
