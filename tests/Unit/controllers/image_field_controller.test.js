import assert from 'node:assert/strict';
import test from 'node:test';
import ImageField from '../../../assets/controllers/image_field_controller.js';
import { IMAGE_FIELD_LABELS as LABELS, imageField } from '../../Markup/image_field.js';
import { answer, assertFocused, later, mount, serve, settle } from '../support/dom.js';

const IMAGE = { id: 'image-1', alt: 'A red bicycle', status: 'Published', published: true, thumbnail: '/media/image-1-400.webp' };
const SECOND = { id: 'image-2', alt: 'A blue door', status: 'Draft', published: false, thumbnail: '/media/image-2-400.webp' };

const open = async (value = '') => {
    const page = await mount(imageField(value), { 'image-field': ImageField });
    const { document } = page;
    const target = (name) => document.querySelector(`[data-image-field-target="${name}"]`);

    return {
        ...page,
        target,
        click: async (name) => {
            [...document.querySelectorAll('button')].find((button) => button.textContent.trim() === name).click();
            await settle();
        },
        shown: () => [target('description').textContent, target('state').textContent],
        /** The thumbnail shown, as its attributes, or null when hidden. */
        thumbnail: () => {
            const images = [...target('thumbnail').querySelectorAll('img')];

            if (target('thumbnail').hidden) {
                assert.equal(images.length, 0);

                return null;
            }

            assert.equal(images.length, 1);
            const [image] = images;

            return [image.className, image.getAttribute('src'), image.alt, image.width, image.height];
        },
        results: () => [...target('results').querySelectorAll('button')],
    };
};

test('an empty field reveals the picker, says no image is chosen and offers nothing to remove', async () => {
    const requests = serve(() => answer({ images: [] }));
    const field = await open();

    assert.equal(field.target('choose').hidden, false);
    assert.equal(field.target('remove').hidden, true);
    assert.deepEqual(field.shown(), [LABELS.empty, '']);
    assert.equal(field.thumbnail(), null);
    assert.deepEqual(requests, []);
});

test('the chosen image is told as loading, then shown with its thumbnail as the CMS knows it now', async () => {
    const pending = later();
    const requests = serve(() => pending.promise);
    const field = await open(IMAGE.id);

    assert.deepEqual(field.shown(), [LABELS.loading, '']);
    assert.equal(field.target('remove').hidden, false);
    assert.equal(field.thumbnail(), null);
    assert.deepEqual(requests.map(({ url }) => url), [`https://blog.test/api/images/lookup?ids=${IMAGE.id}`]);

    pending.resolve(answer({ images: [{ ...IMAGE, published: false }] }));
    await settle();
    assert.deepEqual(field.shown(), [IMAGE.alt, `${IMAGE.status}. ${LABELS.offSite}`]);
    assert.deepEqual(field.thumbnail(), ['block aspect-video h-auto w-28 object-cover', IMAGE.thumbnail, '', 112, 63]);
});

test('an image the lookup doesn\'t find is missing; one it can\'t check, unknown; neither has a thumbnail', async () => {
    serve(() => answer({ images: [] }));
    const missing = await open(IMAGE.id);
    assert.deepEqual(missing.shown(), [LABELS.missing, '']);
    assert.equal(missing.thumbnail(), null);

    serve(() => { throw new Error('Unavailable'); });
    const unknown = await open(IMAGE.id);
    assert.deepEqual(unknown.shown(), [LABELS.unknown, '']);
    assert.equal(unknown.thumbnail(), null);
});

test('a field removed while it looks up cancels it, and the late answer changes nothing', async () => {
    const pending = later();
    const requests = serve(() => pending.promise);
    const field = await open(IMAGE.id);
    const description = field.target('description');

    field.document.querySelector('[data-controller]').remove();
    await settle();
    pending.resolve(answer({ images: [IMAGE] }));
    await settle();

    assert.equal(requests[0].signal.aborted, true);
    assert.equal(description.textContent, LABELS.loading);
});

test('the picker searches every image at once, and a result chosen fills, tells and notifies the field', async () => {
    const requests = serve(() => answer({ images: [IMAGE, SECOND] }));
    const field = await open();
    let inputs = 0;
    field.document.querySelector('form').addEventListener('input', () => { inputs += 1; });

    await field.click('Choose image');

    assert.equal(field.target('dialog').open, true);
    assertFocused(field.target('search'));
    assert.deepEqual(requests.map(({ url }) => url), ['https://blog.test/api/images/search?alt=']);
    assert.equal(field.target('searchStatus').textContent, '2 results');
    assert.deepEqual(field.results().map((button) => [button.type, button.className, button.textContent]), [
        ['button', 'rich-text-image-option', 'A red bicyclePublished'],
        ['button', 'rich-text-image-option', 'A blue doorDraft'],
    ]);
    const [thumbnail] = field.results()[0].querySelectorAll('img');
    assert.deepEqual([thumbnail.getAttribute('src'), thumbnail.alt, thumbnail.width, thumbnail.height, thumbnail.loading], [IMAGE.thumbnail, '', 112, 63, 'lazy']);
    assert.deepEqual(
        [...field.results()[0].querySelectorAll('span')].map((span) => span.className),
        ['rich-text-image-option-text', 'font-bold', 'rich-text-image-status'],
    );

    field.results()[1].click();

    assert.equal(field.target('input').value, SECOND.id);
    assert.equal(field.target('dialog').open, false);
    assert.deepEqual(field.shown(), [SECOND.alt, `${SECOND.status}. ${LABELS.offSite}`]);
    assert.equal(field.thumbnail()[1], SECOND.thumbnail);
    assert.equal(field.target('remove').hidden, false);
    assert.equal(field.target('status').textContent, 'Chosen A blue door');
    assertFocused(field.target('choose'));
    assert.equal(inputs, 1);
});

test('removing the image empties, tells and notifies the field, and leaves the focus on the picker', async () => {
    serve(() => answer({ images: [IMAGE] }));
    const field = await open(IMAGE.id);
    await settle();
    let inputs = 0;
    field.document.querySelector('form').addEventListener('input', () => { inputs += 1; });

    await field.click('Remove image');

    assert.equal(field.target('input').value, '');
    assert.deepEqual(field.shown(), [LABELS.empty, '']);
    assert.equal(field.thumbnail(), null);
    assert.equal(field.target('remove').hidden, true);
    assert.equal(field.target('status').textContent, LABELS.removed);
    assertFocused(field.target('choose'));
    assert.equal(inputs, 1);
});

test('a search sends the text typed and tells how many images answer it', async () => {
    const requests = serve((url) => answer({ images: new URL(url).searchParams.get('alt') === 'red' ? [IMAGE] : [] }));
    const field = await open();
    await field.click('Choose image');

    field.target('search').value = 'red';
    await field.click('Search');
    assert.equal(requests.at(-1).url, 'https://blog.test/api/images/search?alt=red');
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

        return answer({ images: [IMAGE] });
    });
    const field = await open();
    await field.click('Choose image');

    failing = true;
    await field.click('Search');

    assert.equal(field.target('searchStatus').textContent, LABELS.error);
    assert.equal(field.results().length, 1);
});

test('only the newest search draws its results', async () => {
    const stale = later();
    serve((url) => (new URL(url).searchParams.get('alt') === 'old' ? stale.promise : answer({ images: [IMAGE] })));
    const field = await open();
    field.target('search').value = 'old';
    await field.click('Choose image');

    field.target('search').value = 'new';
    await field.click('Search');
    stale.resolve(answer({ images: [SECOND] }));
    await settle();

    assert.deepEqual(field.results().map((button) => button.textContent), ['A red bicyclePublished']);
    assert.equal(field.target('searchStatus').textContent, LABELS.result);
});
