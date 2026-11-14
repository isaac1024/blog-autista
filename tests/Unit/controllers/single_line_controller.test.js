import assert from 'node:assert/strict';
import test from 'node:test';
import SingleLine from '../../../assets/controllers/single_line_controller.js';
import { singleLine } from '../../Markup/small_controllers.js';
import { mount, settle } from '../support/dom.js';

/*
 * A page whose browser sizes fields to their content, or not. One that
 * doesn't measures the text of a text area 88 pixels high, and never less
 * than the height the field is given.
 */
const open = async (value, { fieldSizing = false } = {}) => {
    const page = await mount('', { 'single-line': SingleLine });
    globalThis.CSS = { supports: (property, expected) => fieldSizing && property === 'field-sizing' && expected === 'content' };
    Object.defineProperty(page.window.HTMLTextAreaElement.prototype, 'scrollHeight', { configurable: true, get() {
        return Math.max(88, Number.parseInt(this.style.height || '0', 10));
    } });
    page.document.body.innerHTML = singleLine(value);
    await settle();
    const field = page.document.getElementById('title');

    return {
        field,
        press: (key) => {
            const event = new page.window.KeyboardEvent('keydown', { key, cancelable: true, bubbles: true });
            field.dispatchEvent(event);

            return event.defaultPrevented;
        },
        paste: (text, cursor) => {
            field.value = text;
            field.setSelectionRange(cursor, cursor);
            field.style.height = '200px';
            field.dispatchEvent(new page.window.Event('input', { bubbles: true }));
        },
    };
};

test('where the browser can\'t size the field to its text, it grows to it from the start', async () => {
    const { field } = await open('A title');

    assert.equal(field.style.height, '88px');
});

test('where the browser sizes the field itself, it is left alone', async () => {
    const { field } = await open('A title', { fieldSizing: true });

    assert.equal(field.style.height, '');
});

test('Enter adds no line break; every other key is left alone', async () => {
    const { press } = await open('A title');

    assert.equal(press('Enter'), true);
    assert.equal(press('a'), false);
});

test('pasted breaks become spaces, the cursor stays where it was and the field fits its text', async () => {
    const { field, paste } = await open('');

    paste('First\nsecond', 7);

    assert.equal(field.value, 'First second');
    assert.deepEqual([field.selectionStart, field.selectionEnd], [7, 7]);
    assert.equal(field.style.height, '88px');
});

test('a value already on one line keeps its selection, and the field still fits its text', async () => {
    const { field, paste } = await open('');
    paste('One line', 0);
    field.setSelectionRange(2, 5);

    field.dispatchEvent(new field.ownerDocument.defaultView.Event('input', { bubbles: true }));

    assert.deepEqual([field.selectionStart, field.selectionEnd], [2, 5]);
    assert.equal(field.style.height, '88px');
});
