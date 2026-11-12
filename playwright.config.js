import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '**/*.spec.js',
    outputDir: './var/browser-tests',
    reporter: 'list',
    use: {
        browserName: 'chromium',
        headless: true,
        viewport: { width: 1280, height: 900 },
    },
});
