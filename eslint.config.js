import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: ['assets/vendor/**', 'node_modules/**', 'var/**'],
    },
    {
        ...js.configs.recommended,
        files: ['assets/**/*.js', 'tests/Browser/**/*.js', 'tests/Unit/**/*.js', 'playwright.config.js', 'eslint.config.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser, ...globals.node },
        },
    },
];
