import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

// The browser runs the packages of the import map; npm installs the same ones
// only for their types. Types of another version would check the code against
// an API the browser doesn't run.
const root = new URL('../../', import.meta.url);
const importMap = await readFile(new URL('importmap.php', root), 'utf8');
const { devDependencies } = JSON.parse(await readFile(new URL('package.json', root), 'utf8'));

/** @type {Map<string, string>} */
const mapped = new Map(
    [...importMap.matchAll(/'([^']+)'\s*=>\s*\[\s*'version'\s*=>\s*'([^']+)'/g)].map(([, name, version]) => [name, version]),
);

/** The npm package an import name comes from, such as @tiptap/pm for @tiptap/pm/state. */
const packageOf = (/** @type {string} */ name) => name.split('/').slice(0, name.startsWith('@') ? 2 : 1).join('/');

const typed = Object.keys(devDependencies).filter((name) => [...mapped.keys()].some((imported) => packageOf(imported) === name));

test('npm installs the types of the packages the import map loads', () => {
    for (const name of ['@hotwired/stimulus', '@tiptap/core', '@tiptap/pm', '@tiptap/suggestion']) {
        assert.ok(typed.includes(name), `${name} has no types installed`);
    }
});

test('npm pins each package at the version the import map loads', () => {
    for (const name of typed) {
        for (const [imported, version] of mapped) {
            if (packageOf(imported) === name) {
                assert.equal(devDependencies[name], version, `${imported} loads ${version}, npm has ${devDependencies[name]}`);
            }
        }
    }
});
