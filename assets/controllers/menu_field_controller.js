import { Controller } from '@hotwired/stimulus';
import { notifyForm } from '../shared/forms.js';
import { Lookups, lookupText } from '../shared/lookup.js';
import { pageOption } from '../shared/options.js';
import { Search } from '../shared/search.js';

/** @typedef {import('./resource_field_controller.js').Resource} Page */

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
 * @property {Lookups<Page>} pages
 * @property {Search<Page>} searches
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
 * The id of the page an entry leads to: every entry carries one, empty in
 * the template.
 *
 * @param {HTMLLIElement} row
 */
const pageIdOf = (row) => /** @type {string} */ (row.dataset.pageId);

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
    static targets = ['list', 'row', 'template', 'empty', 'add', 'full', 'dialog', 'search', 'searchStatus', 'results', 'more', 'status'];

    static values = {
        name: String,
        searchUrl: String,
        lookupUrl: String,
        max: Number,
        labels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        const owner = this.abortController.signal;
        this.pages = /** @type {Lookups<Page>} */ (new Lookups(new URL(this.lookupUrlValue, window.location.href), 'pages', owner, () => this.refresh()));
        this.searches = new Search({
            url: new URL(this.searchUrlValue, window.location.href),
            param: 'title',
            key: 'pages',
            owner,
            view: () => ({ input: this.searchTarget, results: this.resultsTarget, status: this.searchStatusTarget, more: this.moreTarget }),
            labels: () => this.labelsValue,
            row: (page) => pageOption(page, () => this.add(page), this.labelsValue.offSite),
        });
        this.addTarget.hidden = false;
        this.refresh();
        this.pages.ask(this.rowTargets.map(pageIdOf));
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
        const title = this.titleOf(pageIdOf(row));
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

        this.pages.know(page);
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
        this.announce(message.replace('%title%', this.titleOf(pageIdOf(row))));
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
        const lookup = this.pages.of(pageIdOf(row));
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
        const lookup = this.pages.of(id);

        return lookup.state === 'found' ? lookup.value.title : '';
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
        this.searches.first();
    }

    more() {
        this.searches.next();
    }

    /* Announcements */

    /** @param {string} message */
    announce(message) {
        this.statusTarget.textContent = message;
    }

}
