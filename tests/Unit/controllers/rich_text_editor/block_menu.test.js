import assert from 'node:assert/strict';
import test from 'node:test';
import { assertFocused, settle } from '../../support/dom.js';
import { doc, heading, openEditor, paragraph } from '../../support/rich_text_editor.js';

const FEATURES = { blocks: ['heading', 'quote', 'list', 'callout', 'table', 'image', 'widget'], headingLevels: [2], marks: [], calloutVariants: ['note'] };
const BLOCK_OPTIONS = [
    { value: 'paragraph', label: 'Paragraph' },
    { value: 'heading:2', label: 'Heading 2' },
    { value: 'quote', label: 'Quote' },
    { value: 'bulletList', label: 'Bulleted list' },
    { value: 'orderedList', label: 'Numbered list' },
    { value: 'callout:note', label: 'Note' },
];

const open = (options = {}) => openEditor({ document: doc(paragraph('Text')), features: FEATURES, blockOptions: BLOCK_OPTIONS, ...options });

/** Opens the menu from its button, with the cursor at the end of the text. */
const fromButton = async (editor) => {
    await editor.focus('end');
    await editor.press(editor.target('blockType'));
};

test('the button opens the menu at the current kind, and takes the focus to it', async () => {
    const editor = await open();
    const button = editor.target('blockType');
    button.getBoundingClientRect = () => ({ top: 100, bottom: 140, left: 30, right: 200, width: 170, height: 40 });

    await fromButton(editor);
    const menu = editor.target('menu');

    assert.equal(button.getAttribute('aria-expanded'), 'true');
    assert.equal(menu.hidden, false);
    assertFocused(menu);
    assert.deepEqual(editor.options(), [
        ['Paragraph', 'true'], ['Heading 2', 'false'], ['Quote', 'false'], ['Bulleted list', 'false'], ['Numbered list', 'false'], ['Note', 'false'],
    ]);
    assert.deepEqual([...menu.children].map((option) => [option.id, option.getAttribute('role'), option.className]).slice(0, 2), [
        ['content-menu-0', 'option', 'rich-text-menu-option'],
        ['content-menu-1', 'option', 'rich-text-menu-option'],
    ]);
    assert.equal(menu.getAttribute('aria-activedescendant'), 'content-menu-0');
    assert.deepEqual([menu.style.top, menu.style.left], ['148px', '30px']);
});

test('the menu opens at the kind the cursor is in', async () => {
    const editor = await open({ document: doc(heading(2, 'Title')) });

    await fromButton(editor);

    assert.deepEqual(editor.options().map(([, selected]) => selected), ['false', 'true', 'false', 'false', 'false', 'false']);
});

test('the keys of the menu move along its options, round, and Escape takes the focus back to the button', async () => {
    const editor = await open();
    const scrolled = [];
    editor.window.HTMLElement.prototype.scrollIntoView = function (options) { scrolled.push([this.textContent, options]); };
    await fromButton(editor);
    const menu = editor.target('menu');

    assert.equal(editor.key('ArrowDown', {}, menu), true);
    assert.equal(menu.getAttribute('aria-activedescendant'), 'content-menu-1');
    assert.equal(editor.key('ArrowUp', {}, menu), true);
    assert.equal(editor.key('ArrowUp', {}, menu), true);
    assert.deepEqual(editor.options().at(-1), ['Note', 'true']);
    assert.equal(editor.key('Home', {}, menu), true);
    assert.deepEqual(scrolled.at(-1), ['Paragraph', { block: 'nearest' }]);
    assert.equal(editor.key('a', {}, menu), false);

    assert.equal(editor.key('Escape', {}, menu), true);
    assert.equal(menu.hidden, true);
    assert.equal(editor.target('blockType').getAttribute('aria-expanded'), 'false');
    assert.equal(menu.hasAttribute('aria-activedescendant'), false);
    assertFocused(editor.target('blockType'));
});

test('Enter on an option turns the block into that kind, back in the text', async () => {
    const editor = await open();
    await fromButton(editor);

    editor.key('ArrowDown', {}, editor.target('menu'));
    editor.key('Enter', {}, editor.target('menu'));
    await editor.frame();

    assert.deepEqual(editor.posted(), doc(heading(2, 'Text')));
    assert.equal(editor.target('menu').hidden, true);
    assertFocused(editor.editor.view.dom);
    assert.equal(editor.target('dock').hidden, false);
});

test('each kind starts from a plain paragraph, out of the list, quote or callout it was in', async () => {
    const quote = { type: 'blockquote', content: [paragraph('Text')] };
    const callout = { type: 'callout', attrs: { variant: 'note' }, content: [paragraph('Text')] };
    const bullets = { type: 'bulletList', content: [{ type: 'listItem', content: [paragraph('Text')] }] };
    const numbers = { type: 'orderedList', attrs: { start: 1, type: null }, content: [{ type: 'listItem', content: [paragraph('Text')] }] };

    for (const [from, label, to] of [
        [paragraph('Text'), 'Quote', quote],
        [paragraph('Text'), 'Bulleted list', bullets],
        [paragraph('Text'), 'Numbered list', numbers],
        [paragraph('Text'), 'Note', callout],
        [bullets, 'Paragraph', paragraph('Text')],
        [numbers, 'Heading 2', heading(2, 'Text')],
        [quote, 'Paragraph', paragraph('Text')],
        [callout, 'Quote', quote],
        [{ type: 'blockquote', content: [callout] }, 'Paragraph', paragraph('Text')],
    ]) {
        const editor = await open({ document: doc(from) });
        await fromButton(editor);

        [...editor.target('menu').children].find((option) => option.textContent === label).click();

        assert.deepEqual(editor.posted(), doc(to), `${JSON.stringify(from)} to ${label}`);
    }
});

test('pressing an option keeps the focus off it, and the button closes the menu it opened', async () => {
    const editor = await open();
    await fromButton(editor);
    const [option] = editor.target('menu').children;
    const down = new editor.window.MouseEvent('mousedown', { bubbles: true, cancelable: true });

    option.dispatchEvent(down);
    assert.equal(down.defaultPrevented, true);

    await editor.press(editor.target('blockType'));
    assert.equal(editor.target('menu').hidden, true);
    assert.equal(editor.target('blockType').getAttribute('aria-expanded'), 'false');
});

test('the menu closes when the pointer goes down elsewhere, the page scrolls or resizes, or the focus leaves it', async () => {
    for (const leave of [
        (editor) => editor.editor.view.dom.dispatchEvent(new editor.window.PointerEvent('pointerdown', { bubbles: true })),
        (editor) => editor.window.dispatchEvent(new editor.window.Event('scroll')),
        (editor) => editor.window.dispatchEvent(new editor.window.Event('resize')),
        (editor) => editor.target('menu').dispatchEvent(new editor.window.FocusEvent('focusout', { bubbles: true, relatedTarget: editor.control('undo') })),
    ]) {
        const editor = await open();
        await fromButton(editor);

        leave(editor);

        assert.equal(editor.target('menu').hidden, true);
    }
});

test('the pointer on the menu, or on its button, and the focus moving within, leave it open', async () => {
    const editor = await open();
    await fromButton(editor);
    const menu = editor.target('menu');

    menu.children[0].dispatchEvent(new editor.window.PointerEvent('pointerdown', { bubbles: true }));
    editor.target('blockType').dispatchEvent(new editor.window.PointerEvent('pointerdown', { bubbles: true }));
    menu.dispatchEvent(new editor.window.FocusEvent('focusout', { bubbles: true, relatedTarget: menu.children[1] }));
    menu.dispatchEvent(new editor.window.FocusEvent('focusout', { bubbles: true, relatedTarget: editor.target('blockType') }));
    assert.equal(menu.hidden, false);

    menu.dispatchEvent(new editor.window.FocusEvent('focusout', { bubbles: true }));
    assert.equal(menu.hidden, true);

    // Once closed, nothing it listened to closes it again.
    editor.window.dispatchEvent(new editor.window.Event('resize'));
    menu.dispatchEvent(new editor.window.FocusEvent('focusout', { bubbles: true }));
    assert.equal(editor.key('ArrowDown', {}, menu), false);
});

test('in a table the kind of block is held, and the menu doesn\'t open', async () => {
    const editor = await open();
    await editor.focus('end');
    await editor.press('insertTable');

    await editor.press(editor.target('blockType'));

    assert.equal(editor.target('menu').hidden, true);
});

test('leaving the editor closes the menu, and so does removing it', async () => {
    const editor = await open();
    await fromButton(editor);

    editor.target('canvas').dispatchEvent(new editor.window.FocusEvent('focusout', { bubbles: true, relatedTarget: editor.document.body }));
    assert.equal(editor.target('menu').hidden, true);

    await fromButton(editor);
    const menu = editor.target('menu');
    editor.document.querySelector('[data-controller]').remove();
    await settle();
    assert.equal(menu.hidden, true);
});

test('"/" at the start of a line lists the kinds and the blocks inserted whole, keeping the focus in the text', async () => {
    const editor = await open({ document: doc(paragraph('Text'), paragraph()), images: true, widgets: true });
    editor.window.HTMLElement.prototype.scrollIntoView = () => {};
    await editor.focus('end');

    editor.editor.commands.insertContent('/');
    await settle();

    const text = editor.editor.view.dom;
    assert.equal(editor.target('menu').hidden, false);
    assert.deepEqual(editor.options().map(([label]) => label), [...BLOCK_OPTIONS.map(({ label }) => label), 'Insert table', 'Insert image', 'Related page']);
    assert.deepEqual(['aria-controls', 'aria-expanded', 'aria-activedescendant'].map((name) => text.getAttribute(name)), ['content-menu', 'true', 'content-menu-0']);
    assert.equal(editor.target('menu').hasAttribute('aria-activedescendant'), false);
    assertFocused(text);

    editor.editor.commands.insertContent('li');
    await settle();
    assert.deepEqual(editor.options().map(([label]) => label), ['Bulleted list', 'Numbered list']);

    assert.equal(editor.key('ArrowDown'), true);
    assert.equal(text.getAttribute('aria-activedescendant'), 'content-menu-1');
    editor.editor.commands.insertContent('s');
    await settle();
    assert.deepEqual(editor.options(), [['Bulleted list', 'false'], ['Numbered list', 'true']]);
    editor.editor.commands.insertContent('t');
    await settle();
    assert.deepEqual(editor.options(), [['Bulleted list', 'false'], ['Numbered list', 'true']]);

    assert.equal(editor.key('Enter'), true);
    await settle();
    assert.deepEqual(editor.posted().content[1].type, 'orderedList');
    assert.equal(editor.target('menu').hidden, true);
    assert.deepEqual(['aria-expanded', 'aria-activedescendant'].map((name) => text.getAttribute(name)), ['false', null]);
});

test('"/" with nothing to answer what follows closes the menu, and Escape leaves the text as written', async () => {
    const editor = await open({ document: doc(paragraph()) });
    editor.window.HTMLElement.prototype.scrollIntoView = () => {};
    await editor.focus('end');

    editor.editor.commands.insertContent('/zz');
    await settle();
    assert.equal(editor.target('menu').hidden, true);

    editor.editor.commands.deleteRange({ from: 2, to: 4 });
    await settle();
    assert.equal(editor.target('menu').hidden, false);
    assert.equal(editor.key('Escape'), true);
    await settle();
    assert.equal(editor.target('menu').hidden, true);
    assert.deepEqual(editor.posted(), doc(paragraph('/')));
});

test('an option clicked in the "/" menu turns the block into it, and the blocks inserted whole open where to pick them', async () => {
    const editor = await open({ document: doc(paragraph()), images: true, widgets: true });
    editor.window.HTMLElement.prototype.scrollIntoView = () => {};
    editor.window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
    const slash = async (query) => {
        await editor.focus('end');
        editor.editor.commands.insertContent(`/${query}`);
        await settle();
        editor.target('menu').children[0].click();
        await settle();
    };

    await slash('quote');
    assert.deepEqual(editor.posted(), doc({ type: 'blockquote', content: [paragraph()] }));

    editor.editor.commands.setContent(doc(paragraph()));
    await slash('table');
    assert.equal(editor.posted().content[0].type, 'table');

    editor.editor.commands.setContent(doc(paragraph()));
    await slash('image');
    assert.equal(editor.target('imageDialog').open, true);
    editor.target('imageDialog').removeAttribute('open');

    editor.editor.commands.setContent(doc(paragraph()));
    await slash('related');
    assert.equal(editor.target('pageDialog').open, true);
    assert.equal(editor.target('pageDialogTitle').textContent, 'Insert related content');
    assert.deepEqual(editor.posted(), doc(paragraph()));
});

test('the "/" menu offers whole blocks only where the field allows them and has their control', async () => {
    const editor = await open({ document: doc(paragraph()), features: { ...FEATURES, blocks: ['heading'] }, images: true, widgets: true });
    await editor.focus('end');

    editor.editor.commands.insertContent('/');
    await settle();
    assert.deepEqual(editor.options().map(([label]) => label), BLOCK_OPTIONS.map(({ label }) => label));

    const unlabelled = await open({ document: doc(paragraph()) });
    unlabelled.control('insertTable').removeAttribute('data-label');
    await unlabelled.focus('end');
    unlabelled.editor.commands.insertContent('/');
    await settle();
    assert.deepEqual(unlabelled.options().map(([label]) => label), BLOCK_OPTIONS.map(({ label }) => label));
});

test('"/" doesn\'t open in a table, nor in the source of a quote', async () => {
    const editor = await open({ document: doc(paragraph()) });
    await editor.focus('end');
    await editor.press('insertTable');

    editor.editor.commands.insertContent('/');
    await settle();

    assert.equal(editor.target('menu').hidden, true);
});

test('the menu closes on a pointer that something keeps to itself, and on the scroll of any part of the page', async () => {
    for (const leave of [
        (editor) => {
            const keeper = editor.document.getElementById('content-label');
            keeper.addEventListener('pointerdown', (event) => event.stopPropagation());
            keeper.dispatchEvent(new editor.window.PointerEvent('pointerdown', { bubbles: true }));
        },
        (editor) => editor.target('editor').dispatchEvent(new editor.window.Event('scroll')),
    ]) {
        const editor = await open();
        await fromButton(editor);

        leave(editor);

        assert.equal(editor.target('menu').hidden, true);
    }
});

test('a menu closed lets go of the page, so it doesn\'t close the "/" menu after it', async () => {
    const editor = await open({ document: doc(paragraph('Text'), paragraph()) });
    editor.window.HTMLElement.prototype.scrollIntoView = () => {};
    await fromButton(editor);
    editor.key('Escape', {}, editor.target('menu'));

    await editor.focus('end');
    editor.editor.commands.insertContent('/');
    await settle();
    editor.window.dispatchEvent(new editor.window.Event('resize'));
    editor.window.dispatchEvent(new editor.window.Event('scroll'));
    editor.document.body.dispatchEvent(new editor.window.PointerEvent('pointerdown', { bubbles: true }));

    assert.equal(editor.target('menu').hidden, false);
});

test('what follows "/" narrows the options, keeping the one highlighted within them, and closes it when none is left', async () => {
    const editor = await open({ document: doc(paragraph()) });
    editor.window.HTMLElement.prototype.scrollIntoView = () => {};
    await editor.focus('end');
    const type = async (text) => {
        editor.editor.view.dispatch(editor.editor.state.tr.insertText(text));
        await settle();
    };

    await type('/');
    editor.key('End');
    assert.deepEqual(editor.options().at(-1), ['Insert table', 'true']);

    await type('l');
    assert.deepEqual(editor.options(), [['Bulleted list', 'false'], ['Numbered list', 'false'], ['Insert table', 'true']]);
    await type('i');
    assert.deepEqual(editor.options(), [['Bulleted list', 'false'], ['Numbered list', 'true']]);

    await type('zz');
    assert.equal(editor.target('menu').hidden, true);
    assert.equal(editor.editor.view.dom.getAttribute('aria-expanded'), 'false');

    // With nothing to choose, Enter is the text's again.
    editor.key('Enter');
    assert.deepEqual(editor.posted(), doc(paragraph('/lizz'), paragraph()));
});
