import assert from 'node:assert/strict';
import test from 'node:test';
import { flatten } from '../../../assets/form/single_line.js';

test('each run of line breaks becomes one space', () => {
    assert.equal(flatten('First\r\n\npage\nof the\rblog'), 'First page of the blog');
});

test('a single line is left as it is', () => {
    assert.equal(flatten('First  page'), 'First  page');
});
