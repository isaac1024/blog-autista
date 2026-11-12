import assert from 'node:assert/strict';
import test from 'node:test';
import { currentSection, sectionsOf } from '../../../assets/rich_text/outline.js';

const node = (name, textContent, level = null) => ({
    type: { name },
    attrs: level === null ? {} : { level },
    nodeSize: 10,
    textContent,
});

const documentOf = (...nodes) => ({
    forEach(callback) {
        nodes.forEach((child, index) => callback(child, index * 10));
    },
});

test('every heading with text names a section, where it starts', () => {
    const doc = documentOf(
        node('paragraph', 'Intro'),
        node('heading', 'First', 2),
        node('paragraph', 'Text'),
        node('heading', '   ', 2),
        node('heading', 'Detail', 3),
    );

    assert.deepEqual(sectionsOf(doc), [
        { from: 10, level: 2, title: 'First' },
        { from: 40, level: 3, title: 'Detail' },
    ]);
});

test('the current section is the last one starting at or before the cursor', () => {
    const sections = [{ from: 10, level: 2, title: 'First' }, { from: 40, level: 3, title: 'Detail' }];

    assert.equal(currentSection(sections, 5), -1);
    assert.equal(currentSection(sections, 10), 0);
    assert.equal(currentSection(sections, 39), 0);
    assert.equal(currentSection(sections, 100), 1);
    assert.equal(currentSection([], 100), -1);
});
