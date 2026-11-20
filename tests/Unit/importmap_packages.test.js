import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';

// The browser runs the packages npm installs as dependencies: the import map
// names their files in node_modules, which npm checks against the hashes of
// package-lock.json, and which a deploy installs without the tools of
// development (npm ci --omit=dev).
const root = new URL('../../', import.meta.url);
const importMap = readFileSync(new URL('importmap.php', root), 'utf8');
const { packages: locked } = JSON.parse(readFileSync(new URL('package-lock.json', root), 'utf8'));

const files = [...importMap.matchAll(/'([^']+)'\s*=>\s*\[\s*'path'\s*=>\s*'\.\/node_modules\/([^']+)'/g)]
    .map(([, name, file]) => ({ name, file, pkg: file.split('/').slice(0, file.startsWith('@') ? 2 : 1).join('/') }));

test('the import map loads the packages from npm, and from nowhere else', () => {
    assert.ok(files.length > 0, 'the import map loads no package from npm');
    assert.doesNotMatch(importMap, /'version'\s*=>/, 'a package is downloaded by version, from outside npm');
});

test('each file the import map loads is installed', () => {
    for (const { name, file } of files) {
        assert.ok(existsSync(new URL(`node_modules/${file}`, root)), `${name} loads node_modules/${file}, which npm didn't install`);
    }
});

test('each package the import map loads is installed without the tools of development', () => {
    for (const { name, pkg } of files) {
        const installed = locked[`node_modules/${pkg}`];

        assert.ok(installed !== undefined, `${name} comes from ${pkg}, which the lock doesn't have`);
        assert.notEqual(installed.dev, true, `${name} comes from ${pkg}, installed for development only`);
    }
});
