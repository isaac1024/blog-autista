import assert from 'node:assert/strict';
import test from 'node:test';
import { countMessage, maxLengthMessage } from '../../../assets/form/character_count.js';

const RULE = { min: 5, max: 10, short: '%count% < %min%', fits: '%count% in %min%-%max%', long: '%count% > %max%' };

test('a text shorter than the minimum falls short', () => {
    assert.equal(countMessage('Hi', RULE), '2 < 5');
    assert.equal(countMessage('', RULE), '0 < 5');
});

test('a text within the length fits, at either end too', () => {
    assert.equal(countMessage('Hello', RULE), '5 in 5-10');
    assert.equal(countMessage('Hello word', RULE), '10 in 5-10');
});

test('a text longer than the maximum runs long', () => {
    assert.equal(countMessage('Hello there', RULE), '11 > 10');
});

test('the spaces around the text do not count, and an emoji counts once', () => {
    assert.equal(countMessage('   Hello   ', RULE), '5 in 5-10');
    assert.equal(countMessage('🙂🙂🙂🙂', RULE), '4 < 5');
});

const MAX = { max: 5, count: '%count% of %max%', over: '%count% of %max%, %over% too many' };

test('a text within the most it takes says how much of it it uses, up to the limit itself', () => {
    assert.equal(maxLengthMessage('Hi', MAX), '2 of 5');
    assert.equal(maxLengthMessage('Hello', MAX), '5 of 5');
});

test('a text past the most it takes says by how much, counting what people see', () => {
    assert.equal(maxLengthMessage('Hello!!', MAX), '7 of 5, 2 too many');
    assert.equal(maxLengthMessage('🙂🙂🙂🙂🙂🙂', MAX), '6 of 5, 1 too many');
});
