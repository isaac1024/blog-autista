import assert from 'node:assert/strict';
import test from 'node:test';
import { Schema } from '@tiptap/pm/model';
import { EditorState, TextSelection } from '@tiptap/pm/state';
import { SlashMenu } from '../../../assets/rich_text/slash_menu.js';

const schema = new Schema({
    nodes: {
        doc: { content: 'paragraph+' },
        paragraph: { content: 'text*' },
        text: {},
    },
});

const editorFor = (active = []) => ({
    isEditable: true,
    isActive: (name) => active.includes(name),
    view: { composing: false, coordsAtPos: () => ({ top: 0, right: 0, bottom: 0, left: 0 }) },
});

const pluginFor = (editor, options = { items: () => [], render: () => ({}) }) => {
    const [plugin] = SlashMenu.config.addProseMirrorPlugins.call({ editor, options });

    return plugin;
};

const typeAtSelection = (editor, text, inserted) => {
    const doc = schema.node('doc', null, [schema.node('paragraph', null, text === '' ? [] : [schema.text(text)])]);
    const before = EditorState.create({ schema, doc, selection: TextSelection.create(doc, text.length + 1) });
    const plugin = pluginFor(editor);
    const withPlugin = EditorState.create({ schema, doc, selection: before.selection, plugins: [plugin] });
    const after = withPlugin.apply(withPlugin.tr.insertText(inserted, withPlugin.selection.from));
    editor.state = after;

    return { plugin, before: withPlugin, after };
};

test('the slash menu starts with no items and no rendering callbacks', () => {
    const options = SlashMenu.config.addOptions();

    assert.equal(SlashMenu.config.name, 'slashMenu');
    assert.deepEqual(options.items('anything'), []);
    assert.deepEqual(options.render(), {});
});

test('a slash opens the menu at the start of a line, but not after other text', () => {
    const atStart = typeAtSelection(editorFor(), '', '/');
    const afterText = typeAtSelection(editorFor(), 'Text ', '/');

    assert.equal(atStart.plugin.getState(atStart.after).active, true);
    assert.equal(atStart.plugin.getState(atStart.after).query, '');
    assert.deepEqual(atStart.plugin.getState(atStart.after).range, { from: 1, to: 2 });
    assert.equal(afterText.plugin.getState(afterText.after).active, false);
});

test('the slash menu stays closed in tables and quote sources', () => {
    for (const active of [[], ['table'], ['quoteSource'], ['table', 'quoteSource']]) {
        const editor = editorFor(active);
        const { plugin, after } = typeAtSelection(editor, '', '/');

        assert.equal(plugin.getState(after).active, active.length === 0);
    }
});

test('the menu renders the items answering the query and applies the one chosen', async () => {
    const events = [];
    const queries = [];
    let rendered;
    let renderCalls = 0;
    let applied;
    const item = { label: 'Paragraph', apply: () => { events.push(['apply']); applied = item; } };
    const chain = {
        focus: () => { events.push(['focus']); return chain; },
        deleteRange: (range) => { events.push(['deleteRange', range]); return chain; },
        run: () => { events.push(['run']); return true; },
    };
    const editor = {
        ...editorFor(),
        state: null,
        chain: () => chain,
    };
    const options = {
        items: (query) => { queries.push(query); return [item]; },
        render: () => {
            renderCalls += 1;
            return {
                onStart: (props) => { rendered = props; },
                onUpdate: (props) => { rendered = props; },
            };
        },
    };
    const plugin = pluginFor(editor, options);
    const doc = schema.node('doc', null, [schema.node('paragraph')]);
    const before = EditorState.create({ schema, doc, selection: TextSelection.create(doc, 1), plugins: [plugin] });
    const after = before.apply(before.tr.insertText('/par', 1));
    editor.state = after;
    const view = { state: after, dom: { querySelector: () => null } };
    const suggestion = plugin.spec.view();

    await suggestion.update(view, before);

    assert.equal(renderCalls, 1);
    assert.deepEqual(queries, ['par']);
    assert.deepEqual(rendered.items, [item]);
    assert.equal(rendered.loading, false);

    assert.equal(rendered.command(item), undefined);
    assert.equal(applied, item);
    assert.deepEqual(events, [
        ['focus'],
        ['deleteRange', { from: 1, to: 5 }],
        ['run'],
        ['apply'],
    ]);

    suggestion.destroy();
});
