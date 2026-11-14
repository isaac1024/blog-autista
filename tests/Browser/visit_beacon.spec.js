import { expect, loadPage, test } from './support/test.js';

const PAGE_ID = '6d1f3f2c-84a8-4b40-9ea3-91d62f55f001';
const SCRIPT = `<script type="module" src="/assets/web.js" data-type="post" data-content="${PAGE_ID}" data-visit="/v"></script>`;

/** Records every visit the script tells, as the site's endpoint would receive it. */
async function recordVisits(page) {
    const visits = [];
    await page.route('**/v', async (route) => {
        visits.push({ request: route.request(), body: new URLSearchParams(route.request().postData() ?? '') });
        await route.fulfill({ status: 204 });
    });

    return visits;
}

/** Lets the test say whether the tab is in sight, which a headless browser always is. */
async function controlVisibility(page, initial) {
    await page.addInitScript((state) => {
        window.visibility = state;
        Object.defineProperty(document, 'visibilityState', { get: () => window.visibility, configurable: true });
    }, initial);

    return async (state) => page.evaluate((next) => {
        window.visibility = next;
        document.dispatchEvent(new Event('visibilitychange'));
    }, state);
}

const settled = (page) => page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 50)));

test('tells the visit of a page in sight once, with the time zone and no cookie', async ({ page }) => {
    const visits = await recordVisits(page);
    const setVisibility = await controlVisibility(page, 'visible');
    await loadPage(page, SCRIPT, { mocked: ['/v'] });
    await expect.poll(() => visits.length).toBe(1);

    await setVisibility('hidden');
    await setVisibility('visible');
    await settled(page);

    expect(visits).toHaveLength(1);
    const { body, request } = visits[0];
    expect(request.method()).toBe('POST');
    expect([body.get('type'), body.get('content')]).toEqual(['post', PAGE_ID]);
    expect(body.get('source')).toBe('');
    expect(body.get('medium')).toBe('');
    expect(body.get('zone')).toBe(await page.evaluate(() => Intl.DateTimeFormat().resolvedOptions().timeZone));
    expect(request.headers().cookie).toBeUndefined();
});

test('waits for a page opened out of sight until its reader looks at it', async ({ page }) => {
    const visits = await recordVisits(page);
    const setVisibility = await controlVisibility(page, 'hidden');
    await loadPage(page, SCRIPT, { mocked: ['/v'] });
    await settled(page);

    expect(visits).toHaveLength(0);

    await setVisibility('visible');
    await expect.poll(() => visits.length).toBe(1);
});

test('sends the campaign of the link that brought the reader', async ({ page }) => {
    const visits = await recordVisits(page);
    await loadPage(page, SCRIPT, { mocked: ['/v'], query: '?utm_source=newsletter&utm_medium=email&utm_term=ignored' });
    await expect.poll(() => visits.length).toBe(1);

    expect(visits[0].body.get('source')).toBe('newsletter');
    expect(visits[0].body.get('medium')).toBe('email');
    expect([...visits[0].body.keys()]).toEqual(['type', 'content', 'referrer', 'source', 'medium', 'zone']);
});
