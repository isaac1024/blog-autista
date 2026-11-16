import assert from 'node:assert/strict';
import test from 'node:test';
import { Window } from 'happy-dom';
import { Mosaic } from '../../../assets/rich_text/mosaic.js';

const LABELS = { label: 'Content mosaic', change: 'Change mosaic', limit: 'Up to %limit% posts', section: 'Section', author: 'Author', tags: 'Tags', loading: 'Loading', missing: 'Not found', unknown: 'Unchecked' };
const ATTRS = { limit: 3, sectionId: 's', authorId: null, tagIds: ['t'] };

const viewFor = ({ getPos = () => 7 } = {}) => {
    const { document } = new Window();
    globalThis.document = document;
    const views = new Set();
    const changes = [];
    const names = { s: 'Daily life', t: 'Sleep' };
    const options = {
        name: (type, id) => `${type}:${names[id]}`,
        change: (at, mosaic) => changes.push([at, mosaic]),
        views,
        labels: LABELS,
    };
    const createView = Mosaic.config.addNodeView.call({ options });
    const view = createView({ node: { attrs: ATTRS }, getPos });

    return { view, views, changes, names };
};

const contentOf = (element) => [...element.children].map((child) => [child.tagName.toLowerCase(), child.className, child.textContent]);

test('a mosaic is an atomic block that can be selected but not dragged', () => {
    assert.equal(Mosaic.config.name, 'mosaic');
    assert.equal(Mosaic.config.group, 'block');
    assert.equal(Mosaic.config.atom, true);
    assert.equal(Mosaic.config.selectable, true);
    assert.equal(Mosaic.config.draggable, false);
});

test('a mosaic names nothing, changes nothing and has no views or labels until configured', () => {
    const options = Mosaic.config.addOptions();

    assert.equal(options.name('term', 's'), '');
    assert.equal(options.change(1, ATTRS), undefined);
    assert.deepEqual(options.views, new Set());
    assert.deepEqual(options.labels, {});
});

test('what it asks for starts as a new mosaic, and is read from and written to data attributes', () => {
    const { limit, sectionId, authorId, tagIds } = Mosaic.config.addAttributes();
    const values = { 'data-limit': '4', 'data-section-id': 's', 'data-author-id': 'a', 'data-tag-ids': 't1,t2' };
    const element = { getAttribute: (name) => values[name] ?? null };

    assert.deepEqual([limit.default, sectionId.default, authorId.default, tagIds.default], [6, null, null, []]);
    assert.deepEqual([limit.parseHTML(element), sectionId.parseHTML(element), authorId.parseHTML(element), tagIds.parseHTML(element)], [4, 's', 'a', ['t1', 't2']]);
    assert.deepEqual(tagIds.parseHTML({ getAttribute: () => null }), []);
    assert.deepEqual(limit.renderHTML({ limit: 4 }), { 'data-limit': 4 });
    assert.deepEqual(sectionId.renderHTML({ sectionId: 's' }), { 'data-section-id': 's' });
    assert.deepEqual(authorId.renderHTML({ authorId: 'a' }), { 'data-author-id': 'a' });
    assert.deepEqual(tagIds.renderHTML({ tagIds: ['t1', 't2'] }), { 'data-tag-ids': 't1,t2' });
});

test('a mosaic is read from and written to its own element', () => {
    assert.deepEqual(Mosaic.config.parseHTML(), [{ tag: 'div[data-mosaic]' }]);
    assert.deepEqual(Mosaic.config.renderHTML({ HTMLAttributes: { 'data-limit': 3 } }), ['div', { 'data-mosaic': '', 'data-limit': 3 }]);
});

test('the editor shows what a mosaic asks for, in words, and a button to change it', () => {
    const { view } = viewFor();

    assert.equal(view.dom.className, 'rich-text-widget');
    assert.equal(view.dom.getAttribute('data-mosaic'), '');
    assert.deepEqual(contentOf(view.dom), [
        ['p', 'rich-text-widget-label', 'Content mosaic'],
        ['p', 'rich-text-widget-title', 'Up to 3 posts · Section: term:Daily life · Tags: term:Sleep'],
        ['button', 'rich-text-mosaic-change', 'Change mosaic'],
    ]);
    // Never a button that would send the form the editor sits in.
    assert.equal(view.dom.querySelector('button').type, 'button');
});

test('its words follow what the CMS knows of what it narrows down to, until the view is destroyed', () => {
    const { view, views, names } = viewFor();

    names.s = 'Everyday life';
    views.forEach((draw) => draw());
    assert.equal(view.dom.children[1].textContent, 'Up to 3 posts · Section: term:Everyday life · Tags: term:Sleep');

    view.destroy();
    assert.equal(views.size, 0);
});

test('its button changes the mosaic where it is, and only its clicks are its own', () => {
    const { view, changes } = viewFor();
    const button = view.dom.querySelector('button');

    button.click();

    assert.deepEqual(changes, [[7, ATTRS]]);
    assert.notEqual(changes[0][1], ATTRS);
    assert.equal(view.stopEvent({ target: button }), true);
    assert.equal(view.stopEvent({ target: view.dom }), false);
    assert.equal(view.ignoreMutation(), true);
});

test('a mosaic no longer in the text changes nothing', () => {
    const { view, changes } = viewFor({ getPos: () => undefined });

    view.dom.querySelector('button').click();

    assert.deepEqual(changes, []);
});
