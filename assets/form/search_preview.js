/**
 * Cuts the text where search engines would, marking the cut with an ellipsis.
 *
 * @param {string} text
 * @param {number} limit
 */
export const cut = (text, limit) => {
    const characters = [...text];

    return characters.length > limit ? `${characters.slice(0, limit - 1).join('').trimEnd()}…` : text;
};

/**
 * @typedef {object} PreviewRule
 * @property {string} base The address of the site, with no trailing slash.
 * @property {number} titleLimit
 * @property {number} descriptionLimit
 * @property {string} empty What a search engine shows with no description.
 */

/**
 * The search result a page would make: its address, its title and its
 * description as a search engine shows them. Without a slug the page is the
 * home of the site.
 *
 * @param {{title: string, description: string, slug: string|null}} page
 * @param {PreviewRule} rule
 */
export const searchResult = ({ title, description, slug }, { base, titleLimit, descriptionLimit, empty }) => {
    const text = description.trim().replace(/\s+/g, ' ');

    return {
        url: slug === null ? `${base}/` : `${base}/${slug.trim()}.html`,
        title: cut(title.trim(), titleLimit),
        description: text === '' ? empty : cut(text, descriptionLimit),
    };
};
