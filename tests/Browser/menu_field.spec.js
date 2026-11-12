import { menuField } from './fixtures/menu_field.js';
import { expect, loadController, test } from './support/test.js';

const FIRST = {
    id: 'c2e80d31-f406-4f4f-bf9e-a0ea5a5c5001',
    title: 'First page',
    status: 'Published',
    published: true,
};
const SECOND = { ...FIRST, id: 'c2e80d31-f406-4f4f-bf9e-a0ea5a5c5002', title: 'Second page' };

test('adding a page creates and numbers its fields, announces it and enforces the limit', async ({ page }) => {
    await page.route('**/api/pages/search*', (route) => route.fulfill({ json: { pages: [FIRST], more: false } }));
    await loadController(page, 'menu-field', menuField({ max: 1 }));
    await page.evaluate(() => {
        window.inputEvents = 0;
        document.querySelector('form').addEventListener('input', () => { window.inputEvents += 1; });
    });

    await page.getByRole('button', { name: 'Add page' }).click();
    await page.locator('[data-menu-field-target="results"] button').click();

    const row = page.locator('[data-menu-field-target="row"]');
    await expect(row).toHaveCount(1);
    await expect(row.locator('[data-field="page_id"]')).toHaveAttribute('name', 'menu[0][page_id]');
    await expect(row.locator('[data-field="page_id"]')).toHaveValue(FIRST.id);
    await expect(row.locator('[data-field="label"]')).toHaveAttribute('name', 'menu[0][label]');
    await expect(row.locator('[data-field="label"]')).toBeFocused();
    await expect(page.locator('[data-menu-field-target="status"]')).toHaveText('Added First page');
    await expect(page.locator('[data-menu-field-target="empty"]')).not.toBeVisible();
    await expect(page.locator('[data-menu-field-target="add"]')).toHaveAttribute('aria-disabled', 'true');
    await expect(page.locator('[data-menu-field-target="full"]')).toBeVisible();
    expect(await page.evaluate(() => window.inputEvents)).toBe(1);
});

test('moving an entry renumbers every field and keeps a useful button focused', async ({ page }) => {
    await page.route('**/api/pages/lookup*', (route) => route.fulfill({ json: { pages: [FIRST, SECOND] } }));
    await loadController(page, 'menu-field', menuField({ items: [FIRST, SECOND] }));
    const rows = page.locator('[data-menu-field-target="row"]');
    await expect(rows.nth(0)).toContainText(FIRST.title);
    await expect(rows.nth(1)).toContainText(SECOND.title);

    await rows.nth(0).locator('[data-field="down"]').click();

    await expect(rows.nth(0)).toHaveAttribute('data-page-id', SECOND.id);
    await expect(rows.nth(1)).toHaveAttribute('data-page-id', FIRST.id);
    await expect(rows.nth(0).locator('[data-field="page_id"]')).toHaveAttribute('name', 'menu[0][page_id]');
    await expect(rows.nth(1).locator('[data-field="page_id"]')).toHaveAttribute('name', 'menu[1][page_id]');
    await expect(rows.nth(0).locator('[data-field="label"]')).toHaveAttribute('id', 'menu-0-label');
    await expect(rows.nth(1).locator('[data-field="label"]')).toHaveAttribute('id', 'menu-1-label');
    await expect(rows.nth(1).locator('[data-field="up"]')).toBeFocused();
    await expect(page.locator('[data-menu-field-target="status"]')).toHaveText('Moved down First page');
});

test('removing an entry restores the empty state and moves focus to the add button', async ({ page }) => {
    await page.route('**/api/pages/lookup*', (route) => route.fulfill({ json: { pages: [FIRST] } }));
    await loadController(page, 'menu-field', menuField({ items: [FIRST], max: 1 }));
    const row = page.locator('[data-menu-field-target="row"]');
    await expect(row).toContainText(FIRST.title);

    await row.locator('[data-field="remove"]').click();

    await expect(row).toHaveCount(0);
    await expect(page.locator('[data-menu-field-target="empty"]')).toBeVisible();
    await expect(page.locator('[data-menu-field-target="full"]')).not.toBeVisible();
    await expect(page.locator('[data-menu-field-target="add"]')).toHaveAttribute('aria-disabled', 'false');
    await expect(page.locator('[data-menu-field-target="add"]')).toBeFocused();
    await expect(page.locator('[data-menu-field-target="status"]')).toHaveText('Removed First page');
});

test('cancels a pending search when the menu is disconnected', async ({ page }) => {
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

        await route.fulfill({ json: { pages: [], more: false } });
    });
    await loadController(page, 'menu-field', menuField());
    await page.getByRole('button', { name: 'Add page' }).click();
    await page.getByRole('searchbox').fill('pending');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await started;

    await page.locator('#menu').evaluate((element) => element.remove());

    await expect.poll(() => failure).toContain('ERR_ABORTED');
});
