import { expect, test as base } from '@playwright/test';

/** A browser error fails an interaction test even when its visible result looks right. */
export const test = base.extend({
    page: async ({ page }, use) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await use(page);

        expect(errors, 'No uncaught browser errors').toEqual([]);
    },
});

export { expect };
export { loadController, loadPage } from './load_controller.js';
