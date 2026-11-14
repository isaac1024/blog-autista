import assert from 'node:assert/strict';
import test from 'node:test';
import { ControlsShortcut } from '../../../assets/rich_text/controls_shortcut.js';

test('Alt+F10 asks for the controls, and is taken, until the editor answers it', () => {
    let requests = 0;
    const shortcuts = ControlsShortcut.config.addKeyboardShortcuts.call({ options: { onRequest: () => { requests += 1; } } });

    assert.equal(ControlsShortcut.config.name, 'controlsShortcut');
    assert.deepEqual(Object.keys(shortcuts), ['Alt-F10']);
    assert.equal(shortcuts['Alt-F10'](), true);
    assert.equal(requests, 1);
    assert.equal(ControlsShortcut.config.addOptions().onRequest(), undefined);
});
