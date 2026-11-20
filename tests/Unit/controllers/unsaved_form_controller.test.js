import assert from 'node:assert/strict';
import test from 'node:test';
import UnsavedForm from '../../../assets/controllers/unsaved_form_controller.js';
import { actionBar } from '../../Markup/small_controllers.js';
import { mount, serve, settle } from '../support/dom.js';

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
        /** Takes the bar off the page, as leaving it for another does. */
        leave: async () => {
            document.querySelector('[data-controller~="unsaved-form"]')?.remove();
            await settle();
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

test('a form back with what was sent and refused holds changes from the start, until it is sent', async () => {
    const { status, send, leaveAsks } = await open({ kept: true });
    const atFirst = [status(), leaveAsks()];

    send('edit');

    assert.deepEqual([atFirst, leaveAsks()], [['Unsaved changes', true], false]);
});

const FIVE_MINUTES = 5 * 60 * 1000;

test('while the form holds changes, the page asks the server for itself every five minutes, so its session lasts', async (t) => {
    t.mock.timers.enable({ apis: ['setInterval'] });
    const requests = serve(() => ({ ok: true, status: 200 }));
    const { type } = await open();
    const methods = () => requests.map(({ url, method }) => `${method} ${url}`);

    t.mock.timers.tick(FIVE_MINUTES);
    const unchanged = methods();
    type('Second');
    t.mock.timers.tick(FIVE_MINUTES - 1);
    const early = methods();
    t.mock.timers.tick(1);

    assert.deepEqual([unchanged, early, methods()], [[], [], ['HEAD https://blog.test/']]);
});

test('a form being sent, or left, no longer keeps its session', async (t) => {
    t.mock.timers.enable({ apis: ['setInterval'] });
    const requests = serve(() => ({ ok: true, status: 200 }));
    const { type, send, leave } = await open();
    type('Second');

    send('edit');
    t.mock.timers.tick(FIVE_MINUTES);
    const sending = requests.length;
    await leave();
    t.mock.timers.tick(FIVE_MINUTES);

    assert.deepEqual([sending, requests.length], [0, 0]);
});

test('a server out of reach is no error: the page asks again later', async (t) => {
    t.mock.timers.enable({ apis: ['setInterval'] });
    const requests = serve(async () => { throw new TypeError('Failed to fetch'); });
    const { type } = await open();
    type('Second');

    t.mock.timers.tick(FIVE_MINUTES);
    await settle();
    t.mock.timers.tick(FIVE_MINUTES);
    await settle();

    assert.equal(requests.length, 2);
});
