import assert from 'node:assert/strict';
import test from 'node:test';
import { grantConsent, hasConsent, revokeConsent } from '../../../assets/external/consent.js';

/** A storage of the browser, kept in memory, as the Storage of the page is. */
const memory = (initial = {}) => {
    const items = new Map(Object.entries(initial));

    return /** @type {Storage} */ (/** @type {unknown} */ ({
        getItem: (key) => (items.has(key) ? items.get(key) : null),
        setItem: (key, value) => { items.set(key, String(value)); },
        kept: () => Object.fromEntries(items),
    }));
};

test('agreeing for a provider keeps it, with when, for that provider alone', () => {
    const storage = memory();

    grantConsent(storage, 'youtube', new Date('2026-11-15T10:00:00Z'));
    grantConsent(storage, 'x', new Date('2026-11-15T11:00:00Z'));

    assert.equal(hasConsent(storage, 'youtube'), true);
    assert.equal(hasConsent(storage, 'x'), true);
    assert.equal(hasConsent(storage, 'bluesky'), false);
    assert.deepEqual(JSON.parse(storage.kept()['external-consent']), { youtube: '2026-11-15T10:00:00.000Z', x: '2026-11-15T11:00:00.000Z' });
});

test('taking it back forgets that provider and keeps the rest', () => {
    const storage = memory();
    grantConsent(storage, 'youtube', new Date('2026-11-15T10:00:00Z'));
    grantConsent(storage, 'x', new Date('2026-11-15T11:00:00Z'));

    revokeConsent(storage, 'youtube');

    assert.equal(hasConsent(storage, 'youtube'), false);
    assert.equal(hasConsent(storage, 'x'), true);
});

test('what is kept otherwise, or broken, is taken as never agreed', () => {
    for (const kept of ['no es JSON', 'null', '[1]', '"youtube"', JSON.stringify({ youtube: 1, x: '2026-11-15T10:00:00Z' })]) {
        const storage = memory({ 'external-consent': kept });

        assert.equal(hasConsent(storage, 'youtube'), false, kept);
    }

    assert.equal(hasConsent(memory({ 'external-consent': JSON.stringify({ x: '2026-11-15T10:00:00Z' }) }), 'x'), true);
});

test('a browser that keeps nothing, or refuses to, asks again and never fails', () => {
    const refusing = /** @type {Storage} */ (/** @type {unknown} */ ({
        getItem: () => { throw new Error('SecurityError'); },
        setItem: () => { throw new Error('QuotaExceededError'); },
    }));

    for (const storage of [undefined, refusing]) {
        assert.doesNotThrow(() => grantConsent(storage, 'youtube', new Date()));
        assert.doesNotThrow(() => revokeConsent(storage, 'youtube'));
        assert.equal(hasConsent(storage, 'youtube'), false);
    }
});
