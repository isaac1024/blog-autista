import assert from 'node:assert/strict';
import test from 'node:test';
import UnsavedForm from '../../../assets/controllers/unsaved_form_controller.js';
import { actionBar } from '../../Markup/small_controllers.js';
import { mount } from '../support/dom.js';

/*
 * An edit form whose bar knows whether it holds changes, and a form of its
 * own to publish, as an editor of the CMS has.
 */
const open = async (options) => {
    const { window, document } = await mount(actionBar(options), { 'unsaved-form': UnsavedForm });
    const field = /** @type {HTMLInputElement} */ (document.getElementById('title'));
    const status = () => document.querySelector('[data-unsaved-form-target="status"]')?.textContent;

    return {
        status,
        type: (value) => {
            field.value = value;
            field.dispatchEvent(new window.Event('input', { bubbles: true }));
        },
        send: (id) => {
            document.getElementById(id)?.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        },
        /** Whether leaving the page asks first. */
        leaveAsks: () => {
            const event = new window.Event('beforeunload', { cancelable: true });
            window.dispatchEvent(event);

            return event.defaultPrevented;
        },
    };
};

test('while the form holds changes, the bar says so, and says again what is saved once they are undone', async () => {
    const { status, type } = await open();

    type('Second');
    const changed = status();
    type('First');

    assert.deepEqual([changed, status()], ['Unsaved changes', 'Draft: not on the site']);
});

test('leaving the page asks first only while the form holds changes', async () => {
    const { type, leaveAsks } = await open();
    const unchanged = leaveAsks();

    type('Second');

    assert.deepEqual([unchanged, leaveAsks()], [false, true]);
});

test('sending the form leaves without asking, but sending another form still asks', async () => {
    const { type, send, leaveAsks } = await open();
    type('Second');

    send('publish');
    const byAnother = leaveAsks();
    send('edit');

    assert.deepEqual([byAnother, leaveAsks()], [true, false]);
});

test('a bar without a status still asks before leaving changes behind', async () => {
    const { type, leaveAsks } = await open({ status: null });

    type('Second');

    assert.equal(leaveAsks(), true);
});
