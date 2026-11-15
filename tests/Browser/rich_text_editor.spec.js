import { richTextEditor } from '../Markup/rich_text_editor.js';
import { expect, loadController, test } from './support/test.js';

const inputDocument = async (page) => page.locator('[data-rich-text-editor-target="input"]').evaluate((input) => JSON.parse(input.value));

const text = (content) => content.map((block) => block.content?.map((run) => run.text).join('') ?? '');

const IMAGE = {
    id: 'a2dd35ec-c1fc-45a3-a06d-c9f0c1b4a001',
    alt: 'Mountains at dawn',
    status: 'Published',
    published: true,
    thumbnail: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="240" height="135"%3E%3C/svg%3E',
    caption: '',
};

const imageEditor = (document = { type: 'doc', content: [{ type: 'paragraph' }] }) => richTextEditor({
    document,
    features: { blocks: ['image'], headingLevels: [], marks: [] },
    blockOptions: [{ value: 'paragraph', label: 'Paragraph' }],
    images: true,
});

const PAGE = {
    id: 'b1e79d31-f406-4f4f-bf9e-a0ea5a5c5001',
    title: 'Target page',
    status: 'Published',
    published: true,
};

const pageEditor = ({ document = { type: 'doc', content: [{ type: 'paragraph' }] }, links = false, widgets = false } = {}) => richTextEditor({
    document,
    features: { blocks: widgets ? ['widget'] : [], headingLevels: [], marks: links ? ['link'] : [] },
    blockOptions: [{ value: 'paragraph', label: 'Paragraph' }],
    links,
    widgets,
});

test('keeps the submitted document and its character count in step with the editor', async ({ page }) => {
    await loadController(page, 'rich-text-editor', richTextEditor({ maxLength: 5 }));
    const editor = page.locator('#content');

    await expect(editor).toBeVisible();
    await editor.focus();
    await page.keyboard.type('Hello');

    expect(await inputDocument(page)).toEqual({
        type: 'doc',
        content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hello' }] }],
    });
    await expect(page.locator('[data-rich-text-editor-target="count"]')).toHaveText('Count 5/5');

    await page.keyboard.type('!');
    await expect(page.locator('[data-rich-text-editor-target="count"]')).toHaveText('Over by 1');
});

test('the slash menu turns the current paragraph into the chosen block from the keyboard', async ({ page }) => {
    await loadController(page, 'rich-text-editor', richTextEditor());
    await page.locator('#content').focus();
    await page.keyboard.type('/');

    const menu = page.locator('[data-rich-text-editor-target="menu"]');
    await expect(menu).toBeVisible();
    await expect(menu.getByRole('option')).toHaveText(['Paragraph', 'Heading 2']);

    await page.keyboard.press('ArrowDown');
    await expect(page.locator('#content')).toHaveAttribute('aria-activedescendant', 'content-menu-1');
    await page.keyboard.press('Enter');

    expect((await inputDocument(page)).content[0].type).toBe('heading');
    expect((await inputDocument(page)).content[0].attrs.level).toBe(2);
    await expect(menu).not.toBeVisible();
});

test('moving a heading from the keyboard moves its whole section', async ({ page }) => {
    const document = {
        type: 'doc',
        content: [
            { type: 'paragraph', content: [{ type: 'text', text: 'Introduction' }] },
            { type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'First' }] },
            { type: 'paragraph', content: [{ type: 'text', text: 'First text' }] },
            { type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'Second' }] },
            { type: 'paragraph', content: [{ type: 'text', text: 'Second text' }] },
        ],
    };
    await loadController(page, 'rich-text-editor', richTextEditor({ document }));

    await page.getByRole('heading', { name: 'First', exact: true }).click();
    await expect.poll(() => page.evaluate(() => {
        const element = document.querySelector('[data-controller="rich-text-editor"]');
        const controller = window.stimulus.getControllerForElementAndIdentifier(element, 'rich-text-editor');

        return controller.editor.state.selection.$from.parent.textContent;
    })).toBe('First');
    await page.keyboard.press('Control+Shift+ArrowDown');

    await expect.poll(async () => text((await inputDocument(page)).content)).toEqual([
        'Introduction',
        'Second',
        'Second text',
        'First',
        'First text',
    ]);
    await expect(page.locator('[data-rich-text-editor-target="status"]')).toHaveText('Section moved down');
});

test('an image found in the library is embedded by id and drawn with its current texts', async ({ page }) => {
    await page.route('**/api/images/search*', (route) => route.fulfill({ json: { images: [IMAGE] } }));
    await loadController(page, 'rich-text-editor', imageEditor());

    await page.locator('#content').focus();
    await page.getByRole('button', { name: 'Insert image' }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.locator('[data-rich-text-editor-target="imageStatus"]')).toHaveText('1 result');
    await page.locator('[data-rich-text-editor-target="imageResults"] button').click();

    const document = await inputDocument(page);
    expect(document.content.some((block) => block.type === 'image' && block.attrs.id === IMAGE.id)).toBe(true);
    await expect(page.locator(`figure[data-image-id="${IMAGE.id}"]`)).toContainText('Embedded image: Mountains at dawn');
    await expect(page.getByRole('dialog')).not.toBeVisible();
});

test('an image lookup failure is explained without leaving an uncaught rejection', async ({ page }) => {
    const document = { type: 'doc', content: [{ type: 'image', attrs: { id: IMAGE.id } }] };
    await page.route('**/api/images/lookup*', (route) => route.abort('failed'));
    await loadController(page, 'rich-text-editor', imageEditor(document));

    await expect(page.locator(`figure[data-image-id="${IMAGE.id}"]`)).toHaveText('Image could not be checked');
});

test('an image search failure is announced without leaving an uncaught rejection', async ({ page }) => {
    await page.route('**/api/images/search*', (route) => route.abort('failed'));
    await loadController(page, 'rich-text-editor', imageEditor());

    await page.locator('#content').focus();
    await page.getByRole('button', { name: 'Insert image' }).click();

    await expect(page.locator('[data-rich-text-editor-target="imageStatus"]')).toHaveText('Image search failed');
    await expect(page.locator('[data-rich-text-editor-target="imageResults"]')).toBeEmpty();
});

test('a newer image search cancels the older one, whose results never show', async ({ page }) => {
    let releaseOld;
    const oldCanFinish = new Promise((resolve) => { releaseOld = resolve; });
    let markOldStarted;
    const oldStarted = new Promise((resolve) => { markOldStarted = resolve; });

    await page.route('**/api/images/search*', async (route) => {
        const alt = new URL(route.request().url()).searchParams.get('alt');

        if (alt === 'old') {
            markOldStarted();
            await oldCanFinish;
        }

        const images = alt === 'new'
            ? [{ ...IMAGE, alt: 'Newest image' }]
            : (alt === 'old' ? [{ ...IMAGE, alt: 'Stale image' }] : []);
        // The stale search was cancelled meanwhile: its answer has nowhere to go.
        await route.fulfill({ json: { images } }).catch(() => {});
    });
    await loadController(page, 'rich-text-editor', imageEditor());

    await page.locator('#content').focus();
    await page.getByRole('button', { name: 'Insert image' }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('searchbox').fill('old');
    await page.getByRole('button', { name: 'Search images', exact: true }).click();
    await oldStarted;
    const oldCancelled = page.waitForEvent('requestfailed', (request) => request.url().includes('alt=old'));
    await page.getByRole('searchbox').fill('new');
    await page.getByRole('button', { name: 'Search images', exact: true }).click();
    await expect(page.locator('[data-rich-text-editor-target="imageResults"]')).toContainText('Newest image');

    // The newer search cancels the older one on the network, not only on screen.
    await oldCancelled;
    releaseOld();
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));

    await expect(page.locator('[data-rich-text-editor-target="imageResults"]')).toContainText('Newest image');
    await expect(page.locator('[data-rich-text-editor-target="imageResults"]')).not.toContainText('Stale image');
});

test('a selected text is linked to the page picked in the dialog', async ({ page }) => {
    const document = {
        type: 'doc',
        content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Read this page' }] }],
    };
    await page.route('**/api/pages/search*', (route) => route.fulfill({ json: { pages: [PAGE], more: false } }));
    await loadController(page, 'rich-text-editor', pageEditor({ document, links: true }));

    await page.locator('#content p').selectText();
    await expect.poll(() => page.evaluate(() => {
        const element = document.querySelector('[data-controller="rich-text-editor"]');
        const controller = window.stimulus.getControllerForElementAndIdentifier(element, 'rich-text-editor');

        return controller.editor.state.selection.empty;
    })).toBe(false);
    const button = page.getByRole('button', { name: 'Link', exact: true });
    await expect(button).toHaveAttribute('aria-disabled', 'false');
    await button.click();
    await page.locator('[data-rich-text-editor-target="pageResults"] button').click();

    const runs = (await inputDocument(page)).content[0].content;
    expect(runs[0].marks).toContainEqual({ type: 'link', attrs: { type: 'page', id: PAGE.id } });
    await expect(page.locator(`a[data-page-id="${PAGE.id}"]`)).toHaveText('Read this page');
    await expect(page.locator('[data-rich-text-editor-target="linkTarget"]')).toHaveText('Linked page: Target page. Published.');
});

test('a link to a page outside the site gets its visible state after lookup', async ({ page }) => {
    const document = {
        type: 'doc',
        content: [{
            type: 'paragraph',
            content: [{ type: 'text', text: 'Draft link', marks: [{ type: 'link', attrs: { type: 'page', id: PAGE.id } }] }],
        }],
    };
    await page.route('**/api/pages/lookup*', (route) => route.fulfill({
        json: { pages: [{ ...PAGE, status: 'Draft', published: false }] },
    }));
    await loadController(page, 'rich-text-editor', pageEditor({ document, links: true }));

    await expect(page.locator(`a[data-page-id="${PAGE.id}"] .rich-text-link-offSite`)).toHaveText('Draft link');
});

test('a page picked as related content becomes a widget with its current state', async ({ page }) => {
    const draft = { ...PAGE, title: 'Draft page', status: 'Draft', published: false };
    await page.route('**/api/pages/search*', (route) => route.fulfill({ json: { pages: [draft], more: false } }));
    await loadController(page, 'rich-text-editor', pageEditor({ widgets: true }));

    await page.locator('#content').focus();
    await page.getByRole('button', { name: 'Related page', exact: true }).click();
    await expect(page.locator('[data-rich-text-editor-target="pageDialogTitle"]')).toHaveText('Insert related content');
    await page.locator('[data-rich-text-editor-target="pageResults"] button').click();

    expect((await inputDocument(page)).content.some((block) => block.type === 'widget'
        && block.attrs.kind === 'related_page' && block.attrs.id === draft.id)).toBe(true);
    const widget = page.locator('.rich-text-widget');
    await expect(widget).toContainText('Related content:');
    await expect(widget).toContainText('Draft page');
    await expect(widget).toContainText('Draft');
    await expect(widget).toContainText('Not on the site');
});

test('the block menu creates a labelled callout variant', async ({ page }) => {
    await loadController(page, 'rich-text-editor', richTextEditor({
        features: { blocks: ['callout'], headingLevels: [], calloutVariants: ['note'], marks: [] },
        blockOptions: [
            { value: 'paragraph', label: 'Paragraph' },
            { value: 'callout:note', label: 'Note' },
        ],
    }));
    await page.locator('#content').focus();
    await page.keyboard.type('/');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');

    const document = await inputDocument(page);
    expect(document.content[0].type).toBe('callout');
    expect(document.content[0].attrs.variant).toBe('note');
    await expect(page.locator('[data-callout][data-variant="note"]')).toHaveAttribute('data-label', 'Note');
    await expect(page.locator('[data-callout][data-variant="note"]')).toHaveAttribute('role', 'note');
});

test('Enter in a quote source leaves the quote for a new paragraph', async ({ page }) => {
    const document = {
        type: 'doc',
        content: [{
            type: 'blockquote',
            content: [
                { type: 'paragraph', content: [{ type: 'text', text: 'A quotation' }] },
                { type: 'quoteSource', content: [{ type: 'text', text: 'Its author' }] },
            ],
        }],
    };
    await loadController(page, 'rich-text-editor', richTextEditor({
        document,
        features: { blocks: ['quote'], headingLevels: [], marks: [] },
        blockOptions: [{ value: 'paragraph', label: 'Paragraph' }],
    }));
    await page.locator('[data-quote-source]').click();
    await expect.poll(() => page.evaluate(() => {
        const element = document.querySelector('[data-controller="rich-text-editor"]');
        const controller = window.stimulus.getControllerForElementAndIdentifier(element, 'rich-text-editor');

        return controller.editor.state.selection.$from.parent.type.name;
    })).toBe('quoteSource');

    await page.keyboard.press('Enter');

    await expect.poll(async () => (await inputDocument(page)).content.map((block) => block.type)).toEqual(['blockquote', 'paragraph']);
    await expect(page.locator('[data-quote-source]')).toHaveText('Its author');
});

test('cancels a pending image search when the editor is disconnected', async ({ page }) => {
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
    await loadController(page, 'rich-text-editor', imageEditor());
    await page.locator('#content').focus();
    await page.getByRole('button', { name: 'Insert image' }).click();
    await page.getByRole('searchbox').fill('pending');
    await page.getByRole('button', { name: 'Search images', exact: true }).click();
    await started;

    await page.locator('[data-controller="rich-text-editor"]').evaluate((element) => element.remove());

    await expect.poll(() => failure).toContain('ERR_ABORTED');
});
