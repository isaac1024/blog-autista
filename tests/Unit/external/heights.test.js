import assert from 'node:assert/strict';
import test from 'node:test';
import { forgetHeights, keepHeight, KEPT, keptHeight } from '../../../assets/external/heights.js';

const POST = 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b';

/** A storage of the browser, kept in memory, as the Storage of the page is. */
const memory = (initial = {}) => {
    const items = new Map(Object.entries(initial));

    return /** @type {Storage} */ (/** @type {unknown} */ ({
        getItem: (key) => (items.has(key) ? items.get(key) : null),
        setItem: (key, value) => { items.set(key, String(value)); },
        kept: () => Object.fromEntries(items),
    }));
};

test('a height kept is the one of that frame at that width, rounded, alone', () => {
    const storage = memory();

    keepHeight(storage, 'bluesky', POST, 671.6, 340);

    assert.equal(keptHeight(storage, 'bluesky', POST, 672), 340);
    assert.equal(keptHeight(storage, 'bluesky', POST, 375), null, 'another width wraps it otherwise');
    assert.equal(keptHeight(storage, 'bluesky', `${POST}c`, 672), null);
    assert.equal(keptHeight(storage, 'x', POST, 672), null);
    assert.deepEqual(JSON.parse(storage.kept()['external-heights']), { [`bluesky 672 ${POST}`]: 340 });
});

test('keeping a height again replaces it, as the latest', () => {
    const storage = memory();
    keepHeight(storage, 'bluesky', POST, 672, 340);
    keepHeight(storage, 'x', 'https://platform.twitter.com/embed/Tweet.html?id=20', 672, 225);

    keepHeight(storage, 'bluesky', POST, 672, 360);

    assert.equal(keptHeight(storage, 'bluesky', POST, 672), 360);
    assert.deepEqual(Object.keys(JSON.parse(storage.kept()['external-heights'])), [`x 672 https://platform.twitter.com/embed/Tweet.html?id=20`, `bluesky 672 ${POST}`]);
});

test('only the latest heights are kept', () => {
    const storage = memory();

    for (let index = 0; index <= KEPT; index += 1) {
        keepHeight(storage, 'x', `https://platform.twitter.com/embed/Tweet.html?id=${index}`, 672, 100 + index);
    }

    assert.equal(KEPT, 50);
    assert.equal(keptHeight(storage, 'x', 'https://platform.twitter.com/embed/Tweet.html?id=0', 672), null, 'the oldest goes');
    assert.equal(keptHeight(storage, 'x', 'https://platform.twitter.com/embed/Tweet.html?id=1', 672), 101);
    assert.equal(keptHeight(storage, 'x', `https://platform.twitter.com/embed/Tweet.html?id=${KEPT}`, 672), 100 + KEPT);
});

test('forgetting a provider forgets its heights alone', () => {
    const storage = memory();
    keepHeight(storage, 'x', POST, 672, 225);
    keepHeight(storage, 'xing', POST, 672, 200);
    keepHeight(storage, 'bluesky', POST, 672, 340);

    forgetHeights(storage, 'x');

    assert.equal(keptHeight(storage, 'x', POST, 672), null);
    assert.equal(keptHeight(storage, 'xing', POST, 672), 200, 'a provider whose name starts alike stays');
    assert.equal(keptHeight(storage, 'bluesky', POST, 672), 340);
});

test('what is kept but heights, or nothing kept, tells none', () => {
    assert.equal(keptHeight(memory({ 'external-heights': JSON.stringify({ [`bluesky 672 ${POST}`]: '340' }) }), 'bluesky', POST, 672), null);
    assert.equal(keptHeight(memory({ 'external-heights': '[340]' }), 'bluesky', POST, 672), null);
    assert.equal(keptHeight(memory({ 'external-heights': 'not json' }), 'bluesky', POST, 672), null);
    assert.equal(keptHeight(memory(), 'bluesky', POST, 672), null);
    assert.equal(keptHeight(undefined, 'bluesky', POST, 672), null);
});

test('a browser that refuses to keep them leaves heights untold, without failing', () => {
    const refusing = /** @type {Storage} */ (/** @type {unknown} */ ({ getItem: () => null, setItem: () => { throw new Error('QuotaExceededError'); } }));

    keepHeight(refusing, 'bluesky', POST, 672, 340);
    forgetHeights(undefined, 'bluesky');

    assert.equal(keptHeight(refusing, 'bluesky', POST, 672), null);
});
