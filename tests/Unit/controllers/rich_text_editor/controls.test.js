import assert from 'node:assert/strict';
import test from 'node:test';
import { assertFocused, settle } from '../../support/dom.js';
import { doc, heading, openEditor, paragraph } from '../../support/rich_text_editor.js';

const TABLES = { blocks: ['heading', 'table', 'quote', 'image', 'widget'], headingLevels: [2], marks: ['bold'] };
const BLOCK_OPTIONS = [
    { value: 'paragraph', label: 'Paragraph' },
    { value: 'heading:2', label: 'Heading 2' },
    { value: 'quote', label: 'Quote' },
];

const focusOut = (editor, from, to) => {
    from.dispatchEvent(new editor.window.FocusEvent('focusout', { bubbles: true, relatedTarget: to }));
};

test('the controls show while the text is edited, and hide once the focus leaves the text and them alike', async () => {
    const editor = await openEditor({ document: doc(paragraph('Hello')) });
    const dock = editor.target('dock');
    assert.equal(dock.hidden, true);

    await editor.focus();
    assert.equal(dock.hidden, false);

    focusOut(editor, editor.editor.view.dom, editor.control('undo'));
    assert.equal(dock.hidden, false);

    // A change made while a control holds the focus is edited from the controls.
    dock.hidden = true;
    editor.control('undo').focus();
    editor.editor.commands.insertContent('!');
    assert.equal(dock.hidden, false);

    focusOut(editor, editor.editor.view.dom, editor.document.body);
    assert.equal(dock.hidden, true);

    await editor.focus();
    focusOut(editor, editor.editor.view.dom, null);
    assert.equal(dock.hidden, true);
});

test('pressing a control keeps the focus, and the selection, in the text', async () => {
    const editor = await openEditor({ document: doc(paragraph('Hello')) });

    assert.equal(await editor.press('undo'), true);
});

test('Alt+F10 reaches the controls, at the kind of block or else the first of them, and Escape goes back to the text', async () => {
    const editor = await openEditor({ document: doc(paragraph('Hello')) });
    await editor.focus();
    editor.editor.view.dom.blur();
    editor.target('dock').hidden = true;

    assert.equal(editor.key('F10', { altKey: true }), true);
    assert.equal(editor.target('dock').hidden, false);
    assertFocused(editor.target('blockType'));

    assert.equal(editor.key('Escape', {}, editor.target('blockType')), true);
    await editor.frame();
    assertFocused(editor.editor.view.dom);
    assert.equal(editor.key('Escape'), false);
    assert.equal(editor.key('a', {}, editor.control('undo')), false);
    editor.target('canvas').dispatchEvent(new editor.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

    const single = await openEditor({ singleParagraph: true, features: { blocks: [], headingLevels: [], marks: ['bold'] } });
    await single.focus();
    single.key('F10', { altKey: true });
    assertFocused(single.control('bold'));
});

test('bold reads as pressed where the text is bold, and stays available to be turned off', async () => {
    const editor = await openEditor({ document: doc(paragraph('Hello')), features: TABLES });
    await editor.focus('end');
    assert.deepEqual(editor.state('bold'), ['false', 'false']);

    editor.editor.commands.selectAll();
    await editor.press('bold');
    assert.deepEqual(editor.posted(), doc(paragraph('Hello', [{ type: 'bold' }])));
    assert.deepEqual(editor.state('bold'), ['false', 'true']);

    await editor.press('bold');
    assert.deepEqual(editor.posted(), doc(paragraph('Hello')));
});

test('undo and redo are held while there is nothing to undo or redo, and a held control does nothing', async () => {
    const editor = await openEditor({ document: doc(paragraph('Hello')) });
    // Told from the start, before anything is edited; neither toggles.
    assert.deepEqual([editor.state('undo'), editor.state('redo')], [['true', null], ['true', null]]);
    await editor.focus('end');

    editor.editor.commands.insertContent('!');
    assert.deepEqual([editor.state('undo')[0], editor.state('redo')[0]], ['false', 'true']);

    await editor.press('undo');
    assert.deepEqual(editor.posted(), doc(paragraph('Hello')));
    assert.deepEqual([editor.state('undo')[0], editor.state('redo')[0]], ['true', 'false']);

    await editor.press('redo');
    assert.deepEqual(editor.posted(), doc(paragraph('Hello!')));
    // Only moves are told.
    assert.deepEqual(await editor.announced(), []);

    editor.control('redo').setAttribute('aria-disabled', 'true');
    await editor.press('undo');
    editor.control('undo').setAttribute('aria-disabled', 'true');
    await editor.press('undo');
    assert.deepEqual(editor.posted(), doc(paragraph('Hello')));
});

test('a block moves up and down, and a heading moves with its section, each move told', async () => {
    const editor = await openEditor({ document: doc(heading(2, 'First'), paragraph('One'), heading(2, 'Second'), paragraph('Two')) });
    await editor.focus(1);
    assert.deepEqual(editor.named(editor.control('moveUp')), ['Move section up', 'Move section up']);
    assert.deepEqual(editor.named(editor.control('moveDown')), ['Move section down', 'Move section down']);
    assert.deepEqual([editor.state('moveUp')[0], editor.state('moveDown')[0]], ['true', 'false']);

    await editor.press('moveDown');
    assert.deepEqual(editor.posted(), doc(heading(2, 'Second'), paragraph('Two'), heading(2, 'First'), paragraph('One')));
    assert.deepEqual(await editor.announced(), ['', 'Section moved down']);

    await editor.focus('end');
    assert.deepEqual(editor.named(editor.control('moveUp')), ['Move up', 'Move up']);
    await editor.press('moveUp');
    assert.deepEqual(editor.posted(), doc(heading(2, 'Second'), paragraph('Two'), paragraph('One'), heading(2, 'First')));
    assert.deepEqual(await editor.announced(), ['', 'Block moved up']);

    await editor.press('moveDown');
    assert.deepEqual(await editor.announced(), ['', 'Block moved down']);

    await editor.focus(1);
    await editor.press('moveUp');
    assert.deepEqual(await editor.announced(), []);
    await editor.focus(19);
    await editor.press('moveUp');
    assert.deepEqual(await editor.announced(), ['', 'Section moved up']);
});

test('the same move told twice is told again', async () => {
    const editor = await openEditor({ document: doc(paragraph('One'), paragraph('Two'), paragraph('Three')) });
    await editor.focus('end');

    await editor.press('moveUp');
    assert.deepEqual(await editor.announced(), ['', 'Block moved up']);

    // Cleared first, the same message is a change the live region tells again.
    await editor.press('moveUp');
    assert.deepEqual(await editor.announced(), ['', 'Block moved up']);
});

test('the kind of block names the current one, and a table, which holds paragraphs only, holds it', async () => {
    const editor = await openEditor({ document: doc(heading(2, 'Title'), paragraph('Text')), features: TABLES, blockOptions: BLOCK_OPTIONS, images: true, widgets: true });
    const kind = editor.target('blockType');
    await editor.focus(1);
    assert.deepEqual(editor.named(kind), ['Heading 2']);
    assert.equal(kind.getAttribute('aria-disabled'), 'false');

    await editor.focus('end');
    assert.deepEqual(editor.named(kind), ['Paragraph']);
    assert.deepEqual([editor.state('insertImage')[0], editor.state('insertWidget')[0]], ['false', 'false']);

    editor.editor.chain().wrapIn('blockquote').run();
    assert.deepEqual(editor.named(kind), ['Quote']);

    editor.editor.chain().lift('blockquote').run();
    await editor.press('insertTable');
    assert.deepEqual(editor.posted().content[2].content.map((row) => row.content.map((cell) => cell.type)), [
        ['tableHeader', 'tableHeader'], ['tableCell', 'tableCell'], ['tableCell', 'tableCell'],
    ]);
    assert.equal(kind.getAttribute('aria-disabled'), 'true');
    assert.equal(editor.target('tableTools').hidden, false);
    assert.deepEqual([editor.state('insertImage')[0], editor.state('insertWidget')[0]], ['true', 'true']);
});

test('the tools of a table edit its rows and columns, but never its header row alone', async () => {
    const editor = await openEditor({ document: doc(paragraph('Text')), features: TABLES });
    await editor.focus('end');
    assert.equal(editor.target('tableTools').hidden, true);
    await editor.press('insertTable');
    const shape = () => editor.posted().content.find((block) => block.type === 'table')?.content.map((row) => row.content.length);

    assert.equal(editor.state('deleteRow')[0], 'true');
    await editor.press('addColumnAfter');
    assert.deepEqual(shape(), [3, 3, 3]);
    await editor.press('deleteColumn');
    assert.deepEqual(shape(), [2, 2, 2]);
    await editor.press('addRowAfter');
    assert.deepEqual(shape(), [2, 2, 2, 2]);

    editor.editor.commands.goToNextCell();
    editor.editor.commands.goToNextCell();
    assert.equal(editor.state('deleteRow')[0], 'false');
    await editor.press('deleteRow');
    assert.deepEqual(shape(), [2, 2, 2]);

    await editor.press('deleteTable');
    assert.equal(shape(), undefined);
    assert.equal(editor.target('tableTools').hidden, true);
});

test('in a quote its tools show, and take the cursor to its source', async () => {
    const editor = await openEditor({ document: doc({ type: 'blockquote', content: [paragraph('Quoted')] }, paragraph('After')), features: TABLES });
    await editor.focus('end');
    assert.equal(editor.target('quoteTools').hidden, true);

    await editor.focus(3);
    assert.equal(editor.target('quoteTools').hidden, false);
    await editor.press('quoteSource');
    editor.editor.commands.insertContent('Its author');

    assert.deepEqual(editor.posted().content[0].content[1], { type: 'quoteSource', content: [{ type: 'text', text: 'Its author' }] });
});

test('a control without a tooltip or a name of its own is renamed without fault', async () => {
    const editor = await openEditor({ document: doc(paragraph('Text')) });
    const moveUp = editor.control('moveUp');
    moveUp.replaceChildren();

    await editor.focus('end');
    await settle();

    assert.equal(moveUp.textContent, '');
});

test('a kind of block no option names leaves the kind without a name', async () => {
    const editor = await openEditor({ document: doc(heading(2, 'Title')), blockOptions: [{ value: 'paragraph', label: 'Paragraph' }] });

    await editor.focus(1);

    assert.deepEqual(editor.named(editor.target('blockType')), ['']);
});

test('Ctrl+Shift+Arrow moves the block from the keyboard, and tells it', async () => {
    const editor = await openEditor({ document: doc(paragraph('One'), paragraph('Two')) });
    await editor.focus('end');

    assert.equal(editor.key('ArrowUp', { ctrlKey: true, shiftKey: true }), true);

    assert.deepEqual(editor.posted(), doc(paragraph('Two'), paragraph('One')));
    assert.deepEqual(await editor.announced(), ['', 'Block moved up']);
});
