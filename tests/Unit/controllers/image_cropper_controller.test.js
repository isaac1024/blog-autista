import assert from 'node:assert/strict';
import test from 'node:test';
import ImageCropper from '../../../assets/controllers/image_cropper_controller.js';
import { imageCropper } from '../../Markup/image_cropper.js';
import { later, mount, settle } from '../support/dom.js';

/*
 * The cropper, on a page whose canvases record what is drawn on them and
 * encode as `encoding` says, whose images decode to the size their file
 * names, and whose stage is 200 pixels wide. The markup crops to 100 × 50.
 */
const open = async ({ markup = imageCropper(), decode } = {}) => {
    const canvases = new Map();
    const encoding = { type: 'image/webp', fail: false };
    const encoded = [];
    const canvasAdapter = {
        getContext: ({ canvas }, type) => {
            if (type !== '2d') {
                return null;
            }

            const drawn = canvases.get(canvas) ?? [];
            canvases.set(canvas, drawn);

            return {
                set imageSmoothingQuality(quality) { drawn.push(['smoothing', quality]); },
                drawImage: (source, ...box) => { drawn.push([canvases.has(source) ? [...canvases.keys()].indexOf(source) : source.name, ...box]); },
            };
        },
        toDataURL: () => '',
        toBlob: ({ canvas }, callback, type, quality) => {
            encoded.push([canvas.width, canvas.height, type, quality]);

            if (encoding.fail) {
                throw new Error('Out of memory');
            }

            callback(encoding.type === null ? null : new page.window.Blob(['webp'], { type: encoding.type }));
        },
    };
    const page = await mount(markup, { 'image-cropper': ImageCropper }, { settings: { canvasAdapter } });
    const { document, window } = page;
    const bitmaps = [];
    const decodings = [];
    globalThis.createImageBitmap = async (file, options) => {
        decodings.push([file.name, options]);
        await decode?.(file);
        const [width, height] = file.name.split('.')[0].split('x').map(Number);

        if (!(height > 0)) {
            throw new Error('Not an image');
        }

        const bitmap = { name: file.name, width, height, closed: false, close() { this.closed = true; } };
        bitmaps.push(bitmap);

        return bitmap;
    };
    const urls = { made: [], revoked: [] };
    URL.createObjectURL = (file) => {
        urls.made.push(file.name);

        return `blob:${file.name}`;
    };
    URL.revokeObjectURL = (url) => { urls.revoked.push(url); };
    const target = (name) => document.querySelector(`[data-image-cropper-target="${name}"]`);
    Object.defineProperty(target('stage'), 'clientWidth', { configurable: true, value: 200 });
    const area = target('area');
    const pointer = (type, element, { x = 0, y = 0, button = 0 } = {}) => {
        const event = new window.PointerEvent(type, { bubbles: true, cancelable: true, button, pointerId: 7, clientX: x, clientY: y });
        element.dispatchEvent(event);

        return event.defaultPrevented;
    };
    // Told last, once the cropper has had its say on the submission.
    let submits = 0;
    window.addEventListener('submit', (event) => { submits += event.defaultPrevented ? 0 : 1; });

    return {
        ...page,
        target,
        area,
        bitmaps,
        decodings,
        urls,
        canvases,
        encoding,
        encoded,
        submitted: () => submits,
        /** Chooses the files, named by the size they decode to, and waits for them. */
        choose: async (...names) => {
            const files = new window.DataTransfer();
            names.forEach((name) => files.items.add(new window.File(['image'], name, { type: 'image/jpeg' })));
            target('source').files = files.files;
            target('source').dispatchEvent(new window.Event('change', { bubbles: true }));
            await settle();
        },
        /** Where the area stands on the image, in percentages. */
        placed: () => [area.style.left, area.style.top, area.style.width, area.style.height],
        drag: (from, to, element = area) => {
            pointer('pointerdown', element, from);
            pointer('pointermove', area, to);
            pointer('pointerup', area, to);
        },
        pointer,
        press: (key, shiftKey = false) => {
            const event = new window.KeyboardEvent('keydown', { key, shiftKey, bubbles: true, cancelable: true });
            area.dispatchEvent(event);

            return event.defaultPrevented;
        },
        /** What the preview canvas was last told to draw. */
        preview: async () => {
            await new Promise((resolve) => { window.requestAnimationFrame(resolve); });

            return canvases.get(target('preview'));
        },
        save: async () => {
            document.querySelector('button[type="submit"]').click();
            await settle();
        },
        error: () => (target('error').hidden ? null : target('error').textContent),
    };
};

test('the preview has the shape of the output, and nothing is shown before an image is chosen', async () => {
    const cropper = await open();

    assert.deepEqual([cropper.target('preview').width, cropper.target('preview').height], [480, 240]);
    assert.equal(cropper.target('editor').hidden, true);
});

test('an image chosen is shown turned as the camera says, with the largest area of the output\'s shape centred on it', async () => {
    const cropper = await open();

    await cropper.choose('400x400.jpg');

    assert.deepEqual(cropper.decodings, [['400x400.jpg', { imageOrientation: 'from-image' }]]);
    assert.equal(cropper.target('editor').hidden, false);
    const shown = cropper.target('stage').firstElementChild;
    assert.deepEqual([shown.tagName, shown.className, shown.alt, shown.getAttribute('src')], ['IMG', 'block h-auto max-h-[55dvh] w-auto max-w-full', '', 'blob:400x400.jpg']);
    assert.deepEqual(cropper.placed(), ['0%', '25%', '100%', '50%']);
    assert.deepEqual(await cropper.preview(), [['smoothing', 'high'], ['400x400.jpg', 0, 100, 400, 200, 0, 0, 480, 240]]);
});

test('the area is resized from its handle and moved by dragging it, in pointer pixels turned into image pixels', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');
    const handle = cropper.area.querySelector('span');

    cropper.drag({ x: 100, y: 100 }, { x: 50, y: 75 }, handle);
    assert.deepEqual(cropper.placed(), ['0%', '25%', '75%', '37.5%']);

    cropper.drag({ x: 10, y: 10 }, { x: 20, y: 14 });
    assert.deepEqual(cropper.placed(), ['5%', '27%', '75%', '37.5%']);
    assert.deepEqual((await cropper.preview()).at(-1), ['400x400.jpg', 20, 108, 300, 150, 0, 0, 480, 240]);
});

test('a drag starts only with the main button and on an image, takes the pointer and the focus, and ends on release', async () => {
    const cropper = await open();
    assert.equal(cropper.pointer('pointerdown', cropper.area), false);
    cropper.pointer('pointermove', cropper.area, { x: 50 });

    await cropper.choose('400x400.jpg');
    cropper.area.querySelector('span').dispatchEvent(new cropper.window.PointerEvent('pointerdown', { bubbles: true, button: 2, pointerId: 7 }));
    assert.equal(cropper.pointer('pointerdown', cropper.area, { button: 2 }), false);
    cropper.pointer('pointermove', cropper.area, { x: -50 });
    assert.deepEqual(cropper.placed(), ['0%', '25%', '100%', '50%']);

    const focused = [];
    const focus = cropper.area.focus;
    cropper.area.focus = function (options) {
        focused.push(options);
        focus.call(this, options);
    };
    assert.equal(cropper.pointer('pointerdown', cropper.area), true);
    // The page stays where it is: the area is already under the pointer.
    assert.deepEqual(focused, [{ preventScroll: true }]);
    assert.equal(cropper.document.activeElement === cropper.area, true);
    assert.equal(cropper.area.hasPointerCapture(7), true);
    cropper.pointer('pointercancel', cropper.area);
    cropper.pointer('pointermove', cropper.area, { x: 50, y: 50 });
    assert.deepEqual(cropper.placed(), ['0%', '25%', '100%', '50%']);
});

test('the arrows move the area and + and - resize it, telling the size; other keys do nothing', async () => {
    const cropper = await open();
    assert.equal(cropper.press('-'), false);
    await cropper.choose('400x400.jpg');

    assert.equal(cropper.press('-'), true);
    assert.deepEqual(cropper.placed(), ['0.5%', '25.25%', '99%', '49.5%']);
    assert.equal(cropper.target('status').textContent, '396 × 198 pixels');

    assert.equal(cropper.press('-', true), true);
    assert.equal(cropper.target('status').textContent, '356 × 178 pixels');

    cropper.target('status').textContent = '';
    assert.equal(cropper.press('ArrowRight'), true);
    assert.deepEqual(cropper.placed()[0], '6.5%');
    assert.equal(cropper.target('status').textContent, '');

    assert.equal(cropper.press('a'), false);
    assert.deepEqual(cropper.placed()[0], '6.5%');
});

test('an image smaller than the output, or one that can\'t be read, is refused, told, and its choice undone', async () => {
    for (const [name, told] of [['80x200.jpg', 'The image is too small'], ['200x40.jpg', 'The image is too small'], ['notes.txt', 'The image cannot be read']]) {
        const cropper = await open();
        await cropper.choose(name);

        assert.equal(cropper.error(), told);
        assert.equal(cropper.target('source').value, '');
        assert.equal(cropper.target('editor').hidden, true);
        assert.deepEqual(cropper.bitmaps.map(({ closed }) => closed), name === 'notes.txt' ? [] : [true]);
    }
});

test('choosing again, or choosing nothing, clears the image before, its error included', async () => {
    const cropper = await open();
    await cropper.choose('notes.txt');
    await cropper.choose('400x400.jpg');
    assert.equal(cropper.error(), null);
    assert.equal(cropper.target('error').textContent, '');

    cropper.press('+');
    await cropper.choose('300x300.jpg');
    assert.deepEqual(cropper.bitmaps.map(({ closed }) => closed), [true, false]);
    assert.deepEqual(cropper.urls.revoked, ['blob:400x400.jpg']);
    assert.equal(cropper.target('stage').querySelectorAll('img').length, 1);
    assert.equal(cropper.target('status').textContent, '');

    await cropper.choose();
    assert.equal(cropper.target('editor').hidden, true);
    assert.equal(cropper.error(), null);
    assert.equal(cropper.target('stage').querySelectorAll('img').length, 0);
    assert.deepEqual(cropper.urls.revoked, ['blob:400x400.jpg', 'blob:300x300.jpg']);
    assert.deepEqual(cropper.decodings.length, 3);
});

test('an image decoded after another was chosen is dropped, and so is its failure', async () => {
    const slow = later();
    const cropper = await open({ decode: (file) => (file.name.startsWith('slow') ? slow.promise : undefined) });

    await cropper.choose('slow.jpg');
    await cropper.choose('400x400.jpg');
    slow.resolve();
    await settle();
    assert.equal(cropper.error(), null);
    assert.deepEqual(cropper.placed(), ['0%', '25%', '100%', '50%']);

    const slower = later();
    const second = await open({ decode: (file) => (file.name.startsWith('slow') ? slower.promise : undefined) });
    await second.choose('slow500x500.jpg');
    await second.choose('400x400.jpg');
    slower.resolve();
    await settle();
    assert.deepEqual(second.bitmaps.map(({ name, closed }) => [name, closed]), [['400x400.jpg', false], ['slow500x500.jpg', true]]);
    assert.deepEqual(second.placed(), ['0%', '25%', '100%', '50%']);
});

test('saving draws the crop down by halves to the output, puts it in the form as WebP, then sends the form', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');

    await cropper.save();

    const steps = [...cropper.canvases].filter(([canvas]) => canvas !== cropper.target('preview')).map(([canvas, drawn]) => [canvas.width, canvas.height, drawn]);
    assert.deepEqual(steps, [
        [400, 200, [['400x400.jpg', 0, 100, 400, 200, 0, 0, 400, 200]]],
        [200, 100, [['smoothing', 'high'], [1, 0, 0, 200, 100]]],
        [100, 50, [['smoothing', 'high'], [2, 0, 0, 100, 50]]],
        [100, 50, [['smoothing', 'high'], [3, 0, 0, 100, 50]]],
    ]);
    assert.deepEqual(cropper.encoded, [[100, 50, 'image/webp', 0.8]]);
    const [file] = cropper.target('output').files;
    assert.deepEqual([file.name, file.type, await file.text()], ['image.webp', 'image/webp', 'webp']);
    assert.equal(cropper.submitted(), 1);

    await cropper.save();
    assert.equal(cropper.encoded.length, 1);
    assert.equal(cropper.submitted(), 2);
});

test('a change after the crop was put in the form makes it stale, to be encoded again', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');
    await cropper.save();

    cropper.press('ArrowDown');
    assert.equal(cropper.target('output').value, '');
    await cropper.save();

    assert.equal(cropper.encoded.length, 2);
    assert.equal(cropper.submitted(), 2);
});

test('a browser that can\'t encode WebP is told, and the form is not sent', async () => {
    for (const encoding of [{ type: 'image/png' }, { type: null }, { fail: true }]) {
        const cropper = await open();
        await cropper.choose('400x400.jpg');
        Object.assign(cropper.encoding, encoding);

        await cropper.save();

        assert.equal(cropper.error(), 'WebP is not supported');
        assert.equal(cropper.submitted(), 0);
        assert.equal(cropper.target('editor').hidden, true);
    }
});

test('without an image the form is left to its own checks', async () => {
    const cropper = await open();
    const submit = new cropper.window.Event('submit', { bubbles: true, cancelable: true });

    cropper.document.querySelector('form').dispatchEvent(submit);

    assert.equal(submit.defaultPrevented, false);
    assert.deepEqual(cropper.encoded, []);
});

test('a save while one is encoding waits for it, and an image chosen meanwhile drops it', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');
    const blocked = later();
    const toBlob = cropper.window.HTMLCanvasElement.prototype.toBlob;
    cropper.window.HTMLCanvasElement.prototype.toBlob = function (callback, ...options) {
        blocked.promise.then(() => toBlob.call(this, callback, ...options));
    };
    const form = cropper.document.querySelector('form');
    const again = new cropper.window.Event('submit', { bubbles: true, cancelable: true });

    await cropper.save();
    form.dispatchEvent(again);
    assert.equal(again.defaultPrevented, true);
    await cropper.choose('300x300.jpg');
    blocked.resolve();
    await settle();

    assert.equal(cropper.encoded.length, 1);
    assert.equal(cropper.submitted(), 0);
    assert.equal(cropper.error(), null);
    assert.equal(cropper.target('output').files.length, 0);

    cropper.window.HTMLCanvasElement.prototype.toBlob = toBlob;
    await cropper.save();
    assert.equal(cropper.submitted(), 1);
});

test('an encoding that fails after another image was chosen tells nothing, and the new one saves', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');
    const blocked = later();
    const toBlob = cropper.window.HTMLCanvasElement.prototype.toBlob;
    cropper.window.HTMLCanvasElement.prototype.toBlob = () => { throw new Error('Interrupted'); };
    const encode = ImageCropper.prototype.encode;
    ImageCropper.prototype.encode = async function () {
        await blocked.promise;

        return encode.call(this);
    };

    try {
        await cropper.save();
        await cropper.choose('300x300.jpg');
        blocked.resolve();
        await settle();
        assert.equal(cropper.error(), null);
        assert.equal(cropper.target('editor').hidden, false);
    } finally {
        ImageCropper.prototype.encode = encode;
        cropper.window.HTMLCanvasElement.prototype.toBlob = toBlob;
    }

    await cropper.save();
    assert.equal(cropper.submitted(), 1);
});

test('a cropper that leaves the page lets go of its image and of the form', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');
    const form = cropper.document.querySelector('form');
    const element = cropper.document.querySelector('[data-controller]');

    element.remove();
    await settle();
    const submit = new cropper.window.Event('submit', { bubbles: true, cancelable: true });
    form.dispatchEvent(submit);

    assert.deepEqual(cropper.urls.revoked, ['blob:400x400.jpg']);
    assert.deepEqual(cropper.bitmaps.map(({ closed }) => closed), [true]);
    assert.equal(submit.defaultPrevented, false);
});

test('a cropper outside a form still crops', async () => {
    const cropper = await open({ markup: imageCropper().replace('<form>', '<div>').replace('</form>', '</div>') });

    await cropper.choose('400x400.jpg');
    cropper.press('+');
    cropper.document.querySelector('[data-controller]').remove();
    await settle();

    assert.equal(cropper.target('editor'), null);
});

test('the preview is drawn once a frame, however often the area changes in it, and not once the image is gone', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');
    await cropper.preview();
    const drawn = cropper.canvases.get(cropper.target('preview'));
    drawn.length = 0;

    cropper.press('ArrowRight');
    cropper.press('ArrowRight');
    assert.deepEqual(await cropper.preview(), [['smoothing', 'high'], ['400x400.jpg', 0, 100, 400, 200, 0, 0, 480, 240]]);

    drawn.length = 0;
    cropper.press('-');
    await cropper.choose();
    assert.deepEqual(await cropper.preview(), []);
});

test('a save that outlived its image doesn\'t free the save of the next one', async () => {
    const cropper = await open();
    await cropper.choose('400x400.jpg');
    const releases = [];
    const toBlob = cropper.window.HTMLCanvasElement.prototype.toBlob;
    cropper.window.HTMLCanvasElement.prototype.toBlob = function (callback, ...options) {
        releases.push(() => toBlob.call(this, callback, ...options));
    };
    const form = cropper.document.querySelector('form');

    await cropper.save();
    await cropper.choose('300x300.jpg');
    await cropper.save();
    releases[0]();
    await settle();
    form.dispatchEvent(new cropper.window.Event('submit', { bubbles: true, cancelable: true }));
    await settle();

    assert.equal(releases.length, 2);
    cropper.window.HTMLCanvasElement.prototype.toBlob = toBlob;
});

test('a form other than the cropper\'s is left alone', async () => {
    const cropper = await open({ markup: `${imageCropper()}<form id="other"><button>Other</button></form>` });
    await cropper.choose('400x400.jpg');
    const submit = new cropper.window.Event('submit', { bubbles: true, cancelable: true });

    cropper.document.getElementById('other').dispatchEvent(submit);

    assert.equal(submit.defaultPrevented, false);
    assert.deepEqual(cropper.encoded, []);
});
