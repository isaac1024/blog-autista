/**
 * Mutation testing of the frontend (`npm run mutation:js`), the JavaScript
 * counterpart of Infection: it mutates the modules node:test covers and
 * fails below the same 80 % score, ignoring nothing.
 *
 * Only what the unit tests reach is mutated. Stimulus controllers are left
 * to the browser suite, too slow to run once per mutant; logic grows into
 * this scope as it moves out of them into modules of its own.
 */
export default {
    testRunner: 'tap',
    tap: {
        testFiles: ['tests/Unit/**/*.test.js'],
    },
    mutate: [
        'assets/shared/**/*.js',
        'assets/image/**/*.js',
        'assets/form/**/*.js',
        'assets/rich_text/block_movement.js',
        'assets/rich_text/block_menu.js',
        'assets/rich_text/outline.js',
        'assets/rich_text/pages.js',
    ],
    coverageAnalysis: 'perTest',
    thresholds: { high: 90, low: 80, break: 80 },
    reporters: ['clear-text', 'progress', 'html'],
    htmlReporter: { fileName: 'var/mutation/js.html' },
    tempDirName: 'var/stryker-tmp',
    // The sandbox only needs the frontend and its tests, not PHP nor its vendors.
    ignorePatterns: [
        '.codegraph', '.claude', '.idea', '.vscode',
        'vendor', 'var', 'public', 'docker', 'src', 'templates', 'translations', 'migrations', 'config', 'tools',
        'tests/Cms', 'tests/Web', 'tests/Shared', 'tests/Browser', 'assets/vendor', 'assets/fonts', 'assets/images',
    ],
};
