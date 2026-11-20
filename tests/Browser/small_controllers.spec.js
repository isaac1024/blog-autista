import { actionBar, characterCount, copyField, searchPreview, singleLine, unsavedChanges } from '../Markup/small_controllers.js';
import { expect, loadController, test } from './support/test.js';

test.describe('character count', () => {
    test('counts the text written, against its recommended length', async ({ page }) => {
        await loadController(page, 'character-count', characterCount('Hi'));
        const output = page.locator('#title-count');

        // The server's static text gives way to the live count.
        await expect(output).toHaveText('2 of 5 to 10: short');

        await page.getByLabel('Title').fill('  Hello  ');
        await expect(output).toHaveText('5 of 5 to 10: fits');

        await page.getByLabel('Title').fill('Hello there!');
        await expect(output).toHaveText('12 of 5 to 10: long');
    });
});

test.describe('copy field', () => {
    // The fixture's origin is not a secure context, so the clipboard is the one the test lends.
    test('copies the value and says so', async ({ page }) => {
        await page.addInitScript(() => {
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: async (text) => { window.copied = text; } },
            });
        });
        await loadController(page, 'copy-field', copyField('https://blogautista.test/first-page.html'));

        await page.getByRole('button', { name: 'Copy' }).click();

        await expect(page.getByRole('status')).toHaveText('Copied');
        expect(await page.evaluate(() => window.copied)).toBe('https://blogautista.test/first-page.html');
    });

    test('leaves the value selected to copy by hand when the browser refuses', async ({ page }) => {
        await page.addInitScript(() => {
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: () => Promise.reject(new DOMException('Denied', 'NotAllowedError')) },
            });
        });
        await loadController(page, 'copy-field', copyField('https://blogautista.test/first-page.html'));

        await page.getByRole('button', { name: 'Copy' }).click();

        await expect(page.getByRole('status')).toHaveText('Copy it by hand');
        await expect(page.getByLabel('Address')).toBeFocused();
        const selected = await page.getByLabel('Address').evaluate((field) => field.value.slice(field.selectionStart, field.selectionEnd));
        expect(selected).toBe('https://blogautista.test/first-page.html');
    });
});

test.describe('search preview', () => {
    test('follows the fields it previews, wherever they are', async ({ page }) => {
        await loadController(page, 'search-preview', searchPreview());

        await page.getByLabel('Title').fill('First page');
        await page.getByLabel('Description').fill('About   the\nfirst page');
        await page.getByLabel('Slug').fill('first');

        await expect(page.locator('[data-search-preview-target="title"]')).toHaveText('First page');
        await expect(page.locator('[data-search-preview-target="description"]')).toHaveText('About the first page');
        await expect(page.locator('[data-search-preview-target="url"]')).toHaveText('https://blogautista.test/first.html');
    });
});

test.describe('single line', () => {
    test('Enter adds no line break', async ({ page }) => {
        await loadController(page, 'single-line', singleLine('First'));

        await page.getByLabel('Title').press('End');
        await page.getByLabel('Title').press('Enter');
        await page.getByLabel('Title').pressSequentially(' page');

        await expect(page.getByLabel('Title')).toHaveValue('First page');
    });

    test('pasted line breaks become spaces', async ({ page }) => {
        await loadController(page, 'single-line', singleLine());

        await page.getByLabel('Title').fill('First\r\n\npage\nof the blog');

        await expect(page.getByLabel('Title')).toHaveValue('First page of the blog');
    });
});

test.describe('unsaved changes', () => {
    test('the button acts while the form holds no changes', async ({ page }) => {
        let published = false;
        await page.route('**/api/publish', (route) => {
            published = true;

            return route.fulfill({ body: 'Published' });
        });
        await loadController(page, 'unsaved-changes', unsavedChanges());

        await expect(page.getByText('Save first')).toBeHidden();
        await page.getByRole('button', { name: 'Publish' }).click();
        await page.waitForURL('**/api/publish');
        expect(published).toBe(true);
    });

    test('a change, even to a field outside the form, holds the button until it is undone', async ({ page }) => {
        let published = false;
        await page.route('**/api/publish', (route) => {
            published = true;

            return route.fulfill({ body: 'Published' });
        });
        await loadController(page, 'unsaved-changes', unsavedChanges());
        const button = page.getByRole('button', { name: 'Publish' });

        await page.getByLabel('Summary').fill('Another summary');

        await expect(page.getByText('Save first')).toBeVisible();
        await expect(button).toHaveAttribute('aria-disabled', 'true');
        // It stays focusable, so whoever reaches it hears why it waits; pressing it does nothing.
        await button.focus();
        await page.keyboard.press('Enter');
        await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(resolve)));
        expect(published).toBe(false);

        await page.getByLabel('Summary').fill('A summary');

        await expect(page.getByText('Save first')).toBeHidden();
        await expect(button).toHaveAttribute('aria-disabled', 'false');
    });
});

test.describe('unsaved form', () => {
    test('leaving a form with changes not saved asks first, and staying keeps them', async ({ page }) => {
        await loadController(page, 'unsaved-form', actionBar());
        await page.getByLabel('Title').fill('Second');
        await expect(page.getByText('Unsaved changes')).toBeVisible();

        let asked = '';
        page.once('dialog', async (dialog) => {
            asked = dialog.type();
            await dialog.dismiss();
        });
        await page.goto('http://blogautista.test/elsewhere').catch(() => {});

        expect(asked).toBe('beforeunload');
        await expect(page.getByLabel('Title')).toHaveValue('Second');
    });

    test('while it holds changes, the page asks for itself every five minutes, with its cookies', async ({ page }) => {
        await page.context().addCookies([{ name: 'session', value: 'kept', url: 'http://blogautista.test' }]);
        await page.clock.install();
        await loadController(page, 'unsaved-form', actionBar());
        const kept = page.waitForRequest((request) => request.method() === 'HEAD');

        await page.getByLabel('Title').fill('Second');
        await page.clock.runFor(5 * 60 * 1000);

        const request = await kept;
        expect([new URL(request.url()).pathname, await request.headerValue('cookie')]).toEqual(['/fixture', 'session=kept']);
    });
});
