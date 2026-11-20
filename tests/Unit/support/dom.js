/*
 * Runs Stimulus controllers the way the browser does, on a happy-dom page:
 * the markup their templates write (tests/Markup) connects them, and the
 * tests act through its events. Fast enough for mutation testing to run once
 * per mutant, where a real browser is not.
 */
import assert from 'node:assert/strict';
import { afterEach } from 'node:test';
import { Window } from 'happy-dom';
import { Application } from '@hotwired/stimulus';

/*
 * What the page lends the code under test as globals: its window and
 * document, and the classes and functions it reaches by name. Timers, URL,
 * AbortController and fetch stay Node's own.
 */
const BORROWED = /^(HTML\w*|SVG\w*|\w*Event|Node|Element|Text|DocumentFragment|MutationObserver|CSS|FormData|File|FileList|DataTransfer|DOMParser|Image|requestAnimationFrame|cancelAnimationFrame|matchMedia|getComputedStyle|getSelection)$/;

/** @type {{window: Window, application: Application, identifiers: string[], errors: unknown[]}|null} */
let current = null;

const close = async () => {
    if (current === null) {
        return;
    }

    const { window, application, identifiers, errors } = current;
    current = null;
    // Stopping leaves the controllers connected, and their timers running.
    application.unload(identifiers);
    application.stop();
    await window.happyDOM.close();
    // Stimulus reports what a controller throws instead of letting it out.
    assert.deepEqual(errors.map(String), [], 'a controller threw');
};

afterEach(close);

/** Lets the observers and promises pending on the page run. */
export const settle = () => new Promise((resolve) => { setTimeout(resolve, 0); });

/**
 * Opens a page of the given markup with the given controllers registered,
 * and waits for them to connect. The page loads nothing and goes nowhere:
 * a form it submits stays put, and what it fetches is what `serve` answers.
 *
 * @param {string} markup
 * @param {Record<string, unknown>} controllers By identifier.
 * @param {{url?: string, settings?: Record<string, unknown>}} [options] The
 *     address of the page, and settings of happy-dom's, such as the device
 *     or a canvas adapter.
 */
export const mount = async (markup, controllers, { url = 'https://blog.test/', settings = {} } = {}) => {
    await close();
    const window = new Window({
        url,
        settings: {
            disableJavaScriptFileLoading: true,
            navigation: { disableMainFrameNavigation: true, disableChildFrameNavigation: true, disableChildPageNavigation: true },
            ...settings,
        },
    });

    for (const name of Object.getOwnPropertyNames(window)) {
        if (BORROWED.test(name)) {
            Object.defineProperty(globalThis, name, { configurable: true, writable: true, value: window[name] });
        }
    }

    Object.defineProperty(globalThis, 'window', { configurable: true, writable: true, value: window });
    Object.defineProperty(globalThis, 'document', { configurable: true, writable: true, value: window.document });
    Object.defineProperty(globalThis, 'navigator', { configurable: true, writable: true, value: window.navigator });

    window.document.body.innerHTML = markup;
    const application = new Application(window.document.documentElement);
    const errors = [];
    application.handleError = (error) => { errors.push(error); };
    // What a listener of the page throws, happy-dom reports instead.
    window.addEventListener('error', (event) => { errors.push(event.error ?? event.message); });
    current = { window, application, identifiers: Object.keys(controllers), errors };
    await application.start();

    for (const [identifier, controller] of Object.entries(controllers)) {
        application.register(identifier, controller);
    }

    await settle();
    const { document } = window;

    return {
        window,
        document,
        application,
        /** What the controllers threw so far, taken: any left fails the test. */
        thrown: () => errors.splice(0).map(String),
        /**
         * The controller connected to the first element of the identifier.
         *
         * @param {string} identifier
         */
        controller: (identifier) => application.getControllerForElementAndIdentifier(
            document.querySelector(`[data-controller~="${identifier}"]`),
            identifier,
        ),
    };
};

/**
 * Fails unless the element holds the focus. It compares the elements by
 * identity: printing a node to tell a difference walks its whole window.
 *
 * @param {Element|null} element
 */
export const assertFocused = (element) => {
    const active = element?.ownerDocument.activeElement ?? null;

    if (active !== element) {
        assert.fail(`expected the focus on ${element?.outerHTML.slice(0, 120)}, found it on ${active?.outerHTML.slice(0, 120)}`);
    }
};

/**
 * An answer of the JSON endpoints.
 *
 * @param {unknown} body
 */
export const answer = (body) => ({ ok: true, status: 200, json: async () => body });

/**
 * Stands in for fetch: each request is recorded and answered by the given
 * function, given its URL as a string and its options.
 *
 * @param {(url: string, options: RequestInit) => unknown} respond
 */
export const serve = (respond) => {
    /** @type {{url: string, method: string, signal: AbortSignal|null|undefined}[]} */
    const requests = [];
    globalThis.fetch = /** @type {typeof fetch} */ (async (url, options = {}) => {
        requests.push({ url: String(url), method: options.method ?? 'GET', signal: options.signal });

        return respond(String(url), options);
    });

    return requests;
};

/** A promise and the function that keeps it, for answers that arrive late. */
export const later = () => {
    /** @type {(value?: unknown) => void} */
    let resolve = () => {};
    /** @type {(reason?: unknown) => void} */
    let reject = () => {};
    const promise = new Promise((keep, fail) => { resolve = keep; reject = fail; });

    return { promise, resolve, reject };
};
