/*
 * What the CMS knows of what a rich text links to or shows in widgets, kept
 * apart by the kind of content it is, each kind asked where its lookup
 * answers; kinds that share a lookup, such as sections and tags, share what
 * is known of them.
 */
import { LOADING, Lookups } from '../shared/lookup.js';

/**
 * A kind of content to link, as the editor offers it.
 *
 * @typedef {object} LinkTarget
 * @property {string} kind What the editor names it, such as "section".
 * @property {string} type What it is kept as, such as "term".
 * @property {string} searchUrl
 * @property {string} param
 * @property {string} key
 * @property {string} lookupUrl
 */

/**
 * @template {{id: string}} T
 */
export class TargetLookups {
    /** @type {Map<string, Lookups<T>>} */
    #byType = new Map();

    /**
     * @param {LinkTarget[]} targets
     * @param {AbortSignal} owner
     * @param {() => void} answered Told once what was asked is known.
     */
    constructor(targets, owner, answered) {
        for (const { type, lookupUrl, key } of targets) {
            if (!this.#byType.has(type)) {
                this.#byType.set(type, /** @type {Lookups<T>} */ (new Lookups(new URL(lookupUrl, window.location.href), key, owner, answered)));
            }
        }
    }

    /**
     * Asks about what is cited and not known yet, each kind where it is looked up.
     *
     * @param {{type: string, id: string}[]} cited
     */
    ask(cited) {
        for (const [type, lookups] of this.#byType) {
            lookups.ask(cited.filter((target) => target.type === type).map((target) => target.id));
        }
    }

    /**
     * What was picked, known without asking.
     *
     * @param {string} type
     * @param {T} value
     */
    know(type, value) {
        this.#byType.get(type)?.know(value);
    }

    /**
     * What is known of a content: loading until it is, as one of a kind
     * never looked up.
     *
     * @param {string} type
     * @param {string} id
     * @returns {import('../shared/lookup.js').Lookup<T>}
     */
    of(type, id) {
        return this.#byType.get(type)?.of(id) ?? LOADING;
    }
}
