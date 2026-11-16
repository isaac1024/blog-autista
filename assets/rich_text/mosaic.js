import { Node, mergeAttributes } from '@tiptap/core';
import { mosaicSummary, newMosaic } from './mosaic_summary.js';

/** @typedef {import('./mosaic_summary.js').MosaicFilters} MosaicFilters */
/** @typedef {import('./mosaic_summary.js').MosaicLabels & {label: string, change: string}} MosaicViewLabels */

/**
 * A mosaic: the latest posts of the blog, as many as it asks for, of a
 * section, an author and any of some tags, when it narrows them down to
 * them. The editor shows what it asks for, in words, and a button to change
 * it; the site shows the posts.
 */
export const Mosaic = Node.create({
    name: 'mosaic',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: false,

    addOptions() {
        return {
            name: /** @type {(type: string, id: string) => string} */ (() => ''),
            change: /** @type {(position: number, mosaic: MosaicFilters) => void} */ (() => {}),
            views: /** @type {Set<() => void>} */ (new Set()),
            labels: /** @type {MosaicViewLabels} */ ({}),
        };
    },

    addAttributes() {
        const start = newMosaic();

        return {
            limit: {
                default: start.limit,
                parseHTML: (element) => Number(element.getAttribute('data-limit')),
                renderHTML: (attributes) => ({ 'data-limit': attributes.limit }),
            },
            sectionId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-section-id'),
                renderHTML: (attributes) => ({ 'data-section-id': attributes.sectionId }),
            },
            authorId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-author-id'),
                renderHTML: (attributes) => ({ 'data-author-id': attributes.authorId }),
            },
            tagIds: {
                default: [],
                parseHTML: (element) => (element.getAttribute('data-tag-ids') ?? '').split(',').filter(Boolean),
                renderHTML: (attributes) => ({ 'data-tag-ids': attributes.tagIds.join(',') }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-mosaic]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes({ 'data-mosaic': '' }, HTMLAttributes)];
    },

    addNodeView() {
        return ({ node, getPos }) => {
            const element = document.createElement('div');
            element.className = 'rich-text-widget';
            element.setAttribute('data-mosaic', '');

            const label = document.createElement('p');
            label.className = 'rich-text-widget-label';
            label.textContent = this.options.labels.label;

            const summary = document.createElement('p');
            summary.className = 'rich-text-widget-title';

            const change = document.createElement('button');
            change.type = 'button';
            change.className = 'rich-text-mosaic-change';
            change.textContent = this.options.labels.change;
            change.addEventListener('click', () => {
                const position = getPos();

                if (typeof position === 'number') {
                    this.options.change(position, /** @type {MosaicFilters} */ ({ ...node.attrs }));
                }
            });

            element.append(label, summary, change);

            const draw = () => {
                summary.textContent = mosaicSummary(/** @type {MosaicFilters} */ (node.attrs), this.options.name, this.options.labels);
            };

            draw();
            this.options.views.add(draw);

            return {
                dom: element,
                // Its button is its own: the editor leaves its clicks alone.
                stopEvent: (event) => event.target === change,
                ignoreMutation: () => true,
                destroy: () => this.options.views.delete(draw),
            };
        };
    },
});
