import assert from 'node:assert/strict';
import test from 'node:test';
import { Schema } from '@tiptap/pm/model';
import { EditorState } from '@tiptap/pm/state';
import { PageLink, pageLinksKey } from '../../../assets/rich_text/page_link.js';

const schema = new Schema({
    nodes: {
        doc: { content: 'paragraph+' },
        paragraph: { content: 'text*', group: 'block' },
        text: {},
    },
    marks: {
        link: { attrs: { type: { default: 'page' }, id: { default: null } } },
        bold: {},
    },
});

test('a page link is a non-inclusive mark, loading until its page is known', () => {
    assert.equal(PageLink.config.name, 'link');
    assert.equal(PageLink.config.inclusive, false);

    const options = PageLink.config.addOptions();
    assert.equal(options.stateOf('a-page'), 'loading');
});

test('its page id is read from and written to its data attribute', () => {
    const { id } = PageLink.config.addAttributes();
    const element = { getAttribute: (name) => name === 'data-page-id' ? 'a-page' : null };

    assert.equal(id.default, null);
    assert.equal(id.parseHTML(element), 'a-page');
    assert.deepEqual(id.renderHTML({ id: 'a-page' }), { 'data-page-id': 'a-page' });
});

test('a page link is read from and written to an anchor without an address of its own', () => {
    assert.deepEqual(PageLink.config.parseHTML(), [{ tag: 'a[data-page-id]' }]);
    assert.deepEqual(PageLink.config.renderHTML({ HTMLAttributes: { 'data-page-id': 'a-page' } }), [
        'a', { 'data-page-id': 'a-page' }, 0,
    ]);
});

test('only links whose pages are not published are decorated over their whole text', () => {
    const paragraph = (text, mark) => schema.node('paragraph', null, [schema.text(text, mark ? [mark] : [])]);
    const link = (id) => schema.mark('link', { id });
    const doc = schema.node('doc', null, [
        paragraph('Published', link('published')),
        paragraph('Plain'),
        paragraph('Draft', link('draft')),
        paragraph('Missing', link('missing')),
        paragraph('Unknown', link('unknown')),
        paragraph('Loading', link('loading')),
        paragraph('Bold', schema.mark('bold')),
    ]);
    const state = EditorState.create({ schema, doc });
    const states = { published: 'published', draft: 'offSite', missing: 'missing', unknown: 'unknown', loading: 'loading' };
    const visited = [];
    const [plugin] = PageLink.config.addProseMirrorPlugins.call({
        options: { stateOf: (type, id) => { visited.push(`${type}:${id}`); return states[id]; } },
    });

    const decorations = plugin.props.decorations(state);

    assert.equal(pageLinksKey.key, 'pageLinks$');
    assert.equal(plugin.spec.key, pageLinksKey);
    // A link of before it named a kind goes to a page.
    assert.deepEqual(visited, ['page:published', 'page:draft', 'page:missing', 'page:unknown', 'page:loading']);
    assert.deepEqual(decorations.find().map(({ from, to, type }) => ({ from, to, class: type.attrs.class })), [
        { from: 19, to: 24, class: 'rich-text-link-offSite' },
        { from: 26, to: 33, class: 'rich-text-link-missing' },
        { from: 35, to: 42, class: 'rich-text-link-unknown' },
        { from: 44, to: 51, class: 'rich-text-link-loading' },
    ]);
});
