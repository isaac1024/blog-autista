import assert from 'node:assert/strict';
import test from 'node:test';
import CharacterCount from '../../../assets/controllers/character_count_controller.js';
import { characterCount } from '../../Markup/small_controllers.js';
import { mount } from '../support/dom.js';

test('the count replaces the server\'s text, and follows what is written against its length', async () => {
    const { document, window } = await mount(characterCount('Hi'), { 'character-count': CharacterCount });
    const field = document.getElementById('title');
    const output = document.getElementById('title-count');
    const type = (value) => {
        field.value = value;
        field.dispatchEvent(new window.Event('input', { bubbles: true }));

        return output.textContent;
    };

    assert.equal(output.textContent, '2 of 5 to 10: short');
    assert.equal(type('  Hello  '), '5 of 5 to 10: fits');
    assert.equal(type('Hello there!'), '12 of 5 to 10: long');
});
