/*
 * How each provider's frame is told apart on the page and tells how tall it
 * is, for those whose height depends on what they show: Bluesky and X take
 * an id in their address and give it back with the height; Mastodon is
 * asked for it, with an id, once its frame loads; Threads sends the height
 * alone. And how a video or an audio the reader played starts on its own:
 * YouTube and TikTok are told in the address, Spotify once it is ready.
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

/** Providers told in the address of their frame to start playing. */
const PLAYED_BY_ADDRESS = new Set(['youtube', 'tiktok']);

/**
 * The address of the frame, with the id the provider gives back, if it
 * takes one, and, for a video the reader played, the word to start.
 *
 * @param {string} provider
 * @param {string} src
 * @param {string} id
 * @param {boolean} played
 */
export const frameUrl = (provider, src, id, played) => {
    const url = new URL(src);

    if (played && PLAYED_BY_ADDRESS.has(provider)) {
        url.searchParams.set('autoplay', '1');
    }

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
 * What to tell a frame the reader played, in answer to a message of it, so
 * it starts: only Spotify, once it says it is ready.
 *
 * @param {string} provider
 * @param {unknown} data
 * @returns {unknown}
 */
export const playCommand = (provider, data) => (provider === 'spotify' && Object(data).type === 'ready' ? { command: 'toggle' } : null);

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
    // No height until a provider's own message tells one. Object() reads a
    // message that isn't one, such as null or a text, as one without fields.
    let height = Number.NaN;

    if (provider === 'threads') {
        height = typeof data === 'number' || typeof data === 'string' ? Number(data) : Number.NaN;
    }

    if (provider === 'bluesky' || provider === 'mastodon') {
        const message = /** @type {{id?: unknown, height?: unknown, type?: unknown}} */ (Object(data));
        const ours = String(message.id) === id && (provider === 'bluesky' || message.type === 'setHeight');
        height = ours ? Number(message.height) : Number.NaN;
    }

    if (provider === 'x') {
        const call = /** @type {{id?: unknown, method?: unknown, params?: unknown}} */ (Object(Object(data)['twttr.embed']));
        const params = /** @type {{height?: unknown}} */ (Object(Object(call.params)[0]));
        height = call.method === 'twttr.private.resize' && String(call.id) === id ? Number(params.height) : Number.NaN;
    }

    return Number.isFinite(height) && height > 0 ? Math.ceil(height) : null;
};
