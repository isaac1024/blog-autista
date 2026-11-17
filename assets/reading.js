/*
 * The reading settings of the header: a button that opens a panel to make
 * the text bigger or smaller and to pick the theme. Each change shows at
 * once and is kept in the browser for the next pages. Without scripts the
 * settings can't be changed, so the button stays hidden: the script of the
 * head shows it.
 */
import { applySettings, readSettings, resize, saveSettings, SIZES } from './reading/settings.js';

/** @type {Storage | undefined} */
let storage;

try {
    storage = window.localStorage;
} catch {
    // A browser that refuses storage still changes this page.
}

for (const settingsElement of /** @type {NodeListOf<HTMLElement>} */ (document.querySelectorAll('[data-reading]'))) {
    // The parts ReadingSettings.html.twig writes, all of them.
    const part = (/** @type {string} */ selector) => /** @type {HTMLElement} */ (settingsElement.querySelector(selector));
    const toggle = /** @type {HTMLButtonElement} */ (part('[data-reading-toggle]'));
    const panel = part('[data-reading-panel]');
    const value = part('[data-reading-size-value]');
    const smaller = /** @type {HTMLButtonElement} */ (part('[data-reading-size="smaller"]'));
    const bigger = /** @type {HTMLButtonElement} */ (part('[data-reading-size="bigger"]'));
    const radios = /** @type {NodeListOf<HTMLInputElement>} */ (settingsElement.querySelectorAll('input[name="reading-theme"]'));

    const percent = new Intl.NumberFormat(document.documentElement.lang || undefined, { style: 'percent' });
    let settings = readSettings(storage);

    const show = () => {
        applySettings(document.documentElement, settings);
        value.textContent = percent.format(Number(settings.size) / 100);
        smaller.disabled = settings.size === SIZES[0];
        bigger.disabled = settings.size === SIZES[SIZES.length - 1];

        for (const radio of radios) {
            radio.checked = radio.value === settings.theme;
        }
    };

    /** @param {Partial<import('./reading/settings.js').ReadingSettings>} changes */
    const change = (changes) => {
        settings = { ...settings, ...changes };
        saveSettings(storage, settings);
        show();
    };

    /** @param {boolean} open */
    const setOpen = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        panel.hidden = !open;
    };

    toggle.addEventListener('click', () => {
        setOpen(panel.hidden !== false);
    });
    smaller.addEventListener('click', () => {
        change({ size: resize(settings.size, -1) });
    });
    bigger.addEventListener('click', () => {
        change({ size: resize(settings.size, 1) });
    });
    for (const radio of radios) {
        radio.addEventListener('change', () => {
            change({ theme: radio.value });
        });
    }

    // Escape closes it from anywhere in it, back on the button; a click
    // anywhere else closes it where the reader went.
    settingsElement.addEventListener('keydown', (event) => {
        if (event instanceof KeyboardEvent && event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!settingsElement.contains(/** @type {Node} */ (event.target))) {
            setOpen(false);
        }
    });

    show();
}
