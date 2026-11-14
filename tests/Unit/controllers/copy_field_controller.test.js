import assert from 'node:assert/strict';
import test from 'node:test';
import CopyField from '../../../assets/controllers/copy_field_controller.js';
import { copyField } from '../../Markup/small_controllers.js';
import { assertFocused, later, mount, settle } from '../support/dom.js';

const ADDRESS = 'https://blogautista.test/first-page.html';

/** The field, its status, and the clipboard the test lends the page. */
const open = async (writeText) => {
    const page = await mount(copyField(ADDRESS), { 'copy-field': CopyField });
    Object.defineProperty(page.window.navigator, 'clipboard', { configurable: true, value: { writeText } });
    const field = page.document.getElementById('address');

    return {
        ...page,
        field,
        status: page.document.querySelector('[role="status"]'),
        copy: () => page.document.querySelector('button').click(),
        selected: () => field.value.slice(field.selectionStart, field.selectionEnd),
    };
};

test('focusing the field selects its whole value', async () => {
    const { field, selected } = await open(async () => {});

    field.focus();

    assert.equal(selected(), ADDRESS);
});

test('copying clears the status while it waits, then says it copied the value', async () => {
    const copying = later();
    const copied = [];
    const { status, copy } = await open((text) => {
        copied.push(text);

        return copying.promise;
    });
    status.textContent = 'Copied';

    copy();
    assert.equal(status.textContent, '');

    copying.resolve();
    await settle();
    assert.deepEqual(copied, [ADDRESS]);
    assert.equal(status.textContent, 'Copied');
});

test('when the browser won\'t copy, the value is left focused and selected to copy by hand', async () => {
    const { field, status, copy, selected } = await open(() => Promise.reject(new Error('Not allowed')));

    copy();
    await settle();

    assertFocused(field);
    assert.equal(selected(), ADDRESS);
    assert.equal(status.textContent, 'Copy it by hand');
});

test('a field already focused is selected again when the browser won\'t copy', async () => {
    const { field, copy, selected } = await open(() => Promise.reject(new Error('Not allowed')));
    field.focus();
    field.setSelectionRange(0, 0);

    copy();
    await settle();

    assert.equal(selected(), ADDRESS);
});
