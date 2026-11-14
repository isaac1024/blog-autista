import assert from 'node:assert/strict';
import test from 'node:test';
import { LOADING, Lookups, MISSING, UNKNOWN, found, lookupIn, lookupText, standing } from '../../../assets/shared/lookup.js';

const PAGE = { id: 'a', title: 'First page', status: 'Published', published: true };
const DRAFT = { id: 'b', title: 'A draft', status: 'Draft', published: false };
const LABELS = { loading: 'Loading', missing: 'Not found', unknown: 'Could not be checked', offSite: 'Not on the site' };

test('an answer finds the id, misses it, or says nothing of it when there is none', () => {
    assert.deepEqual(lookupIn([DRAFT, PAGE], 'a'), { state: 'found', value: PAGE });
    assert.equal(lookupIn([DRAFT], 'a'), MISSING);
    assert.equal(lookupIn([], 'a'), MISSING);
    assert.equal(lookupIn(null, 'a'), UNKNOWN);
});

test('what is published stands as its status; anything else, off the site too', () => {
    assert.equal(standing(PAGE, LABELS.offSite), 'Published');
    assert.equal(standing(DRAFT, LABELS.offSite), 'Draft. Not on the site');
});

test('what was found is told by its name and where it stands', () => {
    assert.deepEqual(lookupText(found(DRAFT), (page) => page.title, LABELS), { name: 'A draft', standing: 'Draft. Not on the site' });
});

test('what was not found is told by what is known of it, standing nowhere', () => {
    const title = (page) => page.title;

    assert.deepEqual(lookupText(LOADING, title, LABELS), { name: 'Loading', standing: '' });
    assert.deepEqual(lookupText(MISSING, title, LABELS), { name: 'Not found', standing: '' });
    assert.deepEqual(lookupText(UNKNOWN, title, LABELS), { name: 'Could not be checked', standing: '' });
});

test('the states that hold nothing cannot be changed', () => {
    assert.equal(Object.isFrozen(LOADING) && Object.isFrozen(MISSING) && Object.isFrozen(UNKNOWN), true);
});

const ENDPOINT = new URL('https://blogautista.test/pages/lookup?except=c');

/**
 * Lookups of pages through an endpoint that answers as given, with the ids
 * it was asked about and how many times it told that it answered.
 */
const lookups = (t, respond, owner = new AbortController()) => {
    const asked = [];
    t.mock.method(globalThis, 'fetch', async (url) => {
        asked.push(url.searchParams.get('ids'));

        return respond();
    });
    const told = { answers: 0 };
    const pages = new Lookups(ENDPOINT, 'pages', owner.signal, () => { told.answers += 1; });

    return { pages, asked, told, owner };
};

test('ids are asked about once, together, and known as found or missing once answered', async (t) => {
    const { pages, asked, told } = lookups(t, () => Response.json({ pages: [PAGE] }));

    const answered = pages.ask(['a', 'b', 'a']);
    assert.equal(pages.of('a'), LOADING);
    await answered;

    assert.deepEqual(asked, ['a,b']);
    assert.equal(ENDPOINT.searchParams.has('ids'), false);
    assert.deepEqual(pages.of('a'), found(PAGE));
    assert.equal(pages.of('b'), MISSING);
    assert.equal(told.answers, 1);

    await pages.ask(['b', 'a']);
    await pages.ask([]);
    assert.deepEqual(asked, ['a,b']);
    assert.equal(told.answers, 1);
});

test('what is picked is known without asking, and an id never asked about is loading', async (t) => {
    const { pages, asked } = lookups(t, () => Response.json({ pages: [] }));

    pages.know(DRAFT);
    await pages.ask(['b']);

    assert.deepEqual(pages.of('b'), found(DRAFT));
    assert.deepEqual(asked, []);
    assert.equal(pages.of('z'), LOADING);
});

test('ids whose lookup failed are unknown, and not asked about again', async (t) => {
    const { pages, asked, told } = lookups(t, () => new Response('', { status: 503 }));

    await pages.ask(['a']);
    await pages.ask(['a']);

    assert.equal(pages.of('a'), UNKNOWN);
    assert.deepEqual(asked, ['a']);
    assert.equal(told.answers, 1);
});

test('an answer that comes once the owner is gone is for no one', async (t) => {
    const owner = new AbortController();
    let respond;
    const { pages, told } = lookups(t, () => new Promise((resolve) => { respond = resolve; }), owner);

    const answered = pages.ask(['a']);
    await Promise.resolve();
    owner.abort();
    respond(Response.json({ pages: [PAGE] }));
    await answered;

    assert.equal(pages.of('a'), LOADING);
    assert.equal(told.answers, 0);
});
