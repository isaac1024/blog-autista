/**
 * Pages included in the UI quality gate (`npm run ui:check`).
 *
 * Add one entry per page that should be validated. The harness is agnostic of
 * the Symfony contexts: `host` and `path` are just data, so a new page is a new
 * entry, not a code change.
 *
 * Tools:
 *   - HTML : W3C Nu HTML Checker (vnu-jar). Requires Java 11+; set JAVA_PATH
 *            or JAVA_HOME if the default `java` is older.
 *   - A11y / performance : Lighthouse (accessibility audits run axe-core),
 *            executed once per configured form factor (desktop and mobile).
 *            There is no W3C performance validator; Lighthouse is the de
 *            facto standard there.
 *
 * Tailwind's generated CSS is not checked by the W3C CSS3 validator because
 * it does not understand modern at-rules; a successful `npm run
 * tailwind:build` is the CSS syntax gate.
 *
 * Each page accepts:
 *   - name:        unique identifier used in reports and `--page=` filters
 *   - host:        host that resolves the route (e.g. cms.blogautista.localhost)
 *   - path:        request path (e.g. /login)
 *   - url:         alternative to host + path (the port is provided by the harness)
 *   - headers:     extra request headers, useful for authenticated pages
 *                  (e.g. { Cookie: 'PHPSESSID=...' })
 *   - thresholds:  per-page overrides for the Lighthouse thresholds
 *   - formFactors: per-page override of `lighthouse.formFactors`
 *
 * Authenticated CMS pages are included when `CMS_SESSION_COOKIE` holds a valid
 * PHPSESSID from a logged-in development session; the page editor also needs
 * `CMS_PAGE_ID`, the id of an existing page.
 *
 * `server.command` supports `{bind}` and `{port}` placeholders. The default
 * checks are HTML, contrast and Lighthouse; run a subset with
 * `--only=html,lighthouse` and a subset of pages with `--page=cms-login`.
 */

const cmsSession = process.env.CMS_SESSION_COOKIE;
const cmsHeaders = cmsSession ? { Cookie: `PHPSESSID=${cmsSession}` } : {};

const pages = [
    {
        name: 'cms-login',
        host: 'cms.blogautista.localhost',
        path: '/login',
    },
];

if (cmsSession) {
    pages.push(
        {
            name: 'cms-home',
            host: 'cms.blogautista.localhost',
            path: '/',
            headers: cmsHeaders,
        },
        {
            name: 'cms-pages',
            host: 'cms.blogautista.localhost',
            path: '/pages',
            headers: cmsHeaders,
        },
    );
}

// The editor needs an existing page: set CMS_PAGE_ID to the id of one in the dev database.
if (cmsSession && process.env.CMS_PAGE_ID) {
    pages.push({
        name: 'cms-page-edit',
        host: 'cms.blogautista.localhost',
        path: `/pages/${process.env.CMS_PAGE_ID}`,
        headers: cmsHeaders,
    });
}
export default {
    server: {
        bind: '127.0.0.1',
        command: ['php', '-S', '{bind}:{port}', '-t', 'public', 'tools/router.php'],
        env: {
            APP_ENV: 'dev',
            APP_DEBUG: '0',
        },
    },

    lighthouse: {
        // Both widths are audited for every page unless the page sets formFactors.
        formFactors: ['desktop', 'mobile'],
    },

    /*
     * Contrast guard for the design tokens. Text pairs require 7:1 (WCAG AAA);
     * borders and focus indicators require 3:1 (WCAG AA non-text contrast).
     */
    contrast: {
        css: 'assets/styles/app.css',
        pairs: [
            { foreground: '--color-text', background: '--color-bg', minimum: 7 },
            { foreground: '--color-text', background: '--color-surface', minimum: 7 },
            { foreground: '--color-muted', background: '--color-bg', minimum: 7 },
            { foreground: '--color-muted', background: '--color-surface', minimum: 7 },
            { foreground: '--color-accent', background: '--color-bg', minimum: 7 },
            { foreground: '--color-accent', background: '--color-surface', minimum: 7 },
            { foreground: '--color-on-accent', background: '--color-accent', minimum: 7 },
            { foreground: '--color-on-accent', background: '--color-accent-strong', minimum: 7 },
            { foreground: '--color-danger', background: '--color-danger-bg', minimum: 7 },
            { foreground: '--color-danger', background: '--color-surface', minimum: 7 },
            { foreground: '--color-on-danger', background: '--color-danger', minimum: 7 },
            { foreground: '--color-on-danger', background: '--color-danger-strong', minimum: 7 },
            { foreground: '--color-border', background: '--color-surface', minimum: 3 },
            { foreground: '--color-focus', background: '--color-bg', minimum: 3 },
        ],
    },

    thresholds: {
        performance: 0.9,
        accessibility: 1,
        'best-practices': 0.9,
    },

    pages,
};
