import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const ASSETS = path.join(ROOT, 'assets');
const ORIGIN = 'http://blogautista.test';
/*
 * The stylesheets of the CMS, in the order its layout links them: the app's,
 * then those of the editor, which come later and win.
 */
const STYLESHEETS = [
    '/assets/styles/app.css',
    '/assets/vendor/prosemirror-view/style/prosemirror.min.css',
    '/assets/vendor/prosemirror-tables/style/tables.min.css',
];

/*
 * Native browser modules, served from the same files AssetMapper uses. Package
 * entry points are resolved below, so no npm copies of the runtime packages
 * nor a Symfony server are needed.
 */
const IMPORTS = {
    '@hotwired/stimulus': '/assets/vendor/@hotwired/stimulus',
    '@tiptap/': '/assets/vendor/@tiptap/',
    '@floating-ui/': '/assets/vendor/@floating-ui/',
    'orderedmap': '/assets/vendor/orderedmap',
    'prosemirror-commands': '/assets/vendor/prosemirror-commands',
    'prosemirror-dropcursor': '/assets/vendor/prosemirror-dropcursor',
    'prosemirror-gapcursor': '/assets/vendor/prosemirror-gapcursor',
    'prosemirror-history': '/assets/vendor/prosemirror-history',
    'prosemirror-keymap': '/assets/vendor/prosemirror-keymap',
    'prosemirror-model': '/assets/vendor/prosemirror-model',
    'prosemirror-schema-list': '/assets/vendor/prosemirror-schema-list',
    'prosemirror-state': '/assets/vendor/prosemirror-state',
    'prosemirror-tables': '/assets/vendor/prosemirror-tables',
    'prosemirror-transform': '/assets/vendor/prosemirror-transform',
    'prosemirror-view': '/assets/vendor/prosemirror-view',
    'rope-sequence': '/assets/vendor/rope-sequence',
    'w3c-keyname': '/assets/vendor/w3c-keyname',
};

async function moduleBody(pathname) {
    const relative = pathname.slice('/assets/'.length);
    const base = path.join(ASSETS, relative);
    const candidates = [base, `${base}.js`, path.join(base, `${path.basename(base)}.index.js`)];

    for (const candidate of candidates) {
        if (!candidate.startsWith(`${ASSETS}${path.sep}`)) {
            continue;
        }

        try {
            return await readFile(candidate);
        } catch (error) {
            if (error.code !== 'ENOENT' && error.code !== 'EISDIR') {
                throw error;
            }
        }
    }

    return null;
}

/**
 * Serves a page of the given body, with the assets and the import map of the
 * app, and opens it. API mocks are registered by each test, outside this loader.
 */
export async function loadPage(page, body, { mocked = ['/api/'], query = '', styled = false } = {}) {
    await page.route('**/*', async (route) => {
        const url = new URL(route.request().url());

        if (mocked.some((prefix) => url.pathname.startsWith(prefix))) {
            await route.fallback();

            return;
        }

        if (url.pathname === '/fixture') {
            await route.fulfill({
                contentType: 'text/html',
                body: `<!doctype html>
                    <html lang="en">
                        <head>
                            <meta charset="utf-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1">
                            ${styled ? STYLESHEETS.map((href) => `<link rel="stylesheet" href="${href}">`).join('') : ''}
                            <script type="importmap">${JSON.stringify({ imports: IMPORTS })}</script>
                        </head>
                        <body>
                            ${body}
                        </body>
                    </html>`,
            });

            return;
        }

        // The stylesheets as written: the browser skips what only Tailwind
        // reads, and lays out the rules of the components.
        if (STYLESHEETS.includes(url.pathname)) {
            await route.fulfill({ contentType: 'text/css', body: await readFile(path.join(ASSETS, url.pathname.slice('/assets/'.length))) });

            return;
        }

        if (url.pathname.startsWith('/assets/')) {
            const module = await moduleBody(url.pathname);

            if (module !== null) {
                await route.fulfill({ contentType: 'text/javascript', body: module });

                return;
            }
        }

        await route.fulfill({ status: 404, contentType: 'text/plain', body: 'Not found' });
    });

    await page.goto(`${ORIGIN}/fixture${query}`);
}

/**
 * Loads one real Stimulus controller around the markup supplied by the test,
 * styled by the stylesheets of the CMS if asked.
 */
export async function loadController(page, name, markup, { styled = false } = {}) {
    await loadPage(page, `
        ${markup}
        <script type="module">
            import { Application } from '@hotwired/stimulus';
            import Controller from '/assets/controllers/${name.replaceAll('-', '_')}_controller.js';

            window.stimulus = Application.start();
            window.stimulus.register('${name}', Controller);
            requestAnimationFrame(() => { window.controllerLoaded = true; });
        </script>
    `, { styled });
    await page.waitForFunction(() => window.controllerLoaded === true);
}
