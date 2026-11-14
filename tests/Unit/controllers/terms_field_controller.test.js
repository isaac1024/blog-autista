import assert from 'node:assert/strict';
import test from 'node:test';
import TermsField from '../../../assets/controllers/terms_field_controller.js';
import { TERMS_FIELD_LABELS as LABELS, termsField } from '../../Markup/terms_field.js';
import { answer, assertFocused, later, mount, serve, settle } from '../support/dom.js';

const CALM = { id: 'tag-1', title: 'Calm', status: 'Published', published: true };
const NOISE = { id: 'tag-2', title: 'Noise', status: 'Draft', published: false };
const LIGHT = { id: 'tag-3', title: 'Light', status: 'Published', published: true };

/** The tags given, whose search answers as given. */
const open = async ({ items = [], search = () => answer({ terms: [], more: false }) } = {}) => {
    const requests = serve((url) => search(url));
    const page = await mount(termsField({ items }), { 'terms-field': TermsField });
    await settle();
    const { document } = page;
    const target = (name) => document.querySelector(`[data-terms-field-target="${name}"]`);
    const rows = () => [...document.querySelectorAll('[data-terms-field-target="row"]')];
    const field = (row, name) => row.querySelector(`[data-field="${name}"]`);

    return {
        ...page,
        requests,
        target,
        rows,
        field,
        /** Each tag: what it posts, how it is named, and what describes its button. */
        tags: () => rows().map((row) => ({
            id: row.dataset.termId,
            posted: [field(row, 'id').name, field(row, 'id').value],
            shown: [field(row, 'name').textContent, field(row, 'status').textContent],
            described: [field(row, 'term').id, field(row, 'remove').getAttribute('aria-describedby')],
        })),
        posted: () => new FormData(document.querySelector('form')).getAll('tags[]'),
        status: () => target('status').textContent,
        results: () => [...target('results').querySelectorAll('button')],
        press: async (element) => {
            element.click();
            await settle();
        },
    };
};

test('the field offers to add tags once it runs, and searches nothing until asked', async () => {
    const field = await open();

    assert.equal(field.target('add').hidden, false);
    assert.equal(field.target('empty').hidden, false);
    assert.deepEqual(field.requests, []);
});

test('adding opens the search, focused, with the first page of tags, each saying where it stands', async () => {
    const field = await open({ search: () => answer({ terms: [CALM, NOISE], more: false }) });

    await field.press(field.target('add'));

    assert.equal(field.target('dialog').open, true);
    assertFocused(field.target('search'));
    assert.deepEqual(field.requests.map(({ url }) => url), ['https://blog.test/api/tags/search?name=&page=1']);
    assert.equal(field.target('searchStatus').textContent, '2 results');
    assert.deepEqual(field.results().map((button) => [button.type, button.className, button.textContent]), [
        ['button', 'rich-text-term-option', 'CalmPublished'],
        ['button', 'rich-text-term-option', `NoiseDraft${LABELS.offSite}`],
    ]);
    assert.deepEqual(
        field.results().map((result) => [...result.querySelectorAll('span')].map((span) => span.className)),
        [
            ['rich-text-term-option-text', 'font-bold', 'rich-text-term-status'],
            ['rich-text-term-option-text', 'font-bold', 'rich-text-term-status', 'rich-text-term-note'],
        ],
    );
});

test('a tag picked joins the list, posted, named, told and focused on its button, once', async () => {
    const field = await open({ items: [CALM], search: () => answer({ terms: [CALM, NOISE], more: false }) });
    let inputs = 0;
    field.document.querySelector('form').addEventListener('input', () => { inputs += 1; });

    await field.press(field.target('add'));
    await field.press(field.results()[1]);

    assert.equal(field.target('dialog').open, false);
    assert.deepEqual(field.tags()[1], {
        id: 'tag-2',
        posted: ['tags[]', 'tag-2'],
        shown: ['Noise', `Draft · ${LABELS.offSite}`],
        described: ['tags-tag-2', 'tags-tag-2'],
    });
    assert.deepEqual(field.posted(), ['tag-1', 'tag-2']);
    assert.equal(field.status(), 'Added Noise');
    assertFocused(field.field(field.rows()[1], 'remove'));
    assert.equal(field.target('empty').hidden, true);
    assert.equal(inputs, 1);

    await field.press(field.target('add'));
    await field.press(field.results()[0]);
    assert.deepEqual(field.posted(), ['tag-1', 'tag-2']);
    assert.equal(field.status(), 'Calm is already listed');
    assert.equal(field.target('dialog').open, true);
    assert.equal(inputs, 1);
});

test('a tag on the site joins the list with its status alone', async () => {
    const field = await open({ search: () => answer({ terms: [LIGHT], more: false }) });

    await field.press(field.target('add'));
    await field.press(field.results()[0]);

    assert.deepEqual(field.tags()[0].shown, ['Light', 'Published']);
    assert.equal(field.target('empty').hidden, true);
});

test('a removed tag is told, and the focus goes to the next one, the one before, or adding a tag', async () => {
    const field = await open({ items: [CALM, NOISE, LIGHT] });
    let inputs = 0;
    field.document.querySelector('form').addEventListener('input', () => { inputs += 1; });
    const [, second, third] = field.rows();

    await field.press(field.field(field.rows()[0], 'remove'));
    assert.deepEqual(field.posted(), ['tag-2', 'tag-3']);
    assert.equal(field.status(), 'Removed Calm');
    assertFocused(field.field(second, 'remove'));
    assert.equal(field.target('empty').hidden, true);

    await field.press(field.field(third, 'remove'));
    assertFocused(field.field(second, 'remove'));

    await field.press(field.field(second, 'remove'));
    assertFocused(field.target('add'));
    assert.equal(field.target('empty').hidden, false);
    assert.deepEqual(field.posted(), []);
    assert.equal(inputs, 3);
});

test('a button pressed outside a tag removes nothing', async () => {
    const field = await open({ items: [CALM] });
    field.target('status').insertAdjacentHTML('beforebegin', '<button type="button" data-action="terms-field#remove">stray</button>');
    // Stimulus binds the actions of new markup once its observer runs.
    await settle();

    await field.press([...field.document.querySelectorAll('button')].find((element) => element.textContent === 'stray'));

    assert.deepEqual(field.posted(), ['tag-1']);
    assert.equal(field.status(), '');
});

test('a template without the fields of a tag is told as broken instead of drawing half of one', async () => {
    for (const [broken, told] of [
        ['<li data-terms-field-target="row" data-term-id="">', 'TypeError: Missing [data-field="id"]'],
        ['<div data-terms-field-target="row"><input data-field="id"></div>', 'TypeError: Missing term row template'],
    ]) {
        serve(() => answer({ terms: [CALM], more: false }));
        const markup = termsField().replace(/<template data-terms-field-target="template">[\s\S]*?<\/template>/, `<template data-terms-field-target="template">${broken}</template>`);
        const page = await mount(markup, { 'terms-field': TermsField });
        page.document.querySelector('[data-terms-field-target="add"]').click();
        await settle();

        page.document.querySelector('[data-terms-field-target="results"] button').click();

        assert.equal(page.document.querySelectorAll('[data-terms-field-target="list"] > *').length, 0);
        assert.deepEqual(page.thrown(), [told]);
    }
});

test('the search takes what is typed, with Enter or its button, and More adds the next page', async () => {
    const field = await open({
        search: (url) => {
            const page = Number(new URL(url).searchParams.get('page'));

            return answer(page === 1 ? { terms: [CALM], more: true } : { terms: [NOISE], more: false });
        },
    });
    await field.press(field.target('add'));

    await field.press(field.target('more'));
    assert.equal(field.requests.at(-1).url, 'https://blog.test/api/tags/search?name=&page=2');
    assert.equal(field.results().length, 2);
    assert.equal(field.target('more').hidden, true);
    assertFocused(field.results()[1]);

    field.target('search').value = 'calm';
    field.target('search').dispatchEvent(new field.window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    await settle();
    assert.equal(field.requests.at(-1).url, 'https://blog.test/api/tags/search?name=calm&page=1');
    assert.equal(field.results().length, 1);

    field.target('search').value = 'noise';
    await field.press([...field.document.querySelectorAll('button')].find((element) => element.textContent === 'Search'));
    assert.equal(field.requests.at(-1).url, 'https://blog.test/api/tags/search?name=noise&page=1');
});

test('a search that fails says so and keeps what was shown', async () => {
    let failing = false;
    const field = await open({
        search: () => {
            if (failing) {
                throw new Error('Unavailable');
            }

            return answer({ terms: [CALM], more: true });
        },
    });
    await field.press(field.target('add'));

    failing = true;
    await field.press(field.target('more'));

    assert.equal(field.target('searchStatus').textContent, LABELS.error);
    assert.equal(field.results().length, 1);
});

test('a field removed while it searches cancels it, and the late answer draws nothing', async () => {
    const pending = later();
    const field = await open({ search: () => pending.promise });
    await field.press(field.target('add'));
    const results = field.target('results');

    field.document.getElementById('tags').remove();
    await settle();
    pending.resolve(answer({ terms: [CALM], more: false }));
    await settle();

    assert.equal(field.requests[0].signal.aborted, true);
    assert.equal(results.children.length, 0);
});
