/*
 * The rich text editor on a happy-dom page, mounted from the markup of its
 * template, with the endpoints the test gives it, and the ways an editor
 * acts on it: focusing, typing, pressing keys and controls.
 */
import RichTextEditor from '../../../assets/controllers/rich_text_editor_controller.js';
import { richTextEditor } from '../../Markup/rich_text_editor.js';
import { answer, mount, serve, settle } from './dom.js';

/** A paragraph of the given text, marked as given. */
export const paragraph = (text = '', marks = []) => (text === '' ? { type: 'paragraph' } : { type: 'paragraph', content: [{ type: 'text', text, ...(marks.length > 0 ? { marks } : {}) }] });

/** A heading of the given level and text. */
export const heading = (level, text) => ({ type: 'heading', attrs: { level }, content: [{ type: 'text', text }] });

/** A document of the given blocks. */
export const doc = (...content) => ({ type: 'doc', content });

/**
 * Mounts the editor. The endpoints answer through `respond`, given the path
 * of the request and its query; by default with nothing found.
 *
 * @param {Parameters<typeof richTextEditor>[0] & {respond?: (path: string, query: URLSearchParams) => unknown, reducedMotion?: boolean}} [options]
 */
export const openEditor = async ({ respond = () => answer({ images: [], pages: [], more: false }), reducedMotion = false, ...markup } = {}) => {
    const requests = serve((url) => {
        const { pathname, searchParams } = new URL(url);

        return respond(pathname, searchParams);
    });
    const page = await mount(richTextEditor(markup), { 'rich-text-editor': RichTextEditor }, {
        settings: { device: { prefersReducedMotion: reducedMotion ? 'reduce' : 'no-preference' } },
    });
    await settle();
    const { document, window } = page;
    const controller = page.controller('rich-text-editor');
    const { editor } = controller;
    const target = (name) => document.querySelector(`[data-rich-text-editor-target="${name}"]`);
    const control = (command) => document.querySelector(`[data-command="${command}"]`);
    const frame = () => new Promise((resolve) => { window.requestAnimationFrame(resolve); });
    // Every text the live region is given, in order: a message is cleared before it is said.
    const said = [];
    const status = target('status');
    const { set, get } = Object.getOwnPropertyDescriptor(window.Node.prototype, 'textContent');
    Object.defineProperty(status, 'textContent', {
        configurable: true,
        get() { return get.call(this); },
        set(value) {
            said.push(value);
            set.call(this, value);
        },
    });

    return {
        ...page,
        controller,
        editor,
        requests,
        target,
        control,
        /** The document as the form would post it. */
        posted: () => JSON.parse(target('input').value),
        /** Presses a control, as a click does: the mouse first, then the click. */
        press: async (element) => {
            const resolved = typeof element === 'string' ? control(element) : element;
            const down = new window.MouseEvent('mousedown', { bubbles: true, cancelable: true });
            resolved.dispatchEvent(down);
            resolved.click();
            await settle();

            return down.defaultPrevented;
        },
        /** Presses a key in the element, the text by default, and says whether something took it. */
        key: (key, modifiers = {}, element = editor.view.dom) => {
            const event = new window.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...modifiers });
            element.dispatchEvent(event);

            return event.defaultPrevented;
        },
        /** The state of a control: whether it is held, and pressed when it toggles. */
        state: (command) => [control(command).getAttribute('aria-disabled'), control(command).getAttribute('aria-pressed')],
        frame,
        /** Puts the cursor in the text, where given, once the editor has taken the focus. */
        focus: async (position = null) => {
            editor.commands.focus(position);
            await frame();
        },
        /** What the live region said since last asked, once the frames that say it have run. */
        announced: async () => {
            await frame();

            return said.splice(0);
        },
        /** The options of the menu of kinds of block, as shown. */
        options: () => [...target('menu').children].map((option) => [option.textContent, option.getAttribute('aria-selected')]),
        /** The name of the control, as its text and its tooltip say. */
        named: (element) => [...element.querySelectorAll('[data-role]')].map((part) => part.textContent),
    };
};
