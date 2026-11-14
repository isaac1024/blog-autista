import assert from 'node:assert/strict';
import test from 'node:test';
import { mount, settle } from './support/dom.js';

const PAGE_ID = '6d1f3f2c-84a8-4b40-9ea3-91d62f55f001';
const SCRIPT = `<script type="module" data-type="post" data-content="${PAGE_ID}" data-visit="/v"></script>`;
const ZONE = Intl.DateTimeFormat().resolvedOptions().timeZone;
let runs = 0;

/**
 * Runs the script of the public site on a page of the given markup, address
 * and state, and records what it tells: by beacon, or by fetch when the
 * beacon is missing or refused.
 */
const visit = async ({
    markup = SCRIPT,
    url = 'https://blog.test/first-page.html',
    visibility = 'visible',
    prerendering,
    referrer = 'https://search.test/',
    beacon = true,
} = {}) => {
    const { document, window } = await mount(markup, {}, { url });
    const state = { visibility, prerendering };
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state.visibility });
    Object.defineProperty(document, 'referrer', { configurable: true, value: referrer });

    if (prerendering !== undefined) {
        Object.defineProperty(document, 'prerendering', { configurable: true, get: () => state.prerendering });
    }

    const beacons = [];
    const fetches = [];
    Object.defineProperty(window.navigator, 'sendBeacon', {
        configurable: true,
        value: beacon === null ? undefined : (address, body) => {
            beacons.push([address, Object.fromEntries(body)]);

            return beacon;
        },
    });
    globalThis.fetch = async (address, options) => {
        fetches.push([address, { ...options, body: Object.fromEntries(options.body) }]);
        throw new TypeError('Offline');
    };

    // A new address runs the module again, as a new page does.
    runs += 1;
    await import(`../../assets/web.js?visit=${runs}`);
    await settle();

    return {
        beacons,
        fetches,
        /** Changes the state of the page and tells it, as the browser does. */
        change: async (changes, type) => {
            Object.assign(state, changes);
            document.dispatchEvent(new window.Event(type));
            await settle();
        },
    };
};

const told = (overrides = {}) => ({ type: 'post', content: PAGE_ID, referrer: 'https://search.test/', source: '', medium: '', zone: ZONE, ...overrides });

test('a page in sight tells its visit once, by beacon, with where the reader came from and their time zone', async () => {
    const { beacons, fetches, change } = await visit();

    await change({ visibility: 'hidden' }, 'visibilitychange');
    await change({ visibility: 'visible' }, 'visibilitychange');

    assert.deepEqual(beacons, [['/v', told()]]);
    assert.deepEqual(fetches, []);
});

test('the campaign of the link is told, and nothing else of the address', async () => {
    const { beacons } = await visit({ url: 'https://blog.test/first-page.html?utm_source=newsletter&utm_medium=email&utm_term=ignored', referrer: '' });

    assert.deepEqual(beacons, [['/v', told({ referrer: '', source: 'newsletter', medium: 'email' })]]);
    assert.deepEqual(Object.keys(beacons[0][1]), ['type', 'content', 'referrer', 'source', 'medium', 'zone']);
});

test('a page out of sight waits until its reader looks at it', async () => {
    const { beacons, change } = await visit({ visibility: 'hidden' });
    assert.deepEqual(beacons, []);

    await change({ visibility: 'visible' }, 'visibilitychange');
    assert.deepEqual(beacons, [['/v', told()]]);
});

test('a page prerendered waits until it is shown, even though it counts as visible', async () => {
    const { beacons, change } = await visit({ prerendering: true });
    assert.deepEqual(beacons, []);

    await change({ prerendering: false }, 'prerenderingchange');
    assert.deepEqual(beacons, [['/v', told()]]);
});

test('without a beacon, or with one refused, the visit goes by a fetch that keeps no cookie and outlives the page', async () => {
    for (const beacon of [null, false]) {
        const { fetches } = await visit({ beacon });

        assert.deepEqual(fetches, [['/v', { method: 'POST', body: told(), keepalive: true, credentials: 'omit' }]]);
    }
});

test('a browser that tells no time zone is told as none', async () => {
    const { DateTimeFormat } = Intl;

    try {
        for (const format of [() => { throw new RangeError('No time zone'); }, () => ({ resolvedOptions: () => ({ timeZone: undefined }) })]) {
            Intl.DateTimeFormat = /** @type {typeof Intl.DateTimeFormat} */ (format);
            const { beacons } = await visit();

            assert.deepEqual(beacons, [['/v', told({ zone: '' })]]);
        }
    } finally {
        Intl.DateTimeFormat = DateTimeFormat;
    }
});

test('a page without its visit script, or with one missing what to tell, tells nothing', async () => {
    for (const markup of [
        '',
        `<div data-type="post" data-content="${PAGE_ID}" data-visit="/v"></div>`,
    ]) {
        const { beacons, fetches } = await visit({ markup });

        assert.deepEqual([beacons, fetches], [[], []]);
    }

    const { beacons } = await visit({ markup: '<script type="module" data-type data-content data-visit></script>' });
    assert.deepEqual(beacons, [['', told({ type: '', content: '' })]]);
});
