import '@tiptap/core';
import type { Direction } from './block_movement.js';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        blockMover: {
            /** Moves the current block, or its section, past its neighbour. */
            moveBlock: (direction: Direction) => ReturnType;
        };
        quoteSource: {
            /** Takes the cursor to the source of the current quote. */
            focusQuoteSource: () => ReturnType;
        };
    }
}
