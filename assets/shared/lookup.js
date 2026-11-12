/*
 * What the CMS is known to hold for an id the editor shows, such as a page
 * a field points to or an image the text embeds, and how that is told.
 */

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
 * What the lookups so far know of an id: found, found missing, failed, or
 * else not answered yet.
 *
 * @template T
 * @param {string} id
 * @param {{found: Map<string, T>, missing: Set<string>, unknown: Set<string>}} known
 * @returns {Lookup<T>}
 */
export const lookupAmong = (id, known) => {
    const value = known.found.get(id);

    if (value !== undefined) {
        return found(value);
    }

    if (known.missing.has(id)) {
        return MISSING;
    }

    return known.unknown.has(id) ? UNKNOWN : LOADING;
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
