import { pageField } from '../Markup/page_field.js';
import { expect, loadController, test } from './support/test.js';

const PAGE = {
    id: '6d1f3f2c-84a8-4b40-9ea3-91d62f55f001',
    title: 'First page',
    status: 'Published',
    published: true,
};

test('shows the selected page as the CMS knows it now', async ({ page }) => {
    await page.route('**/api/pages/lookup*', (route) => route.fulfill({ json: { pages: [PAGE] } }));
    await loadController(page, 'page-field', pageField(PAGE.id));

    await expect(page.locator('[data-page-field-target="title"]')).toHaveText(PAGE.title);
    await expect(page.locator('[data-page-field-target="state"]')).toHaveText(PAGE.status);
    await expect(page.getByRole('button', { name: 'Choose page' })).toBeVisible();
});

test('a newer search cancels the older one, whose results never show', async ({ page }) => {
    let releaseOld;
    const oldCanFinish = new Promise((resolve) => { releaseOld = resolve; });
    let markOldStarted;
    const oldStarted = new Promise((resolve) => { markOldStarted = resolve; });

    await page.route('**/api/pages/search*', async (route) => {
        const title = new URL(route.request().url()).searchParams.get('title');

        if (title === 'old') {
            markOldStarted();
            await oldCanFinish;
        }

        const pages = title === 'new'
            ? [{ ...PAGE, title: 'Newest result' }]
            : (title === 'old' ? [{ ...PAGE, title: 'Stale result' }] : []);
        // The stale search was cancelled meanwhile: its answer has nowhere to go.
        await route.fulfill({ json: { pages } }).catch(() => {});
    });
    await loadController(page, 'page-field', pageField());

    await page.getByRole('button', { name: 'Choose page' }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('searchbox').fill('old');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await oldStarted;
    const oldCancelled = page.waitForEvent('requestfailed', (request) => request.url().includes('title=old'));
    await page.getByRole('searchbox').fill('new');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(page.locator('[data-page-field-target="results"]')).toContainText('Newest result');

    // The newer search cancels the older one on the network, not only on screen.
    await oldCancelled;
    releaseOld();
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));

    await expect(page.locator('[data-page-field-target="results"]')).toContainText('Newest result');
    await expect(page.locator('[data-page-field-target="results"]')).not.toContainText('Stale result');
});

test('choosing a page updates the field, announces it and notifies the form', async ({ page }) => {
    await page.route('**/api/pages/search*', (route) => route.fulfill({ json: { pages: [PAGE] } }));
    await loadController(page, 'page-field', pageField());
    await page.evaluate(() => {
        window.inputEvents = 0;
        document.querySelector('form').addEventListener('input', () => { window.inputEvents += 1; });
    });

    await page.getByRole('button', { name: 'Choose page' }).click();
    await page.locator('[data-page-field-target="results"] button').click();

    await expect(page.locator('[data-page-field-target="input"]')).toHaveValue(PAGE.id);
    await expect(page.locator('[data-page-field-target="title"]')).toHaveText(PAGE.title);
    await expect(page.locator('[data-page-field-target="status"]')).toHaveText(`Chosen ${PAGE.title}`);
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Choose page' })).toBeFocused();
    expect(await page.evaluate(() => window.inputEvents)).toBe(1);
});

test('cancels a pending search when its field is disconnected', async ({ page }) => {
    let requestStarted;
    const started = new Promise((resolve) => { requestStarted = resolve; });
    let failure = null;
    page.on('requestfailed', (request) => {
        if (request.url().includes('title=pending')) {
            failure = request.failure()?.errorText;
        }
    });
    await page.route('**/api/pages/search*', async (route) => {
        if (new URL(route.request().url()).searchParams.get('title') === 'pending') {
            requestStarted();
            await new Promise(() => {});
        }

        await route.fulfill({ json: { pages: [] } });
    });
    await loadController(page, 'page-field', pageField());
    await page.getByRole('button', { name: 'Choose page' }).click();
    await page.getByRole('searchbox').fill('pending');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await started;

    await page.locator('#page').evaluate((element) => element.remove());

    await expect.poll(() => failure).toContain('ERR_ABORTED');
});
