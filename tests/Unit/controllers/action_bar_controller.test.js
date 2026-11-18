import assert from 'node:assert/strict';
import test from 'node:test';
import ActionBar from '../../../assets/controllers/action_bar_controller.js';
import { actionBar } from '../../Markup/small_controllers.js';
import { mount, settle } from '../support/dom.js';

/*
 * A page 768 pixels high, as happy-dom makes it, whose bar is as high as
 * the browser lays it out, which the test sets, and whose browser tells the
 * size of what it watches when it starts watching it and whenever it
 * changes, as browsers do.
 */
const open = async () => {
    const page = await mount('', { 'action-bar': ActionBar });
    const layout = { height: 72 };
    /** @type {(() => void)[]} */
    const resized = [];
    Object.defineProperty(page.window.HTMLElement.prototype, 'offsetHeight', { configurable: true, get: () => layout.height });
    globalThis.ResizeObserver = class {
        /** @param {() => void} callback */
        constructor(callback) {
            this.callback = callback;
            this.watching = false;
        }

        observe() {
            this.watching = true;
            resized.push(() => {
                if (this.watching) {
                    this.callback();
                }
            });
            this.callback();
        }

        disconnect() {
            this.watching = false;
        }
    };
    page.document.body.innerHTML = actionBar();
    await settle();
    const told = () => page.document.documentElement.style.getPropertyValue('--action-bar-height');
    const unstuck = () => page.document.querySelector('[data-controller="action-bar"]')?.hasAttribute('data-unstuck');

    return {
        told,
        unstuck,
        resize: (height) => {
            layout.height = height;
            resized.forEach((tell) => tell());
        },
        remove: async () => {
            page.document.querySelector('[data-controller="action-bar"]')?.remove();
            await settle();
        },
    };
};

test('the page knows how high the bar is from the start', async () => {
    const { told } = await open();

    assert.equal(told(), '72px');
});

test('the page knows again when the bar wraps to another height', async () => {
    const { told, resize } = await open();

    resize(136);

    assert.equal(told(), '136px');
});

test('once the bar is gone, the page holds no height of it, nor learns one', async () => {
    const { told, resize, remove } = await open();

    await remove();
    resize(136);

    assert.equal(told(), '');
});

test('a bar that would take more than a third of the screen waits at the end of the form, and sticks again once it fits', async () => {
    const { told, unstuck, resize } = await open();

    resize(257);
    const tooHigh = [told(), unstuck()];
    resize(256);

    assert.deepEqual([tooHigh, [told(), unstuck()]], [['0px', true], ['256px', false]]);
});
