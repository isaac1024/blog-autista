import assert from 'node:assert/strict';
import test from 'node:test';
import { countText } from '../../../assets/shared/count_text.js';

const LABELS = { none: 'No results', result: '1 result', results: '%count% results' };

test('says none, one or how many', () => {
    assert.equal(countText(0, LABELS), 'No results');
    assert.equal(countText(1, LABELS), '1 result');
    assert.equal(countText(12, LABELS), '12 results');
});
