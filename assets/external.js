/*
 * The script of a page of the public site that embeds videos, posts or
 * audios of other sites. Each sits behind a facade that holds its room and
 * loads nothing of its provider until the reader agrees, in a notice that
 * takes the place of the facade. Once they agree, their browser keeps it,
 * for that provider alone, and its frames load on their own when they near
 * the screen, on this page and the next; the line below each tells how to
 * stop that. The facade stays until the frame has painted itself, and
 * stays for good when it never does, as when a provider changes its frame.
 */
import { grantConsent, hasConsent, revokeConsent } from './external/consent.js';
import { frameUrl, greeting, reportedHeight, reportsHeight } from './external/frames.js';

/** How long a frame has to paint itself before its facade stays for good. */
const PAINT_TIMEOUT = 10_000;

/** How far from the screen a frame starts loading, so it is ready when seen. */
const NEAR = '200% 0px';

/** @type {Storage | undefined} */
let storage;

try {
    storage = window.localStorage;
} catch {
    // A browser that refuses it asks on every page.
}

let frames = 0;

/**
 * @typedef {object} Embed
 * @property {HTMLElement} element
 * @property {string} provider
 * @property {HTMLElement} frame
 * @property {HTMLElement} facade
 * @property {HTMLElement} notice
 * @property {HTMLElement} noticeTitle
 * @property {HTMLButtonElement} load
 * @property {HTMLButtonElement} forget
 * @property {HTMLButtonElement} accept
 * @property {HTMLButtonElement} cancel
 * @property {HTMLIFrameElement | null} iframe
 * @property {string} id
 */

/** @type {Embed[]} */
const embeds = [];

/**
 * @template {Element} T
 * @param {Element} root
 * @param {string} selector
 * @param {new () => T} type
 * @returns {T}
 */
const part = (root, selector, type) => {
    const found = root.querySelector(selector);

    if (!(found instanceof type)) {
        throw new Error(`An external embed lacks ${selector}.`);
    }

    return found;
};

/** @param {Embed} embed */
const showFooter = (embed) => {
    const consented = hasConsent(storage, embed.provider);
    embed.load.hidden = consented;
    embed.forget.hidden = !consented;
};

/**
 * Takes the frame away and shows the facade again.
 *
 * @param {Embed} embed
 */
const unload = (embed) => {
    embed.iframe?.remove();
    embed.iframe = null;
    embed.facade.hidden = false;
};

/**
 * Shows the frame once it painted itself, in place of the facade.
 *
 * @param {Embed} embed
 * @param {HTMLIFrameElement} iframe
 */
const reveal = (embed, iframe) => {
    if (embed.iframe !== iframe) {
        return;
    }

    iframe.toggleAttribute('data-shown', true);
    embed.facade.hidden = true;
};

/**
 * Loads the frame of the resource behind its facade, which stays until the
 * frame has painted itself.
 *
 * @param {Embed} embed
 */
const load = (embed) => {
    const { src, title, origin } = /** @type {{src: string, title: string, origin: string}} */ (embed.element.dataset);
    const iframe = document.createElement('iframe');
    iframe.src = frameUrl(embed.provider, src, embed.id);
    iframe.title = title;
    iframe.allow = 'encrypted-media; fullscreen; picture-in-picture';
    iframe.allowFullscreen = true;
    // The site sends no referrer; YouTube plays only for pages that say where they are.
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation');

    if (reportsHeight(embed.provider)) {
        // As tall as what the facade shows, until the frame tells its height.
        iframe.height = String(embed.facade.offsetHeight);
    }

    embed.iframe = iframe;
    embed.frame.append(iframe);

    iframe.addEventListener('load', () => {
        const hello = greeting(embed.provider, embed.id);

        if (hello !== null) {
            /** @type {Window} */ (iframe.contentWindow).postMessage(hello, origin);
        }

        if (!reportsHeight(embed.provider)) {
            reveal(embed, iframe);
        }
    });

    window.setTimeout(() => {
        if (embed.iframe === iframe && !('shown' in iframe.dataset)) {
            unload(embed);
        }
    }, PAINT_TIMEOUT);
};

const observer = 'IntersectionObserver' in window
    ? new window.IntersectionObserver((entries, self) => {
        for (const entry of entries) {
            // It watches the embeds alone.
            const embed = /** @type {Embed} */ (embeds.find((candidate) => candidate.element === entry.target));

            if (entry.isIntersecting && hasConsent(storage, embed.provider)) {
                self.unobserve(entry.target);
                load(embed);
            }
        }
    }, { rootMargin: NEAR })
    : null;

/**
 * Loads the frame when it nears the screen, or at once without a way to
 * tell.
 *
 * @param {Embed} embed
 */
const loadWhenNear = (embed) => {
    if (observer === null) {
        load(embed);

        return;
    }

    observer.observe(embed.element);
};

/** @param {Embed} embed */
const closeNotice = (embed) => {
    embed.notice.hidden = true;
    embed.load.focus();
};

/** @param {Embed} embed */
const openNotice = (embed) => {
    embed.notice.hidden = false;
    embed.noticeTitle.focus();
};

/**
 * The reader agreed: their browser keeps it for the provider, and every
 * frame of the provider on the page loads, this one at once.
 *
 * @param {Embed} embed
 */
const agree = (embed) => {
    grantConsent(storage, embed.provider, new Date());
    embed.notice.hidden = true;
    load(embed);

    for (const other of embeds.filter((candidate) => candidate.provider === embed.provider)) {
        showFooter(other);

        if (other !== embed) {
            loadWhenNear(other);
        }
    }

    embed.forget.focus();
};

/**
 * The reader takes back what they agreed to: every frame of the provider on
 * the page goes, and its facade shows again.
 *
 * @param {Embed} embed
 */
const disagree = (embed) => {
    revokeConsent(storage, embed.provider);

    for (const other of embeds.filter((candidate) => candidate.provider === embed.provider)) {
        observer?.unobserve(other.element);
        unload(other);
        showFooter(other);
    }

    embed.load.focus();
};

for (const element of document.querySelectorAll('[data-external-embed]')) {
    if (!(element instanceof HTMLElement)) {
        continue;
    }

    frames += 1;
    /** @type {Embed} */
    const embed = {
        element,
        provider: String(element.dataset.provider),
        frame: part(element, '[data-external-frame]', HTMLElement),
        facade: part(element, '[data-external-facade]', HTMLElement),
        notice: part(element, '[data-external-notice]', HTMLElement),
        noticeTitle: part(element, '[data-external-notice-title]', HTMLElement),
        load: part(element, '[data-external-load]', HTMLButtonElement),
        forget: part(element, '[data-external-forget]', HTMLButtonElement),
        accept: part(element, '[data-external-accept]', HTMLButtonElement),
        cancel: part(element, '[data-external-cancel]', HTMLButtonElement),
        iframe: null,
        id: `external-frame-${frames}`,
    };
    embeds.push(embed);

    embed.load.addEventListener('click', () => openNotice(embed));
    embed.accept.addEventListener('click', () => agree(embed));
    embed.cancel.addEventListener('click', () => closeNotice(embed));
    embed.forget.addEventListener('click', () => disagree(embed));
    embed.notice.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNotice(embed);
        }
    });

    showFooter(embed);

    if (hasConsent(storage, embed.provider)) {
        loadWhenNear(embed);
    }
}

/* A frame that reports its height is shown once it does, at that height. */
window.addEventListener('message', (event) => {
    for (const embed of embeds) {
        const { iframe } = embed;

        if (iframe === null || iframe.contentWindow !== event.source || event.origin !== embed.element.dataset.origin) {
            continue;
        }

        const height = reportedHeight(embed.provider, event.data, embed.id);

        if (height !== null) {
            iframe.height = String(height);
            reveal(embed, iframe);
        }
    }
});
