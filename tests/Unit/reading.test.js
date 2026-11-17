import assert from 'node:assert/strict';
import test from 'node:test';
import { readingSettings } from '../Markup/reading_settings.js';
import { assertFocused, mount, settle } from './support/dom.js';

let runs = 0;

/**
 * Opens a page with the reading settings in its header and what the browser
 * keeps, runs the script of the head, then the one of the panel, as the
 * page loads them.
 *
 * @param {object} [options]
 * @param {string|null} [options.kept] What the browser keeps, as written.
 * @param {string} [options.lang] The language of the page.
 * @param {boolean} [options.storage] Whether the browser lends its storage.
 */
const page = async ({ kept = null, lang = 'es', storage = true } = {}) => {
    const { document, window } = await mount(readingSettings(), {});
    document.documentElement.lang = lang;

    if (kept !== null) {
        window.localStorage.setItem('reading', kept);
    }

    if (!storage) {
        Object.defineProperty(window, 'localStorage', { configurable: true, get: () => { throw new Error('SecurityError'); } });
    }

    runs += 1;
    await import(`../../assets/reading_apply.js?page=${runs}`);
    const applied = { ...document.documentElement.dataset };
    await import(`../../assets/reading.js?page=${runs}`);
    await settle();

    const root = document.documentElement;
    const toggle = /** @type {HTMLButtonElement} */ (document.querySelector('[data-reading-toggle]'));
    const panel = /** @type {HTMLElement} */ (document.querySelector('[data-reading-panel]'));
    const smaller = /** @type {HTMLButtonElement} */ (document.querySelector('[data-reading-size="smaller"]'));
    const bigger = /** @type {HTMLButtonElement} */ (document.querySelector('[data-reading-size="bigger"]'));
    const radio = (value) => /** @type {HTMLInputElement} */ (document.querySelector(`input[value="${value}"]`));

    return {
        window,
        document,
        applied,
        toggle,
        panel,
        smaller,
        bigger,
        /** The settings on the root of the page, as the stylesheet reads them. */
        root: () => ({ ...root.dataset }),
        /** What the panel shows: the size, which buttons work, and the theme checked. */
        shown: () => ({
            size: document.querySelector('[data-reading-size-value]')?.textContent,
            smaller: !smaller.disabled,
            bigger: !bigger.disabled,
            theme: ['system', 'light', 'dark'].filter((value) => radio(value).checked),
        }),
        kept: () => window.localStorage.getItem('reading'),
        /** Picks a theme as the reader does, by clicking its label. */
        pick: (value) => {
            radio(value).closest('label')?.click();
        },
    };
};

test('the settings kept go on the root of the page before the panel runs, as they were kept', async () => {
    const { applied, root } = await page({ kept: JSON.stringify({ size: '150', theme: 'dark' }) });

    assert.deepEqual(applied, { textSize: '150', theme: 'dark' });
    assert.deepEqual(root(), { textSize: '150', theme: 'dark' });
});

test('the head sets only what was kept as text, and nothing when nothing, or nothing readable, was kept', async () => {
    for (const [kept, expected] of [
        [null, {}],
        ['no es JSON', {}],
        ['null', {}],
        [JSON.stringify({ size: 130 }), {}],
        [JSON.stringify({ theme: 'light' }), { theme: 'light' }],
        [JSON.stringify({ size: '115' }), { textSize: '115' }],
    ]) {
        const { applied } = await page({ kept });

        assert.deepEqual(applied, expected, String(kept));
    }
});

test('the script of the head marks the page as one that runs scripts, which shows the button, and the panel stays closed', async () => {
    const { document, toggle, panel } = await page();

    assert.equal(document.documentElement.dataset.scripted, '');
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    assert.equal(panel.hidden, true);
});

test('the panel shows the settings kept: the size in the language of the page and the theme checked', async () => {
    const { shown } = await page({ kept: JSON.stringify({ size: '130', theme: 'light' }) });

    assert.deepEqual(shown(), { size: '130\u00a0%', smaller: true, bigger: true, theme: ['light'] });
});

test('the size reads as the language of the page writes a percent', async () => {
    const { shown } = await page({ lang: 'en', kept: JSON.stringify({ size: '115' }) });

    assert.equal(shown().size, '115%');
});

test('the button opens and closes the panel, and says so', async () => {
    const { toggle, panel } = await page();

    toggle.click();
    assert.deepEqual([toggle.getAttribute('aria-expanded'), panel.hidden], ['true', false]);

    toggle.click();
    assert.deepEqual([toggle.getAttribute('aria-expanded'), panel.hidden], ['false', true]);
});

test('bigger and smaller change the text at once, one step at a time, and keep it', async () => {
    const { bigger, smaller, root, shown, kept } = await page();

    assert.deepEqual(shown(), { size: '100\u00a0%', smaller: false, bigger: true, theme: ['system'] });

    bigger.click();
    bigger.click();
    assert.deepEqual(root(), { textSize: '130' });
    assert.equal(shown().size, '130\u00a0%');
    assert.deepEqual(JSON.parse(String(kept())), { size: '130', theme: 'system' });

    smaller.click();
    assert.deepEqual(root(), { textSize: '115' });

    smaller.click();
    assert.deepEqual(root(), {});
    assert.deepEqual(shown(), { size: '100\u00a0%', smaller: false, bigger: true, theme: ['system'] });
});

test('the biggest size stops the bigger button', async () => {
    const { bigger, root, shown } = await page({ kept: JSON.stringify({ size: '175' }) });

    bigger.click();

    assert.deepEqual(root(), { textSize: '200' });
    assert.deepEqual(shown(), { size: '200\u00a0%', smaller: true, bigger: false, theme: ['system'] });
});

test('picking a theme changes the page at once and keeps it; the system\'s takes it off', async () => {
    const { pick, root, shown, kept } = await page();

    pick('dark');
    assert.deepEqual(root(), { theme: 'dark' });
    assert.deepEqual(shown().theme, ['dark']);
    assert.deepEqual(JSON.parse(String(kept())), { size: '100', theme: 'dark' });

    pick('system');
    assert.deepEqual(root(), {});
    assert.deepEqual(JSON.parse(String(kept())), { size: '100', theme: 'system' });
});

test('Escape closes the panel from inside it and puts the focus back on the button', async () => {
    const { window, toggle, panel, bigger } = await page();
    toggle.click();
    bigger.focus();

    bigger.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

    assert.equal(panel.hidden, true);
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    assertFocused(toggle);
});

test('other keys, or Escape with the panel closed, leave the focus where it is', async () => {
    const { window, toggle, panel, bigger } = await page();
    toggle.click();
    bigger.focus();

    bigger.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    assert.equal(panel.hidden, false);

    toggle.click();
    bigger.focus();
    bigger.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    assertFocused(bigger);
});

test('a click elsewhere on the page closes the panel; one inside it does not', async () => {
    const { document, toggle, panel, bigger } = await page();
    toggle.click();

    bigger.click();
    assert.equal(panel.hidden, false);

    /** @type {HTMLElement} */ (document.querySelector('[data-elsewhere]')).click();
    assert.deepEqual([toggle.getAttribute('aria-expanded'), panel.hidden], ['false', true]);
});

test('a browser that refuses storage still changes the page, and reads it as the system says', async () => {
    const { applied, bigger, pick, root } = await page({ storage: false });

    assert.deepEqual(applied, {});

    bigger.click();
    pick('light');
    assert.deepEqual(root(), { textSize: '115', theme: 'light' });
});
