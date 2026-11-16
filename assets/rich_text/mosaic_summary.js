/*
 * What a mosaic asks for, in words: how many posts at most, and what it
 * narrows them down to, each named as the CMS knows it now.
 */

/**
 * What a mosaic keeps: as many posts as its limit at most, of a section and
 * an author, and with any of some tags, when it narrows them down to them.
 *
 * @typedef {object} MosaicFilters
 * @property {number} limit
 * @property {string|null} sectionId
 * @property {string|null} authorId
 * @property {string[]} tagIds
 */

/**
 * @typedef {object} MosaicLabels
 * @property {string} limit With %limit% where the number goes.
 * @property {string} section
 * @property {string} author
 * @property {string} tags
 * @property {string} loading
 * @property {string} missing
 * @property {string} unknown
 */

/** The fewest and the most posts a mosaic shows. */
export const MIN_LIMIT = 1;
export const MAX_LIMIT = 20;

/** As many as a mosaic shows until told otherwise. */
export const DEFAULT_LIMIT = 6;

/**
 * A number written for the limit, kept within what a mosaic shows; anything
 * that isn't a whole number keeps the limit it had.
 *
 * @param {string} written
 * @param {number} current
 */
export const limitFrom = (written, current) => {
    const number = Number(written);

    if (written.trim() === '' || !Number.isInteger(number)) {
        return current;
    }

    return Math.min(MAX_LIMIT, Math.max(MIN_LIMIT, number));
};

/**
 * A mosaic as it starts: the latest posts, of nothing in particular.
 *
 * @returns {MosaicFilters}
 */
export const newMosaic = () => ({ limit: DEFAULT_LIMIT, sectionId: null, authorId: null, tagIds: [] });

/**
 * The tags, with one more at the end, each once.
 *
 * @param {string[]} tagIds
 * @param {string} id
 */
export const withTag = (tagIds, id) => (tagIds.includes(id) ? tagIds : [...tagIds, id]);

/**
 * The tags, without one.
 *
 * @param {string[]} tagIds
 * @param {string} id
 */
export const withoutTag = (tagIds, id) => tagIds.filter((tag) => tag !== id);

/**
 * The name of a content as the CMS knows it, or what is known of it.
 *
 * @param {import('../shared/lookup.js').Lookup<{title: string}>} lookup
 * @param {MosaicLabels} labels
 */
export const nameOf = (lookup, labels) => (lookup.state === 'found' ? lookup.value.title : labels[lookup.state]);

/**
 * What a mosaic asks for, in words, such as "Up to 6 posts · Section: Daily
 * life · Tags: Sleep, Sound".
 *
 * @param {MosaicFilters} mosaic
 * @param {(type: string, id: string) => string} name The name of a term or an author.
 * @param {MosaicLabels} labels
 */
export const mosaicSummary = (mosaic, name, labels) => {
    const parts = [labels.limit.replace('%limit%', String(mosaic.limit))];

    if (mosaic.sectionId) {
        parts.push(`${labels.section}: ${name('term', mosaic.sectionId)}`);
    }

    if (mosaic.authorId) {
        parts.push(`${labels.author}: ${name('author', mosaic.authorId)}`);
    }

    if (mosaic.tagIds.length > 0) {
        parts.push(`${labels.tags}: ${mosaic.tagIds.map((id) => name('term', id)).join(', ')}`);
    }

    return parts.join(' · ');
};
