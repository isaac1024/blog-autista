import { isHeading, topLevelBlocks } from './block_movement.js';

/**
 * @typedef {import('./block_movement.js').EditorNode & {textContent: string}} TextNode
 */

/**
 * A section of the document: where its heading starts, its level and its title.
 *
 * @typedef {{from: number, level: number, title: string}} Section
 */

/**
 * The sections of a document, one per heading at its top level; a heading
 * with no text yet names no section.
 *
 * @param {import('./block_movement.js').DocumentLike<TextNode>} doc
 * @returns {Section[]}
 */
export const sectionsOf = (doc) => topLevelBlocks(doc)
    .filter(({ node }) => isHeading(node) && node.textContent.trim() !== '')
    .map(({ node, from }) => ({ from, level: Number(node.attrs.level), title: node.textContent }));

/**
 * The section a position of the document falls in, or -1 before the first one.
 *
 * @param {Section[]} sections
 * @param {number} position
 */
export const currentSection = (sections, position) => sections.findLastIndex((section) => section.from <= position);
