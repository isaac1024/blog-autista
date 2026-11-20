import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: ['node_modules/**', 'var/**'],
    },
    {
        ...js.configs.recommended,
        files: ['assets/**/*.js', 'tests/Browser/**/*.js', 'tests/Markup/**/*.js', 'tests/Unit/**/*.js', 'tools/**/*.mjs', 'stryker.config.mjs', 'playwright.config.js', 'eslint.config.js'],
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
    {
        // A failing assertion prints both sides with their getters, a
        // thousand levels deep: a node of happy-dom brings its whole window,
        // gigabytes of it, and under mutation testing, one such process per
        // worker took the machine down. A node is compared by what it says,
        // or by identity in a boolean, as assertFocused does.
        files: ['tests/Unit/**/*.js'],
        rules: {
            'no-restricted-syntax': ['error', {
                selector: "CallExpression[callee.object.name='assert'] > CallExpression:matches([callee.property.name=/^(querySelector|closest|getElementById|target|iframe|part)$/], [callee.name=/^(target|iframe|part)$/])",
                message: 'Never pass a DOM node to an assertion: compare what it says, or `node === other` as a boolean.',
            }, {
                selector: "CallExpression[callee.object.name='assert'] > MemberExpression[property.name=/^(activeElement|parentElement|parentNode|firstChild|lastChild|firstElementChild|lastElementChild|nextSibling|previousSibling|nextElementSibling|previousElementSibling|body|documentElement|target|currentTarget)$/]",
                message: 'Never pass a DOM node to an assertion: compare what it says, or `node === other` as a boolean.',
            }],
        },
    },
];
