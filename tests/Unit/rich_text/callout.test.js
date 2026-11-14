import assert from 'node:assert/strict';
import test from 'node:test';
import { Callout } from '../../../assets/rich_text/callout.js';

test('a callout is a defining block holding at least one paragraph', () => {
    assert.equal(Callout.config.name, 'callout');
    assert.equal(Callout.config.group, 'block');
    assert.equal(Callout.config.content, 'paragraph+');
    assert.equal(Callout.config.defining, true);
});

test('a callout has no variant labels unless the editor supplies them', () => {
    assert.deepEqual(Callout.config.addOptions(), { labels: {} });
});

test('its variant is read from and written to its data attribute', () => {
    const { variant } = Callout.config.addAttributes();
    const element = { getAttribute: (name) => name === 'data-variant' ? 'warning' : null };

    assert.equal(variant.default, null);
    assert.equal(variant.parseHTML(element), 'warning');
    assert.deepEqual(variant.renderHTML({ variant: 'warning' }), { 'data-variant': 'warning' });
});

test('a callout is read from its own element', () => {
    assert.deepEqual(Callout.config.parseHTML(), [{ tag: 'div[data-callout]' }]);
});

test('a callout is drawn with its label and an accessible role, keeping its other attributes', () => {
    const rendered = Callout.config.renderHTML.call(
        { options: { labels: { warning: 'Warning' } } },
        { node: { attrs: { variant: 'warning' } }, HTMLAttributes: { class: 'existing' } },
    );

    assert.deepEqual(rendered, ['div', {
        class: 'existing',
        'data-callout': '',
        'data-label': 'Warning',
        role: 'note',
    }, 0]);
});

test('a variant without a label still leaves an explicit empty label', () => {
    const rendered = Callout.config.renderHTML.call(
        { options: { labels: {} } },
        { node: { attrs: { variant: 'unknown' } }, HTMLAttributes: {} },
    );

    assert.equal(rendered[1]['data-label'], '');
});
