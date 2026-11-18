/*
 * The script of a page of the public site that embeds videos, posts or
 * audios of other sites. Each sits behind a facade that holds its room and
 * loads nothing of its provider until the reader agrees, in a notice that
 * takes the place of the facade. Once they agree, their browser keeps it,
 * for that provider alone, and its frames load on their own when they near
 * the screen, on this page and the next; the line below each tells how to
 * stop that. The facade stays until the frame has painted itself, and
 * stays for good when it never does in the time it is on the screen, as
 * when a provider changes its frame: a browser paints no frame off it, so
 * some tell nothing until the reader comes back to them. A video or an
 * audio the reader played starts playing on its own. A post takes the
 * room its frame said it had the last time, at that width, if it did.
 */
import { grantConsent, hasConsent, revokeConsent } from './external/consent.js';
import { frameUrl, greeting, playCommand, reportedHeight, reportsHeight } from './external/frames.js';
import { forgetHeights, keepHeight, keptHeight } from './external/heights.js';

/** How long a frame has to paint itself on the screen before its facade stays for good. */
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
 * @property {HTMLButtonElement | null} play The facade of a video or an audio
 *     plays it; that of a post is what the post says, loaded from the line below.
 * @property {HTMLButtonElement | null} opener What opened the notice, which
 *     closing it goes back to.
 * @property {HTMLButtonElement} forget
 * @property {HTMLButtonElement} accept
 * @property {HTMLButtonElement} cancel
 * @property {HTMLIFrameElement | null} iframe
 * @property {number | null} clock The time its frame has left to paint
 *     itself, running while it is on the screen.
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
 * Stops the time of a frame to paint itself.
 *
 * @param {Embed} embed
 */
const stopClock = (embed) => {
    if (embed.clock !== null) {
        window.clearTimeout(embed.clock);
        embed.clock = null;
    }
};

/**
 * Takes the frame away and shows the facade again.
 *
 * @param {Embed} embed
 */
const unload = (embed) => {
    onScreen?.unobserve(embed.element);
    stopClock(embed);
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

    onScreen?.unobserve(embed.element);
    stopClock(embed);
    iframe.inert = false;
    iframe.toggleAttribute('data-shown', true);
    embed.facade.hidden = true;
};

/**
 * Gives the frame loading its time to paint itself, from now on, unless it
 * already has it; once up, a frame still unpainted goes.
 *
 * @param {Embed} embed
 */
const startClock = (embed) => {
    const { iframe } = embed;

    if (embed.clock !== null) {
        return;
    }

    embed.clock = window.setTimeout(() => {
        if (embed.iframe === iframe) {
            unload(embed);
        }
    }, PAINT_TIMEOUT);
};

/** The time of a frame to paint itself runs while it is on the screen alone. */
const onScreen = 'IntersectionObserver' in window
    ? new window.IntersectionObserver((entries) => {
        for (const entry of entries) {
            // It watches the embeds alone.
            const embed = /** @type {Embed} */ (embeds.find((candidate) => candidate.element === entry.target));

            if (entry.isIntersecting) {
                startClock(embed);
            } else {
                stopClock(embed);
            }
        }
    })
    : null;

/**
 * Loads the frame of the resource behind its facade, which stays until the
 * frame has painted itself.
 *
 * @param {Embed} embed
 * @param {boolean} played Whether the reader played it, so it starts.
 */
const load = (embed, played) => {
    // Played again while its frame still paints.
    if (embed.iframe !== null) {
        return;
    }

    const { src, title, origin } = /** @type {{src: string, title: string, origin: string}} */ (embed.element.dataset);
    const iframe = document.createElement('iframe');
    iframe.src = frameUrl(embed.provider, src, embed.id, played);
    iframe.title = title;
    iframe.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
    iframe.allowFullscreen = true;
    // The site sends no referrer; YouTube plays only for pages that say where they are.
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation');

    // Unseen until it paints itself, it can't be reached.
    iframe.inert = true;

    if (reportsHeight(embed.provider)) {
        // Their own scripts frame them so: the height they tell leaves no
        // room for a scroll bar.
        iframe.setAttribute('scrolling', 'no');
        // As tall as it said it was the last time, or as what the facade
        // shows, until the frame tells its height.
        iframe.height = String(keptHeight(storage, embed.provider, src, embed.frame.clientWidth) ?? embed.facade.offsetHeight);
    }

    // A provider told once it is ready starts what the reader played.
    iframe.toggleAttribute('data-played', played);
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

    if (onScreen === null) {
        startClock(embed);

        return;
    }

    onScreen.observe(embed.element);
};

const observer = 'IntersectionObserver' in window
    ? new window.IntersectionObserver((entries, self) => {
        for (const entry of entries) {
            // It watches the embeds alone.
            const embed = /** @type {Embed} */ (embeds.find((candidate) => candidate.element === entry.target));

            if (entry.isIntersecting && hasConsent(storage, embed.provider)) {
                self.unobserve(entry.target);
                load(embed, false);
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
        load(embed, false);

        return;
    }

    observer.observe(embed.element);
};

/** @param {Embed} embed */
const closeNotice = (embed) => {
    embed.notice.hidden = true;
    showFooter(embed);
    // Only an open notice closes, and opening it said what opened it.
    /** @type {HTMLButtonElement} */ (embed.opener).focus();
};

/**
 * @param {Embed} embed
 * @param {HTMLButtonElement} opener
 */
const openNotice = (embed, opener) => {
    embed.opener = opener;
    embed.notice.hidden = false;
    // The notice loads it, once agreed: the line doesn't offer it twice.
    embed.load.hidden = true;
    embed.noticeTitle.focus();
};

/**
 * Plays it from its facade: at once once the reader agreed for its
 * provider, as when its frame never painted, or after the notice.
 *
 * @param {Embed} embed
 * @param {HTMLButtonElement} button
 */
const play = (embed, button) => {
    if (hasConsent(storage, embed.provider)) {
        load(embed, true);

        return;
    }

    openNotice(embed, button);
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
    load(embed, true);

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
    forgetHeights(storage, embed.provider);

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
    const facadePlay = element.querySelector('[data-external-play]');
    /** @type {Embed} */
    const embed = {
        element,
        provider: String(element.dataset.provider),
        frame: part(element, '[data-external-frame]', HTMLElement),
        facade: part(element, '[data-external-facade]', HTMLElement),
        notice: part(element, '[data-external-notice]', HTMLElement),
        noticeTitle: part(element, '[data-external-notice-title]', HTMLElement),
        load: part(element, '[data-external-load]', HTMLButtonElement),
        play: facadePlay instanceof HTMLButtonElement ? facadePlay : null,
        opener: null,
        forget: part(element, '[data-external-forget]', HTMLButtonElement),
        accept: part(element, '[data-external-accept]', HTMLButtonElement),
        cancel: part(element, '[data-external-cancel]', HTMLButtonElement),
        iframe: null,
        clock: null,
        id: `external-frame-${frames}`,
    };
    embeds.push(embed);

    embed.load.addEventListener('click', () => openNotice(embed, embed.load));

    if (embed.play !== null) {
        const button = embed.play;
        button.hidden = false;
        button.addEventListener('click', () => play(embed, button));
    }
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

        const command = 'played' in iframe.dataset ? playCommand(embed.provider, event.data) : null;

        if (command !== null) {
            delete iframe.dataset.played;
            /** @type {Window} */ (iframe.contentWindow).postMessage(command, event.origin);
        }

        const height = reportedHeight(embed.provider, event.data, embed.id);

        if (height !== null) {
            keepHeight(storage, embed.provider, String(embed.element.dataset.src), embed.frame.clientWidth, height);
            iframe.height = String(height);
            reveal(embed, iframe);
        }
    }
});
