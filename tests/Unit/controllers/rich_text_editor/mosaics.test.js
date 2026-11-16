import assert from 'node:assert/strict';
import test from 'node:test';
import { MOSAIC_LABELS } from '../../../Markup/rich_text_editor.js';
import { answer, assertFocused, settle } from '../../support/dom.js';
import { doc, openEditor, paragraph } from '../../support/rich_text_editor.js';

const FEATURES = { blocks: ['mosaic', 'table'], headingLevels: [], marks: [] };
const SECTION = { id: 'section-1', title: 'Daily life', status: 'Published', published: true };
const AUTHOR = { id: 'author-1', title: 'Isaac', status: 'Published', published: true };
const SLEEP = { id: 'tag-1', title: 'Sleep', status: 'Published', published: true };
const SOUND = { id: 'tag-2', title: 'Sound', status: 'Draft', published: false };
const mosaic = (attrs) => ({ type: 'mosaic', attrs: { limit: 6, sectionId: null, authorId: null, tagIds: [], ...attrs } });

/** Sections, tags and authors, searched and looked up where the editor asks for each. */
const respond = (path, query) => {
    const terms = path.includes('sections') ? [SECTION] : [SLEEP, SOUND];

    if (path === '/api/terms/lookup') {
        return answer({ terms: [SECTION, SLEEP, SOUND].filter(({ id }) => query.get('ids').split(',').includes(id)) });
    }

    if (path === '/api/authors/lookup') {
        return answer({ authors: [AUTHOR].filter(({ id }) => query.get('ids').split(',').includes(id)) });
    }

    if (path === '/api/pages/search') {
        return answer({ pages: [], more: false });
    }

    return path.includes('authors') ? answer({ authors: [AUTHOR], more: false }) : answer({ terms, more: false });
};

const open = (options = {}) => openEditor({ features: FEATURES, mosaics: true, respond, ...options });
const results = (editor) => [...editor.target('pageResults').querySelectorAll('button')];
const mosaics = (editor) => [...editor.editor.view.dom.querySelectorAll('[data-mosaic]')].map((element) => element.querySelector('.rich-text-widget-title').textContent);
const tags = (editor) => [...editor.target('mosaicTags').children].map((item) => item.textContent);
const posted = (editor) => editor.posted().content.filter((node) => node.type === 'mosaic').map((node) => node.attrs);

test('a mosaic is inserted from the dock, asking first how many posts it shows and what narrows them down', async () => {
    const editor = await open({ document: doc(paragraph('Before')) });
    await editor.focus('end');

    await editor.press('insertMosaic');

    assert.equal(editor.target('mosaicDialog').open, true);
    assertFocused(editor.target('mosaicLimit'));
    assert.equal(editor.target('mosaicLimit').value, '6');
    assert.equal(editor.target('mosaicSection').textContent, MOSAIC_LABELS.anySection);
    assert.equal(editor.target('mosaicSectionRemove').hidden, true);
    assert.equal(editor.target('mosaicAuthor').textContent, MOSAIC_LABELS.anyAuthor);
    assert.equal(editor.target('mosaicAuthorRemove').hidden, true);
    assert.deepEqual(tags(editor), [MOSAIC_LABELS.noTags]);
    assert.equal(editor.target('mosaicTags').children[0].className, 'text-muted');
    assert.equal(editor.target('mosaicSubmit').textContent, MOSAIC_LABELS.insert);

    await editor.press(editor.target('mosaicSectionChoose'));

    // Only sections are offered, under the title of what they are picked for.
    assert.equal(editor.target('pageDialog').open, true);
    assert.equal(editor.target('pageKinds').hidden, true);
    assert.equal(editor.target('pageDialogTitle').textContent, MOSAIC_LABELS.pick.section.title);
    assert.equal(new URL(editor.requests.at(-1).url).pathname, '/api/sections/search');

    results(editor)[0].click();
    await settle();

    assert.equal(editor.target('pageDialog').open, false);
    assert.equal(editor.target('mosaicSection').textContent, 'Daily life');
    assert.equal(editor.target('mosaicSectionRemove').hidden, false);
    assertFocused(editor.target('mosaicSectionChoose'));

    await editor.press(editor.target('mosaicAuthorChoose'));
    assert.equal(new URL(editor.requests.at(-1).url).pathname, '/api/authors/search');
    results(editor)[0].click();
    await settle();
    assert.equal(editor.target('mosaicAuthor').textContent, 'Isaac');
    assertFocused(editor.target('mosaicAuthorChoose'));

    for (const index of [1, 0, 1]) {
        await editor.press(editor.target('mosaicTagChoose'));
        assert.equal(new URL(editor.requests.at(-1).url).pathname, '/api/tags/search');
        results(editor)[index].click();
        await settle();
    }

    // Each tag once, in the order picked, each with its own control to take it out, which never sends the form.
    assert.deepEqual(tags(editor), ['SoundRemove Sound', 'SleepRemove Sleep']);
    assert.deepEqual([...editor.target('mosaicTags').children].map((item) => [item.className, item.querySelector('button').type, item.querySelector('button').className]), [
        ['flex flex-wrap items-center justify-between gap-2', 'button', 'rich-text-mosaic-change'],
        ['flex flex-wrap items-center justify-between gap-2', 'button', 'rich-text-mosaic-change'],
    ]);
    assertFocused(editor.target('mosaicTagChoose'));

    editor.target('mosaicLimit').value = '25';
    await editor.press(editor.target('mosaicSubmit'));

    assert.equal(editor.target('mosaicDialog').open, false);
    assert.deepEqual(posted(editor), [{ limit: 20, sectionId: 'section-1', authorId: 'author-1', tagIds: ['tag-2', 'tag-1'] }]);
    assert.deepEqual(mosaics(editor), ['Up to 20 posts · Section: Daily life · Author: Isaac · Tags: Sound, Sleep']);
});

test('a mosaic of the text says what it asks for as the CMS knows it, and changes in place from its own button', async () => {
    const editor = await open({ document: doc(paragraph('Intro'), mosaic({ limit: 3, sectionId: 'section-1', tagIds: ['tag-1', 'gone'] }), paragraph('After')) });

    assert.deepEqual(editor.requests.map(({ url }) => new URL(url).pathname).sort(), ['/api/terms/lookup']);
    assert.deepEqual(mosaics(editor), ['Up to 3 posts · Section: Daily life · Tags: Sleep, Not found']);

    const change = editor.editor.view.dom.querySelector('[data-mosaic] button');
    assert.equal(change.textContent, MOSAIC_LABELS.change);
    change.click();
    await settle();

    assert.equal(editor.target('mosaicDialog').open, true);
    assert.equal(editor.target('mosaicLimit').value, '3');
    assert.equal(editor.target('mosaicSubmit').textContent, MOSAIC_LABELS.save);
    assert.deepEqual(tags(editor), ['SleepRemove Sleep', 'Not foundRemove Not found']);

    editor.target('mosaicTags').querySelectorAll('button')[1].click();
    await settle();
    assert.deepEqual(tags(editor), ['SleepRemove Sleep']);
    assertFocused(editor.target('mosaicTagChoose'));

    await editor.press(editor.target('mosaicSectionRemove'));
    assert.equal(editor.target('mosaicSection').textContent, MOSAIC_LABELS.anySection);
    assertFocused(editor.target('mosaicSectionChoose'));

    editor.target('mosaicLimit').value = 'many';
    await editor.press(editor.target('mosaicSubmit'));

    // Changed in place: still one mosaic, between the same paragraphs, keeping its limit.
    assert.deepEqual(editor.posted().content.map((node) => node.type), ['paragraph', 'mosaic', 'paragraph']);
    assert.deepEqual(posted(editor), [{ limit: 3, sectionId: null, authorId: null, tagIds: ['tag-1'] }]);
    assert.deepEqual(mosaics(editor), ['Up to 3 posts · Tags: Sleep']);
});

test('an author removed from the dialog leaves the mosaic of every author', async () => {
    const editor = await open({ document: doc(mosaic({ authorId: 'author-1' })) });

    assert.deepEqual(mosaics(editor), ['Up to 6 posts · Author: Isaac']);
    editor.editor.view.dom.querySelector('[data-mosaic] button').click();
    await settle();
    assert.equal(editor.target('mosaicAuthorRemove').hidden, false);

    await editor.press(editor.target('mosaicAuthorRemove'));
    assert.equal(editor.target('mosaicAuthor').textContent, MOSAIC_LABELS.anyAuthor);
    assert.equal(editor.target('mosaicAuthorRemove').hidden, true);
    assertFocused(editor.target('mosaicAuthorChoose'));

    await editor.press(editor.target('mosaicSubmit'));
    assert.deepEqual(posted(editor), [{ limit: 6, sectionId: null, authorId: null, tagIds: [] }]);
});

test('closing the dialog leaves the text as it was', async () => {
    const editor = await open({ document: doc(paragraph('Only')) });
    await editor.focus('end');

    await editor.press('insertMosaic');
    editor.target('mosaicDialog').close();
    await settle();

    assert.deepEqual(posted(editor), []);
});

test('the dialog of links offers every kind again after picking for a mosaic', async () => {
    const editor = await open({ document: doc(paragraph('Some text')), features: { ...FEATURES, marks: ['link'] }, links: true });
    await editor.focus('end');

    await editor.press('insertMosaic');
    await editor.press(editor.target('mosaicTagChoose'));
    editor.target('pageDialog').close();
    editor.target('mosaicDialog').close();

    editor.editor.commands.setTextSelection({ from: 1, to: 5 });
    await editor.press('insertLink');

    assert.equal(editor.target('pageKinds').hidden, false);
    // The kind checked, the first one, is searched again.
    assert.equal(new URL(editor.requests.at(-1).url).pathname, '/api/pages/search');
});

test('a mosaic goes between blocks, never in a table, and is offered from "/" as the dock names it', async () => {
    const editor = await open({ document: doc(paragraph()) });
    editor.window.HTMLElement.prototype.scrollIntoView = () => {};
    await editor.focus('end');

    assert.equal(editor.control('insertMosaic').getAttribute('aria-disabled'), 'false');

    editor.editor.commands.insertContent('/mosaic');
    await settle();
    assert.deepEqual(editor.options().map(([label]) => label), ['Content mosaic']);
    editor.target('menu').children[0].click();
    await settle();
    assert.equal(editor.target('mosaicDialog').open, true);
    editor.target('mosaicDialog').close();

    editor.editor.commands.setContent(doc(paragraph()));
    await editor.press('insertTable');
    assert.equal(editor.control('insertMosaic').getAttribute('aria-disabled'), 'true');
});

test('without mosaics among its blocks, the editor offers none', async () => {
    const editor = await openEditor({ document: doc(paragraph()), features: { blocks: ['table'], headingLevels: [], marks: [] }, respond });
    editor.window.HTMLElement.prototype.scrollIntoView = () => {};
    await editor.focus('end');

    assert.equal(editor.control('insertMosaic'), null);
    editor.editor.commands.insertContent('/mosaic');
    await settle();
    assert.deepEqual(editor.options(), []);
});

test('a field that allows mosaics but has no words for them takes none, and drops one pasted', async () => {
    const editor = await openEditor({ document: doc(paragraph('Before')), features: FEATURES, links: true, respond });
    await editor.focus('end');

    editor.editor.commands.insertContent('<div data-mosaic="" data-limit="2"></div>');
    await settle();

    assert.equal(editor.control('insertMosaic'), null);
    assert.deepEqual(editor.posted().content.map((node) => node.type), ['paragraph']);
});
