import { Mark, mergeAttributes } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

/**
 * Where what a link goes to stands for the site, as the CMS knows it now.
 *
 * @typedef {'published'|'offSite'|'missing'|'unknown'|'loading'} PageState
 */

/**
 * An internal link to a content of the blog, such as a page or a post, kept
 * by its kind and its id alone. It has no href in the CMS: the editor must
 * not navigate away, and the site resolves the current address.
 *
 * Links whose content isn't on the site, or isn't known yet, are told apart
 * by a class on decorations, which ProseMirror draws itself: the DOM of the
 * text is never touched by hand. The controller says how each stands through
 * stateOf(), and redraws them once it learns more.
 */
export const pageLinksKey = new PluginKey('pageLinks');

export const PageLink = Mark.create({
    name: 'link',
    inclusive: false,

    addOptions() {
        return {
            stateOf: /** @type {(type: string, id: string) => PageState} */ (() => 'loading'),
        };
    },

    addProseMirrorPlugins() {
        const { stateOf } = this.options;

        return [new Plugin({
            key: pageLinksKey,
            props: {
                decorations: (state) => {
                    /** @type {Decoration[]} */
                    const decorations = [];

                    state.doc.descendants((node, position) => {
                        const link = node.isText ? node.marks.find((mark) => mark.type.name === 'link') : undefined;
                        const linkState = link ? stateOf(link.attrs.type, link.attrs.id) : 'published';

                        if (linkState !== 'published') {
                            decorations.push(Decoration.inline(position, position + node.nodeSize, { class: `rich-text-link-${linkState}` }));
                        }
                    });

                    return DecorationSet.create(state.doc, decorations);
                },
            },
        })];
    },

    addAttributes() {
        return {
            // A link of before it named a kind goes to a page.
            type: {
                default: 'page',
                parseHTML: (element) => element.getAttribute('data-link-type') ?? 'page',
                renderHTML: (attributes) => ({ 'data-link-type': attributes.type }),
            },
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-page-id'),
                renderHTML: (attributes) => ({ 'data-page-id': attributes.id }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'a[data-page-id]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['a', mergeAttributes(HTMLAttributes), 0];
    },
});
