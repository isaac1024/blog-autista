import { Node, mergeAttributes } from '@tiptap/core';

/** @typedef {import('./page_link.js').PageState} PageState */
/** @typedef {{title: string, status: string}} WidgetPage */
/** @typedef {{state: PageState, page: WidgetPage|undefined}} WidgetDescription */
/** @typedef {Partial<Record<PageState|'label'|'offSite', string>>} WidgetLabels */

/**
 * Draws a widget as the CMS knows its page now: its title and where it
 * stands for the site, after what kind of widget it is, all in words.
 *
 * @param {HTMLElement} element
 * @param {WidgetDescription} description
 * @param {WidgetLabels} labels
 */
const drawWidget = (element, { state, page }, labels) => {
    element.replaceChildren();

    const label = document.createElement('p');
    label.className = 'rich-text-widget-label';
    label.textContent = labels.label ?? '';
    element.append(label);

    if (page === undefined) {
        const note = document.createElement('p');
        note.className = 'rich-text-page-note';
        note.textContent = labels[state] ?? '';
        element.append(note);

        return;
    }

    const title = document.createElement('p');
    title.className = 'rich-text-widget-title';
    title.textContent = page.title;

    const status = document.createElement('span');
    status.className = 'rich-text-page-status';
    status.textContent = page.status;

    element.append(title, status);

    if (state === 'offSite') {
        const offSite = document.createElement('p');
        offSite.className = 'rich-text-page-note';
        offSite.textContent = labels.offSite ?? '';
        element.append(offSite);
    }
};

/**
 * A widget: for now, a page of the blog shown as a card of related content.
 * Its page is kept by its id alone, as a link's is, so the editor shows its
 * latest title and status, and the site its current address.
 */
export const Widget = Node.create({
    name: 'widget',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: false,

    addOptions() {
        return {
            describe: /** @type {(id: string) => WidgetDescription} */ (() => ({ state: 'loading', page: undefined })),
            views: /** @type {Set<() => void>} */ (new Set()),
            labels: /** @type {WidgetLabels} */ ({}),
        };
    },

    addAttributes() {
        return {
            kind: {
                default: 'related_page',
                parseHTML: (element) => element.getAttribute('data-widget-kind'),
                renderHTML: (attributes) => ({ 'data-widget-kind': attributes.kind }),
            },
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-page-id'),
                renderHTML: (attributes) => ({ 'data-page-id': attributes.id }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-widget-kind]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes)];
    },

    addNodeView() {
        return ({ node }) => {
            const element = document.createElement('div');
            element.className = 'rich-text-widget';
            element.setAttribute('data-widget-kind', node.attrs.kind);
            const draw = () => drawWidget(element, this.options.describe(node.attrs.id), this.options.labels);

            draw();
            this.options.views.add(draw);

            return { dom: element, destroy: () => this.options.views.delete(draw) };
        };
    },
});
