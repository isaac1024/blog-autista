import { Controller } from '@hotwired/stimulus';
import { searchResult } from '../form/search_preview.js';

/** @typedef {{title: string, description: string, slug?: string}} Sources */

/**
 * @typedef {object} Properties
 * @property {() => void} refresh
 * @property {HTMLElement} urlTarget
 * @property {HTMLElement} titleTarget
 * @property {HTMLElement} descriptionTarget
 * @property {Sources} sourcesValue
 * @property {string} baseValue
 * @property {string} emptyValue
 * @property {number} titleLimitValue
 * @property {number} descriptionLimitValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * Keeps the search result preview in step with the fields that feed it,
 * which may live anywhere on the page, such as a document title.
 */
export default class extends BaseController {
    static targets = ['url', 'title', 'description'];

    static values = {
        sources: Object,
        base: String,
        empty: String,
        titleLimit: Number,
        descriptionLimit: Number,
    };

    connect() {
        this.refresh = () => this.update();
        this.fields().forEach((field) => field.addEventListener('input', this.refresh));
    }

    disconnect() {
        this.fields().forEach((field) => field.removeEventListener('input', this.refresh));
    }

    update() {
        const { title, description, slug } = this.sourcesValue;
        /** @param {string} id */
        const value = (id) => {
            const field = document.getElementById(id);

            return field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement ? field.value : '';
        };

        const result = searchResult(
            { title: value(title), description: value(description), slug: slug ? value(slug) : null },
            { base: this.baseValue, titleLimit: this.titleLimitValue, descriptionLimit: this.descriptionLimitValue, empty: this.emptyValue },
        );

        this.urlTarget.textContent = result.url;
        this.titleTarget.textContent = result.title;
        this.descriptionTarget.textContent = result.description;
    }

    fields() {
        // The sources come as JSON, where a source left out has no key at all.
        return Object.values(/** @type {Record<string, string>} */ (this.sourcesValue))
            .map((id) => document.getElementById(id))
            .filter((field) => field !== null);
    }
}
