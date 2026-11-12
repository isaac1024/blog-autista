/**
 * @typedef {object} CountRule
 * @property {number} min
 * @property {number} max
 * @property {string} short With %count%, %min% and %max% where the numbers go.
 * @property {string} fits
 * @property {string} long
 */

/**
 * How long a text runs against its recommended length, in words. Characters
 * are counted as people see them, so an emoji counts once, and the spaces
 * around the text don't count.
 *
 * @param {string} text
 * @param {CountRule} rule
 */
export const countMessage = (text, { min, max, short, fits, long }) => {
    const count = [...text.trim()].length;
    let message = fits;

    if (count < min) {
        message = short;
    } else if (count > max) {
        message = long;
    }

    return message
        .replace('%count%', String(count))
        .replace('%min%', String(min))
        .replace('%max%', String(max));
};
