/*
 * The pages a rich text points to, through its links and its widgets, and
 * how it tells where each of them stands.
 */

/** @typedef {import('./page_link.js').PageState} PageState */

/**
 * @typedef {object} PageNode
 * @property {{name: string}} type
 * @property {{id?: string|null}} attrs
 * @property {ReadonlyArray<{type: {name: string}, attrs: {id?: string|null}}>} marks
 */

/**
 * The ids of the pages a document links to or shows in widgets, each once.
 *
 * @param {{descendants: (callback: (node: PageNode) => void) => void}} doc
 * @returns {string[]}
 */
export const pageIdsIn = (doc) => {
    /** @type {Set<string>} */
    const ids = new Set();

    doc.descendants((node) => {
        const cited = [
            node.type.name === 'widget' ? node.attrs.id : null,
            ...node.marks.filter((mark) => mark.type.name === 'link').map((mark) => mark.attrs.id),
        ];

        for (const id of cited) {
            if (id) {
                ids.add(id);
            }
        }
    });

    return [...ids];
};

/**
 * Where a page stands for the editor: on the site or off it once found, or
 * else what is known of it.
 *
 * @param {import('../shared/lookup.js').Lookup<{published: boolean}>} lookup
 * @returns {PageState}
 */
export const pageStateOf = (lookup) => {
    if (lookup.state !== 'found') {
        return lookup.state;
    }

    return lookup.value.published ? 'published' : 'offSite';
};

/**
 * @typedef {object} LinkLabels
 * @property {string} target With %title% and %status% where they go.
 * @property {string} offSite
 * @property {string} loading
 * @property {string} missing
 * @property {string} unknown
 */

/**
 * The page a link goes to, in words: never by its look alone.
 *
 * @param {import('../shared/lookup.js').Lookup<{title: string, status: string, published: boolean}>} lookup
 * @param {LinkLabels} labels
 */
export const linkTarget = (lookup, labels) => {
    if (lookup.state !== 'found') {
        return labels[lookup.state];
    }

    const { title, status, published } = lookup.value;
    const target = labels.target.replace('%title%', title).replace('%status%', status);

    return published ? target : `${target} ${labels.offSite}`;
};
