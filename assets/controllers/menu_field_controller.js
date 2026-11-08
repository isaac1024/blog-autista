import { Controller } from '@hotwired/stimulus';

/*
 * Edits a menu of pages: adds pages searched by title, moves entries with
 * Up and Down, takes them out with Remove, and names each by the page it
 * leads to and where that page stands for the site, in words. The entries
 * are form fields of their own, renumbered after every change, so the form
 * posts them in order.
 */
export default class extends Controller {
    static targets = ['list', 'row', 'template', 'empty', 'add', 'full', 'dialog', 'search', 'searchStatus', 'results', 'more', 'status', 'pageTitle', 'pageStatus'];

    static values = {
        name: String,
        searchUrl: String,
        lookupUrl: String,
        max: Number,
        labels: Object,
    };

    connect() {
        this.pages = new Map();
        this.missing = new Set();
        this.unknown = false;
        this.query = { page: 1, count: 0, request: 0 };
        this.addTarget.hidden = false;
        this.refresh();
        this.lookUp(this.rowTargets.map((row) => row.dataset.pageId));
    }

    /* Entries */

    moveUp(event) {
        const row = event.target.closest('li');

        if (row.previousElementSibling) {
            row.previousElementSibling.before(row);
            this.changed(row, 'up', this.labelsValue.movedUp);
        }
    }

    moveDown(event) {
        const row = event.target.closest('li');

        if (row.nextElementSibling) {
            row.nextElementSibling.after(row);
            this.changed(row, 'down', this.labelsValue.movedDown);
        }
    }

    remove(event) {
        const row = event.target.closest('li');
        const next = row.nextElementSibling ?? row.previousElementSibling;
        const title = this.titleOf(row.dataset.pageId);
        row.remove();
        this.refresh();
        this.announce(this.labelsValue.removed.replace('%title%', title));
        // The focus stays in the menu: on the next entry, or else on the button that adds one.
        (next?.querySelector('[data-field="remove"]') ?? this.addTarget).focus();
        this.notifyForm();
    }

    add(page) {
        if (this.rowTargets.some((row) => row.dataset.pageId === page.id)) {
            this.announce(this.labelsValue.repeated);

            return;
        }

        if (this.rowTargets.length >= this.maxValue) {
            return;
        }

        this.pages.set(page.id, page);
        const row = this.templateTarget.content.firstElementChild.cloneNode(true);
        row.dataset.pageId = page.id;
        row.querySelector('[data-field="page_id"]').value = page.id;
        this.listTarget.append(row);
        this.dialogTarget.close();
        this.refresh();
        this.announce(this.labelsValue.added.replace('%title%', page.title));
        row.querySelector('[data-field="label"]').focus();
        this.notifyForm();
    }

    /** Where an entry moved, told, with the focus kept on the button pressed while it still can move. */
    changed(row, direction, message) {
        this.refresh();
        this.announce(message.replace('%title%', this.titleOf(row.dataset.pageId)));
        const button = row.querySelector(`[data-field="${direction}"]`);
        (button.getAttribute('aria-disabled') === 'true' ? row.querySelector(`[data-field="${direction === 'up' ? 'down' : 'up'}"]`) : button).focus();
        this.notifyForm();
    }

    /** Renumbers the fields of every entry, and draws what the menu holds. */
    refresh() {
        const rows = this.rowTargets;
        const id = this.element.id;

        rows.forEach((row, index) => {
            const prefix = `${this.nameValue}[${index}]`;
            row.querySelector('[data-field="page_id"]').name = `${prefix}[page_id]`;
            const label = row.querySelector('[data-field="label"]');
            label.name = `${prefix}[label]`;
            label.id = `${id}-${index}-label`;
            row.querySelector('[data-field="label-for"]').htmlFor = label.id;
            const page = row.querySelector('[data-field="page"]');
            page.id = `${id}-${index}-page`;
            label.setAttribute('aria-describedby', `${id}-label-hint ${page.id}`);

            for (const field of ['up', 'down', 'remove']) {
                row.querySelector(`[data-field="${field}"]`).setAttribute('aria-describedby', page.id);
            }

            // At either end the button stays focusable, and says it can't move further.
            row.querySelector('[data-field="up"]').setAttribute('aria-disabled', String(index === 0));
            row.querySelector('[data-field="down"]').setAttribute('aria-disabled', String(index === rows.length - 1));
            this.drawPage(row);
        });

        const full = rows.length >= this.maxValue;
        this.emptyTarget.hidden = rows.length > 0;
        this.addTarget.setAttribute('aria-disabled', String(full));
        this.fullTarget.hidden = !full;
    }

    drawPage(row) {
        const id = row.dataset.pageId;
        const page = this.pages.get(id);
        const title = row.querySelector('[data-menu-field-target="pageTitle"]');
        const status = row.querySelector('[data-menu-field-target="pageStatus"]');
        const label = row.querySelector('[data-field="label"]');
        const { loading, missing, unknown, offSite } = this.labelsValue;

        if (page) {
            title.textContent = page.title;
            status.textContent = page.published ? page.status : `${page.status}. ${offSite}`;
            label.placeholder = page.title;
        } else {
            title.textContent = this.missing.has(id) ? missing : (this.unknown ? unknown : loading);
            status.textContent = '';
        }
    }

    titleOf(id) {
        return this.pages.get(id)?.title ?? '';
    }

    /* Pages: looked up by id, to name the entries. */

    async lookUp(ids) {
        if (ids.length === 0) {
            return;
        }

        const url = new URL(this.lookupUrlValue, window.location.href);
        url.searchParams.set('ids', ids.join(','));

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const found = (await response.json()).pages;
            found.forEach((page) => this.pages.set(page.id, page));
            ids.filter((id) => !this.pages.has(id)).forEach((id) => this.missing.add(id));
        } catch {
            // Told on each entry: the pages may well exist.
            this.unknown = true;
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
        const request = ++this.query.request;
        const url = new URL(this.searchUrlValue, window.location.href);
        url.searchParams.set('title', this.searchTarget.value);
        url.searchParams.set('page', String(this.query.page));
        this.moreTarget.hidden = true;
        let result = null;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            result = response.ok ? await response.json() : null;
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out meanwhile: this answer is stale.
        if (request !== this.query.request) {
            return;
        }

        const { none, result: one, results, error } = this.labelsValue;

        if (result === null) {
            this.searchStatusTarget.textContent = error;

            return;
        }

        const first = this.resultsTarget.children.length;
        this.resultsTarget.append(...result.pages.map((page) => this.result(page)));
        this.query.count += result.pages.length;
        this.moreTarget.hidden = !result.more;

        const { count } = this.query;
        this.searchStatusTarget.textContent = [none, one][count] ?? results.replace('%count%', String(count));

        if (this.query.page > 1) {
            this.resultsTarget.children[first]?.querySelector('button')?.focus();
        }
    }

    /** One page to add: the whole row is its button, named by its title and status. */
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

    announce(message) {
        this.statusTarget.textContent = message;
    }

    /** Changing fields from a script fires no event, so tell whoever watches the form. */
    notifyForm() {
        this.element.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
