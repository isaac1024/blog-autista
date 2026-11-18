import { pageHeader } from '../Markup/page_header.js';
import { expect, loadPage, test } from './support/test.js';

/*
 * The header of the site with the script of its menu, laid out by the
 * browser. The suite has no Tailwind, so the page carries the few rules of
 * the bar that decide whether the menu fits.
 */
const PAGE = `
    <style>
        body { margin: 0; font: 18px/1.5 sans-serif; }
        .hidden { display: none; }
        [data-menu] > div { display: flex; flex-wrap: wrap; align-items: center; gap: 0 32px; max-width: 1152px; margin: 0 auto; padding: 0 32px; }
        [data-menu-inline] { flex: 1 1 0; min-width: 0; }
        [data-menu-inline] ul { display: flex; gap: 20px; margin: 0; padding: 0; list-style: none; }
        [data-menu-inline] a { white-space: nowrap; }
        .ml-auto { display: flex; margin-left: auto; gap: 4px; }
        [data-menu="inline"] .ml-auto span { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
    </style>
    ${pageHeader()}
    <script type="module" src="/assets/menu.js"></script>
`;

const inline = (page) => page.locator('[data-menu-inline]');
const folded = (page) => page.locator('[data-menu-folded]');

test('shows the menu in line on a wide screen and folds it on a narrow one, as the window changes', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 600 });
    await loadPage(page, PAGE);

    await expect(inline(page)).toBeVisible();
    await expect(folded(page)).toBeHidden();
    await expect(page.getByRole('link', { name: 'Para familias' })).toBeVisible();

    await page.setViewportSize({ width: 700, height: 600 });
    await expect(inline(page)).toBeHidden();
    await expect(folded(page)).toBeVisible();

    await page.setViewportSize({ width: 1400, height: 600 });
    await expect(inline(page)).toBeVisible();
});

test('folds the menu when the reader makes the text bigger', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 600 });
    await loadPage(page, PAGE);
    await expect(inline(page)).toBeVisible();

    await page.evaluate(() => { document.body.style.fontSize = '36px'; });

    await expect(inline(page)).toBeHidden();
    await expect(folded(page)).toBeVisible();
});
