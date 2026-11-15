import assert from 'node:assert/strict';
import test from 'node:test';
import { doc, heading, openEditor, paragraph } from '../../support/rich_text_editor.js';

const FEATURES = { blocks: ['heading'], headingLevels: [2, 3], marks: [] };

/** The sections of the outline, as listed: their titles, indents and which one is current. */
const listed = (editor) => [...editor.target('outlineDialogList').children].map((item) => {
    const [link] = item.children;

    return [link.tagName, link.type, link.className, link.textContent, link.getAttribute('aria-current')];
});

test('the outline lists every heading, indented by level, and only shows its button when there is one', async () => {
    const editor = await openEditor({ document: doc(paragraph('Intro')), features: FEATURES });
    assert.equal(editor.target('outlineButton').hidden, true);
    assert.deepEqual(listed(editor), []);

    editor.editor.chain().focus('end').insertContent([heading(2, 'First'), heading(3, 'Inside'), paragraph('Text')]).run();

    assert.equal(editor.target('outlineButton').hidden, false);
    assert.deepEqual(listed(editor), [
        ['BUTTON', 'button', 'rich-text-outline-item pl-2', 'First', null],
        ['BUTTON', 'button', 'rich-text-outline-item pl-6', 'Inside', 'location'],
    ]);
});

test('the section where the cursor stands is marked as current, and none before the first', async () => {
    const editor = await openEditor({ document: doc(paragraph('Intro'), heading(2, 'First'), paragraph('One'), heading(2, 'Second')), features: FEATURES });
    const current = () => listed(editor).map((section) => section[4]);

    await editor.focus(1);
    assert.deepEqual(current(), [null, null]);

    await editor.focus(10);
    assert.deepEqual(current(), ['location', null]);

    await editor.focus('end');
    assert.deepEqual(current(), [null, 'location']);
});

test('a section picked in the outline closes it, takes the cursor to its heading and scrolls it to the top', async () => {
    for (const [reducedMotion, behavior] of [[false, 'smooth'], [true, 'auto']]) {
        const editor = await openEditor({ document: doc(heading(2, 'First'), paragraph('One'), heading(2, 'Second'), paragraph('Two')), features: FEATURES, reducedMotion });
        const scrolled = [];
        editor.window.HTMLElement.prototype.scrollIntoView = function (options) { scrolled.push([this.textContent, options]); };
        // The heading scrolls itself to the top; the editor doesn't scroll to the cursor first.
        editor.window.scrollBy = (...by) => { scrolled.push(['page', by]); };
        editor.target('outlineDialog').showModal();

        editor.target('outlineDialogList').children[1].firstElementChild.click();
        await editor.frame();

        assert.equal(editor.target('outlineDialog').open, false);
        assert.equal(editor.editor.state.selection.from, 13);
        assert.equal(editor.editor.isFocused, true);
        assert.deepEqual(scrolled, [['Second', { block: 'start', behavior }]]);
    }
});

test('a section whose heading isn\'t drawn is still reached, with nothing to scroll', async () => {
    const editor = await openEditor({ document: doc(heading(2, 'First'), paragraph('One')), features: FEATURES });
    const scrolled = [];
    editor.window.HTMLElement.prototype.scrollIntoView = function () { scrolled.push(this.textContent); };
    editor.editor.view.nodeDOM = () => editor.document.createTextNode('First');

    editor.target('outlineDialogList').children[0].firstElementChild.click();

    assert.equal(editor.editor.state.selection.from, 1);
    assert.deepEqual(scrolled, []);
});

test('a single paragraph has no outline', async () => {
    const editor = await openEditor({ singleParagraph: true, document: doc(paragraph('Line')) });

    editor.editor.chain().focus('end').insertContent(' more').run();

    assert.equal(editor.target('outlineDialogList') === null, true);
    assert.equal(editor.document.querySelector('.rich-text-outline-item') === null, true);
});
