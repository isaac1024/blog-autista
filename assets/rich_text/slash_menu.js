import { Extension } from '@tiptap/core';
import { Suggestion } from '@tiptap/suggestion';

/** Lists the kinds of block when "/" starts an empty line. */
export const SlashMenu = Extension.create({
    name: 'slashMenu',

    addOptions() {
        return { items: () => [], render: () => ({}) };
    },

    addProseMirrorPlugins() {
        return [Suggestion({
            editor: this.editor,
            char: '/',
            startOfLine: true,
            // Table cells and the source of a quote only hold text, so there is nothing to choose there.
            allow: ({ editor }) => !editor.isActive('table') && !editor.isActive('quoteSource'),
            items: ({ query }) => this.options.items(query),
            command: ({ editor, range, props }) => {
                editor.chain().focus().deleteRange(range).run();
                props.apply();
            },
            render: this.options.render,
        })];
    },
});
