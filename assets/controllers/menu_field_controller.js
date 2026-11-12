import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { countText } from '../shared/count_text.js';
import { fetchJson } from '../shared/http.js';
import { LatestRequest } from '../shared/latest_request.js';
import { lookupAmong, lookupText } from '../shared/lookup.js';

/** @typedef {import('./page_field_controller.js').Page} Page */

/**
 * @typedef {object} Labels
 * @property {string} loading
 * @property {string} missing
 * @property {string} unknown
 * @property {string} offSite
 * @property {string} added
 * @property {string} removed
 * @property {string} movedUp
 * @property {string} movedDown
 * @property {string} full
 * @property {string} repeated
 * @property {string} none
 * @property {string} result
 * @property {string} results
 * @property {string} error
 */

/**
 * @typedef {object} Properties
 * @property {AbortController} abortController
 * @property {Map<string, Page>} pages
 * @property {Set<string>} missing
 * @property {Set<string>} unknown
 * @property {{page: number, count: number}} query
 * @property {LatestRequest} searches
 * @property {HTMLOListElement} listTarget
 * @property {HTMLLIElement[]} rowTargets
 * @property {HTMLTemplateElement} templateTarget
 * @property {HTMLElement} emptyTarget
 * @property {HTMLButtonElement} addTarget
 * @property {HTMLElement} fullTarget
 * @property {HTMLDialogElement} dialogTarget
 * @property {HTMLInputElement} searchTarget
 * @property {HTMLElement} searchStatusTarget
 * @property {HTMLUListElement} resultsTarget
 * @property {HTMLButtonElement} moreTarget
 * @property {HTMLElement} statusTarget
 * @property {string} nameValue
 * @property {string} searchUrlValue
 * @property {string} lookupUrlValue
 * @property {number} maxValue
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
 * Edits a menu of pages: adds pages searched by title, moves entries with
 * Up and Down, takes them out with Remove, and names each by the page it
 * leads to and where that page stands for the site, in words. The entries
 * are form fields of their own, renumbered after every change, so the form
 * posts them in order.
 */
export default class extends BaseController {
    static targets = ['list', 'row', 'template', 'empty', 'add', 'full', 'dialog', 'search', 'searchStatus', 'results', 'more', 'status', 'pageTitle', 'pageStatus'];

    static values = {
        name: String,
        searchUrl: String,
        lookupUrl: String,
        max: Number,
        labels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        this.pages = new Map();
        this.missing = new Set();
        this.unknown = new Set();
        this.query = { page: 1, count: 0 };
        this.searches = new LatestRequest(this.abortController.signal);
        this.addTarget.hidden = false;
        this.refresh();
        this.lookUp(this.rowTargets.map((row) => row.dataset.pageId ?? ''));
    }

    disconnect() {
        this.abortController.abort();
    }

    /* Entries */

    /** @param {Event} event */
    moveUp(event) {
        const row = eventRow(event);

        if (row?.previousElementSibling) {
            row.previousElementSibling.before(row);
            this.changed(row, 'up', this.labelsValue.movedUp);
        }
    }

    /** @param {Event} event */
    moveDown(event) {
        const row = eventRow(event);

        if (row?.nextElementSibling) {
            row.nextElementSibling.after(row);
            this.changed(row, 'down', this.labelsValue.movedDown);
        }
    }

    /** @param {Event} event */
    remove(event) {
        const row = eventRow(event);

        if (row === null) {
            return;
        }

        const next = row.nextElementSibling ?? row.previousElementSibling;
        const title = this.titleOf(row.dataset.pageId ?? '');
        row.remove();
        this.refresh();
        this.announce(this.labelsValue.removed.replace('%title%', title));
        // The focus stays in the menu: on the next entry, or else on the button that adds one.
        (next ? field(next, '[data-field="remove"]', HTMLButtonElement) : this.addTarget).focus();
        notifyForm(this.element);
    }

    /** @param {Page} page */
    add(page) {
        if (this.rowTargets.some((row) => row.dataset.pageId === page.id)) {
            this.announce(this.labelsValue.repeated);

            return;
        }

        if (this.rowTargets.length >= this.maxValue) {
            return;
        }

        this.pages.set(page.id, page);
        const template = this.templateTarget.content.firstElementChild;

        if (!(template instanceof HTMLLIElement)) {
            throw new TypeError('Missing menu row template');
        }

        const row = /** @type {HTMLLIElement} */ (template.cloneNode(true));
        row.dataset.pageId = page.id;
        field(row, '[data-field="page_id"]', HTMLInputElement).value = page.id;
        this.listTarget.append(row);
        this.dialogTarget.close();
        this.refresh();
        this.announce(this.labelsValue.added.replace('%title%', page.title));
        field(row, '[data-field="label"]', HTMLInputElement).focus();
        notifyForm(this.element);
    }

    /**
     * Where an entry moved, told, with the focus kept on the button pressed while it still can move.
     *
     * @param {HTMLLIElement} row
     * @param {'up'|'down'} direction
     * @param {string} message
     */
    changed(row, direction, message) {
        this.refresh();
        this.announce(message.replace('%title%', this.titleOf(row.dataset.pageId ?? '')));
        const button = field(row, `[data-field="${direction}"]`, HTMLButtonElement);
        const other = field(row, `[data-field="${direction === 'up' ? 'down' : 'up'}"]`, HTMLButtonElement);
        (button.getAttribute('aria-disabled') === 'true' ? other : button).focus();
        notifyForm(this.element);
    }

    /** Renumbers the fields of every entry, and draws what the menu holds. */
    refresh() {
        const rows = this.rowTargets;
        const id = this.element.id;

        rows.forEach((row, index) => {
            const prefix = `${this.nameValue}[${index}]`;
            field(row, '[data-field="page_id"]', HTMLInputElement).name = `${prefix}[page_id]`;
            const label = field(row, '[data-field="label"]', HTMLInputElement);
            label.name = `${prefix}[label]`;
            label.id = `${id}-${index}-label`;
            field(row, '[data-field="label-for"]', HTMLLabelElement).htmlFor = label.id;
            const page = field(row, '[data-field="page"]', HTMLElement);
            page.id = `${id}-${index}-page`;
            label.setAttribute('aria-describedby', `${id}-label-hint ${page.id}`);

            for (const name of ['up', 'down', 'remove']) {
                field(row, `[data-field="${name}"]`, HTMLButtonElement).setAttribute('aria-describedby', page.id);
            }

            // At either end the button stays focusable, and says it can't move further.
            field(row, '[data-field="up"]', HTMLButtonElement).setAttribute('aria-disabled', String(index === 0));
            field(row, '[data-field="down"]', HTMLButtonElement).setAttribute('aria-disabled', String(index === rows.length - 1));
            this.drawPage(row);
        });

        const full = rows.length >= this.maxValue;
        this.emptyTarget.hidden = rows.length > 0;
        this.addTarget.setAttribute('aria-disabled', String(full));
        this.fullTarget.hidden = !full;
    }

    /** @param {HTMLLIElement} row */
    drawPage(row) {
        const lookup = lookupAmong(row.dataset.pageId ?? '', { found: this.pages, missing: this.missing, unknown: this.unknown });
        const { name, standing } = lookupText(lookup, (page) => page.title, this.labelsValue);

        field(row, '[data-menu-field-target="pageTitle"]', HTMLElement).textContent = name;
        field(row, '[data-menu-field-target="pageStatus"]', HTMLElement).textContent = standing;

        // The label left empty takes the title of the page.
        if (lookup.state === 'found') {
            field(row, '[data-field="label"]', HTMLInputElement).placeholder = lookup.value.title;
        }
    }

    /** @param {string} id */
    titleOf(id) {
        return this.pages.get(id)?.title ?? '';
    }

    /* Pages: looked up by id, to name the entries. */

    /** @param {string[]} ids */
    async lookUp(ids) {
        if (ids.length === 0) {
            return;
        }

        const { signal } = this.abortController;

        const url = new URL(this.lookupUrlValue, window.location.href);
        url.searchParams.set('ids', ids.join(','));

        /** @type {Page[]|null} */
        let found = null;

        try {
            found = (/** @type {{pages: Page[]}} */ (await fetchJson(url, signal))).pages;
        } catch {
            // Told on each entry below: the pages may well exist.
        }

        // The menu was disconnected meanwhile: this answer is for no one.
        if (signal.aborted) {
            return;
        }

        if (found === null) {
            ids.forEach((id) => this.unknown.add(id));
        } else {
            found.forEach((page) => this.pages.set(page.id, page));
            ids.filter((id) => !this.pages.has(id)).forEach((id) => this.missing.add(id));
        }

        this.refresh();
    }

    /* Search */

    openDialog() {
        if (this.addTarget.getAttribute('aria-disabled') === 'true') {
            return;
        }

        this.dialogTarget.showModal();
        this.searchTarget.focus();
        this.search();
    }

    /** @param {Event} [event] */
    search(event) {
        event?.preventDefault();
        this.query.page = 1;
        this.query.count = 0;
        this.resultsTarget.replaceChildren();
        this.load();
    }

    more() {
        this.query.page += 1;
        this.load();
    }

    async load() {
        const signal = this.searches.next();
        const url = new URL(this.searchUrlValue, window.location.href);
        url.searchParams.set('title', this.searchTarget.value);
        url.searchParams.set('page', String(this.query.page));
        this.moreTarget.hidden = true;
        /** @type {{pages: Page[], more: boolean}|null} */
        let result = null;

        try {
            result = /** @type {{pages: Page[], more: boolean}} */ (await fetchJson(url, signal));
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out, or the menu was disconnected meanwhile.
        if (signal.aborted) {
            return;
        }

        if (result === null) {
            this.searchStatusTarget.textContent = this.labelsValue.error;

            return;
        }

        const first = this.resultsTarget.children.length;
        this.resultsTarget.append(...result.pages.map((page) => this.result(page)));
        this.query.count += result.pages.length;
        this.moreTarget.hidden = !result.more;

        this.searchStatusTarget.textContent = countText(this.query.count, this.labelsValue);

        if (this.query.page > 1) {
            this.resultsTarget.children[first]?.querySelector('button')?.focus();
        }
    }

    /**
     * One page to add: the whole row is its button, named by its title and status.
     *
     * @param {Page} page
     */
    result(page) {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-text-page-option';
        button.addEventListener('click', () => this.add(page));

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

    /* Announcements */

    /** @param {string} message */
    announce(message) {
        this.statusTarget.textContent = message;
    }

}
