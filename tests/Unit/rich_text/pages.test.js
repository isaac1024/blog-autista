import assert from 'node:assert/strict';
import test from 'node:test';
import { LOADING, MISSING, UNKNOWN, found } from '../../../assets/shared/lookup.js';
import { linkTarget, pageStateOf, targetsIn } from '../../../assets/rich_text/pages.js';

const node = (name, { id = null, type = undefined, links = [], marks = [] } = {}) => ({
    type: { name },
    attrs: { type, id },
    marks: [...links.map((link) => ({ type: { name: 'link' }, attrs: typeof link === 'string' ? { id: link } : link })), ...marks],
});

const documentOf = (...nodes) => ({
    descendants(callback) {
        nodes.forEach((child) => callback(child));
    },
});

const PUBLISHED = { title: 'First page', status: 'Published', published: true };
const DRAFT = { title: 'A draft', status: 'Draft', published: false };
const LABELS = { target: 'Goes to %title% (%status%)', offSite: 'Not on the site', loading: 'Looking it up', missing: 'No such page', unknown: 'Could not be checked' };

test('a document cites what its widgets and its links point to, each once by its kind, a page when they name none', () => {
    const doc = documentOf(
        node('paragraph'),
        node('text', { links: ['a'] }),
        node('widget', { id: 'b' }),
        node('text', { links: [{ type: 'post', id: 'a' }, { type: 'page', id: 'a' }] }),
        node('widget', { type: 'term', id: 'c' }),
        node('text', { links: ['a'], marks: [{ type: { name: 'bold' }, attrs: {} }] }),
        // Only a link points to a page, whatever other marks hold.
        node('text', { marks: [{ type: { name: 'comment' }, attrs: { id: 'not-a-page-either' } }] }),
        node('image', { id: 'not-a-page' }),
        node('widget', { id: null }),
        node('text', { links: [''] }),
    );

    assert.deepEqual(targetsIn(doc), [{ type: 'page', id: 'a' }, { type: 'page', id: 'b' }, { type: 'post', id: 'a' }, { type: 'term', id: 'c' }]);
});

test('a mosaic cites what it narrows its posts down to: its section and its tags as terms, its author', () => {
    const mosaic = (attrs) => ({ type: { name: 'mosaic' }, attrs, marks: [] });
    const doc = documentOf(
        mosaic({ sectionId: 's', authorId: 'a', tagIds: ['t1', 's'] }),
        mosaic({ sectionId: null, authorId: null, tagIds: [] }),
        mosaic({ sectionId: null, authorId: 'a' }),
        // Any other node narrows nothing down.
        { type: { name: 'paragraph' }, attrs: {}, marks: [] },
    );

    assert.deepEqual(targetsIn(doc), [{ type: 'term', id: 's' }, { type: 'author', id: 'a' }, { type: 'term', id: 't1' }]);
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
