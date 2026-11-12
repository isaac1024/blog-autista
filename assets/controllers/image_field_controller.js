import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { countText } from '../shared/count_text.js';
import { fetchJson } from '../shared/http.js';
import { LatestRequest } from '../shared/latest_request.js';
import { LOADING, found, lookupIn, lookupText } from '../shared/lookup.js';

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
 * @property {import('../shared/lookup.js').Lookup<Image>} image
 * @property {LatestRequest} searches
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
        this.searches = new LatestRequest(this.abortController.signal);
        this.chooseTarget.hidden = false;
        this.image = LOADING;
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

        /** @type {Image[]|null} */
        let images = null;

        try {
            images = (/** @type {{images: Image[]}} */ (await fetchJson(url, signal))).images;
        } catch {
            // Told as unknown: the image may well exist.
        }

        // The field was disconnected meanwhile: this answer is for no one.
        if (signal.aborted) {
            return;
        }

        this.image = lookupIn(images, id);
        this.draw();
    }

    /** What the field holds: no image, one being looked up, missing, unknown, or the image itself. */
    draw() {
        const empty = this.inputTarget.value === '';
        const { name, standing } = empty
            ? { name: this.labelsValue.empty, standing: '' }
            : lookupText(this.image, (image) => image.alt, this.labelsValue);

        this.removeTarget.hidden = empty;
        this.descriptionTarget.textContent = name;
        this.stateTarget.textContent = standing;
        this.thumbnailTarget.replaceChildren();
        this.thumbnailTarget.hidden = true;

        if (!empty && this.image.state === 'found') {
            // Decorative: the text next to it says which image it is.
            const thumbnail = document.createElement('img');
            thumbnail.className = 'block aspect-video h-auto w-28 object-cover';
            thumbnail.src = this.image.value.thumbnail;
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
        this.image = found(image);
        this.dialogTarget.close();
        this.draw();
        this.statusTarget.textContent = this.labelsValue.chosen.replace('%alt%', image.alt);
        this.chooseTarget.focus();
        notifyForm(this.inputTarget);
    }

    remove() {
        this.inputTarget.value = '';
        this.image = LOADING;
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
    async search(event) {
        event?.preventDefault();

        const signal = this.searches.next();
        const url = new URL(this.searchUrlValue, window.location.href);
        url.searchParams.set('alt', this.searchTarget.value);
        /** @type {Image[]|null} */
        let images = null;

        try {
            images = (/** @type {{images: Image[]}} */ (await fetchJson(url, signal))).images;
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out, or the field was disconnected meanwhile.
        if (signal.aborted) {
            return;
        }

        if (images === null) {
            this.searchStatusTarget.textContent = this.labelsValue.error;

            return;
        }

        this.resultsTarget.replaceChildren(...images.map((image) => this.result(image)));
        this.searchStatusTarget.textContent = countText(images.length, this.labelsValue);
    }

    /**
     * One image to pick: the whole row is its button, named by its text and status.
     *
     * @param {Image} image
     */
    result(image) {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-text-image-option';
        button.addEventListener('click', () => this.choose(image));

        const thumbnail = document.createElement('img');
        thumbnail.src = image.thumbnail;
        thumbnail.alt = '';
        thumbnail.width = 112;
        thumbnail.height = 63;
        thumbnail.loading = 'lazy';

        const text = document.createElement('span');
        text.className = 'rich-text-image-option-text';
        const alt = document.createElement('span');
        alt.className = 'font-bold';
        alt.textContent = image.alt;
        const status = document.createElement('span');
        status.className = 'rich-text-image-status';
        status.textContent = image.status;
        text.append(alt, status);

        button.append(thumbnail, text);
        item.append(button);

        return item;
    }

}
