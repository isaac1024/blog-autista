import { Node, mergeAttributes } from '@tiptap/core';
import { TextSelection } from '@tiptap/pm/state';

/**
 * The quote around the cursor, with where it starts, or null outside of one.
 *
 * @param {import('@tiptap/pm/state').EditorState} state
 */
const currentQuote = (state) => {
    const { $from } = state.selection;

    for (let depth = $from.depth; depth > 0; depth -= 1) {
        if ($from.node(depth).type.name === 'blockquote') {
            return { node: $from.node(depth), pos: $from.before(depth) };
        }
    }

    return null;
};

/**
 * Where a quote comes from, written as plain text at its end. Enter leaves
 * the quote for a new paragraph; Backspace on an empty source removes it.
 */
export const QuoteSource = Node.create({
    name: 'quoteSource',
    content: 'text*',
    marks: '',
    defining: true,

    addOptions() {
        return { hint: '' };
    },

    parseHTML() {
        return [{ tag: 'div[data-quote-source]' }];
    },

    // Tiptap's Placeholder only marks top-level blocks, so the source carries its own hint.
    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-quote-source': '', 'data-placeholder': this.options.hint }), 0];
    },

    addCommands() {
        return {
            /** Takes the cursor to the source of the current quote, adding it if it has none. */
            focusQuoteSource: () => ({ tr, state, dispatch }) => {
                const quote = currentQuote(state);

                if (quote === null) {
                    return false;
                }

                if (dispatch) {
                    const { node, pos } = quote;
                    let end = pos + node.nodeSize - 1;

                    if (node.lastChild?.type.name !== this.name) {
                        tr.insert(end, this.type.create());
                        end += 2;
                    }

                    tr.setSelection(TextSelection.create(tr.doc, end - 1));
                    tr.scrollIntoView();
                }

                return true;
            },
        };
    },

    addKeyboardShortcuts() {
        const inSource = () => this.editor.state.selection.$from.parent.type.name === this.name;

        return {
            Enter: () => {
                if (!inSource()) {
                    return false;
                }

                const quote = currentQuote(this.editor.state);

                if (quote === null) {
                    return false;
                }

                const { pos, node } = quote;
                const after = pos + node.nodeSize;

                return this.editor.chain()
                    .insertContentAt(after, { type: 'paragraph' })
                    .setTextSelection(after + 1)
                    .scrollIntoView()
                    .run();
            },
            Backspace: () => {
                const { $from, empty } = this.editor.state.selection;

                if (!inSource() || !empty || $from.parentOffset > 0) {
                    return false;
                }

                // At the start of a source with text, it stays a source rather than joining the quote.
                if ($from.parent.content.size > 0) {
                    return true;
                }

                const from = $from.before();

                return this.editor.chain()
                    .deleteRange({ from, to: from + $from.parent.nodeSize })
                    .setTextSelection(from - 1)
                    .run();
            },
        };
    },
});
