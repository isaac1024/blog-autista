/*
 * How each provider's frame is told apart on the page and tells how tall it
 * is, for those whose height depends on what they show: Bluesky and X take
 * an id in their address and give it back with the height; Mastodon is
 * asked for it, with an id, once its frame loads; Threads sends the height
 * alone.
 */

/** Providers whose frames report their height. */
const REPORTED = new Set(['bluesky', 'x', 'mastodon', 'threads']);

/**
 * Whether the frame of the provider reports its height, rather than keeping
 * the room its facade has.
 *
 * @param {string} provider
 */
export const reportsHeight = (provider) => REPORTED.has(provider);

/**
 * The address of the frame, with the id the provider gives back, if it
 * takes one.
 *
 * @param {string} provider
 * @param {string} src
 * @param {string} id
 */
export const frameUrl = (provider, src, id) => {
    const url = new URL(src);

    if (provider === 'bluesky') {
        url.searchParams.set('id', id);
    }

    if (provider === 'x') {
        url.searchParams.set('embedId', id);
    }

    return url.toString();
};

/**
 * What to ask the frame once it loads, so it tells its height: only
 * Mastodon waits to be asked.
 *
 * @param {string} provider
 * @param {string} id
 * @returns {unknown}
 */
export const greeting = (provider, id) => (provider === 'mastodon' ? { type: 'setHeight', id } : null);

/**
 * The height in pixels a message of a frame tells, or null when it tells
 * none, or is of another frame.
 *
 * @param {string} provider
 * @param {unknown} data
 * @param {string} id
 * @returns {number | null}
 */
export const reportedHeight = (provider, data, id) => {
    let height = null;

    if (provider === 'threads') {
        height = typeof data === 'number' || typeof data === 'string' ? Number(data) : null;
    }

    if (provider === 'bluesky' || provider === 'mastodon') {
        const message = /** @type {{id?: unknown, height?: unknown, type?: unknown}} */ (data !== null && typeof data === 'object' ? data : {});
        const ours = String(message.id) === id && (provider === 'bluesky' || message.type === 'setHeight');
        height = ours ? Number(message.height) : null;
    }

    if (provider === 'x') {
        const call = /** @type {{id?: unknown, method?: unknown, params?: unknown}} */ (data !== null && typeof data === 'object' ? (/** @type {Record<string, unknown>} */ (data)['twttr.embed'] ?? {}) : {});
        const params = Array.isArray(call.params) ? /** @type {{height?: unknown}} */ (call.params[0] ?? {}) : {};
        height = call.method === 'twttr.private.resize' && String(call.id) === id ? Number(params.height) : null;
    }

    return height !== null && Number.isFinite(height) && height > 0 ? Math.ceil(height) : null;
};
