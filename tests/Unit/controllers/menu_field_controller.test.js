import assert from 'node:assert/strict';
import test from 'node:test';
import MenuField from '../../../assets/controllers/menu_field_controller.js';
import { MENU_FIELD_LABELS as LABELS, menuField } from '../../Markup/menu_field.js';
import { answer, assertFocused, later, mount, serve, settle } from '../support/dom.js';

const FIRST = { id: 'page-1', title: 'First page', status: 'Published', published: true };
const SECOND = { id: 'page-2', title: 'Second page', status: 'Draft', published: false };
const THIRD = { id: 'page-3', title: 'Third page', status: 'Published', published: true };

/** A menu of the given entries, whose lookup and search answer as given. */
const open = async ({ items = [], max = 3, lookup = () => answer({ pages: [FIRST, SECOND, THIRD] }), search = () => answer({ pages: [], more: false }) } = {}) => {
    const requests = serve((url) => (new URL(url).pathname.endsWith('/lookup') ? lookup(url) : search(url)));
    const page = await mount(menuField({ items, max }), { 'menu-field': MenuField });
    await settle();
    const { document } = page;
    const target = (name) => document.querySelector(`[data-menu-field-target="${name}"]`);
    const rows = () => [...document.querySelectorAll('[data-menu-field-target="row"]')];
    const button = (row, name) => row.querySelector(`[data-field="${name}"]`);

    return {
        ...page,
        requests,
        target,
        rows,
        button,
        /** Each entry: its page, its fields' names and ids, and its buttons' states. */
        entries: () => rows().map((row) => ({
            page: row.dataset.pageId,
            value: button(row, 'page_id').value,
            names: [button(row, 'page_id').name, button(row, 'label').name],
            label: [button(row, 'label').id, button(row, 'label-for').htmlFor, button(row, 'label').getAttribute('aria-describedby')],
            described: ['up', 'down', 'remove'].map((name) => button(row, name).getAttribute('aria-describedby')),
            disabled: [button(row, 'up').getAttribute('aria-disabled'), button(row, 'down').getAttribute('aria-disabled')],
            shown: [row.querySelector('[data-menu-field-target="pageTitle"]').textContent, row.querySelector('[data-menu-field-target="pageStatus"]').textContent],
            placeholder: button(row, 'label').placeholder,
        })),
        /** Whether the menu tells it is empty, it is full, and adding is held. */
        state: () => [target('empty').hidden, target('full').hidden, target('add').getAttribute('aria-disabled')],
        status: () => target('status').textContent,
        results: () => [...target('results').querySelectorAll('button')],
        press: async (element) => {
            element.click();
            await settle();
        },
    };
};

test('an empty menu says so, offers to add a page and looks nothing up', async () => {
    const menu = await open();

    assert.equal(menu.target('add').hidden, false);
    assert.deepEqual(menu.state(), [false, true, 'false']);
    assert.deepEqual(menu.requests, []);
});

test('the entries are looked up at once, then each is named, numbered and told where it stands', async () => {
    const menu = await open({ items: [FIRST, SECOND, { id: 'gone' }] });

    assert.deepEqual(menu.requests.map(({ url }) => url), ['https://blog.test/api/pages/lookup?ids=page-1%2Cpage-2%2Cgone']);
    assert.deepEqual(menu.state(), [true, false, 'true']);
    assert.deepEqual(menu.entries(), [
        {
            page: 'page-1',
            value: 'page-1',
            names: ['menu[0][page_id]', 'menu[0][label]'],
            label: ['menu-0-label', 'menu-0-label', 'menu-label-hint menu-0-page'],
            described: ['menu-0-page', 'menu-0-page', 'menu-0-page'],
            disabled: ['true', 'false'],
            shown: ['First page', 'Published'],
            placeholder: 'First page',
        },
        {
            page: 'page-2',
            value: 'page-2',
            names: ['menu[1][page_id]', 'menu[1][label]'],
            label: ['menu-1-label', 'menu-1-label', 'menu-label-hint menu-1-page'],
            described: ['menu-1-page', 'menu-1-page', 'menu-1-page'],
            disabled: ['false', 'false'],
            shown: ['Second page', `Draft. ${LABELS.offSite}`],
            placeholder: 'Second page',
        },
        {
            page: 'gone',
            value: 'gone',
            names: ['menu[2][page_id]', 'menu[2][label]'],
            label: ['menu-2-label', 'menu-2-label', 'menu-label-hint menu-2-page'],
            described: ['menu-2-page', 'menu-2-page', 'menu-2-page'],
            disabled: ['false', 'true'],
            shown: [LABELS.missing, ''],
            placeholder: '',
        },
    ]);
});

test('entries are told as loading while looked up, and as unknown when the lookup fails', async () => {
    const pending = later();
    const menu = await open({ items: [FIRST], lookup: () => pending.promise });
    assert.deepEqual(menu.entries()[0].shown, [LABELS.loading, '']);

    pending.reject(new Error('Unavailable'));
    await settle();
    assert.deepEqual(menu.entries()[0].shown, [LABELS.unknown, '']);
});

test('a menu removed while it looks up cancels it, and the late answer changes nothing', async () => {
    const pending = later();
    const menu = await open({ items: [FIRST], lookup: () => pending.promise });
    const [row] = menu.rows();

    menu.document.getElementById('menu').remove();
    await settle();
    pending.resolve(answer({ pages: [FIRST] }));
    await settle();

    assert.equal(menu.requests[0].signal.aborted, true);
    assert.equal(row.textContent.includes(FIRST.title), false);
});

test('a page picked joins the menu as a numbered entry, told and focused on its label', async () => {
    const menu = await open({ max: 2, search: () => answer({ pages: [FIRST, SECOND], more: false }) });
    let inputs = 0;
    menu.document.querySelector('form').addEventListener('input', () => { inputs += 1; });

    await menu.press(menu.target('add'));
    await menu.press(menu.results()[0]);

    assert.equal(menu.target('dialog').open, false);
    assert.deepEqual(menu.entries().map(({ page, value, names, shown, placeholder }) => ({ page, value, names, shown, placeholder })), [
        { page: 'page-1', value: 'page-1', names: ['menu[0][page_id]', 'menu[0][label]'], shown: ['First page', 'Published'], placeholder: 'First page' },
    ]);
    assert.equal(menu.status(), 'Added First page');
    assertFocused(menu.button(menu.rows()[0], 'label'));
    assert.deepEqual(menu.state(), [true, true, 'false']);
    assert.equal(inputs, 1);

    await menu.press(menu.target('add'));
    await menu.press(menu.results()[0]);
    assert.equal(menu.rows().length, 1);
    assert.equal(menu.status(), LABELS.repeated);

    await menu.press(menu.results()[1]);
    assert.deepEqual(menu.rows().map((row) => row.dataset.pageId), ['page-1', 'page-2']);
    assert.deepEqual(menu.state(), [true, false, 'true']);
    assert.equal(inputs, 2);
});

test('a full menu opens no search, and a page picked meanwhile is not added', async () => {
    const menu = await open({ items: [FIRST], max: 2, search: () => answer({ pages: [SECOND, THIRD], more: false }) });
    await menu.press(menu.target('add'));
    await menu.press(menu.results()[0]);
    const searches = menu.requests.length;

    await menu.press(menu.target('add'));
    assert.equal(menu.target('dialog').open, false);
    assert.equal(menu.requests.length, searches);

    await menu.press(menu.results()[1]);
    assert.deepEqual(menu.rows().map((row) => row.dataset.pageId), ['page-1', 'page-2']);
});

test('a template without an entry\'s fields is told as broken instead of drawing half an entry', async () => {
    for (const [broken, told] of [
        ['<li data-menu-field-target="row" data-page-id="">', 'TypeError: Missing [data-field="page_id"]'],
        ['<div data-menu-field-target="row" data-page-id=""><input data-field="page_id"></div>', 'TypeError: Missing menu row template'],
    ]) {
        serve(() => answer({ pages: [FIRST], more: false }));
        const markup = menuField().replace(/<template data-menu-field-target="template">[\s\S]*?<\/template>/, `<template data-menu-field-target="template">${broken}</template>`);
        const page = await mount(markup, { 'menu-field': MenuField });
        page.document.querySelector('[data-menu-field-target="add"]').click();
        await settle();

        page.document.querySelector('[data-menu-field-target="results"] button').click();

        assert.equal(page.document.querySelectorAll('[data-menu-field-target="list"] > *').length, 0);
        assert.deepEqual(page.thrown(), [told]);
    }
});

test('an entry moves up and down, renumbered and told, with the focus kept where it can still move', async () => {
    const menu = await open({ items: [FIRST, SECOND, THIRD] });
    let inputs = 0;
    menu.document.querySelector('form').addEventListener('input', () => { inputs += 1; });
    const order = () => menu.rows().map((row) => row.dataset.pageId);
    const third = menu.rows()[2];

    await menu.press(menu.button(third, 'up'));
    assert.deepEqual(order(), ['page-1', 'page-3', 'page-2']);
    assert.deepEqual(menu.entries()[1].names, ['menu[1][page_id]', 'menu[1][label]']);
    assert.equal(menu.status(), 'Moved up Third page');
    assertFocused(menu.button(third, 'up'));

    await menu.press(menu.button(third, 'up'));
    assert.deepEqual(order(), ['page-3', 'page-1', 'page-2']);
    assert.deepEqual(menu.entries().map(({ disabled }) => disabled), [['true', 'false'], ['false', 'false'], ['false', 'true']]);
    assertFocused(menu.button(third, 'down'));

    await menu.press(menu.button(third, 'up'));
    assert.deepEqual(order(), ['page-3', 'page-1', 'page-2']);

    await menu.press(menu.button(third, 'down'));
    assert.deepEqual(order(), ['page-1', 'page-3', 'page-2']);
    assert.equal(menu.status(), 'Moved down Third page');
    assertFocused(menu.button(third, 'down'));

    await menu.press(menu.button(third, 'down'));
    assert.deepEqual(order(), ['page-1', 'page-2', 'page-3']);
    assertFocused(menu.button(third, 'up'));

    await menu.press(menu.button(third, 'down'));
    assert.deepEqual(order(), ['page-1', 'page-2', 'page-3']);
    assert.equal(inputs, 4);
});

test('a removed entry is told, and the focus goes to the next one, the one before, or adding a page', async () => {
    const menu = await open({ items: [FIRST, SECOND, THIRD] });
    let inputs = 0;
    menu.document.querySelector('form').addEventListener('input', () => { inputs += 1; });
    const [, second, third] = menu.rows();

    await menu.press(menu.button(menu.rows()[0], 'remove'));
    assert.deepEqual(menu.rows().map((row) => row.dataset.pageId), ['page-2', 'page-3']);
    assert.deepEqual(menu.entries()[0].names, ['menu[0][page_id]', 'menu[0][label]']);
    assert.equal(menu.status(), 'Removed First page');
    assertFocused(menu.button(second, 'remove'));

    await menu.press(menu.button(third, 'remove'));
    assertFocused(menu.button(second, 'remove'));

    await menu.press(menu.button(second, 'remove'));
    assertFocused(menu.target('add'));
    assert.deepEqual(menu.state(), [false, true, 'false']);
    assert.equal(inputs, 3);
});

test('a button pressed outside an entry moves and removes nothing', async () => {
    const menu = await open({ items: [FIRST, SECOND] });
    for (const action of ['moveUp', 'moveDown', 'remove']) {
        menu.target('status').insertAdjacentHTML('beforebegin', `<button type="button" data-action="menu-field#${action}">${action}</button>`);
    }
    // Stimulus binds the actions of new markup once its observer runs.
    await settle();

    for (const action of ['moveUp', 'moveDown', 'remove']) {
        await menu.press([...menu.document.querySelectorAll('button')].find((element) => element.textContent === action));
    }

    assert.deepEqual(menu.rows().map((row) => row.dataset.pageId), ['page-1', 'page-2']);
    assert.equal(menu.status(), '');
});

test('the search asks for its first page of results, and More adds the next one, focused on its first', async () => {
    const menu = await open({
        max: 5,
        search: (url) => {
            const page = Number(new URL(url).searchParams.get('page'));

            return answer(page === 1 ? { pages: [FIRST, SECOND], more: true } : { pages: page === 2 ? [THIRD] : [], more: page === 2 });
        },
    });

    await menu.press(menu.target('add'));
    assert.equal(menu.target('dialog').open, true);
    assertFocused(menu.target('search'));
    assert.deepEqual(menu.requests.map(({ url }) => url), ['https://blog.test/api/pages/search?title=&page=1']);
    assert.equal(menu.target('searchStatus').textContent, '2 results');
    assert.equal(menu.target('more').hidden, false);
    assert.deepEqual(menu.results().map((button) => [button.type, button.className, button.textContent]), [
        ['button', 'rich-text-page-option', 'First pagePublished'],
        ['button', 'rich-text-page-option', `Second pageDraft${LABELS.offSite}`],
    ]);
    // A page off the site says so, before it joins the menu.
    assert.deepEqual(
        menu.results().map((result) => [...result.querySelectorAll('span')].map((span) => span.className)),
        [
            ['rich-text-page-option-text', 'font-bold', 'rich-text-page-status'],
            ['rich-text-page-option-text', 'font-bold', 'rich-text-page-status', 'rich-text-page-note'],
        ],
    );

    await menu.press(menu.target('more'));
    assert.equal(menu.requests.at(-1).url, 'https://blog.test/api/pages/search?title=&page=2');
    assert.equal(menu.results().length, 3);
    assert.equal(menu.target('searchStatus').textContent, '3 results');
    assertFocused(menu.results()[2]);

    await menu.press(menu.target('more'));
    assert.equal(menu.results().length, 3);
    assert.equal(menu.target('more').hidden, true);
    assertFocused(menu.results()[2]);

    menu.target('search').value = 'first';
    menu.target('search').focus();
    await menu.press([...menu.document.querySelectorAll('button')].find((element) => element.textContent === 'Search'));
    assert.equal(menu.requests.at(-1).url, 'https://blog.test/api/pages/search?title=first&page=1');
    assert.equal(menu.results().length, 2);
    assert.equal(menu.target('searchStatus').textContent, '2 results');
    assertFocused(menu.target('search'));
});

test('a search that fails says so, keeps what was shown and offers no more', async () => {
    let failing = false;
    const menu = await open({
        search: () => {
            if (failing) {
                throw new Error('Unavailable');
            }

            return answer({ pages: [FIRST], more: true });
        },
    });
    await menu.press(menu.target('add'));

    failing = true;
    await menu.press(menu.target('more'));

    assert.equal(menu.target('searchStatus').textContent, LABELS.error);
    assert.equal(menu.results().length, 1);
    assert.equal(menu.target('more').hidden, true);
});

test('only the newest search draws its results', async () => {
    const stale = later();
    const menu = await open({
        search: (url) => (new URL(url).searchParams.get('title') === 'old' ? stale.promise : answer({ pages: [FIRST], more: false })),
    });
    menu.target('search').value = 'old';
    await menu.press(menu.target('add'));

    menu.target('search').value = 'new';
    await menu.press([...menu.document.querySelectorAll('button')].find((element) => element.textContent === 'Search'));
    stale.resolve(answer({ pages: [SECOND, THIRD], more: true }));
    await settle();

    assert.deepEqual(menu.results().map((button) => button.textContent), ['First pagePublished']);
    assert.equal(menu.target('searchStatus').textContent, LABELS.result);
    assert.equal(menu.target('more').hidden, true);
});

test('an entry whose page wasn\'t found is removed and moved without a title to tell', async () => {
    const menu = await open({ items: [FIRST, { id: 'gone' }], lookup: () => answer({ pages: [FIRST] }) });

    await menu.press(menu.button(menu.rows()[1], 'up'));
    assert.equal(menu.status(), 'Moved up ');

    await menu.press(menu.button(menu.rows()[0], 'remove'));
    assert.equal(menu.status(), 'Removed ');
});
