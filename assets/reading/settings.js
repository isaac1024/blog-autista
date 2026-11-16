/*
 * How the reader likes to read the blog, as kept in their browser: the size
 * of the text and the theme. Nothing else is kept, and none of it ever leaves
 * the browser. A browser that keeps nothing, or refuses to, reads as the
 * system says, at the size of its own.
 *
 * reading_apply.js reads the same key before the page paints, so the two
 * change together.
 */

const KEY = 'reading';

/** The sizes of the text, in percent of the browser's own, up to the double WCAG asks to reach. */
export const SIZES = Object.freeze(['100', '115', '130', '150', '175', '200']);

/** The themes, where "system" follows the system's. */
export const THEMES = Object.freeze(['system', 'light', 'dark']);

/**
 * @typedef {object} ReadingSettings
 * @property {string} size One of SIZES.
 * @property {string} theme One of THEMES.
 */

/** @type {Readonly<ReadingSettings>} */
export const DEFAULTS = Object.freeze({ size: '100', theme: 'system' });

/**
 * What the reader kept, each setting checked on its own: one unknown, or
 * broken, falls back to its default and leaves the other as it was.
 *
 * @param {Storage | undefined} storage
 * @returns {ReadingSettings}
 */
export const readSettings = (storage) => {
    /** @type {Record<string, unknown>} */
    let kept = {};

    try {
        // Without storage, or with nothing kept, this throws or reads null.
        kept = JSON.parse(String(/** @type {Storage} */ (storage).getItem(KEY))) ?? {};
    } catch {
        // Nothing kept that can be read: the defaults stand.
    }

    const pick = (/** @type {readonly string[]} */ allowed, /** @type {keyof ReadingSettings} */ name) => (
        allowed.find((option) => option === kept[name]) ?? DEFAULTS[name]
    );

    return { size: pick(SIZES, 'size'), theme: pick(THEMES, 'theme') };
};

/**
 * Keeps the settings for the next pages; a browser that refuses to keep
 * them reads the next page as the system says.
 *
 * @param {Storage | undefined} storage
 * @param {ReadingSettings} settings
 */
export const saveSettings = (storage, settings) => {
    try {
        /** @type {Storage} */ (storage).setItem(KEY, JSON.stringify({ size: settings.size, theme: settings.theme }));
    } catch {
        // Kept for this page alone.
    }
};

/**
 * The size a step bigger, or smaller, than the given one, held within SIZES.
 *
 * @param {string} size
 * @param {1 | -1} step
 */
export const resize = (size, step) => {
    const index = Math.max(0, SIZES.indexOf(size)) + step;

    return SIZES[Math.min(SIZES.length - 1, Math.max(0, index))];
};

/**
 * Sets the settings on the root of the page, where the stylesheet reads
 * them; the defaults set nothing, so the system and the browser decide.
 *
 * @param {HTMLElement} root
 * @param {ReadingSettings} settings
 */
export const applySettings = (root, settings) => {
    if (settings.theme === DEFAULTS.theme) {
        delete root.dataset.theme;
    } else {
        root.dataset.theme = settings.theme;
    }

    if (settings.size === DEFAULTS.size) {
        delete root.dataset.textSize;
    } else {
        root.dataset.textSize = settings.size;
    }
};
