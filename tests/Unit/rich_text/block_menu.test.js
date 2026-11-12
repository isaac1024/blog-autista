import assert from 'node:assert/strict';
import test from 'node:test';
import { matches, menuAction, menuPosition } from '../../../assets/rich_text/block_menu.js';

const slash = (index, count = 4) => ({ mode: 'slash', index, count });
const button = (index, count = 4) => ({ mode: 'button', index, count });

test('a kind answers what was typed, whatever its case or accents', () => {
    assert.equal(matches('Párrafo', 'parr'), true);
    assert.equal(matches('Título 2', 'TITULO'), true);
    assert.equal(matches('Cita', 'lista'), false);
    assert.equal(matches('Cita', ''), true);
});

test('the arrows move through the kinds, round from one end to the other', () => {
    assert.deepEqual(menuAction('ArrowDown', slash(1)), { highlight: 2 });
    assert.deepEqual(menuAction('ArrowDown', slash(3)), { highlight: 0 });
    assert.deepEqual(menuAction('ArrowUp', slash(0)), { highlight: 3 });
});

test('Home and End go to either end', () => {
    assert.deepEqual(menuAction('Home', button(2)), { highlight: 0 });
    assert.deepEqual(menuAction('End', button(0)), { highlight: 3 });
});

test('Enter picks the kind highlighted, and so does Tab in the "/" menu only', () => {
    assert.deepEqual(menuAction('Enter', button(2)), { choose: 2 });
    assert.deepEqual(menuAction('Tab', slash(1)), { choose: 1 });
    assert.deepEqual(menuAction('Tab', button(1)), { close: true });
});

test('Escape closes, and any other key is left to the text', () => {
    assert.deepEqual(menuAction('Escape', slash(0)), { close: true });
    assert.equal(menuAction('a', slash(0)), null);
    assert.equal(menuAction('ArrowLeft', button(0)), null);
});

test('the menu goes below what opened it when it fits, above otherwise', () => {
    const menu = { width: 200, height: 300 };
    const viewport = { width: 1000, height: 800 };

    assert.deepEqual(menuPosition({ top: 80, bottom: 100, left: 40 }, menu, viewport), { top: 108, left: 40 });
    assert.deepEqual(menuPosition({ top: 600, bottom: 620, left: 40 }, menu, viewport), { top: 292, left: 40 });
    // Nowhere to fit above either: it keeps clear of the top of the screen.
    assert.deepEqual(menuPosition({ top: 200, bottom: 220, left: 40 }, { width: 200, height: 700 }, viewport), { top: 8, left: 40 });
});

test('the menu never runs past the sides of the screen', () => {
    const menu = { width: 200, height: 100 };
    const viewport = { width: 400, height: 800 };

    assert.deepEqual(menuPosition({ top: 0, bottom: 20, left: 350 }, menu, viewport).left, 184);
    assert.deepEqual(menuPosition({ top: 0, bottom: 20, left: 2 }, menu, viewport).left, 16);
});

test('the menu stays below when it fits exactly, and keeps its gap to what opened it', () => {
    const menu = { width: 200, height: 300 };

    // 500 + 8 + 300 = 808: just fits a screen of 808, not one of 805.
    assert.equal(menuPosition({ top: 480, bottom: 500, left: 40 }, menu, { width: 1000, height: 808 }).top, 508);
    assert.equal(menuPosition({ top: 480, bottom: 500, left: 40 }, menu, { width: 1000, height: 805 }).top, 172);
});
