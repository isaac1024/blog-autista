import assert from 'node:assert/strict';
import test from 'node:test';
import { DEFAULT_LIMIT, MAX_LIMIT, MIN_LIMIT, limitFrom, mosaicSummary, nameOf, newMosaic, withTag, withoutTag } from '../../../assets/rich_text/mosaic_summary.js';
import { LOADING, MISSING, UNKNOWN, found } from '../../../assets/shared/lookup.js';

const LABELS = { limit: 'Up to %limit% posts', section: 'Section', author: 'Author', tags: 'Tags', loading: 'Loading', missing: 'Not found', unknown: 'Unchecked' };
const NAMES = { 'term:s': 'Daily life', 'author:a': 'Isaac', 'term:t1': 'Sleep', 'term:t2': 'Sound' };
const name = (type, id) => NAMES[`${type}:${id}`] ?? '?';

test('a new mosaic shows the latest posts of anything, as many as it shows until told otherwise', () => {
    assert.deepEqual(newMosaic(), { limit: DEFAULT_LIMIT, sectionId: null, authorId: null, tagIds: [] });
    assert.equal(DEFAULT_LIMIT, 6);
    assert.deepEqual([MIN_LIMIT, MAX_LIMIT], [1, 20]);
    // Each a mosaic of its own: changing one changes no other.
    assert.notEqual(newMosaic().tagIds, newMosaic().tagIds);
});

test('a number written for the limit stays within what a mosaic shows; anything else keeps the limit it had', () => {
    assert.equal(limitFrom('12', 6), 12);
    assert.equal(limitFrom(' 3 ', 6), 3);
    assert.equal(limitFrom('1', 6), 1);
    assert.equal(limitFrom('20', 6), 20);
    assert.equal(limitFrom('0', 6), 1);
    assert.equal(limitFrom('-4', 6), 1);
    assert.equal(limitFrom('21', 6), 20);
    assert.equal(limitFrom('250', 6), 20);
    assert.equal(limitFrom('', 6), 6);
    assert.equal(limitFrom('  ', 7), 7);
    assert.equal(limitFrom('2.5', 6), 6);
    assert.equal(limitFrom('many', 8), 8);
});

test('a tag is added once, at the end, and removed alone', () => {
    assert.deepEqual(withTag(['a'], 'b'), ['a', 'b']);
    assert.deepEqual(withTag(['a', 'b'], 'a'), ['a', 'b']);
    assert.deepEqual(withoutTag(['a', 'b', 'c'], 'b'), ['a', 'c']);
    assert.deepEqual(withoutTag(['a'], 'z'), ['a']);
});

test('what a mosaic is narrowed down to is named as the CMS knows it, or by what is known of it', () => {
    assert.equal(nameOf(found({ title: 'Daily life' }), LABELS), 'Daily life');
    assert.equal(nameOf(LOADING, LABELS), 'Loading');
    assert.equal(nameOf(MISSING, LABELS), 'Not found');
    assert.equal(nameOf(UNKNOWN, LABELS), 'Unchecked');
});

test('a mosaic says how many posts it shows, then what narrows them down, in order', () => {
    assert.equal(mosaicSummary(newMosaic(), name, LABELS), 'Up to 6 posts');
    assert.equal(
        mosaicSummary({ limit: 3, sectionId: 's', authorId: 'a', tagIds: ['t1', 't2'] }, name, LABELS),
        'Up to 3 posts · Section: Daily life · Author: Isaac · Tags: Sleep, Sound',
    );
    assert.equal(mosaicSummary({ limit: 1, sectionId: null, authorId: 'a', tagIds: [] }, name, LABELS), 'Up to 1 posts · Author: Isaac');
    assert.equal(mosaicSummary({ limit: 2, sectionId: null, authorId: null, tagIds: ['t2'] }, name, LABELS), 'Up to 2 posts · Tags: Sound');
});
