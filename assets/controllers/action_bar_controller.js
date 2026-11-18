import { Controller } from '@hotwired/stimulus';

const BaseController = /** @type {import('./controller_types.js').ControllerWith<object, HTMLElement>} */ (Controller);

/*
 * The bar of the actions of a form, stuck to the bottom of the screen. It
 * tells the page how high it is, as --action-bar-height, so what sticks to
 * the screen above it, such as the toolbar of the rich-text editor, stays
 * clear of it however its status and buttons wrap, at any width and size of
 * the text. A bar that would take more than a third of the screen, as with
 * big text on a phone, waits at the end of the form instead, and leaves the
 * screen to what is edited. The browser tells its height when it starts
 * watching it, and again whenever it changes.
 */
export default class extends BaseController {
    observer = new ResizeObserver(() => {
        const { offsetHeight } = this.element;
        const stuck = offsetHeight * 3 <= window.innerHeight;

        this.element.toggleAttribute('data-unstuck', !stuck);
        this.element.ownerDocument.documentElement.style.setProperty('--action-bar-height', `${stuck ? offsetHeight : 0}px`);
    });

    connect() {
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer.disconnect();
        this.element.ownerDocument.documentElement.style.removeProperty('--action-bar-height');
    }
}
