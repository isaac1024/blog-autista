import assert from 'node:assert/strict';
import test from 'node:test';
import ResourceField from '../../../assets/controllers/resource_field_controller.js';
import { RESOURCE_FIELD_LABELS as LABELS, resourceField } from '../../Markup/resource_field.js';
import { answer, assertFocused, later, mount, serve, settle } from '../support/dom.js';

const PAGE = { id: 'page-1', title: 'First page', status: 'Published', published: true };
const SECOND = { id: 'page-2', title: 'Second page', status: 'Draft', published: false };

const open = async (value = '') => {
    const page = await mount(resourceField(value), { 'resource-field': ResourceField });
    const { document } = page;
    const target = (name) => document.querySelector(`[data-resource-field-target="${name}"]`);

    return {
        ...page,
        target,
        /** Clicks the button of the given name. */
        click: async (name) => {
            [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === name).click();
            await settle();
        },
        shown: () => [target('title').textContent, target('state').textContent],
        results: () => [...target('results').querySelectorAll('button')],
    };
};

test('an empty field reveals the picker and says no page is chosen, looking nothing up', async () => {
    const requests = serve(() => answer({ pages: [] }));
    const field = await open();

    assert.equal(field.target('choose').hidden, false);
    assert.deepEqual(field.shown(), [LABELS.empty, '']);
    assert.deepEqual(requests, []);
});

test('the chosen page is told as loading, then as the CMS knows it now', async () => {
    const pending = later();
    const requests = serve(() => pending.promise);
    const field = await open(PAGE.id);

    assert.deepEqual(field.shown(), [LABELS.loading, '']);
    assert.deepEqual(requests.map(({ url }) => url), [`https://blog.test/api/pages/lookup?ids=${PAGE.id}`]);

    pending.resolve(answer({ pages: [{ ...PAGE, published: false }] }));
    await settle();
    assert.deepEqual(field.shown(), [PAGE.title, `${PAGE.status}. ${LABELS.offSite}`]);
});

test('a page the lookup doesn\'t find is missing; one it can\'t check, unknown', async () => {
    serve(() => answer({ pages: [] }));
    assert.deepEqual((await open(PAGE.id)).shown(), [LABELS.missing, '']);

    serve(() => { throw new Error('Unavailable'); });
    assert.deepEqual((await open(PAGE.id)).shown(), [LABELS.unknown, '']);
});

test('a field removed while it looks up cancels it, and the late answer changes nothing', async () => {
    const pending = later();
    const requests = serve(() => pending.promise);
    const field = await open(PAGE.id);
    const title = field.target('title');

    field.document.querySelector('[data-controller]').remove();
    await settle();
    pending.resolve(answer({ pages: [PAGE] }));
    await settle();

    assert.equal(requests[0].signal.aborted, true);
    assert.equal(title.textContent, LABELS.loading);
});

test('the picker searches every page at once, and a result chosen fills, tells and notifies the field', async () => {
    const requests = serve(() => answer({ pages: [PAGE, SECOND] }));
    const field = await open();
    let inputs = 0;
    field.document.querySelector('form').addEventListener('input', () => { inputs += 1; });

    await field.click('Choose page');

    assert.equal(field.target('dialog').open, true);
    assertFocused(field.target('search'));
    assert.deepEqual(requests.map(({ url }) => url), ['https://blog.test/api/pages/search?title=&page=1']);
    assert.equal(field.target('searchStatus').textContent, '2 results');
    assert.deepEqual(field.results().map((button) => [button.type, button.className, button.textContent]), [
        ['button', 'rich-text-page-option', 'First pagePublished'],
        ['button', 'rich-text-page-option', `Second pageDraft${LABELS.offSite}`],
    ]);
    // A page off the site says so before it is chosen.
    assert.deepEqual(
        field.results().map((result) => [...result.querySelectorAll('span')].map((span) => span.className)),
        [
            ['rich-text-page-option-text', 'font-bold', 'rich-text-page-status'],
            ['rich-text-page-option-text', 'font-bold', 'rich-text-page-status', 'rich-text-page-note'],
        ],
    );

    field.results()[1].click();

    assert.equal(field.target('input').value, SECOND.id);
    assert.equal(field.target('dialog').open, false);
    assert.deepEqual(field.shown(), [SECOND.title, `${SECOND.status}. ${LABELS.offSite}`]);
    assert.equal(field.target('status').textContent, 'Chosen Second page');
    assertFocused(field.target('choose'));
    assert.equal(inputs, 1);
});

test('a search sends the title typed and tells how many pages answer it', async () => {
    const requests = serve((url) => answer({ pages: new URL(url).searchParams.get('title') === 'first' ? [PAGE] : [] }));
    const field = await open();
    await field.click('Choose page');

    field.target('search').value = 'first';
    await field.click('Search');
    assert.equal(requests.at(-1).url, 'https://blog.test/api/pages/search?title=first&page=1');
    assert.equal(field.target('searchStatus').textContent, LABELS.result);
    assert.equal(field.results().length, 1);

    field.target('search').value = 'nothing';
    await field.click('Search');
    assert.equal(field.target('searchStatus').textContent, LABELS.none);
    assert.equal(field.results().length, 0);
});

test('a search that fails keeps the results shown and says it failed', async () => {
    let failing = false;
    serve(() => {
        if (failing) {
            throw new Error('Unavailable');
        }

        return answer({ pages: [PAGE] });
    });
    const field = await open();
    await field.click('Choose page');

    failing = true;
    await field.click('Search');

    assert.equal(field.target('searchStatus').textContent, LABELS.error);
    assert.equal(field.results().length, 1);
});

test('only the newest search draws its results', async () => {
    const stale = later();
    serve((url) => (new URL(url).searchParams.get('title') === 'old' ? stale.promise : answer({ pages: [PAGE] })));
    const field = await open();
    field.target('search').value = 'old';
    await field.click('Choose page');

    field.target('search').value = 'new';
    await field.click('Search');
    stale.resolve(answer({ pages: [SECOND] }));
    await settle();

    assert.deepEqual(field.results().map((button) => button.textContent), ['First pagePublished']);
    assert.equal(field.target('searchStatus').textContent, LABELS.result);
});

test('More adds the next page of results, focused on its first, until there are no more', async () => {
    const requests = serve((url) => {
        const page = Number(new URL(url).searchParams.get('page'));

        return answer(page === 1 ? { pages: [PAGE], more: true } : { pages: [SECOND], more: false });
    });
    const field = await open();
    await field.click('Choose page');
    assert.equal(field.target('more').hidden, false);

    await field.click('More');

    assert.equal(requests.at(-1).url, 'https://blog.test/api/pages/search?title=&page=2');
    assert.deepEqual(field.results().map((button) => button.textContent), ['First pagePublished', `Second pageDraft${LABELS.offSite}`]);
    assert.equal(field.target('searchStatus').textContent, '2 results');
    assert.equal(field.target('more').hidden, true);
    assertFocused(field.results()[1]);
});
