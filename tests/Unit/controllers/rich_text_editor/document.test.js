import assert from 'node:assert/strict';
import test from 'node:test';
import { answer, later, settle } from '../../support/dom.js';
import { doc, heading, openEditor, paragraph } from '../../support/rich_text_editor.js';

const ALL = { blocks: ['heading', 'quote', 'list', 'callout', 'table', 'image', 'widget'], headingLevels: [2, 3], marks: ['bold', 'link'], calloutVariants: ['note'] };

test('the editor takes the place of the notice, named and described by the texts of the field', async () => {
    const editor = await openEditor({ document: doc(paragraph('Hello')) });
    const text = editor.editor.view.dom;

    assert.equal(editor.target('frame').hidden, false);
    assert.equal(editor.target('hint').hidden, false);
    assert.equal(editor.target('editor').contains(text), true);
    assert.deepEqual(
        ['id', 'class', 'role', 'aria-multiline', 'aria-labelledby', 'aria-describedby'].map((name) => text.getAttribute(name)),
        ['content', 'tiptap ProseMirror rich-text rich-text-editable', 'textbox', 'true', 'content-label', 'content-hint'],
    );
    assert.equal(text.textContent, 'Hello');
    // The styles come from the site's stylesheets: an injected one breaks its security policy.
    assert.equal(editor.document.querySelectorAll('style').length, 0);
    // The cursor keeps clear of the dock, above and below, when scrolled into view.
    assert.deepEqual(editor.editor.view.someProp('scrollMargin'), { top: 80, right: 5, bottom: 220, left: 5 });
    assert.deepEqual(editor.editor.view.someProp('scrollThreshold'), { top: 80, right: 0, bottom: 220, left: 0 });
});

test('what is written goes to the form, which is told, and to the count of characters', async () => {
    const editor = await openEditor({ document: doc(paragraph('Hello')), maxLength: 6 });
    let inputs = 0;
    editor.target('input').addEventListener('input', () => { inputs += 1; });

    assert.equal(editor.editor.view.dom.getAttribute('aria-describedby'), 'content-hint content-count');
    assert.equal(editor.target('count').textContent, 'Count 5/6');

    editor.editor.chain().focus('end').insertContent(' world').run();

    assert.deepEqual(editor.posted(), doc(paragraph('Hello world')));
    assert.equal(inputs, 1);
    assert.equal(editor.target('count').textContent, 'Over by 5');
});

test('a single paragraph is one line of text, with only the marks the field allows', async () => {
    const editor = await openEditor({
        document: doc(paragraph('One line')),
        singleParagraph: true,
        features: { blocks: ['heading'], headingLevels: [2], marks: ['bold', 'link'] },
        links: true,
    });
    const text = editor.editor.view.dom;

    assert.deepEqual([text.getAttribute('class'), text.getAttribute('aria-multiline')], ['tiptap ProseMirror rich-text rich-text-editable rich-text-single', 'false']);
    assert.deepEqual(Object.keys(editor.editor.schema.nodes).sort(), ['doc', 'paragraph', 'text']);
    assert.deepEqual(Object.keys(editor.editor.schema.marks).sort(), ['bold', 'link']);

    editor.editor.commands.focus('end');
    editor.key('Enter');
    assert.deepEqual(editor.posted(), doc(paragraph('One line')));
    assert.equal(editor.target('blockType') === null, true);
});

test('a single paragraph without marks, or without where to look links up, takes none', async () => {
    const editor = await openEditor({ singleParagraph: true, features: { blocks: [], headingLevels: [], marks: ['link'] } });

    assert.deepEqual(Object.keys(editor.editor.schema.marks), []);
    assert.equal(editor.editor.view.dom.querySelector('p').dataset.placeholder, 'Write something');
});

test('the editor registers only the blocks and marks its field allows, and what they need', async () => {
    const editor = await openEditor({ features: ALL, images: true, links: true, widgets: true });
    const { schema } = editor.editor;

    assert.deepEqual(Object.keys(schema.nodes).sort(), [
        'blockquote', 'bulletList', 'callout', 'doc', 'heading', 'image', 'listItem', 'orderedList', 'paragraph',
        'quoteSource', 'table', 'tableCell', 'tableHeader', 'tableRow', 'text', 'widget',
    ]);
    assert.deepEqual(Object.keys(schema.marks).sort(), ['bold', 'link']);
    // Quotes end in their source; lists, quotes and table cells hold paragraphs only.
    assert.deepEqual(
        ['blockquote', 'listItem', 'tableHeader', 'tableCell'].map((name) => schema.nodes[name].spec.content),
        ['paragraph+ quoteSource?', 'paragraph', 'paragraph', 'paragraph'],
    );
    assert.equal(editor.editor.can().setHeading({ level: 3 }), true);
    assert.equal(editor.editor.can().setHeading({ level: 4 }), false);

    editor.editor.chain().focus().insertTable({ rows: 1, cols: 1 }).run();
    // Columns keep a readable width and aren't resized by dragging.
    assert.equal(editor.editor.view.dom.querySelector('col').style.minWidth, '144px');
    assert.equal(editor.editor.state.plugins.some((plugin) => plugin.key.startsWith('tableColumnResizing')), false);

    editor.editor.commands.setContent(doc(
        { type: 'blockquote', content: [paragraph('Quoted'), { type: 'quoteSource' }] },
        { type: 'callout', attrs: { variant: 'note' }, content: [paragraph('Noted')] },
    ));
    assert.equal(editor.editor.view.dom.querySelector('[data-quote-source]').dataset.placeholder, 'Source');
    assert.equal(editor.editor.view.dom.querySelector('[data-callout]').dataset.label, 'Note');
});

test('without the endpoints they need, images, links and widgets are left out', async () => {
    for (const options of [{}, { links: true }, { images: false, widgets: false }]) {
        const editor = await openEditor({ features: ALL, ...options });
        const { nodes, marks } = editor.editor.schema;

        assert.equal('image' in nodes, false);
        assert.equal('widget' in nodes, false);
        assert.equal('link' in marks, options.links === true);
    }

    const blocks = await openEditor({ features: { blocks: [], headingLevels: [], marks: [] }, images: true, links: true, widgets: true });
    assert.deepEqual(Object.keys(blocks.editor.schema.nodes).sort(), ['doc', 'paragraph', 'text']);
    assert.deepEqual(Object.keys(blocks.editor.schema.marks), []);
});

test('an empty document says what goes in it, and an empty line how to pick a kind of block', async () => {
    const empty = await openEditor();
    assert.equal(empty.editor.view.dom.querySelector('p').dataset.placeholder, 'Write something');

    const editor = await openEditor({ document: doc(heading(2, 'Title'), paragraph()) });
    editor.editor.commands.focus('end');
    await settle();
    assert.equal(editor.editor.view.dom.querySelector('p').dataset.placeholder, 'Choose a block');

    editor.editor.chain().setHeading({ level: 2 }).run();
    assert.equal(editor.editor.view.dom.querySelectorAll('h2')[1].dataset.placeholder, '');
});

test('an editor removed from the page cancels what it was looking up and lets go of the text', async () => {
    const pending = later();
    const editor = await openEditor({
        document: doc(paragraph('Linked', [{ type: 'link', attrs: { type: 'page', id: 'page-1' } }])),
        features: { blocks: [], headingLevels: [], marks: ['link'] },
        links: true,
        respond: () => pending.promise,
    });

    editor.document.querySelector('[data-controller]').remove();
    await settle();
    pending.resolve(answer({ pages: [] }));
    await settle();

    assert.equal(editor.requests[0].signal.aborted, true);
    assert.equal(editor.editor.isDestroyed, true);
});
