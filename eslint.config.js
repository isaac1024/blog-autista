import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: ['assets/vendor/**', 'node_modules/**', 'var/**'],
    },
    {
        ...js.configs.recommended,
        files: ['assets/**/*.js', 'tests/Browser/**/*.js', 'tests/Unit/**/*.js', 'tools/**/*.mjs', 'stryker.config.mjs', 'playwright.config.js', 'eslint.config.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser, ...globals.node },
        },
        rules: {
            ...js.configs.recommended.rules,
            // Consistency the recommended set leaves open.
            'curly': 'error',
            'eqeqeq': 'error',
            'no-var': 'error',
            'object-shorthand': 'error',
            'prefer-const': 'error',
            'yoda': 'error',
        },
    },
    {
        // The browser code has no one reading its console; the tools do.
        files: ['assets/**/*.js'],
        rules: { 'no-console': 'error' },
    },
];
