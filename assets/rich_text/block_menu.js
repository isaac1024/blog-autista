/*
 * What the menu of kinds of block decides, apart from drawing it: which
 * kinds answer what was typed after "/", what each key does, and where the
 * menu goes next to what opened it.
 */

/**
 * Lower case and without accents, so "parrafo" finds "Párrafo".
 *
 * @param {string} text
 */
const normalise = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

/**
 * Whether a kind of block answers what was typed after "/".
 *
 * @param {string} label
 * @param {string} query
 */
export const matches = (label, query) => normalise(label).includes(normalise(query));

/**
 * @typedef {{highlight: number} | {choose: number} | {close: true}} MenuAction
 */

/**
 * What a key does in the menu: arrows move through the kinds, round from one
 * end to the other; Home and End go to either end; Enter picks the one
 * highlighted; Escape closes. In the "/" menu Tab picks, as editors do;
 * leaving the button's menu with Tab must not change the block, so there it
 * only closes it. Null for a key the menu leaves alone.
 *
 * @param {string} key
 * @param {{mode: 'button'|'slash', index: number, count: number}} menu
 * @returns {MenuAction|null}
 */
export const menuAction = (key, { mode, index, count }) => {
    if (key === 'ArrowDown' || key === 'ArrowUp') {
        return { highlight: (index + (key === 'ArrowDown' ? 1 : -1) + count) % count };
    }

    if (key === 'Home' || key === 'End') {
        return { highlight: key === 'Home' ? 0 : count - 1 };
    }

    if (key === 'Enter' || (key === 'Tab' && mode === 'slash')) {
        return { choose: index };
    }

    if (key === 'Escape' || key === 'Tab') {
        return { close: true };
    }

    return null;
};

const GAP = 8;
const EDGE = 16;

/**
 * Where the menu goes: below what opened it when it fits, above otherwise,
 * and never past the sides of the screen.
 *
 * @param {{top: number, bottom: number, left: number}} anchor
 * @param {{width: number, height: number}} menu
 * @param {{width: number, height: number}} viewport
 */
export const menuPosition = (anchor, menu, viewport) => {
    const below = anchor.bottom + GAP + menu.height <= viewport.height;

    return {
        top: below ? anchor.bottom + GAP : Math.max(anchor.top - menu.height - GAP, GAP),
        left: Math.max(Math.min(anchor.left, viewport.width - menu.width - EDGE), EDGE),
    };
};
