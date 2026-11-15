import assert from 'node:assert/strict';
import test from 'node:test';
import { frameUrl, greeting, reportedHeight, reportsHeight } from '../../../assets/external/frames.js';

const ID = 'external-frame-1';

test('the frames of posts report their height; videos and audios keep their room', () => {
    assert.deepEqual(
        ['bluesky', 'x', 'mastodon', 'threads', 'youtube', 'tiktok', 'spotify'].map(reportsHeight),
        [true, true, true, true, false, false, false],
    );
});

test('Bluesky and X take the id they give back in the address of their frame', () => {
    assert.equal(frameUrl('bluesky', 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b', ID), 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b?id=external-frame-1');
    assert.equal(frameUrl('x', 'https://platform.twitter.com/embed/Tweet.html?id=20&dnt=true', ID), 'https://platform.twitter.com/embed/Tweet.html?id=20&dnt=true&embedId=external-frame-1');
    assert.equal(frameUrl('youtube', 'https://www.youtube-nocookie.com/embed/abc?hl=es', ID), 'https://www.youtube-nocookie.com/embed/abc?hl=es');
});

test('only Mastodon is asked for its height, with the id', () => {
    assert.deepEqual(greeting('mastodon', ID), { type: 'setHeight', id: ID });
    assert.equal(greeting('bluesky', ID), null);
    assert.equal(greeting('youtube', ID), null);
});

test('each provider tells its height in a message of its own', () => {
    assert.equal(reportedHeight('bluesky', { id: ID, height: 412.4 }, ID), 413);
    assert.equal(reportedHeight('mastodon', { type: 'setHeight', id: ID, height: 300 }, ID), 300);
    assert.equal(reportedHeight('x', { 'twttr.embed': { method: 'twttr.private.resize', id: ID, params: [{ height: 631, width: 550 }] } }, ID), 631);
    assert.equal(reportedHeight('threads', 520, ID), 520);
    assert.equal(reportedHeight('threads', '520', ID), 520);
});

test('a message of another frame, of another kind, or without a height tells none', () => {
    const none = [
        ['bluesky', { id: 'other', height: 400 }],
        ['bluesky', { id: ID }],
        ['bluesky', null],
        ['mastodon', { type: 'other', id: ID, height: 300 }],
        ['mastodon', { type: 'setHeight', id: 'other', height: 300 }],
        ['x', { 'twttr.embed': { method: 'twttr.private.rendered', id: ID, params: [{ height: 631 }] } }],
        ['x', { 'twttr.embed': { method: 'twttr.private.resize', id: 'other', params: [{ height: 631 }] } }],
        ['x', { 'twttr.embed': { method: 'twttr.private.resize', id: ID, params: 'none' } }],
        ['x', { other: {} }],
        ['x', 'twttr'],
        ['threads', { height: 520 }],
        ['threads', 'tall'],
        ['threads', 0],
        ['threads', -5],
        ['threads', 'Infinity'],
        ['threads', true],
        ['threads', [520]],
        ['youtube', 400],
        ['youtube', { id: ID, height: 400 }],
    ];

    for (const [provider, data] of none) {
        assert.equal(reportedHeight(provider, data, ID), null, JSON.stringify([provider, data]));
    }
});
