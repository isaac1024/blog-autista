import assert from 'node:assert/strict';
import test from 'node:test';
import { movePlan, onHeading, topLevelBlocks } from '../../../assets/rich_text/block_movement.js';

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
