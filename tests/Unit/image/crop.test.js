import assert from 'node:assert/strict';
import test from 'node:test';
import {
    areaOf, clamp, coversOutput, dragged, frameOf, largestCrop, nudged, resizedAroundCentre, sizeOf, steps,
} from '../../../assets/image/crop.js';

const OUTPUT = { width: 1200, height: 675 };
// A wide photo: its height limits the crop.
const WIDE = frameOf({ width: 4000, height: 1800 }, OUTPUT);
// A tall one: its width does.
const TALL = frameOf({ width: 1600, height: 3000 }, OUTPUT);

test('an image covers the output only with pixels enough both ways', () => {
    assert.equal(coversOutput({ width: 1200, height: 675 }, OUTPUT), true);
    assert.equal(coversOutput({ width: 1199, height: 2000 }, OUTPUT), false);
    assert.equal(coversOutput({ width: 3000, height: 674 }, OUTPUT), false);
});

test('a frame takes the ratio of the output and the widest crop the image holds', () => {
    assert.equal(WIDE.ratio, 1200 / 675);
    assert.equal(WIDE.minWidth, 1200);
    assert.equal(WIDE.maxWidth, 3200);
    assert.equal(TALL.maxWidth, 1600);
});

test('the crop starts as large as it fits, centred', () => {
    assert.deepEqual(largestCrop(WIDE), { x: 400, y: 0, width: 3200 });
    assert.deepEqual(largestCrop(TALL), { x: 0, y: 1050, width: 1600 });
});

test('a crop is kept as wide as the output at least, and as the image allows at most', () => {
    assert.equal(clamp({ x: 0, y: 0, width: 800 }, WIDE).width, 1200);
    assert.equal(clamp({ x: 0, y: 0, width: 5000 }, WIDE).width, 3200);
    assert.equal(clamp({ x: 0, y: 0, width: 2000 }, WIDE).width, 2000);
});

test('a crop is kept inside the image', () => {
    assert.deepEqual(clamp({ x: -50, y: -20, width: 2000 }, WIDE), { x: 0, y: 0, width: 2000 });
    assert.deepEqual(clamp({ x: 3500, y: 1500, width: 2000 }, WIDE), { x: 2000, y: 1800 - 1125, width: 2000 });
    assert.deepEqual(clamp({ x: 100, y: 200, width: 2000 }, WIDE), { x: 100, y: 200, width: 2000 });
});

test('dragging the area moves it whole', () => {
    assert.deepEqual(dragged({ x: 100, y: 50, width: 2000 }, 'move', { dx: 30, dy: -20 }, WIDE), { x: 130, y: 30, width: 2000 });
});

test('dragging the handle resizes from the top left corner, along the ratio', () => {
    const start = { x: 100, y: 50, width: 2000 };

    // The pointer's longer stretch, along the ratio, sets the width.
    assert.equal(dragged(start, 'resize', { dx: 100, dy: 10 }, WIDE).width, 2100);
    assert.equal(dragged(start, 'resize', { dx: 10, dy: 100 }, WIDE).width, 2000 + 100 * WIDE.ratio);
    assert.equal(dragged(start, 'resize', { dx: -300, dy: -400 }, WIDE).width, 1700);
    assert.deepEqual({ ...dragged(start, 'resize', { dx: 100, dy: 10 }, WIDE), width: 0 }, { x: 100, y: 50, width: 0 });
});

test('resizing from the handle never takes the crop past the image', () => {
    // Past the widest crop of the image.
    assert.equal(dragged({ x: 0, y: 0, width: 2000 }, 'resize', { dx: 5000, dy: 0 }, WIDE).width, 3200);
    // Past its right side.
    assert.equal(dragged({ x: 3000, y: 0, width: 800 }, 'resize', { dx: 500, dy: 0 }, WIDE).width, 1000);
    // Past its bottom.
    assert.equal(dragged({ x: 0, y: 1400, width: 600 }, 'resize', { dx: 500, dy: 0 }, WIDE).width, 400 * WIDE.ratio);
});

test('a crop resized around its centre keeps the centre', () => {
    const crop = resizedAroundCentre({ x: 100, y: 100, width: 1600 }, 2000, 16 / 9);

    assert.deepEqual(crop, { x: -100, y: 100 - 112.5, width: 2000 });
});

test('the arrows move the crop a hundredth of the image, a tenth with Shift', () => {
    const crop = { x: 400, y: 100, width: 2000 };

    assert.deepEqual(nudged(crop, { key: 'ArrowRight', shiftKey: false }, WIDE), { crop: { x: 440, y: 100, width: 2000 }, resized: false });
    assert.deepEqual(nudged(crop, { key: 'ArrowLeft', shiftKey: true }, WIDE), { crop: { x: 0, y: 100, width: 2000 }, resized: false });
    assert.deepEqual(nudged(crop, { key: 'ArrowUp', shiftKey: false }, WIDE)?.crop, { x: 400, y: 60, width: 2000 });
    assert.deepEqual(nudged(crop, { key: 'ArrowDown', shiftKey: false }, WIDE)?.crop, { x: 400, y: 140, width: 2000 });
});

test('+ and - resize the crop around its centre, and = and _ too', () => {
    const crop = { x: 400, y: 100, width: 2000 };

    for (const key of ['+', '=']) {
        assert.deepEqual(nudged(crop, { key, shiftKey: false }, WIDE), { crop: resizedAroundCentre(crop, 2040, WIDE.ratio), resized: true });
    }

    for (const key of ['-', '_']) {
        assert.deepEqual(nudged(crop, { key, shiftKey: true }, WIDE), { crop: resizedAroundCentre(crop, 1600, WIDE.ratio), resized: true });
    }
});

test('any other key leaves the crop alone', () => {
    assert.equal(nudged({ x: 0, y: 0, width: 2000 }, { key: 'a', shiftKey: false }, WIDE), null);
    assert.equal(nudged({ x: 0, y: 0, width: 2000 }, { key: 'Enter', shiftKey: true }, WIDE), null);
});

test('the area is drawn in percentages of the image', () => {
    assert.deepEqual(areaOf({ x: 400, y: 450, width: 2000 }, frameOf({ width: 4000, height: 1800 }, { width: 1600, height: 900 })), {
        left: 10,
        top: 25,
        width: 50,
        height: 62.5,
    });
});

test('the size of a crop is told in whole pixels', () => {
    assert.deepEqual(sizeOf({ x: 0, y: 0, width: 1999.6 }, 16 / 9), { width: 2000, height: 1125 });
});

test('a crop halves on its way down while it stays at least twice the output', () => {
    assert.deepEqual(steps({ x: 0, y: 0, width: 4800.4 }, 16 / 9, 1200), [
        { width: 4800, height: 2700 },
        { width: 2400, height: 1350 },
        { width: 1200, height: 675 },
    ]);
    assert.deepEqual(steps({ x: 0, y: 0, width: 2399 }, 16 / 9, 1200), [{ width: 2399, height: 1349 }]);
    assert.deepEqual(steps({ x: 0, y: 0, width: 1200 }, 16 / 9, 1200), [{ width: 1200, height: 675 }]);
});
