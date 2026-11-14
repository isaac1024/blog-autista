import { Extension } from '@tiptap/core';

/**
 * Alt+F10, the usual shortcut of accessible editors to reach their
 * controls: in every editor, a single line of text included.
 */
export const ControlsShortcut = Extension.create({
    name: 'controlsShortcut',

    addOptions() {
        return {
            onRequest: () => {},
        };
    },

    addKeyboardShortcuts() {
        return {
            'Alt-F10': () => (this.options.onRequest(), true),
        };
    },
});
