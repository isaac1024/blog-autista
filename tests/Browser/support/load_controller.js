import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const ASSETS = path.join(ROOT, 'assets');
const PACKAGES = path.join(ROOT, 'node_modules');
const ORIGIN = 'http://blogautista.test';
/*
 * The stylesheets of the CMS, in the order its layout links them: the app's,
 * then those of the editor, which come later and win.
 */
const STYLESHEETS = [
    '/assets/styles/app.css',
    '/node_modules/prosemirror-view/style/prosemirror.css',
    '/node_modules/prosemirror-tables/style/tables.css',
];

/*
 * The import map of the app, without Symfony: each package at the file of
 * node_modules importmap.php names, the very one AssetMapper serves.
 */
const IMPORTS = Object.fromEntries(
    [...(await readFile(path.join(ROOT, 'importmap.php'), 'utf8')).matchAll(/'([^']+)'\s*=>\s*\[\s*'path'\s*=>\s*'\.\/node_modules\/([^']+)'/g)]
        .map(([, name, file]) => [name, `/node_modules/${file}`]),
);

/**
 * A file of the directory given at the path asked for, or null outside it or
 * when there is none; a module of the app may be asked for without its
 * extension.
 */
async function fileBody(directory, relative) {
    const base = path.join(directory, relative);

    for (const candidate of [base, `${base}.js`]) {
        if (!candidate.startsWith(`${directory}${path.sep}`)) {
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
            await route.fulfill({ contentType: 'text/css', body: await readFile(path.join(ROOT, url.pathname)) });

            return;
        }

        for (const [prefix, directory] of [['/assets/', ASSETS], ['/node_modules/', PACKAGES]]) {
            const module = url.pathname.startsWith(prefix) ? await fileBody(directory, url.pathname.slice(prefix.length)) : null;

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
