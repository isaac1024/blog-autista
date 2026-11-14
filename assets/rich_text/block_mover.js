import { Extension } from '@tiptap/core';
import { TextSelection } from '@tiptap/pm/state';
import { movePlan, onHeading } from './block_movement.js';

/** @typedef {import('./block_movement.js').Direction} Direction */

/** Moves the current block, or its section, past its neighbour. */
export const BlockMover = Extension.create({
    name: 'blockMover',

    addOptions() {
        return {
            onMove: /** @type {(direction: Direction, section: boolean) => void} */ (() => {}),
        };
    },

    addCommands() {
        return {
            moveBlock: (direction) => ({ tr, dispatch }) => {
                const { $from, from } = tr.selection;
                const plan = $from.depth > 0 ? movePlan(tr.doc, $from.index(0), direction) : null;

                if (plan === null) {
                    return false;
                }

                if (dispatch) {
                    const { blocks, first, second, moving } = plan;
                    const firstFrom = blocks[first[0]].from;
                    const middle = blocks[second[0]].from;
                    const secondTo = blocks[second[1] - 1].to;
                    const swapped = tr.doc.slice(middle, secondTo).content.append(tr.doc.slice(firstFrom, middle).content);
                    // The cursor travels with what moves.
                    const cursor = moving === 'first' ? from + (secondTo - middle) : from - (middle - firstFrom);

                    tr.replaceWith(firstFrom, secondTo, swapped);
                    tr.setSelection(TextSelection.near(tr.doc.resolve(cursor)));
                    tr.scrollIntoView();
                }

                return true;
            },
        };
    },

    addKeyboardShortcuts() {
        /** @param {Direction} direction */
        const move = (direction) => {
            const section = onHeading(this.editor.state);

            return this.editor.commands.moveBlock(direction) && (this.options.onMove(direction, section), true);
        };

        return {
            'Mod-Shift-ArrowUp': () => move(-1),
            'Mod-Shift-ArrowDown': () => move(1),
        };
    },
});
