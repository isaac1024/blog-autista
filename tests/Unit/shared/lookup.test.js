import assert from 'node:assert/strict';
import test from 'node:test';
import { LOADING, MISSING, UNKNOWN, found, lookupAmong, lookupIn, lookupText, standing } from '../../../assets/shared/lookup.js';

const PAGE = { id: 'a', title: 'First page', status: 'Published', published: true };
const DRAFT = { id: 'b', title: 'A draft', status: 'Draft', published: false };
const LABELS = { loading: 'Loading', missing: 'Not found', unknown: 'Could not be checked', offSite: 'Not on the site' };

test('an answer finds the id, misses it, or says nothing of it when there is none', () => {
    assert.deepEqual(lookupIn([DRAFT, PAGE], 'a'), { state: 'found', value: PAGE });
    assert.equal(lookupIn([DRAFT], 'a'), MISSING);
    assert.equal(lookupIn([], 'a'), MISSING);
    assert.equal(lookupIn(null, 'a'), UNKNOWN);
});

test('what is published stands as its status; anything else, off the site too', () => {
    assert.equal(standing(PAGE, LABELS.offSite), 'Published');
    assert.equal(standing(DRAFT, LABELS.offSite), 'Draft. Not on the site');
});

test('what was found is told by its name and where it stands', () => {
    assert.deepEqual(lookupText(found(DRAFT), (page) => page.title, LABELS), { name: 'A draft', standing: 'Draft. Not on the site' });
});

test('what was not found is told by what is known of it, standing nowhere', () => {
    const title = (page) => page.title;

    assert.deepEqual(lookupText(LOADING, title, LABELS), { name: 'Loading', standing: '' });
    assert.deepEqual(lookupText(MISSING, title, LABELS), { name: 'Not found', standing: '' });
    assert.deepEqual(lookupText(UNKNOWN, title, LABELS), { name: 'Could not be checked', standing: '' });
});

test('the states that hold nothing cannot be changed', () => {
    assert.equal(Object.isFrozen(LOADING) && Object.isFrozen(MISSING) && Object.isFrozen(UNKNOWN), true);
});

test('the lookups so far know an id as found, missing, unknown, or not answered yet', () => {
    const known = { found: new Map([['a', PAGE]]), missing: new Set(['b']), unknown: new Set(['c', 'a']) };

    assert.deepEqual(lookupAmong('a', known), { state: 'found', value: PAGE });
    assert.equal(lookupAmong('b', known), MISSING);
    assert.equal(lookupAmong('c', known), UNKNOWN);
    assert.equal(lookupAmong('d', known), LOADING);
});
