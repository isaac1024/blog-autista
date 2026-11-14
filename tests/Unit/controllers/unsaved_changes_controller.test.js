import assert from 'node:assert/strict';
import test from 'node:test';
import UnsavedChanges from '../../../assets/controllers/unsaved_changes_controller.js';
import { unsavedChanges } from '../../Markup/small_controllers.js';
import { mount } from '../support/dom.js';

const open = async (markup = unsavedChanges()) => {
    const { document, window } = await mount(markup, { 'unsaved-changes': UnsavedChanges });
    const button = document.querySelector('[data-unsaved-changes-target="button"]');
    const hint = document.querySelector('[data-unsaved-changes-target="hint"]');

    return {
        document,
        /** Whether the button waits, as it tells and as its hint shows. */
        waiting: () => [button.getAttribute('aria-disabled'), hint.hidden],
        type: (id, value, type = 'input') => {
            const field = document.getElementById(id);
            field.value = value;
            field.dispatchEvent(new window.Event(type, { bubbles: true }));
        },
        /** Whether pressing the button went ahead. */
        press: () => {
            const click = new window.MouseEvent('click', { bubbles: true, cancelable: true });
            button.dispatchEvent(click);

            return !click.defaultPrevented;
        },
    };
};

test('a form as saved leaves the button free to act', async () => {
    const { waiting, press } = await open();

    assert.deepEqual(waiting(), ['false', true]);
    assert.equal(press(), true);
});

test('a change, even to a field outside the form, holds the button until it is undone', async () => {
    const { waiting, type, press } = await open();

    type('summary', 'Another summary');
    assert.deepEqual(waiting(), ['true', false]);
    assert.equal(press(), false);

    type('summary', 'A summary', 'change');
    assert.deepEqual(waiting(), ['false', true]);
    assert.equal(press(), true);
});

test('the fields are compared by name and value, so a field without a name changes nothing', async () => {
    const { document, waiting, type } = await open();
    const unnamed = document.createElement('input');
    unnamed.id = 'unnamed';
    unnamed.setAttribute('form', 'edit');
    document.body.append(unnamed);

    type('unnamed', 'Anything');
    assert.deepEqual(waiting(), ['false', true]);

    type('title', 'Second page');
    assert.deepEqual(waiting(), ['true', false]);
});

test('without the form, or with an element that isn\'t one, nothing ever waits', async () => {
    for (const markup of [
        unsavedChanges().replace('<form id="edit"', '<form id="other"'),
        unsavedChanges().replace('<form id="edit" action="/api/save" method="post">', '<div id="edit">').replace('</form>', '</div>'),
    ]) {
        const { waiting, type } = await open(markup);

        type('title', 'Second page');
        assert.deepEqual(waiting(), ['false', true]);
    }
});
