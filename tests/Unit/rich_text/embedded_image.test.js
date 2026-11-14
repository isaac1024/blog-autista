import assert from 'node:assert/strict';
import test from 'node:test';
import { Window } from 'happy-dom';
import { EmbeddedImage } from '../../../assets/rich_text/embedded_image.js';
import { LOADING } from '../../../assets/shared/lookup.js';

const IMAGE = {
    thumbnail: '/images/thumbnail.webp',
    status: 'Published',
    alt: 'Mountains at dawn',
    caption: '',
    published: true,
};
const LABELS = {
    loading: 'Loading',
    unknown: 'Could not be checked',
    missing: 'No such image',
    embedded: 'Embedded image:',
    offSite: 'Not on the site',
};

const contentOf = (element) => [...element.children].map((child) => ({
    tag: child.tagName.toLowerCase(),
    class: child.className,
    text: child.textContent,
}));

const viewFor = (lookup, labels = LABELS) => {
    globalThis.document = new Window().document;
    const views = new Set();
    const visited = [];
    const options = {
        describe: (id) => { visited.push(id); return lookup; },
        views,
        labels,
    };
    const view = EmbeddedImage.config.addNodeView.call({ options })({ node: { attrs: { id: 'an-image' } } });

    return { view, views, visited };
};

test('an embedded image is an atomic block that can be selected but not dragged', () => {
    assert.equal(EmbeddedImage.config.name, 'image');
    assert.equal(EmbeddedImage.config.group, 'block');
    assert.equal(EmbeddedImage.config.atom, true);
    assert.equal(EmbeddedImage.config.selectable, true);
    assert.equal(EmbeddedImage.config.draggable, false);
});

test('an embedded image is loading by default and starts with no views or labels', () => {
    const options = EmbeddedImage.config.addOptions();

    assert.equal(options.describe('an-image'), LOADING);
    assert.deepEqual(options.views, new Set());
    assert.deepEqual(options.labels, {});
});

test('its image id is read from and written to its data attribute', () => {
    const { id } = EmbeddedImage.config.addAttributes();
    const element = { getAttribute: (name) => name === 'data-image-id' ? 'an-image' : null };

    assert.equal(id.default, null);
    assert.equal(id.parseHTML(element), 'an-image');
    assert.deepEqual(id.renderHTML({ id: 'an-image' }), { 'data-image-id': 'an-image' });
});

test('an embedded image is read from and written to its own figure', () => {
    assert.deepEqual(EmbeddedImage.config.parseHTML(), [{ tag: 'figure[data-image-id]' }]);
    assert.deepEqual(EmbeddedImage.config.renderHTML({ HTMLAttributes: { 'data-image-id': 'an-image', class: 'existing' } }), [
        'figure', { 'data-image-id': 'an-image', class: 'existing' },
    ]);
});

test('an image not found tells what is known of it, without drawing a thumbnail', () => {
    for (const state of ['loading', 'unknown', 'missing']) {
        const { view, visited } = viewFor({ state });

        assert.deepEqual(visited, ['an-image']);
        assert.equal(view.dom.className, 'rich-text-image');
        assert.equal(view.dom.getAttribute('data-image-id'), 'an-image');
        assert.deepEqual(contentOf(view.dom), [
            { tag: 'p', class: 'rich-text-image-note', text: LABELS[state] },
        ]);
    }
});

test('a published image draws its thumbnail, status and alternative text', () => {
    const { view } = viewFor({ state: 'found', value: IMAGE });
    const [thumbnail, details] = view.dom.children;

    assert.equal(thumbnail.tagName.toLowerCase(), 'img');
    assert.equal(thumbnail.getAttribute('src'), '/images/thumbnail.webp');
    assert.equal(thumbnail.alt, '');
    assert.equal(thumbnail.width, 240);
    assert.equal(thumbnail.height, 135);
    assert.equal(details.className, 'rich-text-image-details');
    assert.deepEqual(contentOf(details), [
        { tag: 'span', class: 'rich-text-image-status', text: 'Published' },
        { tag: 'p', class: 'rich-text-image-alt', text: 'Embedded image: Mountains at dawn' },
    ]);
});

test('an image draws a nonempty caption and says when it is off the site', () => {
    const { view } = viewFor({ state: 'found', value: {
        ...IMAGE,
        status: 'Draft',
        caption: 'The first light over the valley',
        published: false,
    } });
    const details = view.dom.children[1];

    assert.deepEqual(contentOf(details), [
        { tag: 'span', class: 'rich-text-image-status', text: 'Draft' },
        { tag: 'p', class: 'rich-text-image-alt', text: 'Embedded image: Mountains at dawn' },
        { tag: 'p', class: '', text: 'The first light over the valley' },
        { tag: 'p', class: 'rich-text-image-note', text: 'Not on the site' },
    ]);
});

test('a registered image view redraws its latest state and unregisters when destroyed', () => {
    globalThis.document = new Window().document;
    let lookup = LOADING;
    const views = new Set();
    const options = { describe: () => lookup, views, labels: LABELS };
    const view = EmbeddedImage.config.addNodeView.call({ options })({ node: { attrs: { id: 'an-image' } } });

    assert.equal(views.size, 1);
    assert.deepEqual(contentOf(view.dom).map((child) => child.text), ['Loading']);

    lookup = { state: 'found', value: IMAGE };
    for (const redraw of views) {
        redraw();
    }

    assert.equal(view.dom.children.length, 2);
    assert.equal(view.dom.querySelector('img').getAttribute('src'), IMAGE.thumbnail);
    assert.equal(view.dom.textContent.includes('Loading'), false);

    view.destroy();
    assert.equal(views.size, 0);
});
