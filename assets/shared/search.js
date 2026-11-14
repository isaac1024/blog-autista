import { countText } from './count_text.js';
import { answerOf } from './http.js';
import { LatestRequest } from './latest_request.js';

/**
 * @typedef {import('./count_text.js').CountLabels & {error: string}} SearchLabels
 */

/**
 * Where a search is typed and answered. With a More button, the results
 * come a page at a time.
 *
 * @typedef {object} SearchView
 * @property {HTMLInputElement} input
 * @property {HTMLElement} results
 * @property {HTMLElement} status
 * @property {HTMLElement|null} more
 */

/**
 * @template T
 * @typedef {object} SearchOptions
 * @property {URL} url The endpoint, which takes what is typed in `param`.
 * @property {string} param
 * @property {string} key Where its answer lists what it found.
 * @property {AbortSignal} owner
 * @property {() => SearchView} view Read when searching: a dialog may come and go.
 * @property {() => SearchLabels} labels
 * @property {(item: T) => HTMLLIElement} row The result to pick, its button first.
 */

/**
 * A search of the CMS listed to pick from, with how many results it found
 * told in words. Each search outdates the ones before, and one that fails
 * says so and keeps what was shown. Paged, the next results are added to
 * those listed, and the focus goes to the first of them, where reading goes
 * on.
 *
 * @template T
 */
export class Search {
    #page = 1;

    /** @param {SearchOptions<T>} options */
    constructor(options) {
        this.options = options;
        this.requests = new LatestRequest(options.owner);
    }

    /** Lists the first results for what is typed. */
    first() {
        this.#page = 1;

        return this.#load();
    }

    /** Adds the next results to those listed. */
    next() {
        this.#page += 1;

        return this.#load();
    }

    async #load() {
        const { url, param, key, view, labels, row } = this.options;
        const { input, results, status, more } = view();
        const signal = this.requests.next();
        const address = new URL(url);
        address.searchParams.set(param, input.value);

        if (more !== null) {
            address.searchParams.set('page', String(this.#page));
            more.hidden = true;
        }

        const answer = /** @type {Record<string, unknown>|null} */ (await answerOf(address, signal));

        // A newer search went out, or the owner is gone.
        if (signal.aborted) {
            return;
        }

        if (answer === null) {
            status.textContent = labels().error;

            return;
        }

        const items = /** @type {T[]} */ (answer[key]).map(row);

        if (this.#page === 1) {
            results.replaceChildren();
        }

        results.append(...items);
        status.textContent = countText(results.children.length, labels());

        if (more !== null) {
            more.hidden = !answer.more;
        }

        if (this.#page > 1 && items.length > 0) {
            /** @type {HTMLElement} */ (items[0].firstElementChild).focus();
        }
    }
}
