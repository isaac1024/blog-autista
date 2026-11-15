/*
 * What a rich text points to, through its links and its widgets, and how
 * it tells where each of them stands.
 */

/** @typedef {import('./page_link.js').PageState} PageState */

/**
 * @typedef {object} PageNode
 * @property {{name: string}} type
 * @property {{type?: string|null, id?: string|null}} attrs
 * @property {ReadonlyArray<{type: {name: string}, attrs: {type?: string|null, id?: string|null}}>} marks
 */

/**
 * What a document links to or shows in widgets, each once, by its kind and
 * id; a page when it names no kind, as links did before they named one.
 *
 * @param {{descendants: (callback: (node: PageNode) => void) => void}} doc
 * @returns {{type: string, id: string}[]}
 */
export const targetsIn = (doc) => {
    /** @type {Map<string, {type: string, id: string}>} */
    const cited = new Map();

    doc.descendants((node) => {
        const targets = [
            node.type.name === 'widget' ? node.attrs : null,
            ...node.marks.filter((mark) => mark.type.name === 'link').map((mark) => mark.attrs),
        ];

        for (const target of targets) {
            if (target?.id) {
                const type = target.type || 'page';
                cited.set(`${type}:${target.id}`, { type, id: target.id });
            }
        }
    });

    return [...cited.values()];
};

/**
 * Where a content stands for the editor: on the site or off it once found,
 * or else what is known of it.
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
 * What a link goes to, in words: never by its look alone.
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
