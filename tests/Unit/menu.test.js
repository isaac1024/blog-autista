import assert from 'node:assert/strict';
import test from 'node:test';
import { pageHeader } from '../Markup/page_header.js';
import { mount, settle } from './support/dom.js';

let runs = 0;

/**
 * Opens a page with the header, whose menu in line takes the room given and
 * leaves the room given, and runs the script of the menu. happy-dom lays
 * nothing out, so the widths are the page's: the room the menu in line has
 * is the bar's, less what the button of the folded menu and the name of the
 * search take while they show. The browser tells a change of size through
 * its observers, which the page keeps.
 *
 * @param {object} [options]
 * @param {number} [options.needed] The width of the entries in a row.
 * @param {number} [options.room] The width the bar leaves the menu in line, with no button nor name of the search.
 * @param {string[]} [options.entries]
 */
const page = async ({ needed = 600, room = 700, entries } = {}) => {
    const { document } = await mount(pageHeader({ entries }), {});
    const sizes = { needed, room };
    /** @type {{callback: () => void, targets: Element[]}[]} */
    const observers = [];
    globalThis.ResizeObserver = class {
        /** @param {() => void} callback */
        constructor(callback) {
            this.observed = { callback, targets: /** @type {Element[]} */ ([]) };
            observers.push(this.observed);
        }

        /** @param {Element} target */
        observe(target) {
            this.observed.targets.push(target);
            // As the browser does, it tells the size it starts at.
            this.observed.callback();
        }
    };

    const header = /** @type {HTMLElement} */ (document.querySelector('[data-menu]'));
    const inline = /** @type {HTMLElement|null} */ (document.querySelector('[data-menu-inline]'));
    const folded = /** @type {HTMLDetailsElement|null} */ (document.querySelector('[data-menu-folded]'));

    if (inline !== null && folded !== null) {
        const list = /** @type {HTMLElement} */ (inline.querySelector('ul'));
        Object.defineProperty(list, 'scrollWidth', { configurable: true, get: () => sizes.needed });
        Object.defineProperty(inline, 'clientWidth', {
            configurable: true,
            // Hidden, it takes no room at all.
            get: () => (inline.classList.contains('hidden') ? 0 : sizes.room)
                - (folded.classList.contains('hidden') ? 0 : 44)
                - (header.dataset.menu === 'inline' ? 0 : 60),
        });
    }

    runs += 1;
    await import(`../../assets/menu.js?page=${runs}`);
    await settle();

    return {
        header,
        inline,
        folded,
        observed: () => observers.flatMap((observer) => observer.targets.map((target) => target === header ? 'header' : target.tagName)),
        /** The bar, or the text, takes another size, which the browser tells. */
        resize: (changes) => {
            Object.assign(sizes, changes);
            observers.forEach((observer) => observer.callback());
        },
        /** What shows: the menu in line, the folded one, and what the header says. */
        shown: () => ({
            inline: inline === null ? null : !inline.classList.contains('hidden'),
            folded: folded === null ? null : !folded.classList.contains('hidden'),
            menu: header.dataset.menu,
        }),
    };
};

test('shows the menu in line where its entries fit beside the bar, measured without the button nor the name of the search', async () => {
    // The entries fit only once the button and the name of the search leave their room.
    const { shown } = await page({ needed: 680, room: 700 });

    assert.deepEqual(shown(), { inline: true, folded: false, menu: 'inline' });
});

test('keeps the menu folded where its entries do not fit', async () => {
    const { shown } = await page({ needed: 701, room: 700 });

    assert.deepEqual(shown(), { inline: false, folded: true, menu: 'folded' });
});

test('folds the menu when the bar narrows or the text grows, and shows it in line again when it fits', async () => {
    const { shown, resize, folded } = await page({ needed: 600, room: 700 });

    resize({ room: 599 });
    assert.deepEqual(shown(), { inline: false, folded: true, menu: 'folded' });

    // The reader opens it, then widens the window: it shows in line, closed.
    /** @type {HTMLDetailsElement} */ (folded).open = true;
    resize({ room: 600 });
    assert.deepEqual(shown(), { inline: true, folded: false, menu: 'inline' });
    assert.equal(folded?.open, false);

    resize({ needed: 900 });
    assert.deepEqual(shown(), { inline: false, folded: true, menu: 'folded' });
});

test('leaves the folded menu open while it still does not fit', async () => {
    const { resize, folded } = await page({ needed: 701, room: 700 });

    /** @type {HTMLDetailsElement} */ (folded).open = true;
    resize({ room: 650 });

    assert.equal(folded?.open, true);
});

test('watches the bar and the entries in line, whose size follows the text', async () => {
    const { observed } = await page();

    assert.deepEqual(observed(), ['header', 'UL']);
});

test('leaves alone a header with no menu', async () => {
    const { shown, observed } = await page({ entries: [] });

    assert.deepEqual(shown(), { inline: null, folded: null, menu: '' });
    assert.deepEqual(observed(), []);
});
