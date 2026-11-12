import assert from 'node:assert/strict';
import test from 'node:test';
import { movePlan, movedMessage, onHeading, topLevelBlocks } from '../../../assets/rich_text/block_movement.js';

const block = (type = 'paragraph', level = null) => ({
    type: { name: type },
    attrs: level === null ? {} : { level },
    nodeSize: 2,
});

const documentOf = (...blocks) => ({
    forEach(callback) {
        blocks.forEach((node, index) => callback(node, index * 2));
    },
});

test('top-level blocks carry the positions used by a move', () => {
    const blocks = topLevelBlocks(documentOf(block(), block('heading', 2)));

    assert.deepEqual(blocks.map(({ from, to }) => [from, to]), [[0, 2], [2, 4]]);
});

test('onHeading distinguishes a heading from another top-level block', () => {
    const state = (node) => ({ selection: { $from: { depth: 1, node: () => node } } });

    assert.equal(onHeading(state(block('heading', 2))), true);
    assert.equal(onHeading(state(block())), false);
});

test('a heading moves as a section, including its lower-level headings', () => {
    const document = documentOf(
        block(),
        block('heading', 2),
        block(),
        block('heading', 3),
        block(),
        block('heading', 2),
    );
    const plan = movePlan(document, 1, 1);

    assert.deepEqual(plan.first, [1, 5]);
    assert.deepEqual(plan.second, [5, 6]);
    assert.equal(plan.moving, 'first');
});

test('moving up swaps the same two neighbouring sections in the other direction', () => {
    const document = documentOf(block('heading', 2), block(), block('heading', 2), block());
    const plan = movePlan(document, 2, -1);

    assert.deepEqual(plan.first, [0, 2]);
    assert.deepEqual(plan.second, [2, 4]);
    assert.equal(plan.moving, 'second');
});

test('a section never moves across a paragraph or out of the document', () => {
    assert.equal(movePlan(documentOf(block('heading', 2), block()), 0, 1), null);
    assert.equal(movePlan(documentOf(block()), 0, -1), null);
    assert.equal(movePlan(documentOf(block()), 0, 1), null);
});

test('onHeading is false outside of any block', () => {
    const state = { selection: { $from: { depth: 0, node: () => block('heading', 2) } } };

    assert.equal(onHeading(state), false);
});

test('a paragraph swaps with the block next to it, alone', () => {
    const down = movePlan(documentOf(block(), block(), block(), block()), 1, 1);
    const up = movePlan(documentOf(block(), block(), block()), 1, -1);

    assert.deepEqual([down.first, down.second, down.moving], [[1, 2], [2, 3], 'first']);
    assert.deepEqual([up.first, up.second, up.moving], [[0, 1], [1, 2], 'second']);
});

test('moving up, a section passes over the subsections of the one before', () => {
    const plan = movePlan(documentOf(block('heading', 2), block('heading', 3), block(), block('heading', 2), block()), 3, -1);

    assert.deepEqual([plan.first, plan.second], [[0, 3], [3, 5]]);
});

test('a section never leaves its parent nor rises above the first heading of its level', () => {
    // Nothing of its level before it.
    assert.equal(movePlan(documentOf(block(), block('heading', 2)), 1, -1), null);
    // An h3 just under its h2, either way.
    assert.equal(movePlan(documentOf(block('heading', 2), block(), block('heading', 3), block()), 2, -1), null);
    assert.equal(movePlan(documentOf(block('heading', 2), block('heading', 3), block(), block('heading', 2)), 1, 1), null);
});

test('what is said once something moved names a block or a section, and where it went', () => {
    const labels = { movedUp: 'Block up', movedDown: 'Block down', sectionMovedUp: 'Section up', sectionMovedDown: 'Section down' };

    assert.equal(movedMessage(-1, false, labels), 'Block up');
    assert.equal(movedMessage(1, false, labels), 'Block down');
    assert.equal(movedMessage(-1, true, labels), 'Section up');
    assert.equal(movedMessage(1, true, labels), 'Section down');
});
