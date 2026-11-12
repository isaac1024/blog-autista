/**
 * The part of a ProseMirror node this calculation reads.
 *
 * @typedef {object} EditorNode
 * @property {{name: string}} type
 * @property {{level?: number}} attrs
 * @property {number} nodeSize
 */

/**
 * @typedef {object} DocumentLike
 * @property {(callback: (node: EditorNode, offset: number) => void) => void} forEach
 */

/**
 * @typedef {object} BlockPosition
 * @property {EditorNode} node
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {object} SelectionState
 * @property {{$from: {depth: number, node: (depth: number) => EditorNode}}} selection
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
 * @param {DocumentLike} doc
 * @returns {BlockPosition[]}
 */
export const topLevelBlocks = (doc) => {
    /** @type {BlockPosition[]} */
    const blocks = [];

    doc.forEach((node, offset) => blocks.push({ node, from: offset, to: offset + node.nodeSize }));

    return blocks;
};

/** @param {EditorNode} node */
export const isHeading = (node) => node.type.name === 'heading';

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

    while (end < blocks.length && !(isHeading(blocks[end].node) && /** @type {number} */ (blocks[end].node.attrs.level) <= /** @type {number} */ (node.attrs.level))) {
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
 * @param {number} direction
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
            while (previous >= 0 && !(isHeading(blocks[previous].node) && /** @type {number} */ (blocks[previous].node.attrs.level) <= /** @type {number} */ (node.attrs.level))) {
                previous -= 1;
            }

            if (previous < 0 || blocks[previous].node.attrs.level !== node.attrs.level) {
                return null;
            }
        }

        return { blocks, first: [previous, start], second: [start, end], moving: 'second' };
    }

    if (end >= blocks.length) {
        return null;
    }

    if (isHeading(node) && !(isHeading(blocks[end].node) && blocks[end].node.attrs.level === node.attrs.level)) {
        return null;
    }

    const next = isHeading(node) ? unitAt(blocks, end)[1] : end + 1;

    return { blocks, first: [start, end], second: [end, next], moving: 'first' };
};
