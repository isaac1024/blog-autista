import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import test from 'node:test';

/*
 * The CMS loads @symfony/stimulus-bundle through the import map, from the
 * bundle's PHP package; here it is one that tells it was started.
 */
const BUNDLE = `data:text/javascript,${encodeURIComponent('export const startStimulusApp = () => { globalThis.stimulusStarts = (globalThis.stimulusStarts ?? 0) + 1; };')}`;

registerHooks({
    resolve: (specifier, context, nextResolve) => (specifier === '@symfony/stimulus-bundle'
        ? { url: BUNDLE, shortCircuit: true }
        : nextResolve(specifier, context)),
});

test('the entry point of the CMS starts the Stimulus app, once', async () => {
    await import('../../assets/cms.js');

    assert.equal(globalThis.stimulusStarts, 1);
});
