import { termsField } from '../Markup/terms_field.js';
import { expect, loadController, test } from './support/test.js';

const CALM = { id: 'c2e80d31-f406-4f4f-bf9e-a0ea5a5c6001', title: 'Calm', status: 'Published', published: true };
const NOISE = { id: 'c2e80d31-f406-4f4f-bf9e-a0ea5a5c6002', title: 'Noise', status: 'Draft', published: false };

test('a tag picked in the dialog is posted with the form, announced and focused', async ({ page }) => {
    await page.route('**/api/tags/search*', (route) => route.fulfill({ json: { terms: [CALM, NOISE], more: false } }));
    await loadController(page, 'terms-field', termsField());
    await page.evaluate(() => {
        window.inputEvents = 0;
        document.querySelector('form').addEventListener('input', () => { window.inputEvents += 1; });
    });

    await page.getByRole('button', { name: 'Add tag' }).click();
    await expect(page.locator('[data-terms-field-target="dialog"]')).toBeVisible();
    await page.locator('[data-terms-field-target="results"] button').nth(1).click();

    await expect(page.locator('[data-terms-field-target="dialog"]')).not.toBeVisible();
    const row = page.locator('[data-terms-field-target="row"]');
    await expect(row).toHaveCount(1);
    await expect(row.locator('[data-field="remove"]')).toBeFocused();
    await expect(page.locator('[data-terms-field-target="status"]')).toHaveText('Added Noise');
    await expect(page.locator('[data-terms-field-target="empty"]')).not.toBeVisible();
    expect(await page.evaluate(() => new FormData(document.querySelector('form')).getAll('tags[]'))).toEqual([NOISE.id]);
    expect(await page.evaluate(() => window.inputEvents)).toBe(1);
});

test('removing the last tag restores the empty state and focuses adding one', async ({ page }) => {
    await loadController(page, 'terms-field', termsField({ items: [CALM] }));
    const row = page.locator('[data-terms-field-target="row"]');

    await row.locator('[data-field="remove"]').click();

    await expect(row).toHaveCount(0);
    await expect(page.locator('[data-terms-field-target="empty"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Add tag' })).toBeFocused();
    await expect(page.locator('[data-terms-field-target="status"]')).toHaveText('Removed Calm');
    expect(await page.evaluate(() => new FormData(document.querySelector('form')).getAll('tags[]'))).toEqual([]);
});
