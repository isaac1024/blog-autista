import assert from 'node:assert/strict';
import test from 'node:test';
import { notifyForm } from '../../../assets/shared/forms.js';

test('notifyForm sends a bubbling input event through the changed element', () => {
    const element = {
        event: null,
        dispatchEvent(event) {
            this.event = event;
        },
    };

    notifyForm(element);

    assert.equal(element.event.type, 'input');
    assert.equal(element.event.bubbles, true);
});
