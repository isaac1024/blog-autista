/**
 * The part of a ProseMirror node this calculation reads.
 *
 * @typedef {object} EditorNode
 * @property {{name: string}} type
 * @property {{level?: number}} attrs
 * @property {number} nodeSize
 */

/**
 * A document whose top-level blocks are nodes of a kind: the calculation
 * reads only an EditorNode of each, and hands back what it was given.
 *
 * @template {EditorNode} [Node=EditorNode]
 * @typedef {object} DocumentLike
 * @property {(callback: (node: Node, offset: number) => void) => void} forEach
 */

/**
 * @template {EditorNode} [Node=EditorNode]
 * @typedef {object} BlockPosition
 * @property {Node} node
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {object} SelectionState
 * @property {{$from: {depth: number, node: (depth: number) => EditorNode}}} selection
 */

/**
 * Up, towards the start of the document, or down, towards its end.
 *
 * @typedef {-1|1} Direction
 */

/**
 * @typedef {object} MovePlan
 * @property {BlockPosition[]} blocks
 * @property {[number, number]} first
 * @property {[number, number]} second
 * @property {'first'|'second'} moving
 */

/**
 * Top-level blocks of a document, with where each starts and ends.
 *
 * @template {EditorNode} Node
 * @param {DocumentLike<Node>} doc
 * @returns {BlockPosition<Node>[]}
 */
export const topLevelBlocks = (doc) => {
    /** @type {BlockPosition<Node>[]} */
    const blocks = [];

    doc.forEach((node, offset) => blocks.push({ node, from: offset, to: offset + node.nodeSize }));

    return blocks;
};

/** @param {EditorNode} node */
export const isHeading = (node) => node.type.name === 'heading';

/**
 * The level of a heading; only headings carry one.
 *
 * @param {EditorNode} node
 */
const levelOf = (node) => node.attrs.level ?? 0;

/**
 * Whether the cursor is on a heading, so moving it moves its section.
 *
 * @param {SelectionState} state
 */
export const onHeading = (state) => {
    const { $from } = state.selection;

    return $from.depth > 0 && isHeading($from.node(1));
};

/**
 * What moves together from the block at the index: a heading takes its whole
 * section, down to the next heading of its level or above; any other block,
 * only itself. Returns the range of block indexes, end excluded.
 *
 * @param {BlockPosition[]} blocks
 * @param {number} index
 * @returns {[number, number]}
 */
const unitAt = (blocks, index) => {
    const { node } = blocks[index];

    if (!isHeading(node)) {
        return [index, index + 1];
    }

    let end = index + 1;

    while (end < blocks.length && !(isHeading(blocks[end].node) && levelOf(blocks[end].node) <= levelOf(node))) {
        end += 1;
    }

    return [index, end];
};

/**
 * The two neighbouring units to swap for a move, or null when there is
 * nowhere to go. A section only swaps with a sibling section, so it never
 * leaves its parent nor lands among the blocks of another one.
 *
 * @param {DocumentLike} doc
 * @param {number} index
 * @param {Direction} direction
 * @returns {MovePlan|null}
 */
export const movePlan = (doc, index, direction) => {
    const blocks = topLevelBlocks(doc);
    const [start, end] = unitAt(blocks, index);
    const { node } = blocks[start];

    if (direction < 0) {
        if (start === 0) {
            return null;
        }

        let previous = start - 1;

        if (isHeading(node)) {
            while (previous >= 0 && !(isHeading(blocks[previous].node) && levelOf(blocks[previous].node) <= levelOf(node))) {
                previous -= 1;
            }

            if (previous < 0 || levelOf(blocks[previous].node) !== levelOf(node)) {
                return null;
            }
        }

        return { blocks, first: [previous, start], second: [start, end], moving: 'second' };
    }

    if (end >= blocks.length) {
        return null;
    }

    if (isHeading(node) && !(isHeading(blocks[end].node) && levelOf(blocks[end].node) === levelOf(node))) {
        return null;
    }

    const next = isHeading(node) ? unitAt(blocks, end)[1] : end + 1;

    return { blocks, first: [start, end], second: [end, next], moving: 'first' };
};
