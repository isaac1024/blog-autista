import { Node, mergeAttributes } from '@tiptap/core';

/** Paragraphs set apart with a visible label, never just a colour. */
export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'paragraph+',
    defining: true,

    addOptions() {
        return { labels: {} };
    },

    addAttributes() {
        return {
            variant: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-variant'),
                renderHTML: (attributes) => ({ 'data-variant': attributes.variant }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-callout]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, {
            'data-callout': '',
            'data-label': this.options.labels[node.attrs.variant] ?? '',
            role: 'note',
        }), 0];
    },
});
