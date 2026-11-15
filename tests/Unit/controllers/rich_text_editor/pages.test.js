import assert from 'node:assert/strict';
import test from 'node:test';
import { PAGE_LABELS, WIDGET_LABELS } from '../../../Markup/rich_text_editor.js';
import { answer, assertFocused, later, settle } from '../../support/dom.js';
import { doc, openEditor, paragraph } from '../../support/rich_text_editor.js';

const FEATURES = { blocks: ['widget', 'table'], headingLevels: [], marks: ['link'] };
const TARGET = { id: 'page-1', title: 'Target page', status: 'Published', published: true };
const DRAFT = { id: 'page-2', title: 'Draft page', status: 'Draft', published: false };
const THIRD = { id: 'page-3', title: 'Third page', status: 'Published', published: true };
const link = (text, id) => paragraph(text, [{ type: 'link', attrs: { type: 'page', id } }]);
const widget = (id) => ({ type: 'widget', attrs: { kind: 'related_page', type: 'page', id } });

/** The links of the text, as drawn: their pages and how they look. */
const links = (editor) => [...editor.controller.editor.view.dom.querySelectorAll('a')].map((element) => [element.dataset.pageId, element.textContent, element.querySelector('span')?.className ?? null]);
/** The widgets of the text, as drawn. */
const widgets = (editor) => [...editor.controller.editor.view.dom.querySelectorAll('.rich-text-widget')].map((element) => [element.dataset.widgetKind, element.textContent]);
const results = (editor) => [...editor.target('pageResults').querySelectorAll('button')];
const searchButton = (editor) => [...editor.document.querySelectorAll('button')].find((button) => button.textContent === 'Search pages');

/** Pages searched answer from the list, two at a time. */
const pagesOf = (list) => (path, query) => {
    if (path.endsWith('/lookup')) {
        return answer({ pages: list.filter((page) => query.get('ids').split(',').includes(page.id)) });
    }

    const page = Number(query.get('page'));

    return answer({ pages: list.slice((page - 1) * 2, page * 2), more: page * 2 < list.length });
};

const open = (options = {}) => openEditor({ features: FEATURES, links: true, widgets: true, respond: pagesOf([TARGET, DRAFT, THIRD]), ...options });

test('the pages linked and shown in widgets are looked up at once, and drawn as the CMS knows them', async () => {
    const pending = later();
    const editor = await open({
        document: doc(link('Published', 'page-1'), link('Draft', 'page-2'), widget('page-1'), widget('gone'), link('Gone', 'gone')),
        respond: () => pending.promise,
    });

    assert.deepEqual(editor.requests.map(({ url }) => url), ['https://blog.test/api/pages/lookup?ids=page-1%2Cpage-2%2Cgone']);
    assert.deepEqual(links(editor).map(([, , look]) => look), ['rich-text-link-loading', 'rich-text-link-loading', 'rich-text-link-loading']);
    assert.deepEqual(widgets(editor), [['related_page', 'Related content:Loading page'], ['related_page', 'Related content:Loading page']]);

    pending.resolve(answer({ pages: [TARGET, DRAFT] }));
    await settle();

    assert.deepEqual(links(editor), [['page-1', 'Published', null], ['page-2', 'Draft', 'rich-text-link-offSite'], ['gone', 'Gone', 'rich-text-link-missing']]);
    assert.deepEqual(widgets(editor), [['related_page', 'Related content:Target pagePublished'], ['related_page', 'Related content:Page not found']]);
});

test('pages that can\'t be checked are told so, and are not asked about again while their lookup runs', async () => {
    const pending = later();
    const editor = await open({ document: doc(link('Linked', 'page-1')), respond: () => pending.promise });

    editor.editor.chain().focus('end').insertContent(' more').run();
    assert.equal(editor.requests.length, 1);

    pending.reject(new Error('Unavailable'));
    await settle();
    assert.deepEqual(links(editor), [['page-1', 'Linked', 'rich-text-link-unknown']]);

    editor.editor.chain().insertContent(' again').run();
    assert.equal(editor.requests.length, 1);
});

test('a link pasted to a page not known yet is looked up, and a known one is not', async () => {
    const editor = await open({ document: doc(link('Linked', 'page-1')) });
    await settle();

    editor.editor.chain().focus('end').insertContent([link('Pasted', 'page-2'), link('Again', 'page-1')]).run();
    await settle();

    assert.deepEqual(editor.requests.map(({ url }) => new URL(url).searchParams.get('ids')), ['page-1', 'page-2']);
    assert.deepEqual(links(editor).map(([id, , look]) => [id, look]), [['page-1', null], ['page-2', 'rich-text-link-offSite'], ['page-1', null]]);
});

test('an editor removed while it looks pages up draws nothing of the late answer', async () => {
    const pending = later();
    const editor = await open({ document: doc(widget('page-1')), respond: () => pending.promise });
    const [element] = editor.editor.view.dom.querySelectorAll('.rich-text-widget');

    editor.document.querySelector('[data-controller]').remove();
    await settle();
    pending.resolve(answer({ pages: [TARGET] }));
    await settle();

    assert.equal(element.textContent, 'Related content:Loading page');
});

test('a link wraps only selected text, and is held until there is some', async () => {
    const editor = await open({ document: doc(paragraph('Read this page')) });
    await editor.focus('end');
    assert.equal(editor.state('insertLink')[0], 'true');

    await editor.press('insertLink');
    assert.equal(editor.target('pageDialog').open, false);

    editor.editor.commands.setTextSelection({ from: 1, to: 5 });
    assert.equal(editor.state('insertLink')[0], 'false');

    editor.editor.commands.setNodeSelection(0);
    assert.equal(editor.state('insertLink')[0], 'true');
});

test('the page picked for the selected text links it, and the tools of the link tell where it goes', async () => {
    const editor = await open({ document: doc(paragraph('Read this page')) });
    await editor.focus();
    editor.editor.commands.setTextSelection({ from: 1, to: 5 });

    await editor.press('insertLink');

    const dialog = editor.target('pageDialog');
    assert.equal(dialog.open, true);
    assert.equal(editor.target('pageDialogTitle').textContent, PAGE_LABELS.title);
    assertFocused(editor.target('pageSearch'));
    assert.deepEqual(editor.requests.map(({ url }) => url), ['https://blog.test/api/pages/search?title=&page=1']);
    assert.equal(editor.target('pageStatus').textContent, '2 results');
    assert.deepEqual(results(editor).map((button) => [button.type, button.className, button.textContent]), [
        ['button', 'rich-text-page-option', 'Target pagePublished'],
        ['button', 'rich-text-page-option', `Draft pageDraft${PAGE_LABELS.offSite}`],
    ]);
    assert.deepEqual([...results(editor)[1].querySelectorAll('span')].map((span) => span.className), [
        'rich-text-page-option-text', 'font-bold', 'rich-text-page-status', 'rich-text-page-note',
    ]);

    results(editor)[1].click();
    await editor.frame();

    assert.equal(dialog.open, false);
    assert.deepEqual(editor.posted(), doc({ type: 'paragraph', content: [{ type: 'text', text: 'Read', marks: [{ type: 'link', attrs: { type: 'page', id: 'page-2' } }] }, { type: 'text', text: ' this page' }] }));
    assert.deepEqual(links(editor), [['page-2', 'Read', 'rich-text-link-offSite']]);
    assert.equal(editor.target('linkTools').hidden, false);
    assert.equal(editor.target('linkTarget').textContent, `Linked page: Draft page. Draft. ${PAGE_LABELS.offSite}`);
    assert.deepEqual([editor.state('changeLink')[0], editor.state('unlink')[0]], ['false', 'false']);

    await editor.focus('end');
    assert.equal(editor.target('linkTools').hidden, true);
    assert.equal(editor.target('linkTarget').textContent, '');
    assert.deepEqual([editor.state('changeLink')[0], editor.state('unlink')[0]], ['true', 'true']);
});

test('the whole link under the cursor goes to another page, or is taken out, keeping its text', async () => {
    const editor = await open({ document: doc(link('Read this', 'page-1'), paragraph('After')) });
    await settle();
    await editor.focus(3);
    assert.equal(editor.target('linkTarget').textContent, 'Linked page: Target page. Published.');

    await editor.press('changeLink');
    editor.target('pageSearch').value = 'third';
    assert.equal(editor.key('Enter', {}, editor.target('pageSearch')), true);
    await settle();
    assert.equal(editor.requests.at(-1).url, 'https://blog.test/api/pages/search?title=third&page=1');
    results(editor)[0].click();
    assert.deepEqual(editor.posted().content[0], link('Read this', 'page-1'));

    await editor.press('changeLink');
    await editor.press(editor.target('pageMore'));
    results(editor).at(-1).click();
    assert.deepEqual(editor.posted().content[0], link('Read this', 'page-3'));

    await editor.focus(3);
    await editor.press('unlink');
    assert.deepEqual(editor.posted().content[0], paragraph('Read this'));
    assert.deepEqual(await editor.announced(), ['', PAGE_LABELS.removed]);
});

test('More adds the next pages of results, focused on the first of them, until there are no more', async () => {
    const editor = await open({ document: doc(paragraph('Read this page')), respond: pagesOf([TARGET, DRAFT, THIRD, { ...THIRD, id: 'page-4', title: 'Fourth page' }, { ...THIRD, id: 'page-5', title: 'Fifth page' }]) });
    await editor.focus();
    editor.editor.commands.setTextSelection({ from: 1, to: 5 });
    await editor.press('insertLink');
    const more = editor.target('pageMore');
    assert.equal(more.hidden, false);

    await editor.press(more);
    assert.equal(editor.requests.at(-1).url, 'https://blog.test/api/pages/search?title=&page=2');
    assert.deepEqual(results(editor).map((button) => button.textContent.slice(0, 5)), ['Targe', 'Draft', 'Third', 'Fourt']);
    assert.equal(editor.target('pageStatus').textContent, '4 results');
    assertFocused(results(editor)[2]);

    await editor.press(more);
    assert.equal(results(editor).length, 5);
    assert.equal(more.hidden, true);
    assertFocused(results(editor)[4]);

    await editor.press(searchButton(editor));
    assert.equal(results(editor).length, 2);
    assert.equal(editor.requests.at(-1).url, 'https://blog.test/api/pages/search?title=&page=1');
});

test('a page search that fails says so, keeps what was found and offers no more', async () => {
    let failing = false;
    const editor = await open({
        document: doc(paragraph('Text')),
        respond: (path, query) => {
            if (failing) {
                throw new Error('Unavailable');
            }

            return pagesOf([TARGET, DRAFT, THIRD])(path, query);
        },
    });
    await editor.press('insertWidget');

    failing = true;
    await editor.press(editor.target('pageMore'));

    assert.equal(editor.target('pageStatus').textContent, PAGE_LABELS.error);
    assert.equal(results(editor).length, 2);
    assert.equal(editor.target('pageMore').hidden, true);
});

test('a page search outdated by a newer one never shows', async () => {
    const stale = later();
    const editor = await open({
        document: doc(paragraph('Text')),
        respond: (path, query) => (query.get('title') === 'old' ? stale.promise : answer({ pages: [TARGET], more: false })),
    });
    editor.target('pageSearch').value = 'old';
    await editor.press('insertWidget');

    editor.target('pageSearch').value = 'new';
    await editor.press(searchButton(editor));
    stale.resolve(answer({ pages: [DRAFT, THIRD], more: true }));
    await settle();

    assert.deepEqual(results(editor).map((button) => button.textContent), ['Target pagePublished']);
    assert.equal(editor.target('pageMore').hidden, true);
});

test('a widget shows the page picked for it, in the words of widgets, where the cursor was', async () => {
    const editor = await open({
        document: doc(paragraph('Text')),
        respond: (path, query) => (query.get('title') === 'nothing' ? answer({ pages: [], more: false }) : pagesOf([DRAFT])(path, query)),
    });
    await editor.focus('end');

    await editor.press('insertWidget');

    assert.equal(editor.target('pageDialogTitle').textContent, WIDGET_LABELS.title);
    assert.equal(editor.target('pageStatus').textContent, WIDGET_LABELS.result);
    assert.deepEqual(results(editor).map((button) => button.textContent), [`Draft pageDraft${WIDGET_LABELS.offSite}`]);

    results(editor)[0].click();

    assert.equal(editor.target('pageDialog').open, false);
    assert.deepEqual(editor.posted(), doc(paragraph('Text'), widget('page-2')));
    assert.deepEqual(widgets(editor), [['related_page', `Related content:Draft pageDraft${WIDGET_LABELS.offSite}`]]);

    editor.target('pageSearch').value = 'nothing';
    await editor.press('insertWidget');
    assert.equal(editor.target('pageStatus').textContent, PAGE_LABELS.none);
});

test('a page found missing or unknown before, once picked, is known', async () => {
    const editor = await open({ document: doc(widget('page-1'), link('Link', 'page-2'), paragraph('End')), respond: (path) => (path.endsWith('/lookup') ? answer({ pages: [] }) : answer({ pages: [TARGET, DRAFT], more: false })) });
    await settle();
    assert.deepEqual(widgets(editor), [['related_page', 'Related content:Page not found']]);

    await editor.focus('end');
    await editor.press('insertWidget');
    results(editor)[0].click();
    assert.deepEqual(widgets(editor).map(([, text]) => text), ['Related content:Target pagePublished', 'Related content:Target pagePublished']);

    editor.editor.commands.setTextSelection({ from: 3, to: 5 });
    await editor.press('insertLink');
    results(editor)[1].click();
    assert.deepEqual(links(editor).map(([id, , look]) => [id, look]), [['page-2', 'rich-text-link-offSite']]);
});

test('a page lookup that fails, then a page picked, is no longer unknown', async () => {
    let lookups = 0;
    const editor = await open({
        document: doc(widget('page-1'), link('Link', 'page-2'), paragraph('End')),
        respond: (path) => {
            if (path.endsWith('/lookup')) {
                lookups += 1;
                throw new Error('Unavailable');
            }

            return answer({ pages: [TARGET, DRAFT], more: false });
        },
    });
    await settle();
    assert.deepEqual(widgets(editor), [['related_page', 'Related content:Page could not be checked']]);

    await editor.focus('end');
    await editor.press('insertWidget');
    results(editor)[0].click();
    editor.editor.commands.setTextSelection({ from: 3, to: 5 });
    await editor.press('insertLink');
    results(editor)[1].click();

    assert.equal(widgets(editor)[0][1], 'Related content:Target pagePublished');
    assert.deepEqual(links(editor).map(([id, , look]) => [id, look]), [['page-2', 'rich-text-link-offSite']]);
    assert.equal(lookups, 1);
});

test('without where to look pages up, links and widgets are left out of the controls\' work', async () => {
    const editor = await openEditor({ document: doc(paragraph('Text')), features: FEATURES });

    editor.editor.chain().focus('end').insertContent(' more').run();

    assert.deepEqual(editor.requests, []);
    assert.equal('link' in editor.editor.schema.marks, false);
});

test('an editor moved on the page looks its pages up again, and the answer to the old lookup is dropped', async () => {
    const answers = [later(), later()];
    let asked = 0;
    const editor = await open({ document: doc(widget('page-1')), respond: () => answers[asked++].promise });
    const element = editor.document.querySelector('[data-controller]');

    element.remove();
    await settle();
    editor.document.body.append(element);
    await settle();
    answers[0].resolve(answer({ pages: [{ ...TARGET, title: 'Stale' }] }));
    await settle();
    assert.deepEqual(widgets(editor), [['related_page', 'Related content:Loading page']]);

    answers[1].resolve(answer({ pages: [TARGET] }));
    await settle();
    assert.deepEqual(widgets(editor), [['related_page', 'Related content:Target pagePublished']]);
});

test('what is typed while the pages are looked up is undone in one step, the redrawing left out of it', async () => {
    const pending = later();
    const editor = await open({ document: doc(link('Linked', 'page-1'), paragraph('')), respond: () => pending.promise });
    await editor.focus('end');

    editor.editor.commands.insertContent('ab');
    pending.resolve(answer({ pages: [TARGET] }));
    await settle();
    editor.editor.commands.insertContent('cd');
    editor.editor.commands.undo();

    assert.deepEqual(editor.posted(), doc(link('Linked', 'page-1'), paragraph()));
});

test('a link to a page a widget shows as missing makes the widget show the page too', async () => {
    const editor = await open({ document: doc(widget('page-2'), paragraph('Read this')), respond: (path) => (path.endsWith('/lookup') ? answer({ pages: [] }) : answer({ pages: [DRAFT], more: false })) });
    await settle();
    await editor.focus();
    editor.editor.commands.setTextSelection({ from: 2, to: 6 });

    await editor.press('insertLink');
    results(editor)[0].click();

    assert.deepEqual(widgets(editor), [['related_page', `Related content:Draft pageDraft${WIDGET_LABELS.offSite}`]]);
});

test('a single line links pages too, drawn as the CMS knows them', async () => {
    const editor = await openEditor({
        singleParagraph: true,
        document: doc(link('Draft', 'page-2')),
        features: { blocks: [], headingLevels: [], marks: ['link'] },
        links: true,
        respond: pagesOf([DRAFT]),
    });
    await settle();

    assert.deepEqual(links(editor), [['page-2', 'Draft', 'rich-text-link-offSite']]);
});

test('More that brings no pages leaves the focus where it was', async () => {
    const editor = await open({
        document: doc(paragraph('Text')),
        respond: (path, query) => answer(query.get('page') === '1' ? { pages: [TARGET], more: true } : { pages: [], more: false }),
    });
    await editor.press('insertWidget');
    editor.target('pageMore').focus();

    await editor.press(editor.target('pageMore'));

    assert.equal(results(editor).length, 1);
    assert.equal(editor.target('pageStatus').textContent, WIDGET_LABELS.result);
});

test('a page picked is known, so editing after asks nothing about it', async () => {
    const editor = await open({ document: doc(paragraph('Text')) });
    await editor.focus('end');
    await editor.press('insertWidget');
    results(editor)[0].click();

    editor.editor.chain().focus('end').insertContent(' more').run();
    await settle();

    assert.deepEqual(editor.requests.map(({ url }) => new URL(url).pathname), ['/api/pages/search']);
});
