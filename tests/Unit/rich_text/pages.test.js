import assert from 'node:assert/strict';
import test from 'node:test';
import { LOADING, MISSING, UNKNOWN, found } from '../../../assets/shared/lookup.js';
import { linkTarget, pageIdsIn, pageStateOf } from '../../../assets/rich_text/pages.js';

const node = (name, { id = null, links = [], marks = [] } = {}) => ({
    type: { name },
    attrs: { id },
    marks: [...links.map((link) => ({ type: { name: 'link' }, attrs: { id: link } })), ...marks],
});

const documentOf = (...nodes) => ({
    descendants(callback) {
        nodes.forEach((child) => callback(child));
    },
});

const PUBLISHED = { title: 'First page', status: 'Published', published: true };
const DRAFT = { title: 'A draft', status: 'Draft', published: false };
const LABELS = { target: 'Goes to %title% (%status%)', offSite: 'Not on the site', loading: 'Looking it up', missing: 'No such page', unknown: 'Could not be checked' };

test('a document cites the pages of its widgets and of its links, each once', () => {
    const doc = documentOf(
        node('paragraph'),
        node('text', { links: ['a'] }),
        node('widget', { id: 'b' }),
        node('text', { links: ['a'], marks: [{ type: { name: 'bold' }, attrs: {} }] }),
        // Only a link points to a page, whatever other marks hold.
        node('text', { marks: [{ type: { name: 'comment' }, attrs: { id: 'not-a-page-either' } }] }),
        node('image', { id: 'not-a-page' }),
        node('widget', { id: null }),
        node('text', { links: [''] }),
    );

    assert.deepEqual(pageIdsIn(doc), ['a', 'b']);
});

test('a page found stands on the site or off it; one not found, as the lookup knows it', () => {
    assert.equal(pageStateOf(found(PUBLISHED)), 'published');
    assert.equal(pageStateOf(found(DRAFT)), 'offSite');
    assert.equal(pageStateOf(LOADING), 'loading');
    assert.equal(pageStateOf(MISSING), 'missing');
    assert.equal(pageStateOf(UNKNOWN), 'unknown');
});

test('a link names the page it goes to, and says when the site does not show it', () => {
    assert.equal(linkTarget(found(PUBLISHED), LABELS), 'Goes to First page (Published)');
    assert.equal(linkTarget(found(DRAFT), LABELS), 'Goes to A draft (Draft) Not on the site');
});

test('a link to a page not found says what is known of it', () => {
    assert.equal(linkTarget(LOADING, LABELS), 'Looking it up');
    assert.equal(linkTarget(MISSING, LABELS), 'No such page');
    assert.equal(linkTarget(UNKNOWN, LABELS), 'Could not be checked');
});
