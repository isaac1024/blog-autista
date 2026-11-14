import assert from 'node:assert/strict';
import test from 'node:test';
import { Schema } from '@tiptap/pm/model';
import { EditorState, NodeSelection, TextSelection } from '@tiptap/pm/state';
import { BlockMover } from '../../../assets/rich_text/block_mover.js';

const schema = new Schema({
    nodes: {
        doc: { content: 'block+' },
        paragraph: { content: 'text*', group: 'block' },
        heading: { content: 'text*', group: 'block', attrs: { level: { default: 2 } } },
        text: {},
    },
});

const document = () => schema.node('doc', null, [
    schema.node('heading', { level: 2 }, [schema.text('First')]),
    schema.node('paragraph', null, [schema.text('one')]),
    schema.node('heading', { level: 2 }, [schema.text('Second')]),
    schema.node('paragraph', null, [schema.text('two')]),
]);

const stateAt = (position, Selection = TextSelection) => {
    const doc = document();

    return EditorState.create({ schema, doc, selection: Selection.create(doc, position) });
};

const blocksIn = (doc) => {
    const blocks = [];
    doc.forEach((node) => blocks.push({ type: node.type.name, text: node.textContent }));

    return blocks;
};

const movedBlocks = [
    { type: 'heading', text: 'Second' },
    { type: 'paragraph', text: 'two' },
    { type: 'heading', text: 'First' },
    { type: 'paragraph', text: 'one' },
];

test('the mover has inert callbacks until the editor supplies them', () => {
    const options = BlockMover.config.addOptions();

    assert.equal(BlockMover.config.name, 'blockMover');
    assert.equal(options.onMove(1, true), undefined);
});

test('moving a section down swaps both whole sections and takes the cursor along', () => {
    const tr = stateAt(1).tr;
    const command = BlockMover.config.addCommands().moveBlock(1);

    assert.equal(command({ tr, dispatch: () => {} }), true);
    assert.deepEqual(blocksIn(tr.doc), movedBlocks);
    assert.equal(tr.selection.from, 14);
    assert.equal(tr.selection.to, 14);
    assert.equal(tr.scrolledIntoView, true);
});

test('moving the following section up makes the inverse swap and takes its cursor along', () => {
    const tr = stateAt(13).tr;
    const command = BlockMover.config.addCommands().moveBlock(-1);

    assert.equal(command({ tr, dispatch: () => {} }), true);
    assert.deepEqual(blocksIn(tr.doc), movedBlocks);
    assert.equal(tr.selection.from, 1);
    assert.equal(tr.selection.to, 1);
    assert.equal(tr.scrolledIntoView, true);
});

test('moving up subtracts the distance between sections even when the first starts later in the document', () => {
    const doc = schema.node('doc', null, [
        schema.node('paragraph', null, [schema.text('Intro')]),
        ...document().content.content,
    ]);
    const state = EditorState.create({ schema, doc, selection: TextSelection.create(doc, 20) });
    const tr = state.tr;
    const command = BlockMover.config.addCommands().moveBlock(-1);

    assert.equal(command({ tr, dispatch: () => {} }), true);
    assert.deepEqual(blocksIn(tr.doc), [{ type: 'paragraph', text: 'Intro' }, ...movedBlocks]);
    assert.equal(tr.selection.from, 8);
});

test('a move with nowhere to go changes nothing', () => {
    const state = stateAt(1);
    const tr = state.tr;
    const command = BlockMover.config.addCommands().moveBlock(-1);

    assert.equal(command({ tr, dispatch: () => {} }), false);
    assert.equal(tr.doc, state.doc);
    assert.equal(tr.scrolledIntoView, false);
});

test('a top-level node selection has no enclosing block to move', () => {
    const state = stateAt(0, NodeSelection);
    const tr = state.tr;
    const command = BlockMover.config.addCommands().moveBlock(1);

    assert.equal(command({ tr, dispatch: () => {} }), false);
    assert.equal(tr.doc, state.doc);
});

test('checking a possible move without dispatching it changes nothing', () => {
    const state = stateAt(1);
    const tr = state.tr;
    const command = BlockMover.config.addCommands().moveBlock(1);

    assert.equal(command({ tr }), true);
    assert.equal(tr.doc, state.doc);
    assert.equal(tr.selection.from, 1);
    assert.equal(tr.scrolledIntoView, false);
});

test('the keyboard moves up and down, saying whether a section moved', () => {
    const editor = { state: stateAt(1), commands: { moveBlock: () => true } };
    const directions = [];
    const notifications = [];
    const shortcuts = BlockMover.config.addKeyboardShortcuts.call({
        editor,
        options: { onMove: (direction, section) => notifications.push({ direction, section }) },
    });

    assert.deepEqual(Object.keys(shortcuts), ['Mod-Shift-ArrowUp', 'Mod-Shift-ArrowDown']);

    editor.commands.moveBlock = (direction) => { directions.push(direction); return true; };
    assert.equal(shortcuts['Mod-Shift-ArrowUp'](), true);

    editor.state = stateAt(8);
    assert.equal(shortcuts['Mod-Shift-ArrowDown'](), true);
    assert.deepEqual(directions, [-1, 1]);
    assert.deepEqual(notifications, [{ direction: -1, section: true }, { direction: 1, section: false }]);
});

test('a refused move is not announced', () => {
    let notifications = 0;
    const shortcuts = BlockMover.config.addKeyboardShortcuts.call({
        editor: { state: stateAt(1), commands: { moveBlock: () => false } },
        options: { onMove: () => { notifications += 1; } },
    });

    assert.equal(shortcuts['Mod-Shift-ArrowUp'](), false);
    assert.equal(shortcuts['Mod-Shift-ArrowDown'](), false);
    assert.equal(notifications, 0);
});
