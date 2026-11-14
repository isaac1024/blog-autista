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
 * @property {string} address Where the page is served, with %slug% where its slug goes.
 * @property {number} titleLimit
 * @property {number} descriptionLimit
 * @property {string} empty What a search engine shows with no description.
 */

/**
 * The search result a page would make: its address, its title and its
 * description as a search engine shows them. An address without %slug%,
 * such as the home of the site, is the same whatever the slug.
 *
 * @param {{title: string, description: string, slug: string}} page
 * @param {PreviewRule} rule
 */
export const searchResult = ({ title, description, slug }, { address, titleLimit, descriptionLimit, empty }) => {
    const text = description.trim().replace(/\s+/g, ' ');

    return {
        url: address.replace('%slug%', slug.trim()),
        title: cut(title.trim(), titleLimit),
        description: text === '' ? empty : cut(text, descriptionLimit),
    };
};
