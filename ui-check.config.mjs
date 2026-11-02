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
 *   - CSS  : W3C CSS Validation Service (network access). Override `css.validator`
 *            to point at a self-hosted instance.
 *   - A11y / performance : Lighthouse (accessibility audits run axe-core).
 *            There is no W3C performance validator; Lighthouse is the de
 *            facto standard there.
 *
 * Each page accepts:
 *   - name:       unique identifier used in reports and `--page=` filters
 *   - host:       host that resolves the route (e.g. cms.blogautista.localhost)
 *   - path:       request path (e.g. /login)
 *   - url:        alternative to host + path (the port is provided by the harness)
 *   - headers:    extra request headers, useful for authenticated pages
 *                 (e.g. { Cookie: 'PHPSESSID=...' })
 *   - thresholds: per-page overrides for the Lighthouse thresholds
 *
 * `server.command` supports `{bind}` and `{port}` placeholders. The default
 * checks are HTML, CSS, contrast and Lighthouse; run a subset with
 * `--only=html,css,contrast` and a subset of pages with `--page=cms-login`.
 */
export default {
    server: {
        bind: '127.0.0.1',
        command: ['php', '-S', '{bind}:{port}', '-t', 'public', 'tools/router.php'],
        env: {
            APP_ENV: 'dev',
            APP_DEBUG: '0',
        },
    },

    css: {
        files: ['public/**/*.css'],
        validator: 'https://jigsaw.w3.org/css-validator/validator',
        profile: 'css3svg',
    },

    /*
     * Contrast guard for the design tokens. Text pairs require 7:1 (WCAG AAA);
     * borders and focus indicators require 3:1 (WCAG AA non-text contrast).
     */
    contrast: {
        css: 'public/styles/theme.css',
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
            { foreground: '--color-border', background: '--color-surface', minimum: 3 },
            { foreground: '--color-focus', background: '--color-bg', minimum: 3 },
        ],
    },

    thresholds: {
        performance: 0.9,
        accessibility: 1,
        'best-practices': 0.9,
    },

    pages: [
        {
            name: 'cms-login',
            host: 'cms.blogautista.localhost',
            path: '/login',
        },
    ],
};
