import assert from 'node:assert/strict';
import test from 'node:test';
import { Window } from 'happy-dom';
import { Widget } from '../../../assets/rich_text/widget.js';
import { LOADING, found } from '../../../assets/shared/lookup.js';

const contentOf = (element) => [...element.children].map((child) => ({
    tag: child.tagName.toLowerCase(),
    class: child.className,
    text: child.textContent,
}));

const viewFor = (description, labels = { label: 'Related content', loading: 'Loading', missing: 'No such page', unknown: 'Could not be checked', offSite: 'Not on the site' }) => {
    globalThis.document = new Window().document;
    const views = new Set();
    const visited = [];
    const options = {
        describe: (type, id) => { visited.push(`${type}:${id}`); return description; },
        views,
        labels,
    };
    const createView = Widget.config.addNodeView.call({ options });
    const view = createView({ node: { attrs: { kind: 'related_page', type: 'page', id: 'a-page' } } });

    return { view, views, visited };
};

test('a widget is an atomic block that can be selected but not dragged', () => {
    assert.equal(Widget.config.name, 'widget');
    assert.equal(Widget.config.group, 'block');
    assert.equal(Widget.config.atom, true);
    assert.equal(Widget.config.selectable, true);
    assert.equal(Widget.config.draggable, false);
});

test('a widget is loading by default and starts with no views or labels', () => {
    const options = Widget.config.addOptions();

    assert.equal(options.describe('page', 'a-page'), LOADING);
    assert.deepEqual(options.views, new Set());
    assert.deepEqual(options.labels, {});
});

test('its kind and page id are read from and written to data attributes', () => {
    const { kind, id } = Widget.config.addAttributes();
    const values = { 'data-widget-kind': 'related_page', 'data-page-id': 'a-page' };
    const element = { getAttribute: (name) => values[name] ?? null };

    assert.equal(kind.default, 'related_page');
    assert.equal(id.default, null);
    assert.equal(kind.parseHTML(element), 'related_page');
    assert.equal(id.parseHTML(element), 'a-page');
    assert.deepEqual(kind.renderHTML({ kind: 'related_page' }), { 'data-widget-kind': 'related_page' });
    assert.deepEqual(id.renderHTML({ id: 'a-page' }), { 'data-page-id': 'a-page' });
});

test('the kind of what it shows is read from and written to its data attribute, a page when it names none', () => {
    const { type } = Widget.config.addAttributes();

    assert.equal(type.default, 'page');
    assert.equal(type.parseHTML({ getAttribute: (name) => name === 'data-widget-type' ? 'author' : null }), 'author');
    assert.equal(type.parseHTML({ getAttribute: () => null }), 'page');
    assert.deepEqual(type.renderHTML({ type: 'post' }), { 'data-widget-type': 'post' });
});

test('a widget is read from and written to its own element', () => {
    assert.deepEqual(Widget.config.parseHTML(), [{ tag: 'div[data-widget-kind]' }]);
    assert.deepEqual(Widget.config.renderHTML({ HTMLAttributes: { 'data-widget-kind': 'related_page', class: 'existing' } }), [
        'div', { 'data-widget-kind': 'related_page', class: 'existing' },
    ]);
});

test('a widget without a page yet tells what is known of it', () => {
    for (const state of ['loading', 'missing', 'unknown']) {
        const { view, visited } = viewFor({ state });

        assert.deepEqual(visited, ['page:a-page']);
        assert.equal(view.dom.className, 'rich-text-widget');
        assert.equal(view.dom.getAttribute('data-widget-kind'), 'related_page');
        assert.deepEqual(contentOf(view.dom), [
            { tag: 'p', class: 'rich-text-widget-label', text: 'Related content' },
            { tag: 'p', class: 'rich-text-page-note', text: { loading: 'Loading', missing: 'No such page', unknown: 'Could not be checked' }[state] },
        ]);
    }
});

test('a widget with a page names the page and its status, adding a note when it is off the site', () => {
    const page = { title: 'Target page', status: 'Draft' };
    const published = viewFor(found({ ...page, published: true }));
    const offSite = viewFor(found({ ...page, published: false }));

    assert.deepEqual(contentOf(published.view.dom), [
        { tag: 'p', class: 'rich-text-widget-label', text: 'Related content' },
        { tag: 'p', class: 'rich-text-widget-title', text: 'Target page' },
        { tag: 'span', class: 'rich-text-page-status', text: 'Draft' },
    ]);
    assert.deepEqual(contentOf(offSite.view.dom), [
        { tag: 'p', class: 'rich-text-widget-label', text: 'Related content' },
        { tag: 'p', class: 'rich-text-widget-title', text: 'Target page' },
        { tag: 'span', class: 'rich-text-page-status', text: 'Draft' },
        { tag: 'p', class: 'rich-text-page-note', text: 'Not on the site' },
    ]);
});

test('missing labels become empty text rather than leaking undefined into the widget', () => {
    const pending = viewFor(LOADING, {});
    const offSite = viewFor(found({ title: 'Target', status: 'Draft', published: false }), {});

    assert.deepEqual(contentOf(pending.view.dom).map((child) => child.text), ['', '']);
    assert.equal(contentOf(offSite.view.dom).at(-1).text, '');
    assert.equal(contentOf(offSite.view.dom)[0].text, '');
});

test('a registered view redraws its latest state and unregisters when destroyed', () => {
    globalThis.document = new Window().document;
    let description = LOADING;
    const views = new Set();
    const options = {
        describe: () => description,
        views,
        labels: { label: 'Related content', loading: 'Loading', offSite: 'Not on the site' },
    };
    const view = Widget.config.addNodeView.call({ options })({ node: { attrs: { kind: 'related_page', type: 'page', id: 'a-page' } } });

    assert.equal(views.size, 1);
    assert.deepEqual(contentOf(view.dom).map((child) => child.text), ['Related content', 'Loading']);

    description = found({ title: 'Current title', status: 'Published', published: true });
    for (const redraw of views) {
        redraw();
    }

    assert.deepEqual(contentOf(view.dom).map((child) => child.text), ['Related content', 'Current title', 'Published']);

    view.destroy();
    assert.equal(views.size, 0);
});
