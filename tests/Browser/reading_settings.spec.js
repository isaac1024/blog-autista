import { readingSettings } from '../Markup/reading_settings.js';
import { expect, loadPage, test } from './support/test.js';

/*
 * The head script and the panel module as the layout loads them: the first
 * plain and blocking, the second a module that imports the settings beside it.
 */
const PAGE = `
    <script src="/assets/reading_apply.js"></script>
    ${readingSettings()}
    <script type="module" src="/assets/reading.js"></script>
`;

const root = (page) => page.evaluate(() => ({ ...document.documentElement.dataset }));

test('changes the text and the theme at once and finds them again on the next page', async ({ page }) => {
    await loadPage(page, PAGE);
    const toggle = page.getByRole('button', { name: 'Ajustes de lectura' });

    await toggle.click();
    await page.getByRole('button', { name: 'Texto más grande' }).click();
    await page.getByRole('radio', { name: 'Oscuro' }).check();
    expect(await root(page)).toEqual({ scripted: '', textSize: '115', theme: 'dark' });

    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    await expect(page.locator('[data-reading-panel]')).toBeHidden();

    await page.reload();
    expect(await root(page)).toEqual({ scripted: '', textSize: '115', theme: 'dark' });
    await toggle.click();
    // Written as the language of the page writes a percent, which this one doesn't name.
    await expect(page.locator('[data-reading-size-value]')).toHaveText(/^115\s?%$/);
    await expect(page.getByRole('radio', { name: 'Oscuro' })).toBeChecked();
});

test('sets what was kept before the page paints, ahead of the panel', async ({ page }) => {
    await page.addInitScript(() => {
        window.localStorage.setItem('reading', JSON.stringify({ size: '150', theme: 'light' }));
        // What the root holds when the body starts, before any module runs.
        document.addEventListener('readystatechange', () => {
            window.atInteractive ??= { ...document.documentElement.dataset };
        });
    });
    await loadPage(page, PAGE);

    expect(await page.evaluate(() => window.atInteractive)).toEqual({ scripted: '', textSize: '150', theme: 'light' });
});
