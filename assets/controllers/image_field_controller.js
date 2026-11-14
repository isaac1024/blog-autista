import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { Lookups, lookupText } from '../shared/lookup.js';
import { imageOption } from '../shared/options.js';
import { Search } from '../shared/search.js';

/**
 * @typedef {object} Image
 * @property {string} id
 * @property {string} alt
 * @property {string} thumbnail
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
 * @property {string} removed
 * @property {string} none
 * @property {string} result
 * @property {string} results
 * @property {string} error
 */

/**
 * @typedef {object} Properties
 * @property {AbortController} abortController
 * @property {Lookups<Image>} images
 * @property {Search<Image>} searches
 * @property {HTMLInputElement} inputTarget
 * @property {HTMLElement} thumbnailTarget
 * @property {HTMLElement} descriptionTarget
 * @property {HTMLElement} stateTarget
 * @property {HTMLButtonElement} chooseTarget
 * @property {HTMLButtonElement} removeTarget
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
 * Edits one optional image of the library: shows the one chosen, as the CMS
 * knows it now, picks another searched by alternative text, or removes it,
 * leaving the field without any. Where the image stands for the site is said
 * in words.
 */
export default class extends BaseController {
    static targets = ['input', 'thumbnail', 'description', 'state', 'choose', 'remove', 'dialog', 'search', 'searchStatus', 'results', 'status'];

    static values = {
        searchUrl: String,
        lookupUrl: String,
        labels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        const owner = this.abortController;
        this.images = /** @type {Lookups<Image>} */ (new Lookups(new URL(this.lookupUrlValue, window.location.href), 'images', owner.signal, () => this.draw()));
        this.searches = new Search({
            url: new URL(this.searchUrlValue, window.location.href),
            param: 'alt',
            key: 'images',
            owner: owner.signal,
            view: () => ({ input: this.searchTarget, results: this.resultsTarget, status: this.searchStatusTarget, more: null }),
            labels: () => this.labelsValue,
            row: (image) => imageOption(image, () => this.choose(image)),
        });
        this.chooseTarget.hidden = false;
        this.draw();

        if (this.inputTarget.value !== '') {
            this.images.ask([this.inputTarget.value]);
        }
    }

    disconnect() {
        this.abortController.abort();
    }

    /** What the field holds: no image, one being looked up, missing, unknown, or the image itself. */
    draw() {
        const empty = this.inputTarget.value === '';
        const image = this.images.of(this.inputTarget.value);
        const { name, standing } = empty
            ? { name: this.labelsValue.empty, standing: '' }
            : lookupText(image, ({ alt }) => alt, this.labelsValue);

        this.removeTarget.hidden = empty;
        this.descriptionTarget.textContent = name;
        this.stateTarget.textContent = standing;
        this.thumbnailTarget.replaceChildren();
        this.thumbnailTarget.hidden = true;

        // No image is known by an empty id.
        if (image.state === 'found') {
            // Decorative: the text next to it says which image it is.
            const thumbnail = document.createElement('img');
            thumbnail.className = 'block aspect-video h-auto w-28 object-cover';
            thumbnail.src = image.value.thumbnail;
            thumbnail.alt = '';
            thumbnail.width = 112;
            thumbnail.height = 63;
            this.thumbnailTarget.replaceChildren(thumbnail);
            this.thumbnailTarget.hidden = false;
        }
    }

    /** @param {Image} image */
    choose(image) {
        this.inputTarget.value = image.id;
        this.images.know(image);
        this.dialogTarget.close();
        this.draw();
        this.statusTarget.textContent = this.labelsValue.chosen.replace('%alt%', image.alt);
        this.chooseTarget.focus();
        notifyForm(this.inputTarget);
    }

    remove() {
        this.inputTarget.value = '';
        this.draw();
        this.statusTarget.textContent = this.labelsValue.removed;
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
}
