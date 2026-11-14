import assert from 'node:assert/strict';
import test from 'node:test';
import { cut, searchResult } from '../../../assets/form/search_preview.js';

const RULE = { address: 'https://blogautista.test/%slug%.html', titleLimit: 20, descriptionLimit: 30, empty: 'No description' };

test('a text within its limit is shown whole', () => {
    assert.equal(cut('Twenty characters ok', 20), 'Twenty characters ok');
});

test('a longer text is cut to the limit, ellipsis included, with no space before it', () => {
    assert.equal(cut('A title much longer than twenty', 20), 'A title much longer…');
    assert.equal(cut('Words and more words here', 10), 'Words and…');
    assert.equal(cut('🙂🙂🙂🙂', 3), '🙂🙂…');
    // Only the end of the cut is trimmed: the text still starts where it did.
    assert.equal(cut('  Indented text here', 10), '  Indente…');
});

test('a page shows its address, title and description as a search engine would', () => {
    assert.deepEqual(searchResult({ title: '  First page ', description: ' About   the\nfirst page ', slug: ' first ' }, RULE), {
        url: 'https://blogautista.test/first.html',
        title: 'First page',
        description: 'About the first page',
    });
});

test('an address that follows no slug stays as it is', () => {
    assert.equal(searchResult({ title: '', description: '', slug: '' }, { ...RULE, address: 'https://blogautista.test/' }).url, 'https://blogautista.test/');
});

test('the slug goes wherever the address puts it', () => {
    assert.equal(searchResult({ title: '', description: '', slug: 'guias' }, { ...RULE, address: 'https://blogautista.test/%slug%/' }).url, 'https://blogautista.test/guias/');
});

test('long texts are cut, and an empty description says so', () => {
    const result = searchResult({ title: 'A title much longer than twenty', description: ' \n ', slug: 'x' }, RULE);

    assert.equal(result.title, 'A title much longer…');
    assert.equal(result.description, 'No description');
    assert.equal(searchResult({ title: '', description: 'A description much longer than thirty characters', slug: 'x' }, RULE).description, 'A description much longer tha…');
});
