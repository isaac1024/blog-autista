import assert from 'node:assert/strict';
import test from 'node:test';
import SearchPreview from '../../../assets/controllers/search_preview_controller.js';
import { searchPreview } from '../../Markup/small_controllers.js';
import { mount, settle } from '../support/dom.js';

const SOURCES = '{"title":"page-title","description":"page-description","slug":"page-slug"}';

const open = async (markup = searchPreview()) => {
    const { document, window } = await mount(markup, { 'search-preview': SearchPreview });
    const shown = (name) => document.querySelector(`[data-search-preview-target="${name}"]`).textContent;

    return {
        document,
        type: (id, value) => {
            const field = document.getElementById(id);
            field.value = value;
            field.dispatchEvent(new window.Event('input', { bubbles: true }));
        },
        result: () => [shown('url'), shown('title'), shown('description')],
    };
};

test('the preview follows what is written in the fields it previews, wherever they are', async () => {
    const { type, result } = await open();

    type('page-title', 'First page of the blog, with a long title');
    assert.deepEqual(result(), ['https://blogautista.test/first-page.html', 'First page of the b…', 'No description']);

    type('page-description', ' About   the\nfirst page of the blog, at length ');
    assert.deepEqual(result()[2], 'About the first page of the b…');

    type('page-slug', ' first ');
    assert.deepEqual(result()[0], 'https://blogautista.test/first.html');
});

test('without a slug source the page is the home of the site', async () => {
    const { type, result } = await open(searchPreview().replace(SOURCES, '{"title":"page-title","description":"page-description"}'));

    type('page-title', 'Home');

    assert.deepEqual(result(), ['https://blogautista.test/', 'Home', 'No description']);
});

test('a source that is no field gives no text, and one that is missing is skipped', async () => {
    const { document, type, result } = await open(searchPreview().replace(SOURCES, '{"title":"heading","description":"page-description","slug":"missing"}'));
    document.body.insertAdjacentHTML('afterbegin', '<h1 id="heading">Not a field</h1>');

    type('page-description', 'About it');

    assert.deepEqual(result(), ['https://blogautista.test/.html', '', 'About it']);
});

test('once the preview is gone, the fields no longer feed it', async () => {
    const { document, type, result } = await open();
    type('page-title', 'First page');
    const preview = document.querySelector('figure');
    preview.remove();
    await settle();

    type('page-title', 'Changed after it left');
    document.body.append(preview);
    const before = result();

    assert.equal(before[1], 'First page');
});
