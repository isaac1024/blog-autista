import path from 'node:path';

/**
 * Mutation testing of the frontend (`npm run mutation:js`), the JavaScript
 * counterpart of Infection: it mutates every module under assets/ but the
 * vendored packages, and fails below the same 80 % score, ignoring nothing.
 *
 * The node:test suites reach all of it: pure modules directly, Stimulus
 * controllers mounted on happy-dom (tests/Unit/support/dom.js). The browser
 * suite only checks the wiring in Chromium, too slow to run once per mutant.
 */

// Every test file is a Node process of its own, once per mutant: compiled
// once, the editor and the DOM load faster in each of them.
process.env.NODE_COMPILE_CACHE ??= path.resolve('var/node-compile-cache');

export default {
    testRunner: 'tap',
    tap: {
        testFiles: ['tests/Unit/**/*.test.js'],
    },
    // The vendored packages stay out of the sandbox, so out of this too.
    mutate: ['assets/**/*.js'],
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
