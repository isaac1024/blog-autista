import assert from 'node:assert/strict';
import test from 'node:test';
import { fetchJson } from '../../../assets/shared/http.js';

test('fetchJson asks for JSON and returns its decoded body', async (t) => {
    let received;
    t.mock.method(globalThis, 'fetch', async (...args) => {
        received = args;

        return new Response('{"pages":[]}', { status: 200 });
    });
    const url = new URL('https://blogautista.test/pages');
    const controller = new AbortController();

    assert.deepEqual(await fetchJson(url, controller.signal), { pages: [] });
    assert.equal(received[0], url);
    assert.equal(received[1].headers.Accept, 'application/json');
    assert.equal(received[1].signal, controller.signal);
});

test('fetchJson refuses an HTTP error', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => new Response('', { status: 503 }));

    await assert.rejects(fetchJson(new URL('https://blogautista.test/pages')), /503/);
});
