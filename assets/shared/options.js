/*
 * The results of a search to pick from: each is a row whose whole width is
 * its button, named by its text and where it stands.
 */

/**
 * @param {string} className
 * @param {string} text
 */
const span = (className, text) => {
    const element = document.createElement('span');
    element.className = className;
    element.textContent = text;

    return element;
};

/**
 * @param {string} kind The kind of option, as its classes name it.
 * @param {() => void} pick
 * @param {Node[]} leading What comes before its text, such as a thumbnail.
 * @param {string} name
 * @param {string} status
 * @param {Node[]} notes
 */
const option = (kind, pick, leading, name, status, notes) => {
    const item = document.createElement('li');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `rich-text-${kind}-option`;
    button.addEventListener('click', pick);

    const text = span(`rich-text-${kind}-option-text`, '');
    text.append(span('font-bold', name), span(`rich-text-${kind}-status`, status), ...notes);
    button.append(...leading, text);
    item.append(button);

    return item;
};

/**
 * An image to pick, by its thumbnail, decorative next to its text.
 *
 * @param {{alt: string, status: string, thumbnail: string}} image
 * @param {() => void} pick
 */
export const imageOption = (image, pick) => {
    const thumbnail = document.createElement('img');
    thumbnail.src = image.thumbnail;
    thumbnail.alt = '';
    thumbnail.width = 112;
    thumbnail.height = 63;
    thumbnail.loading = 'lazy';

    return option('image', pick, [thumbnail], image.alt, image.status, []);
};

/**
 * A page to pick, by its title; one that isn't published says the site
 * doesn't show it.
 *
 * @param {{title: string, status: string, published: boolean}} page
 * @param {() => void} pick
 * @param {string} offSite
 */
export const pageOption = (page, pick, offSite) => option(
    'page',
    pick,
    [],
    page.title,
    page.status,
    page.published ? [] : [span('rich-text-page-note', offSite)],
);
