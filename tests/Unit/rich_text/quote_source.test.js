import assert from 'node:assert/strict';
import test from 'node:test';
import { Schema } from '@tiptap/pm/model';
import { EditorState, TextSelection } from '@tiptap/pm/state';
import { QuoteSource } from '../../../assets/rich_text/quote_source.js';

const schema = new Schema({
    nodes: {
        doc: { content: 'block+' },
        blockquote: { content: 'block+', group: 'block' },
        paragraph: { content: 'text*', group: 'block' },
        quoteSource: { content: 'text*', group: 'block', marks: '' },
        text: {},
    },
});

const quoteDocument = (source = 'Origin') => {
    const children = [schema.node('paragraph', null, [schema.text('Quote')])];
    if (source !== null) {
        children.push(schema.node('quoteSource', null, source === '' ? [] : [schema.text(source)]));
    }

    return schema.node('doc', null, [schema.node('blockquote', null, children)]);
};

const stateIn = (doc, from, to = from) => EditorState.create({ schema, doc, selection: TextSelection.create(doc, from, to) });
const focusCommand = () => QuoteSource.config.addCommands.call({ name: 'quoteSource', type: schema.nodes.quoteSource }).focusQuoteSource();

const shortcutsFor = (state, runResult = true) => {
    const calls = [];
    const chain = {
        insertContentAt: (...args) => { calls.push(['insertContentAt', ...args]); return chain; },
        setTextSelection: (...args) => { calls.push(['setTextSelection', ...args]); return chain; },
        deleteRange: (...args) => { calls.push(['deleteRange', ...args]); return chain; },
        scrollIntoView: () => { calls.push(['scrollIntoView']); return chain; },
        run: () => { calls.push(['run']); return runResult; },
    };
    const shortcuts = QuoteSource.config.addKeyboardShortcuts.call({
        name: 'quoteSource',
        editor: { state, chain: () => chain },
    });

    return { shortcuts, calls };
};

test('a quote source is defining plain text with a configurable hint', () => {
    assert.equal(QuoteSource.config.name, 'quoteSource');
    assert.equal(QuoteSource.config.content, 'text*');
    assert.equal(QuoteSource.config.marks, '');
    assert.equal(QuoteSource.config.defining, true);
    assert.deepEqual(QuoteSource.config.addOptions(), { hint: '' });
});

test('a quote source is read from and written to its own element with its hint', () => {
    assert.deepEqual(QuoteSource.config.parseHTML(), [{ tag: 'div[data-quote-source]' }]);
    assert.deepEqual(QuoteSource.config.renderHTML.call(
        { options: { hint: 'Source' } },
        { HTMLAttributes: { class: 'existing' } },
    ), ['div', {
        class: 'existing',
        'data-quote-source': '',
        'data-placeholder': 'Source',
    }, 0]);
});

test('focusing outside a quote is refused and changes nothing', () => {
    const doc = schema.node('doc', null, [schema.node('paragraph', null, [schema.text('Plain')])]);
    const state = stateIn(doc, 1);
    const tr = state.tr;

    assert.equal(focusCommand()({ tr, state, dispatch: () => {} }), false);
    assert.equal(tr.doc, doc);
    assert.equal(tr.scrolledIntoView, false);
});

test('focusing a quote with a source takes the cursor to the end of its text', () => {
    const doc = quoteDocument();
    const state = stateIn(doc, 9);
    const tr = state.tr;

    assert.equal(focusCommand()({ tr, state, dispatch: () => {} }), true);
    assert.equal(tr.doc, doc);
    assert.equal(tr.selection.from, 15);
    assert.equal(tr.selection.$from.parent.type.name, 'quoteSource');
    assert.equal(tr.scrolledIntoView, true);
});

test('focusing a quote without a source appends one and puts the cursor inside', () => {
    const doc = quoteDocument(null);
    const state = stateIn(doc, 2);
    const tr = state.tr;

    assert.equal(focusCommand()({ tr, state, dispatch: () => {} }), true);
    assert.equal(tr.doc.firstChild.lastChild.type.name, 'quoteSource');
    assert.equal(tr.doc.firstChild.lastChild.content.size, 0);
    assert.equal(tr.selection.from, 9);
    assert.equal(tr.selection.$from.parent.type.name, 'quoteSource');
    assert.equal(tr.scrolledIntoView, true);
});

test('checking the source command without dispatching it does not add a source', () => {
    const doc = quoteDocument(null);
    const state = stateIn(doc, 2);
    const tr = state.tr;

    assert.equal(focusCommand()({ tr, state }), true);
    assert.equal(tr.doc, doc);
    assert.equal(tr.scrolledIntoView, false);
});

test('Enter outside a source leaves the key alone', () => {
    const { shortcuts, calls } = shortcutsFor(stateIn(quoteDocument(), 2));

    assert.equal(shortcuts.Enter(), false);
    assert.deepEqual(calls, []);
});

test('Enter in a source opens a paragraph just after its quote and scrolls to it', () => {
    const { shortcuts, calls } = shortcutsFor(stateIn(quoteDocument(), 9));

    assert.equal(shortcuts.Enter(), true);
    assert.deepEqual(calls, [
        ['insertContentAt', 17, { type: 'paragraph' }],
        ['setTextSelection', 18],
        ['scrollIntoView'],
        ['run'],
    ]);
});

test('Enter in a source outside a quote cannot leave the quote', () => {
    const doc = schema.node('doc', null, [schema.node('quoteSource')]);
    const { shortcuts, calls } = shortcutsFor(stateIn(doc, 1));

    assert.equal(shortcuts.Enter(), false);
    assert.deepEqual(calls, []);
});

test('Backspace is left alone outside a source, with a selection, or after its start', () => {
    const paragraph = schema.node('doc', null, [schema.node('paragraph', null, [schema.text('Plain')])]);
    const cases = [stateIn(paragraph, 1), stateIn(quoteDocument(), 9, 12), stateIn(quoteDocument(), 10)];

    for (const state of cases) {
        const { shortcuts, calls } = shortcutsFor(state);
        assert.equal(shortcuts.Backspace(), false);
        assert.deepEqual(calls, []);
    }
});

test('Backspace at the start of a source with text keeps the source', () => {
    const { shortcuts, calls } = shortcutsFor(stateIn(quoteDocument(), 9));

    assert.equal(shortcuts.Backspace(), true);
    assert.deepEqual(calls, []);
});

test('Backspace at the start of an empty source removes just that source', () => {
    const { shortcuts, calls } = shortcutsFor(stateIn(quoteDocument(''), 9));

    assert.equal(shortcuts.Backspace(), true);
    assert.deepEqual(calls, [
        ['deleteRange', { from: 8, to: 10 }],
        ['setTextSelection', 7],
        ['run'],
    ]);
});
