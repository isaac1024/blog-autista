import { Node, mergeAttributes } from '@tiptap/core';

/**
 * @typedef {object} ImageSummary
 * @property {string} thumbnail
 * @property {string} status
 * @property {string} alt
 * @property {string} caption
 * @property {boolean} published
 */

/**
 * @typedef {object} ImageLabels
 * @property {string} loading
 * @property {string} unknown
 * @property {string} missing
 * @property {string} embedded
 * @property {string} offSite
 */

/**
 * Draws an embedded image as the CMS knows it now: its thumbnail, its texts
 * and where it stands for the site. Undefined while it is being looked up,
 * null when there is no such image any longer, false when it can't be checked.
 *
 * @param {HTMLElement} figure
 * @param {ImageSummary|null|false|undefined} image
 * @param {ImageLabels} labels
 */
const drawImage = (figure, image, labels) => {
    figure.replaceChildren();

    if (image === undefined || image === null || image === false) {
        const note = document.createElement('p');
        note.className = 'rich-text-image-note';
        note.textContent = image === undefined ? labels.loading : (image === false ? labels.unknown : labels.missing);
        figure.append(note);

        return;
    }

    const thumbnail = document.createElement('img');
    thumbnail.src = image.thumbnail;
    // The texts below name it.
    thumbnail.alt = '';
    thumbnail.width = 240;
    thumbnail.height = 135;

    const details = document.createElement('div');
    details.className = 'rich-text-image-details';

    const status = document.createElement('span');
    status.className = 'rich-text-image-status';
    status.textContent = image.status;

    const alt = document.createElement('p');
    alt.className = 'rich-text-image-alt';
    alt.textContent = `${labels.embedded} ${image.alt}`;

    details.append(status, alt);

    if (image.caption !== '') {
        const caption = document.createElement('p');
        caption.textContent = image.caption;
        details.append(caption);
    }

    if (!image.published) {
        const offSite = document.createElement('p');
        offSite.className = 'rich-text-image-note';
        offSite.textContent = labels.offSite;
        details.append(offSite);
    }

    figure.append(thumbnail, details);
};

/**
 * An image of the library, embedded by its id alone: the editor shows what
 * the CMS knows of it now, so its texts and status are always the latest.
 */
export const EmbeddedImage = Node.create({
    name: 'image',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: false,

    addOptions() {
        return {
            describe: /** @type {(id: string) => ImageSummary|null|false|undefined} */ (() => undefined),
            views: /** @type {Set<() => void>} */ (new Set()),
            labels: /** @type {ImageLabels} */ ({}),
        };
    },

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-image-id'),
                renderHTML: (attributes) => ({ 'data-image-id': attributes.id }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-image-id]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['figure', mergeAttributes(HTMLAttributes)];
    },

    addNodeView() {
        return ({ node }) => {
            const figure = document.createElement('figure');
            figure.className = 'rich-text-image';
            figure.setAttribute('data-image-id', node.attrs.id);
            const draw = () => drawImage(figure, this.options.describe(node.attrs.id), this.options.labels);

            draw();
            this.options.views.add(draw);

            return { dom: figure, destroy: () => this.options.views.delete(draw) };
        };
    },
});
