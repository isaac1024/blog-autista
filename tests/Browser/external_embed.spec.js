import { externalEmbed } from '../Markup/external_embed.js';
import { expect, loadPage, test } from './support/test.js';

const SCRIPT = '<script type="module" src="/assets/external.js"></script>';

/**
 * Serves the frames of the providers: a page that paints, and, for a post,
 * tells the page above how tall it is, as Bluesky's frame does.
 */
async function serveFrames(page) {
    const served = [];
    await page.route('https://embed.bsky.app/**', async (route) => {
        served.push(route.request().url());
        const id = new URL(route.request().url()).searchParams.get('id');
        await route.fulfill({
            contentType: 'text/html',
            body: `<!doctype html><p>Una publicación</p><script>parent.postMessage({ id: ${JSON.stringify(id)}, height: 321 }, '*');</script>`,
        });
    });
    await page.route('https://www.youtube-nocookie.com/**', async (route) => {
        served.push(route.request().url());
        await route.fulfill({ contentType: 'text/html', body: '<!doctype html><p>Un vídeo</p>' });
    });

    return served;
}

const POST = externalEmbed({ provider: 'bluesky', src: 'https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b', origin: 'https://embed.bsky.app', space: 'reported' });

test('loads nothing of the provider until the reader agrees, then the post at the height it tells', async ({ page }) => {
    const served = await serveFrames(page);
    await loadPage(page, POST + SCRIPT, { mocked: ['/embed/'] });

    await page.getByRole('button', { name: 'Cargar aquí' }).click();
    await expect(page.locator('[data-external-notice-title]')).toBeFocused();
    expect(served).toEqual([]);

    await page.getByRole('button', { name: 'Aceptar y cargar' }).click();

    const frame = page.locator('[data-external-frame] iframe');
    await expect(frame).toHaveAttribute('data-shown', '');
    await expect(frame).toHaveAttribute('height', '321');
    await expect(page.locator('[data-external-facade]')).toBeHidden();
    await expect(page.getByRole('button', { name: 'No cargar más' })).toBeFocused();
    expect(served).toEqual(['https://embed.bsky.app/embed/did:plc:a/app.bsky.feed.post/b?id=external-frame-1']);
});

test('remembers the consent on the next page, and forgets it when asked', async ({ page }) => {
    await serveFrames(page);
    await loadPage(page, externalEmbed() + SCRIPT, { mocked: ['/embed/'] });
    await page.getByRole('button', { name: 'Cargar aquí' }).click();
    await page.getByRole('button', { name: 'Aceptar y cargar' }).click();
    await expect(page.locator('iframe')).toHaveAttribute('data-shown', '');

    await page.reload();

    // On its own, with no notice.
    await expect(page.locator('iframe')).toHaveAttribute('data-shown', '');
    await expect(page.locator('[data-external-notice]')).toBeHidden();

    await page.getByRole('button', { name: 'No cargar más' }).click();

    await expect(page.locator('iframe')).toHaveCount(0);
    await expect(page.locator('[data-external-facade]')).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('external-consent'))).toBe('{}');
});
