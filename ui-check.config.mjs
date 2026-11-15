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
 * it does not understand modern at-rules; a successful
 * `bin/console tailwind:build` is the CSS syntax gate.
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
 * The pages are read from the app running in Docker (`docker compose up -d`),
 * through its edge on port 80, as a browser reaches it: the PHP of the host
 * has no driver for the database.
 *
 * Set `CMS_HOST` and `WEB_HOST` when the development contexts use custom hosts.
 * Authenticated CMS pages are included when `CMS_SESSION_COOKIE` holds a valid
 * PHPSESSID from a logged-in development session. Those of one page, one
 * image or one redirect need its id: `CMS_PAGE_ID` and `CMS_IMAGE_ID` of a
 * published one, whose form that takes it off the site is checked too, and
 * `CMS_REDIRECT_ID`, and `CMS_SECTION_ID`, `CMS_TAG_ID` and `CMS_AUTHOR_ID`
 * those of a section, a tag and an author, and `CMS_POST_ID` that of a
 * post, each published, whose forms that take them off the site and pages
 * of what uses them are checked too, and `CMS_EXTERNAL_ID` that of an
 * external resource, checked as edited and where it is used. Pages of the site are included with `WEB_PAGE_SLUG`,
 * `WEB_SECTION_SLUG`, `WEB_TAG_SLUG`, `WEB_AUTHOR_SLUG`, `WEB_POST_PATH`
 * (such as /vida-diaria/2026/11/14/un-post.html) and `WEB_HOME`, once
 * published.
 *
 * `server.command` supports `{bind}` and `{port}` placeholders, or is null to
 * use a server already running at `server.port`. The default checks are HTML,
 * contrast and Lighthouse; run a subset with `--only=html,lighthouse` and a
 * subset of pages with `--page=cms-login`.
 */

const cmsHost = process.env.CMS_HOST ?? 'cms.blogautista.localhost';
const webHost = process.env.WEB_HOST ?? 'www.blogautista.localhost';
const cmsSession = process.env.CMS_SESSION_COOKIE;
const cmsHeaders = cmsSession ? { Cookie: `PHPSESSID=${cmsSession}` } : {};

const pages = [
    {
        name: 'cms-login',
        host: cmsHost,
        path: '/login',
    },
];

const cmsPage = (name, path) => ({ name, host: cmsHost, path, headers: cmsHeaders });

if (cmsSession) {
    pages.push(
        cmsPage('cms-posts', '/posts'),
        cmsPage('cms-pages', '/pages'),
        cmsPage('cms-images', '/images'),
        cmsPage('cms-sections', '/sections'),
        cmsPage('cms-tags', '/tags'),
        cmsPage('cms-authors', '/authors'),
        cmsPage('cms-redirects', '/redirects'),
        cmsPage('cms-redirect-new', '/redirects/new'),
        cmsPage('cms-settings', '/settings'),
        cmsPage('cms-analytics', '/analytics'),
        cmsPage('cms-externals', '/externals'),
    );
}

const pageId = process.env.CMS_PAGE_ID;
if (cmsSession && pageId) {
    pages.push(
        cmsPage('cms-page-edit', `/pages/${pageId}`),
        cmsPage('cms-page-unpublish', `/pages/${pageId}/unpublish`),
        cmsPage('cms-page-relations', `/pages/${pageId}/relations`),
    );
}

const imageId = process.env.CMS_IMAGE_ID;
if (cmsSession && imageId) {
    pages.push(
        cmsPage('cms-image-edit', `/images/${imageId}`),
        cmsPage('cms-image-unpublish', `/images/${imageId}/unpublish`),
    );
}

const redirectId = process.env.CMS_REDIRECT_ID;
if (cmsSession && redirectId) {
    pages.push(cmsPage('cms-redirect-edit', `/redirects/${redirectId}`));
}

// An external resource is taken off the site by a button of its editor,
// with no form of its own.
const externalId = process.env.CMS_EXTERNAL_ID;
if (cmsSession && externalId) {
    pages.push(
        cmsPage('cms-external-edit', `/externals/${externalId}`),
        cmsPage('cms-external-relations', `/externals/${externalId}/relations`),
    );
}

// Each is checked as edited, where it is used, and, published, as the form
// that takes it off the site.
for (const [name, path, variable] of [
    ['cms-section', '/sections', 'CMS_SECTION_ID'],
    ['cms-tag', '/tags', 'CMS_TAG_ID'],
    ['cms-author', '/authors', 'CMS_AUTHOR_ID'],
    ['cms-post', '/posts', 'CMS_POST_ID'],
]) {
    if (cmsSession && process.env[variable]) {
        const id = process.env[variable];
        pages.push(
            cmsPage(`${name}-edit`, `${path}/${id}`),
            cmsPage(`${name}-relations`, `${path}/${id}/relations`),
            cmsPage(`${name}-unpublish`, `${path}/${id}/unpublish`),
        );
    }
}

for (const [name, path, variable] of [
    ['web-section', (slug) => `/${slug}/`, 'WEB_SECTION_SLUG'],
    ['web-tag', (slug) => `/tag/${slug}.html`, 'WEB_TAG_SLUG'],
    ['web-author', (slug) => `/autor/${slug}.html`, 'WEB_AUTHOR_SLUG'],
    ['web-post', (path) => path, 'WEB_POST_PATH'],
]) {
    if (process.env[variable]) {
        pages.push({ name, host: webHost, path: path(process.env[variable]) });
    }
}

// The site only serves published pages: set WEB_PAGE_SLUG to the slug of one
// published in the dev database, and WEB_HOME to check the home page once it
// is published.
if (process.env.WEB_PAGE_SLUG) {
    pages.push({
        name: 'web-page',
        host: webHost,
        path: `/${process.env.WEB_PAGE_SLUG}.html`,
    });
}

if (process.env.WEB_HOME) {
    pages.push({
        name: 'web-home',
        host: webHost,
        path: '/',
    });
}

export default {
    server: {
        // The edge of `docker compose`, already running.
        bind: '127.0.0.1',
        port: 80,
        command: null,
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
            { foreground: '--color-accent', background: '--color-accent-soft', minimum: 7 },
            { foreground: '--color-text', background: '--color-accent-soft', minimum: 7 },
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
