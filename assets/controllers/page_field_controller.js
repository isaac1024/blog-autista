import { Controller } from '@hotwired/stimulus';

/*
 * Edits one page: shows the one chosen, as the CMS knows it now, and picks
 * another searched by title. Where the page stands for the site is said in
 * words.
 */
export default class extends Controller {
    static targets = ['input', 'title', 'state', 'choose', 'dialog', 'search', 'searchStatus', 'results', 'status'];

    static values = {
        searchUrl: String,
        lookupUrl: String,
        labels: Object,
    };

    connect() {
        this.chooseTarget.hidden = false;
        this.page = null;
        this.draw();

        if (this.inputTarget.value !== '') {
            this.lookUp(this.inputTarget.value);
        }
    }

    async lookUp(id) {
        const url = new URL(this.lookupUrlValue, window.location.href);
        url.searchParams.set('ids', id);

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            this.page = (await response.json()).pages.find((page) => page.id === id) ?? false;
        } catch {
            // Told as unknown: the page may well exist.
            this.page = undefined;
        }

        this.draw();
    }

    /** What the field holds: no page, one being looked up, missing, unknown, or the page itself. */
    draw() {
        const { empty, loading, missing, unknown, offSite } = this.labelsValue;
        const id = this.inputTarget.value;
        const page = this.page;
        this.stateTarget.textContent = '';

        if (id === '') {
            this.titleTarget.textContent = empty;
        } else if (page === null) {
            this.titleTarget.textContent = loading;
        } else if (page === false) {
            this.titleTarget.textContent = missing;
        } else if (page === undefined) {
            this.titleTarget.textContent = unknown;
        } else {
            this.titleTarget.textContent = page.title;
            this.stateTarget.textContent = page.published ? page.status : `${page.status}. ${offSite}`;
        }
    }

    choose(page) {
        this.inputTarget.value = page.id;
        this.page = page;
        this.dialogTarget.close();
        this.draw();
        this.statusTarget.textContent = this.labelsValue.chosen.replace('%title%', page.title);
        this.chooseTarget.focus();
        this.notifyForm();
    }

    openDialog() {
        this.dialogTarget.showModal();
        this.searchTarget.focus();
        this.search();
    }

    async search(event) {
        event?.preventDefault();

        const request = (this.request ?? 0) + 1;
        this.request = request;
        const url = new URL(this.searchUrlValue, window.location.href);
        url.searchParams.set('title', this.searchTarget.value);
        let pages = null;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            pages = response.ok ? (await response.json()).pages : null;
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out meanwhile: this answer is stale.
        if (request !== this.request) {
            return;
        }

        const { none, result, results, error } = this.labelsValue;

        if (pages === null) {
            this.searchStatusTarget.textContent = error;

            return;
        }

        this.resultsTarget.replaceChildren(...pages.map((page) => this.result(page)));
        this.searchStatusTarget.textContent = [none, result][pages.length] ?? results.replace('%count%', String(pages.length));
    }

    /** One page to pick: the whole row is its button, named by its title and status. */
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

    /** Changing the field from a script fires no event, so tell whoever watches the form. */
    notifyForm() {
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
