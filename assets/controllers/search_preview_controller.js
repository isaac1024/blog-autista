import { Controller } from '@hotwired/stimulus';

/** Cuts the text where search engines would, marking the cut with an ellipsis. */
const cut = (text, limit) => ([...text].length > limit ? `${[...text].slice(0, limit - 1).join('').trimEnd()}…` : text);

/*
 * Keeps the search result preview in step with the fields that feed it,
 * which may live anywhere on the page, such as a document title.
 */
export default class extends Controller {
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
        const value = (id) => document.getElementById(id)?.value.trim() ?? '';

        const text = value(description).replace(/\s+/g, ' ');

        this.titleTarget.textContent = cut(value(title), this.titleLimitValue);
        this.descriptionTarget.textContent = text === '' ? this.emptyValue : cut(text, this.descriptionLimitValue);
        this.urlTarget.textContent = slug ? `${this.baseValue}/${value(slug)}.html` : `${this.baseValue}/`;
    }

    fields() {
        return Object.values(this.sourcesValue).map((id) => document.getElementById(id)).filter(Boolean);
    }
}
