import assert from 'node:assert/strict';
import test from 'node:test';
import { Window } from 'happy-dom';
import { TargetLookups } from '../../../assets/rich_text/targets.js';
import { LOADING, found } from '../../../assets/shared/lookup.js';

const TARGETS = [
    { kind: 'page', type: 'page', searchUrl: '/api/pages/search', param: 'title', key: 'pages', lookupUrl: '/api/pages/lookup' },
    { kind: 'section', type: 'term', searchUrl: '/api/sections/search', param: 'name', key: 'terms', lookupUrl: '/api/terms/lookup' },
    { kind: 'tag', type: 'term', searchUrl: '/api/tags/search', param: 'name', key: 'terms', lookupUrl: '/api/terms/lookup' },
];

/** Lookups over a window whose fetch answers each lookup with every id asked, found. */
const lookups = () => {
    const window = new Window({ url: 'https://blog.test/' });
    globalThis.window = /** @type {any} */ (window);
    const asked = [];
    globalThis.fetch = /** @type {any} */ (async (url) => {
        const address = new URL(url);
        asked.push(address.pathname + address.search);
        const key = address.pathname.includes('terms') ? 'terms' : 'pages';

        return { ok: true, status: 200, json: async () => ({ [key]: address.searchParams.get('ids').split(',').map((id) => ({ id })) }) };
    });

    return { asked, targets: new TargetLookups(TARGETS, new AbortController().signal, () => {}) };
};

test('what is cited is asked about where its kind is looked up, kinds that share one asked once', async () => {
    const { asked, targets } = lookups();

    await targets.ask([{ type: 'page', id: 'p' }, { type: 'term', id: 's' }, { type: 'term', id: 't' }, { type: 'video', id: 'v' }]);
    await new Promise((resolve) => { setTimeout(resolve, 0); });

    assert.deepEqual(asked, ['/api/pages/lookup?ids=p', '/api/terms/lookup?ids=s%2Ct']);
    assert.deepEqual([targets.of('page', 'p'), targets.of('term', 't')], [found({ id: 'p' }), found({ id: 't' })]);
    // A kind looked up nowhere is never known, nor is an id of another kind.
    assert.deepEqual([targets.of('video', 'v'), targets.of('page', 's')], [LOADING, LOADING]);
});

test('what is picked is known without asking, only under its own kind', () => {
    const { asked, targets } = lookups();

    targets.know('term', { id: 's' });
    targets.know('video', { id: 'v' });

    assert.deepEqual([targets.of('term', 's'), targets.of('page', 's'), targets.of('video', 'v')], [found({ id: 's' }), LOADING, LOADING]);
    assert.deepEqual(asked, []);
});
