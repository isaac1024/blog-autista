import { Controller } from '@hotwired/stimulus';

/*
 * Edits one optional image of the library: shows the one chosen, as the CMS
 * knows it now, picks another searched by alternative text, or removes it,
 * leaving the field without any. Where the image stands for the site is said
 * in words.
 */
export default class extends Controller {
    static targets = ['input', 'thumbnail', 'description', 'state', 'choose', 'remove', 'dialog', 'search', 'searchStatus', 'results', 'status'];

    static values = {
        searchUrl: String,
        lookupUrl: String,
        labels: Object,
    };

    connect() {
        this.chooseTarget.hidden = false;
        this.image = null;
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

            this.image = (await response.json()).images.find((image) => image.id === id) ?? false;
        } catch {
            // Told as unknown: the image may well exist.
            this.image = undefined;
        }

        this.draw();
    }

    /** What the field holds: no image, one being looked up, missing, unknown, or the image itself. */
    draw() {
        const { empty, loading, missing, unknown, offSite } = this.labelsValue;
        const id = this.inputTarget.value;
        const image = this.image;
        this.removeTarget.hidden = id === '';
        this.thumbnailTarget.hidden = true;
        this.thumbnailTarget.replaceChildren();
        this.stateTarget.textContent = '';

        if (id === '') {
            this.descriptionTarget.textContent = empty;
        } else if (image === null) {
            this.descriptionTarget.textContent = loading;
        } else if (image === false) {
            this.descriptionTarget.textContent = missing;
        } else if (image === undefined) {
            this.descriptionTarget.textContent = unknown;
        } else {
            // Decorative: the text next to it says which image it is.
            const thumbnail = document.createElement('img');
            thumbnail.className = 'block aspect-video h-auto w-28 object-cover';
            thumbnail.src = image.thumbnail;
            thumbnail.alt = '';
            thumbnail.width = 112;
            thumbnail.height = 63;
            this.thumbnailTarget.replaceChildren(thumbnail);
            this.thumbnailTarget.hidden = false;
            this.descriptionTarget.textContent = image.alt;
            this.stateTarget.textContent = image.published ? image.status : `${image.status}. ${offSite}`;
        }
    }

    choose(image) {
        this.inputTarget.value = image.id;
        this.image = image;
        this.dialogTarget.close();
        this.draw();
        this.statusTarget.textContent = this.labelsValue.chosen.replace('%alt%', image.alt);
        this.chooseTarget.focus();
        this.notifyForm();
    }

    remove() {
        this.inputTarget.value = '';
        this.image = null;
        this.draw();
        this.statusTarget.textContent = this.labelsValue.removed;
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
        url.searchParams.set('alt', this.searchTarget.value);
        let images = null;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            images = response.ok ? (await response.json()).images : null;
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out meanwhile: this answer is stale.
        if (request !== this.request) {
            return;
        }

        const { none, result, results, error } = this.labelsValue;

        if (images === null) {
            this.searchStatusTarget.textContent = error;

            return;
        }

        this.resultsTarget.replaceChildren(...images.map((image) => this.result(image)));
        this.searchStatusTarget.textContent = [none, result][images.length] ?? results.replace('%count%', String(images.length));
    }

    /** One image to pick: the whole row is its button, named by its text and status. */
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

    /** Changing the field from a script fires no event, so tell whoever watches the form. */
    notifyForm() {
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
