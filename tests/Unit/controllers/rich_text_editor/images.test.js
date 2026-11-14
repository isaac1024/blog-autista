import assert from 'node:assert/strict';
import test from 'node:test';
import { IMAGE_LABELS } from '../../../Markup/rich_text_editor.js';
import { answer, assertFocused, later, settle } from '../../support/dom.js';
import { doc, openEditor, paragraph } from '../../support/rich_text_editor.js';

const FEATURES = { blocks: ['image', 'table'], headingLevels: [], marks: [] };
const MOUNTAINS = { id: 'image-1', alt: 'Mountains at dawn', caption: 'The Alps', status: 'Published', published: true, thumbnail: '/media/image-1-400.webp' };
const DOOR = { id: 'image-2', alt: 'A blue door', caption: '', status: 'Draft', published: false, thumbnail: '/media/image-2-400.webp' };
const image = (id) => ({ type: 'image', attrs: { id } });

/** The images of the text, as drawn: their ids and texts. */
const drawn = (editor) => [...editor.controller.editor.view.dom.querySelectorAll('figure')].map((figure) => [figure.dataset.imageId, figure.textContent]);

const open = (options = {}) => openEditor({ features: FEATURES, images: true, ...options });

test('the images embedded are looked up at once, each once, told as loading meanwhile, then drawn as the library knows them', async () => {
    const pending = later();
    const editor = await open({ document: doc(image('image-1'), paragraph('Text'), image('image-2'), image(null), image('gone'), image('image-1')), respond: () => pending.promise });

    assert.deepEqual(editor.requests.map(({ url }) => url), ['https://blog.test/api/images/lookup?ids=image-1%2Cimage-2%2Cgone']);
    // An image without an id is of no image at all, with nothing to ask.
    assert.deepEqual(drawn(editor).map(([, text]) => text), [IMAGE_LABELS.loading, IMAGE_LABELS.loading, IMAGE_LABELS.missing, IMAGE_LABELS.loading, IMAGE_LABELS.loading]);

    pending.resolve(answer({ images: [MOUNTAINS, DOOR] }));
    await settle();

    assert.deepEqual(drawn(editor), [
        ['image-1', 'PublishedEmbedded image: Mountains at dawnThe Alps'],
        ['image-2', `DraftEmbedded image: A blue door${IMAGE_LABELS.offSite}`],
        ['null', IMAGE_LABELS.missing],
        ['gone', IMAGE_LABELS.missing],
        ['image-1', 'PublishedEmbedded image: Mountains at dawnThe Alps'],
    ]);
});

test('images that can\'t be checked are told so, and a text without images looks nothing up', async () => {
    const failing = await open({ document: doc(image('image-1')), respond: () => { throw new Error('Unavailable'); } });
    assert.deepEqual(drawn(failing), [['image-1', IMAGE_LABELS.unknown]]);

    const empty = await open({ document: doc(paragraph('Text')) });
    assert.deepEqual(empty.requests, []);
});

test('an image pasted that the editor doesn\'t know is looked up, and one it knows is not', async () => {
    const editor = await open({ document: doc(image('image-1'), paragraph('Text')), respond: (path, query) => answer({ images: query.get('ids') === 'pasted' ? [{ ...DOOR, id: 'pasted' }] : [MOUNTAINS] }) });
    await settle();

    editor.editor.chain().focus('end').insertContent([image('pasted'), image('image-1')]).run();
    assert.deepEqual(drawn(editor).map(([, text]) => text).slice(1), [IMAGE_LABELS.loading, 'PublishedEmbedded image: Mountains at dawnThe Alps']);
    await settle();

    assert.deepEqual(editor.requests.map(({ url }) => url), [
        'https://blog.test/api/images/lookup?ids=image-1',
        'https://blog.test/api/images/lookup?ids=pasted',
    ]);
    assert.deepEqual(drawn(editor)[1], ['pasted', `DraftEmbedded image: A blue door${IMAGE_LABELS.offSite}`]);
});

test('an editor removed while it looks images up draws nothing of the late answer', async () => {
    const pending = later();
    const editor = await open({ document: doc(image('image-1')), respond: () => pending.promise });
    const [figure] = editor.editor.view.dom.querySelectorAll('figure');

    editor.document.querySelector('[data-controller]').remove();
    await settle();
    pending.resolve(answer({ images: [MOUNTAINS] }));
    await settle();

    assert.equal(figure.textContent, IMAGE_LABELS.loading);
});

test('an image is picked from the library, searched by its text, and embedded where the cursor was', async () => {
    const editor = await open({
        document: doc(paragraph('Text')),
        respond: (path, query) => answer({ images: query.get('alt') === 'door' ? [DOOR] : [MOUNTAINS, DOOR] }),
    });
    await editor.focus('end');

    await editor.press('insertImage');
    const dialog = editor.target('imageDialog');
    assert.equal(dialog.open, true);
    assertFocused(editor.target('imageSearch'));
    assert.deepEqual(editor.requests.map(({ url }) => url), ['https://blog.test/api/images/search?alt=']);
    assert.equal(editor.target('imageStatus').textContent, '2 results');
    const results = () => [...editor.target('imageResults').querySelectorAll('button')];
    assert.deepEqual(results().map((button) => [button.type, button.className, button.textContent]), [
        ['button', 'rich-text-image-option', 'Mountains at dawnPublished'],
        ['button', 'rich-text-image-option', 'A blue doorDraft'],
    ]);
    const [thumbnail] = results()[0].querySelectorAll('img');
    assert.deepEqual([thumbnail.getAttribute('src'), thumbnail.alt, thumbnail.width, thumbnail.height, thumbnail.loading], [MOUNTAINS.thumbnail, '', 112, 63, 'lazy']);
    assert.deepEqual([...results()[0].querySelectorAll('span')].map((span) => span.className), ['rich-text-image-option-text', 'font-bold', 'rich-text-image-status']);

    editor.target('imageSearch').value = 'door';
    assert.equal(editor.key('Enter', {}, editor.target('imageSearch')), true);
    await settle();
    assert.equal(editor.requests.at(-1).url, 'https://blog.test/api/images/search?alt=door');
    assert.equal(editor.target('imageStatus').textContent, IMAGE_LABELS.result);

    results()[0].click();
    await settle();

    assert.equal(dialog.open, false);
    assert.deepEqual(editor.posted(), doc(paragraph('Text'), image('image-2')));
    assert.deepEqual(drawn(editor), [['image-2', `DraftEmbedded image: A blue door${IMAGE_LABELS.offSite}`]]);
    // Picked, the image is known: there is nothing to look up.
    assert.deepEqual(editor.requests.filter(({ url }) => url.includes('lookup')), []);
});

test('an image search that fails says so and keeps what was found before', async () => {
    let failing = false;
    const editor = await open({
        document: doc(paragraph('Text')),
        respond: () => {
            if (failing) {
                throw new Error('Unavailable');
            }

            return answer({ images: [MOUNTAINS] });
        },
    });
    await editor.press('insertImage');

    failing = true;
    await editor.press([...editor.document.querySelectorAll('button')].find((button) => button.textContent === 'Search images'));

    assert.equal(editor.target('imageStatus').textContent, IMAGE_LABELS.error);
    assert.equal(editor.target('imageResults').children.length, 1);
});

test('an image search outdated by a newer one never shows', async () => {
    const stale = later();
    const editor = await open({
        document: doc(paragraph('Text')),
        respond: (path, query) => (query.get('alt') === 'old' ? stale.promise : answer({ images: [MOUNTAINS] })),
    });
    editor.target('imageSearch').value = 'old';
    await editor.press('insertImage');

    editor.target('imageSearch').value = 'new';
    await editor.press([...editor.document.querySelectorAll('button')].find((button) => button.textContent === 'Search images'));
    stale.resolve(answer({ images: [DOOR] }));
    await settle();

    assert.equal(editor.target('imageResults').textContent, 'Mountains at dawnPublished');
    assert.equal(editor.target('imageStatus').textContent, IMAGE_LABELS.result);
});

test('an editor moved on the page looks its images up again, and the answer to the old lookup is dropped', async () => {
    const answers = [later(), later()];
    let asked = 0;
    const editor = await open({ document: doc(image('image-1')), respond: () => answers[asked++].promise });
    const element = editor.document.querySelector('[data-controller]');

    element.remove();
    await settle();
    editor.document.body.append(element);
    await settle();
    answers[0].resolve(answer({ images: [{ ...MOUNTAINS, alt: 'Stale' }] }));
    await settle();
    assert.deepEqual(drawn(editor), [['image-1', IMAGE_LABELS.loading]]);

    answers[1].resolve(answer({ images: [MOUNTAINS] }));
    await settle();
    assert.deepEqual(drawn(editor), [['image-1', 'PublishedEmbedded image: Mountains at dawnThe Alps']]);
});
