import assert from 'node:assert/strict';
import test from 'node:test';
import { externalEmbed } from '../Markup/external_embed.js';
import { assertFocused, mount, settle } from './support/dom.js';

const CONSENT = 'external-consent';
let runs = 0;

/**
 * Runs the script of a page that embeds resources, on the given markup and
 * with what the browser keeps, and records the frames it is told to load
 * near the screen, and the timers it sets.
 */
const page = async ({ markup = externalEmbed(), kept = null, observer = true } = {}) => {
    const { document, window } = await mount(markup, {});

    if (kept !== null) {
        window.localStorage.setItem(CONSENT, JSON.stringify(kept));
    }

    const observed = [];
    const watching = [];
    window.IntersectionObserver = observer
        ? class {
            constructor(callback, options) {
                this.callback = callback;
                watching.push(options);
            }

            observe(element) {
                observed.push({ element, near: () => this.callback([{ target: element, isIntersecting: true }], this), far: () => this.callback([{ target: element, isIntersecting: false }], this) });
            }

            unobserve(element) {
                observed.splice(observed.findIndex((entry) => entry.element === element), 1);
            }
        }
        : undefined;

    if (!observer) {
        delete window.IntersectionObserver;
    }

    const timers = [];
    window.setTimeout = (callback, delay) => {
        timers.push({ callback, delay });

        return timers.length;
    };

    runs += 1;
    await import(`../../assets/external.js?page=${runs}`);
    await settle();

    const embeds = [...document.querySelectorAll('[data-external-embed]')];
    const part = (index, selector) => embeds[index].querySelector(selector);

    return {
        document,
        window,
        observed,
        watching,
        timers,
        part,
        iframe: (index = 0) => part(index, 'iframe'),
        // Never the frame itself against null: a failing assertion would
        // print the node, and the whole window behind it, with it.
        loaded: (index = 0) => part(index, 'iframe') !== null,
        consent: () => JSON.parse(window.localStorage.getItem(CONSENT) ?? '{}'),
        click: async (index, selector) => {
            part(index, selector).click();
            await settle();
        },
    };
};

test('without consent, the facade stays and its line offers to load it', async () => {
    const { loaded, part, observed } = await page();

    assert.equal(part(0, '[data-external-load]').hidden, false);
    assert.equal(part(0, '[data-external-forget]').hidden, true);
    assert.equal(part(0, '[data-external-notice]').hidden, true);
    assert.equal(loaded(), false);
    assert.equal(observed.length, 0);
});

test('loading asks first, in a notice that takes the focus, and cancelling, or Escape, goes back', async () => {
    const { loaded, part, click, window } = await page();

    await click(0, '[data-external-load]');
    assert.equal(part(0, '[data-external-notice]').hidden, false);
    assertFocused(part(0, '[data-external-notice-title]'));

    await click(0, '[data-external-cancel]');
    assert.equal(part(0, '[data-external-notice]').hidden, true);
    assertFocused(part(0, '[data-external-load]'));

    await click(0, '[data-external-load]');
    part(0, '[data-external-notice]').dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    assert.equal(part(0, '[data-external-notice]').hidden, true);
    part(0, '[data-external-notice]').hidden = false;
    part(0, '[data-external-notice]').dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    assert.equal(part(0, '[data-external-notice]').hidden, false);
    assert.equal(loaded(), false);
});

test('the facade of a video plays it once the script runs, asking first, and cancelling goes back to it', async () => {
    const { loaded, part, click } = await page();

    assert.equal(part(0, '[data-external-play]').hidden, false);
    await click(0, '[data-external-play]');
    assert.equal(part(0, '[data-external-notice]').hidden, false);
    assertFocused(part(0, '[data-external-notice-title]'));
    assert.equal(loaded(), false);

    await click(0, '[data-external-cancel]');
    assertFocused(part(0, '[data-external-play]'));

    await click(0, '[data-external-play]');
    await click(0, '[data-external-accept]');
    assert.equal(loaded(), true);
});

test('with consent, a frame that never painted plays again from its facade, one at a time', async () => {
    const { loaded, click, timers, part, document } = await page({ kept: { youtube: '2026-11-15T10:00:00.000Z' }, observer: false });
    timers[0].callback();
    assert.equal(loaded(), false);

    await click(0, '[data-external-play]');
    await click(0, '[data-external-play]');

    assert.equal(document.querySelectorAll('iframe').length, 1);
    assert.equal(part(0, '[data-external-notice]').hidden, true, 'agreed, it asks nothing');
});

test('the facade of a post is what it says, and has nothing to play', async () => {
    const markup = externalEmbed({ provider: 'bluesky', src: 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b', origin: 'https://embed.bsky.app', space: 'reported' });
    const { loaded, part, click } = await page({ markup });

    assert.equal(part(0, '[data-external-play]') === null, true);
    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');
    assert.equal(loaded(), true);
});

test('agreeing keeps it for the provider and loads its frame, unseen until it paints, then in place of the facade', async () => {
    const { part, click, iframe, consent, window } = await page();

    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');

    assert.deepEqual(Object.keys(consent()), ['youtube']);
    const frame = iframe();
    assert.equal(frame.src, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?hl=es');
    assert.equal(frame.title, 'Un vídeo (YouTube)');
    assert.equal(frame.getAttribute('referrerpolicy'), 'strict-origin-when-cross-origin');
    assert.equal(frame.getAttribute('sandbox'), 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation');
    assert.equal(frame.getAttribute('allow'), 'encrypted-media; fullscreen; picture-in-picture');
    assert.equal(frame.allowFullscreen, true);
    assert.equal(frame.hasAttribute('height'), false, 'it keeps the room of its facade');
    assert.equal(part(0, '[data-external-notice]').hidden, true);
    assert.equal(part(0, '[data-external-facade]').hidden, false, 'the facade stays until the frame paints');
    assert.equal('shown' in frame.dataset, false);
    assert.equal([part(0, '[data-external-load]').hidden, part(0, '[data-external-forget]').hidden].join(), 'true,false');
    assertFocused(part(0, '[data-external-forget]'));
    const asked = [];
    Object.defineProperty(frame, 'contentWindow', { configurable: true, value: { postMessage: (message) => asked.push(message) } });

    frame.dispatchEvent(new window.Event('load'));

    assert.equal('shown' in frame.dataset, true);
    assert.deepEqual(asked, [], 'YouTube is asked nothing');
    assert.equal(part(0, '[data-external-facade]').hidden, true);
});

test('agreeing loads the other frames of the provider as they near the screen, and those of others not', async () => {
    const markup = externalEmbed({ index: 1 }) + externalEmbed({ index: 2 }) + externalEmbed({ index: 3, provider: 'spotify', src: 'https://open.spotify.com/embed/episode/a', origin: 'https://open.spotify.com', space: 'compact' });
    const { loaded, click, observed, part } = await page({ markup });

    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');

    assert.equal(loaded(0), true);
    assert.equal(loaded(1), false);
    assert.equal(observed.length, 1, 'the second YouTube frame waits to be near');
    assert.equal(part(1, '[data-external-forget]').hidden, false);
    assert.equal(part(2, '[data-external-load]').hidden, false, 'Spotify still asks');
    observed[0].far();
    assert.equal(loaded(1), false);
    observed[0].near();
    assert.equal(loaded(1), true);
    assert.equal(loaded(2), false);
});

test('with consent kept, a frame loads near the screen, once, and at once where nearness cannot be told', async () => {
    const kept = { youtube: '2026-11-15T10:00:00.000Z' };
    const near = await page({ kept });

    assert.equal(near.loaded(), false);
    assert.equal(near.part(0, '[data-external-forget]').hidden, false);
    assert.deepEqual(near.watching, [{ rootMargin: '200% 0px' }], 'it loads two screens ahead');
    near.observed[0].near();
    assert.equal(near.loaded(), true);
    assert.equal(near.observed.length, 0);

    const blind = await page({ kept, observer: false });
    assert.equal(blind.loaded(), true);
});

test('consent of one provider loads nothing of another', async () => {
    const { loaded, observed, part } = await page({ kept: { x: '2026-11-15T10:00:00.000Z' } });

    assert.equal(loaded(), false);
    assert.equal(observed.length, 0);
    assert.equal(part(0, '[data-external-load]').hidden, false);
});

test('a frame that never paints in time goes, and the facade stays', async () => {
    const { loaded, click, iframe, timers, part } = await page();
    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');
    const frame = iframe();

    const [{ callback, delay }] = timers;
    assert.equal(delay, 10_000);
    callback();

    assert.equal(frame.isConnected, false);
    assert.equal(loaded(), false);
    assert.equal(part(0, '[data-external-facade]').hidden, false);
});

test('a frame that painted in time stays when the time is up', async () => {
    const { loaded, click, iframe, timers, window } = await page();
    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');
    iframe().dispatchEvent(new window.Event('load'));

    timers[0].callback();

    assert.equal(loaded(), true);
});

test('a frame taken away and loaded again is not taken away by the time of the first', async () => {
    const { loaded, click, iframe, timers, window, part } = await page();
    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');
    const first = iframe();
    await click(0, '[data-external-forget]');
    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');

    first.dispatchEvent(new window.Event('load'));
    assert.equal(part(0, '[data-external-facade]').hidden, false, 'the first frame, gone, shows nothing');
    timers[0].callback();

    assert.equal(loaded(), true);
});

test('a post is shown its frame tells its height, from its origin alone, as tall as it says', async () => {
    const markup = externalEmbed({ provider: 'bluesky', src: 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b', origin: 'https://embed.bsky.app', space: 'reported' });
    const { click, iframe, window, part } = await page({ markup });
    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');
    const frame = iframe();
    // The page of the frame, which a test page never loads.
    const framed = { postMessage: () => {} };
    Object.defineProperty(frame, 'contentWindow', { configurable: true, value: framed });
    assert.equal(frame.src, 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b?id=external-frame-1');
    assert.equal(frame.getAttribute('height'), String(part(0, '[data-external-facade]').offsetHeight));

    frame.dispatchEvent(new window.Event('load'));
    assert.equal('shown' in frame.dataset, false, 'loaded, it still has to tell its height');

    const tell = (data, origin, source = framed) => {
        const event = new window.MessageEvent('message', { data, origin });
        Object.defineProperty(event, 'source', { value: source });
        window.dispatchEvent(event);
    };
    tell({ id: 'external-frame-1', height: 400 }, 'https://evil.example');
    tell({ id: 'external-frame-1', height: 400 }, 'https://embed.bsky.app', window);
    tell({ id: 'external-frame-2', height: 400 }, 'https://embed.bsky.app');
    assert.equal('shown' in frame.dataset, false);

    tell({ id: 'external-frame-1', height: 400 }, 'https://embed.bsky.app');

    assert.equal(frame.getAttribute('height'), '400');
    assert.equal('shown' in frame.dataset, true);
    assert.equal(part(0, '[data-external-facade]').hidden, true);
});

test('a post is told apart from the frames that are not loaded', async () => {
    const markup = externalEmbed({ index: 1 }) + externalEmbed({ index: 2, provider: 'bluesky', src: 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b', origin: 'https://embed.bsky.app', space: 'reported' });
    const { click, iframe, window } = await page({ markup });
    await click(1, '[data-external-load]');
    await click(1, '[data-external-accept]');
    const frame = iframe(1);
    const framed = { postMessage: () => {} };
    Object.defineProperty(frame, 'contentWindow', { configurable: true, value: framed });

    const event = new window.MessageEvent('message', { data: { id: 'external-frame-2', height: 300 }, origin: 'https://embed.bsky.app' });
    Object.defineProperty(event, 'source', { value: framed });
    window.dispatchEvent(event);

    assert.equal(frame.getAttribute('height'), '300');
});

test('a frame of Mastodon is asked for its height once it loads, at its origin', async () => {
    const markup = externalEmbed({ provider: 'mastodon', src: 'https://mastodon.social/@a/1/embed', origin: 'https://mastodon.social', space: 'reported' });
    const { click, iframe, window } = await page({ markup });
    await click(0, '[data-external-load]');
    await click(0, '[data-external-accept]');
    const frame = iframe();
    const asked = [];
    Object.defineProperty(frame, 'contentWindow', { configurable: true, value: { postMessage: (message, origin) => asked.push([message, origin]) } });

    frame.dispatchEvent(new window.Event('load'));

    assert.deepEqual(asked, [[{ type: 'setHeight', id: 'external-frame-1' }, 'https://mastodon.social']]);
});

test('taking consent back unloads every frame of the provider and offers to load them again', async () => {
    const markup = externalEmbed({ index: 1 }) + externalEmbed({ index: 2 });
    const { loaded, click, consent, part, observed } = await page({ markup, kept: { youtube: '2026-11-15T10:00:00.000Z', x: '2026-11-15T10:00:00.000Z' } });
    observed[0].near();

    await click(0, '[data-external-forget]');

    assert.deepEqual(Object.keys(consent()), ['x']);
    assert.equal(loaded(0), false);
    assert.equal(observed.length, 0, 'none waits to load any longer');
    for (const index of [0, 1]) {
        assert.equal([part(index, '[data-external-load]').hidden, part(index, '[data-external-forget]').hidden, part(index, '[data-external-facade]').hidden].join(), 'false,true,false');
    }
    assertFocused(part(0, '[data-external-load]'));
});

test('taking consent back leaves the frames of other providers, with nearness told or not', async () => {
    const markup = externalEmbed({ index: 1 }) + externalEmbed({ index: 2, provider: 'spotify', src: 'https://open.spotify.com/embed/episode/a', origin: 'https://open.spotify.com', space: 'compact' });
    const { loaded, click, part } = await page({ markup, kept: { youtube: '2026-11-15T10:00:00.000Z', spotify: '2026-11-15T10:00:00.000Z' }, observer: false });

    await click(0, '[data-external-forget]');

    assert.equal(loaded(0), false);
    assert.equal(loaded(1), true);
    assert.equal(part(1, '[data-external-forget]').hidden, false);
});

test('what only looks like a facade, outside the markup of the page, is left alone', async () => {
    const { loaded } = await page({ markup: '<svg data-external-embed data-provider="youtube"></svg>' + externalEmbed(), kept: { youtube: '2026-11-15T10:00:00.000Z' }, observer: false });

    // The first of the page is the drawing; the facade comes second.
    assert.equal(loaded(1), true);
});

test('a facade missing one of its parts is told, as the template always writes them all', async () => {
    const { document } = await mount('<figure data-external-embed data-provider="youtube"></figure>', {});
    runs += 1;

    await assert.rejects(import(`../../assets/external.js?page=${runs}`), /An external embed lacks \[data-external-frame\]\./);
    document.body.innerHTML = '';
});
