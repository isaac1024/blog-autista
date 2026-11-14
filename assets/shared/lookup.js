/*
 * What the CMS is known to hold for an id the editor shows, such as a page
 * a field points to or an image the text embeds, and how that is told.
 */
import { answerOf } from './http.js';

/**
 * Still being looked up, found, not found, or unknown when the lookup
 * failed and it may well exist.
 *
 * @template T
 * @typedef {{state: 'loading'} | {state: 'missing'} | {state: 'unknown'} | {state: 'found', value: T}} Lookup
 */

/** @type {{state: 'loading'}} */
export const LOADING = Object.freeze({ state: 'loading' });

/** @type {{state: 'missing'}} */
export const MISSING = Object.freeze({ state: 'missing' });

/** @type {{state: 'unknown'}} */
export const UNKNOWN = Object.freeze({ state: 'unknown' });

/**
 * @template T
 * @param {T} value
 * @returns {{state: 'found', value: T}}
 */
export const found = (value) => ({ state: 'found', value });

/**
 * What an answer of the CMS says of one id: found in it, missing from it,
 * or unknown when there was no answer.
 *
 * @template {{id: string}} T
 * @param {T[]|null} answer
 * @param {string} id
 * @returns {Lookup<T>}
 */
export const lookupIn = (answer, id) => {
    if (answer === null) {
        return UNKNOWN;
    }

    const value = answer.find((item) => item.id === id);

    return value === undefined ? MISSING : found(value);
};

/**
 * Where something stands for the site, in words: its status, and that the
 * site doesn't show it while it isn't published.
 *
 * @param {{status: string, published: boolean}} item
 * @param {string} offSite
 */
export const standing = ({ status, published }, offSite) => (published ? status : `${status}. ${offSite}`);

/**
 * @typedef {object} LookupLabels
 * @property {string} loading
 * @property {string} missing
 * @property {string} unknown
 * @property {string} offSite
 */

/**
 * A lookup in words: the name of what was found and where it stands, or
 * else what is known of it, standing nowhere.
 *
 * @template {{status: string, published: boolean}} T
 * @param {Lookup<T>} lookup
 * @param {(value: T) => string} name
 * @param {LookupLabels} labels
 * @returns {{name: string, standing: string}}
 */
export const lookupText = (lookup, name, labels) => (lookup.state === 'found'
    ? { name: name(lookup.value), standing: standing(lookup.value, labels.offSite) }
    : { name: labels[lookup.state], standing: '' });

/**
 * What the CMS holds for every id shown, asked of one lookup endpoint: each
 * id once, unless picked meanwhile, which is known without asking. An answer
 * that comes after the owner is gone, such as a controller disconnected, is
 * for no one.
 *
 * @template {{id: string}} T
 */
export class Lookups {
    /** @type {Map<string, Lookup<T>>} */
    #known = new Map();

    /**
     * @param {URL} url The endpoint, which takes the ids in `ids`.
     * @param {string} key Where its answer lists what it found.
     * @param {AbortSignal} owner
     * @param {() => void} answered Told once what was asked is known.
     */
    constructor(url, key, owner, answered) {
        this.url = url;
        this.key = key;
        this.owner = owner;
        this.answered = answered;
    }

    /**
     * Asks about the ids never asked about nor picked.
     *
     * @param {string[]} ids
     */
    async ask(ids) {
        const asked = [...new Set(ids)].filter((id) => !this.#known.has(id));

        if (asked.length === 0) {
            return;
        }

        asked.forEach((id) => this.#known.set(id, LOADING));
        const url = new URL(this.url);
        url.searchParams.set('ids', asked.join(','));
        const answer = /** @type {Record<string, T[]>|null} */ (await answerOf(url, this.owner));

        if (this.owner.aborted) {
            return;
        }

        // No answer tells each as unknown: they may well exist.
        asked.forEach((id) => this.#known.set(id, lookupIn(answer === null ? null : answer[this.key], id)));
        this.answered();
    }

    /**
     * What was picked, known without asking.
     *
     * @param {T} value
     */
    know(value) {
        this.#known.set(value.id, found(value));
    }

    /**
     * What is known of an id: loading until it is.
     *
     * @param {string} id
     * @returns {Lookup<T>}
     */
    of(id) {
        return this.#known.get(id) ?? LOADING;
    }
}
