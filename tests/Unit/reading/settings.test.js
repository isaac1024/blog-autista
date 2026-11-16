import assert from 'node:assert/strict';
import test from 'node:test';
import { applySettings, DEFAULTS, readSettings, resize, saveSettings, SIZES, THEMES } from '../../../assets/reading/settings.js';

/** A storage of the browser, kept in memory, as the Storage of the page is. */
const memory = (initial = {}) => {
    const items = new Map(Object.entries(initial));

    return /** @type {Storage} */ (/** @type {unknown} */ ({
        getItem: (key) => (items.has(key) ? items.get(key) : null),
        setItem: (key, value) => { items.set(key, String(value)); },
        kept: () => Object.fromEntries(items),
    }));
};

/** A storage the browser refuses, as in some private windows. */
const refusing = /** @type {Storage} */ (/** @type {unknown} */ ({
    getItem: () => { throw new Error('SecurityError'); },
    setItem: () => { throw new Error('QuotaExceededError'); },
}));

test('the sizes go from the browser\'s own up to the double, and the themes start with the system\'s', () => {
    assert.deepEqual([...SIZES], ['100', '115', '130', '150', '175', '200']);
    assert.deepEqual([...THEMES], ['system', 'light', 'dark']);
    assert.deepEqual({ ...DEFAULTS }, { size: '100', theme: 'system' });
    assert.equal(Object.isFrozen(SIZES) && Object.isFrozen(THEMES) && Object.isFrozen(DEFAULTS), true);
});

test('what is saved is read back, under one key, with nothing else', () => {
    const storage = memory();

    saveSettings(storage, /** @type {any} */ ({ size: '150', theme: 'dark', other: 'x' }));

    assert.deepEqual(readSettings(storage), { size: '150', theme: 'dark' });
    assert.deepEqual(storage.kept(), { reading: '{"size":"150","theme":"dark"}' });
});

test('nothing kept, or no storage, reads as the defaults', () => {
    assert.deepEqual(readSettings(memory()), { size: '100', theme: 'system' });
    assert.deepEqual(readSettings(undefined), { size: '100', theme: 'system' });
    assert.deepEqual(readSettings(refusing), { size: '100', theme: 'system' });
});

test('each setting kept is checked on its own: an unknown or broken one falls back alone', () => {
    const cases = [
        ['no es JSON', { size: '100', theme: 'system' }],
        ['null', { size: '100', theme: 'system' }],
        ['[1]', { size: '100', theme: 'system' }],
        ['"dark"', { size: '100', theme: 'system' }],
        [JSON.stringify({ size: '130', theme: 'sepia' }), { size: '130', theme: 'system' }],
        [JSON.stringify({ size: '120', theme: 'light' }), { size: '100', theme: 'light' }],
        [JSON.stringify({ size: 150, theme: ['dark'] }), { size: '100', theme: 'system' }],
        [JSON.stringify({ size: 'theme', theme: 'size' }), { size: '100', theme: 'system' }],
    ];

    for (const [kept, expected] of cases) {
        assert.deepEqual(readSettings(memory({ reading: kept })), expected, kept);
    }
});

test('a browser that refuses to keep them leaves this page as it is, without failing', () => {
    assert.doesNotThrow(() => saveSettings(refusing, { size: '115', theme: 'light' }));
    assert.doesNotThrow(() => saveSettings(undefined, { size: '115', theme: 'light' }));
});

test('the size goes one step at a time and stays within the sizes', () => {
    assert.equal(resize('100', 1), '115');
    assert.equal(resize('115', 1), '130');
    assert.equal(resize('130', -1), '115');
    assert.equal(resize('175', 1), '200');
    assert.equal(resize('200', 1), '200');
    assert.equal(resize('100', -1), '100');
    assert.equal(resize('unknown', 1), '115');
    assert.equal(resize('unknown', -1), '100');
});

test('the settings go on the root, and the defaults take them off', () => {
    const root = { dataset: /** @type {Record<string, string>} */ ({}) };

    applySettings(/** @type {any} */ (root), { size: '150', theme: 'dark' });
    assert.deepEqual({ ...root.dataset }, { theme: 'dark', textSize: '150' });

    applySettings(/** @type {any} */ (root), { size: '100', theme: 'system' });
    assert.deepEqual({ ...root.dataset }, {});

    applySettings(/** @type {any} */ (root), { size: '100', theme: 'light' });
    assert.deepEqual({ ...root.dataset }, { theme: 'light' });

    applySettings(/** @type {any} */ (root), { size: '115', theme: 'system' });
    assert.deepEqual({ ...root.dataset }, { textSize: '115' });
});
