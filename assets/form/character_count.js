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

/**
 * @typedef {object} MaxLengthRule
 * @property {number} max
 * @property {string} count With %count% and %max% where the numbers go.
 * @property {string} over With %count%, %max% and %over%, how many past it.
 */

/**
 * How long a text runs against the most it takes, in words, every
 * character counted as people see it.
 *
 * @param {string} text
 * @param {MaxLengthRule} rule
 */
export const maxLengthMessage = (text, { max, count: within, over }) => {
    const count = [...text].length;

    return (count > max ? over : within)
        .replace('%count%', String(count))
        .replace('%max%', String(max))
        .replace('%over%', String(count - max));
};
