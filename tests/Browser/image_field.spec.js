import { imageField } from '../Markup/image_field.js';
import { expect, loadController, test } from './support/test.js';

const IMAGE = {
    id: 'd3f91e42-a507-4f50-bf9e-a0ea5a5c5001',
    alt: 'Mountains at dawn',
    status: 'Published',
    published: true,
    thumbnail: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="112" height="63"%3E%3C/svg%3E',
};

test('shows the selected image as the CMS knows it now', async ({ page }) => {
    await page.route('**/api/images/lookup*', (route) => route.fulfill({ json: { images: [IMAGE] } }));
    await loadController(page, 'image-field', imageField(IMAGE.id));

    await expect(page.locator('[data-image-field-target="description"]')).toHaveText(IMAGE.alt);
    await expect(page.locator('[data-image-field-target="state"]')).toHaveText(IMAGE.status);
    await expect(page.locator('[data-image-field-target="thumbnail"] img')).toHaveAttribute('src', IMAGE.thumbnail);
    await expect(page.getByRole('button', { name: 'Remove image' })).toBeVisible();
});

test('choosing and removing an image updates and notifies the form', async ({ page }) => {
    await page.route('**/api/images/search*', (route) => route.fulfill({ json: { images: [IMAGE] } }));
    await loadController(page, 'image-field', imageField());
    await page.evaluate(() => {
        window.inputEvents = 0;
        document.querySelector('form').addEventListener('input', () => { window.inputEvents += 1; });
    });

    await page.getByRole('button', { name: 'Choose image' }).click();
    await page.locator('[data-image-field-target="results"] button').click();
    await expect(page.locator('[data-image-field-target="input"]')).toHaveValue(IMAGE.id);
    await expect(page.locator('[data-image-field-target="status"]')).toHaveText(`Chosen ${IMAGE.alt}`);

    await page.getByRole('button', { name: 'Remove image' }).click();
    await expect(page.locator('[data-image-field-target="input"]')).toHaveValue('');
    await expect(page.locator('[data-image-field-target="status"]')).toHaveText('Image removed');
    await expect(page.getByRole('button', { name: 'Choose image' })).toBeFocused();
    expect(await page.evaluate(() => window.inputEvents)).toBe(2);
});

test('cancels a pending search when its field is disconnected', async ({ page }) => {
    let requestStarted;
    const started = new Promise((resolve) => { requestStarted = resolve; });
    let failure = null;
    page.on('requestfailed', (request) => {
        if (request.url().includes('alt=pending')) {
            failure = request.failure()?.errorText;
        }
    });
    await page.route('**/api/images/search*', async (route) => {
        if (new URL(route.request().url()).searchParams.get('alt') === 'pending') {
            requestStarted();
            await new Promise(() => {});
        }

        await route.fulfill({ json: { images: [] } });
    });
    await loadController(page, 'image-field', imageField());
    await page.getByRole('button', { name: 'Choose image' }).click();
    await page.getByRole('searchbox').fill('pending');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await started;

    await page.locator('#image').evaluate((element) => element.remove());

    await expect.poll(() => failure).toContain('ERR_ABORTED');
});

test('a lookup cancelled by moving the field leaves the new lookup in charge', async ({ page }) => {
    const lookups = [];
    let firstAborted = false;
    page.on('requestfailed', (request) => {
        if (request.url().includes('/api/images/lookup')) {
            firstAborted = true;
        }
    });
    await page.route('**/api/images/lookup*', (route) => new Promise((resolve) => {
        lookups.push(() => resolve(route.fulfill({ json: { images: [IMAGE] } })));
    }));
    await loadController(page, 'image-field', imageField(IMAGE.id));
    await expect.poll(() => lookups.length).toBe(1);

    // Moved in the page: Stimulus disconnects the field and connects it again.
    await page.locator('#image').evaluate((element) => element.ownerDocument.body.append(element));
    await expect.poll(() => lookups.length).toBe(2);
    await expect.poll(() => firstAborted).toBe(true);

    await expect(page.locator('[data-image-field-target="description"]')).toHaveText('Loading image');

    lookups[1]();
    await expect(page.locator('[data-image-field-target="description"]')).toHaveText(IMAGE.alt);
});
