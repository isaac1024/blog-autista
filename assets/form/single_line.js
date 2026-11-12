/**
 * A text with its line breaks turned into spaces, one for each run of them.
 *
 * @param {string} text
 */
export const flatten = (text) => text.replace(/[\r\n]+/g, ' ');
