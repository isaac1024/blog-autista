/**
 * @typedef {object} CountLabels
 * @property {string} none
 * @property {string} result
 * @property {string} results With %count% where the number goes.
 */

/**
 * How many results there are, in words: none, one, or several.
 *
 * @param {number} count
 * @param {CountLabels} labels
 */
export const countText = (count, { none, result, results }) => [none, result][count] ?? results.replace('%count%', String(count));
