/*
 * How tall the frame of each post of another site said it was, at the width
 * it had, as kept in the browser of a reader who agreed to load it, so the
 * next time it loads it takes its room at once rather than when it tells it.
 * Only the latest few are kept, and those of a provider go when the reader
 * takes back what they agreed to; none of it ever leaves the browser.
 */

const KEY = 'external-heights';

/** How many heights are kept, the latest. */
export const KEPT = 50;

/**
 * @param {Storage | undefined} storage
 * @returns {[string, number][]}
 */
const read = (storage) => {
    try {
        // Without storage, or with nothing kept, this throws: null has no
        // entries. A list kept has entries of its own, by their position,
        // which never tell a height of a frame.
        const kept = JSON.parse(String(/** @type {Storage} */ (storage).getItem(KEY)));

        return Object.entries(kept).filter(([, height]) => typeof height === 'number');
    } catch {
        return [];
    }
};

/**
 * @param {Storage | undefined} storage
 * @param {[string, number][]} heights
 */
const write = (storage, heights) => {
    try {
        /** @type {Storage} */ (storage).setItem(KEY, JSON.stringify(Object.fromEntries(heights.slice(-KEPT))));
    } catch {
        // A browser without storage, or that refuses to keep it, takes the
        // room of the facade again.
    }
};

/**
 * What a frame is told apart by: its provider, its address and its width.
 *
 * @param {string} provider
 * @param {string} src
 * @param {number} width
 */
const key = (provider, src, width) => `${provider} ${Math.round(width)} ${src}`;

/**
 * The height the frame said it had at this width, or null when it never did.
 *
 * @param {Storage | undefined} storage
 * @param {string} provider
 * @param {string} src
 * @param {number} width
 * @returns {number | null}
 */
export const keptHeight = (storage, provider, src, width) => {
    const found = read(storage).find(([candidate]) => candidate === key(provider, src, width));

    return found === undefined ? null : found[1];
};

/**
 * Keeps the height the frame said it had at this width, as the latest.
 *
 * @param {Storage | undefined} storage
 * @param {string} provider
 * @param {string} src
 * @param {number} width
 * @param {number} height
 */
export const keepHeight = (storage, provider, src, width, height) => {
    const kept = key(provider, src, width);
    write(storage, [...read(storage).filter(([candidate]) => candidate !== kept), [kept, height]]);
};

/**
 * Forgets every height of the frames of the provider.
 *
 * @param {Storage | undefined} storage
 * @param {string} provider
 */
export const forgetHeights = (storage, provider) => {
    write(storage, read(storage).filter(([candidate]) => !candidate.startsWith(`${provider} `)));
};
