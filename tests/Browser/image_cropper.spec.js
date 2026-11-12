import { imageCropper } from './fixtures/image_cropper.js';
import { expect, loadController, test } from './support/test.js';

async function sourceImage(page, { width = 400, height = 200, name = 'source.png' } = {}) {
    const base64 = await page.evaluate(({ width, height }) => {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        context.fillStyle = '#345678';
        context.fillRect(0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/png').split(',')[1];
    }, { width, height });

    return { name, mimeType: 'image/png', buffer: Buffer.from(base64, 'base64') };
}

test('loads an image and resizes its crop from the keyboard', async ({ page }) => {
    await loadController(page, 'image-cropper', imageCropper());
    await page.locator('[data-image-cropper-target="source"]').setInputFiles(await sourceImage(page));

    await expect(page.locator('[data-image-cropper-target="editor"]')).toBeVisible();
    await expect(page.locator('[data-image-cropper-target="preview"]')).toHaveAttribute('width', '480');
    await expect(page.locator('[data-image-cropper-target="preview"]')).toHaveAttribute('height', '240');

    await page.locator('[data-image-cropper-target="area"]').focus();
    await page.keyboard.press('-');

    await expect(page.locator('[data-image-cropper-target="status"]')).toHaveText('396 × 198 pixels');
    await expect(page.locator('[data-image-cropper-target="area"]')).toHaveCSS('width', /\d+(?:\.\d+)?px/);
});

test('puts a WebP crop at the output dimensions in the submitted form', async ({ page }) => {
    await loadController(page, 'image-cropper', imageCropper());
    await page.locator('[data-image-cropper-target="source"]').setInputFiles(await sourceImage(page));
    await expect(page.locator('[data-image-cropper-target="editor"]')).toBeVisible();
    await page.evaluate(() => {
        window.submitFileCounts = [];
        document.querySelector('form').addEventListener('submit', (event) => {
            event.preventDefault();
            window.submitFileCounts.push(document.querySelector('[data-image-cropper-target="output"]').files.length);
        });
    });

    await page.getByRole('button', { name: 'Save' }).click();
    await expect.poll(() => page.evaluate(() => window.submitFileCounts.at(-1))).toBe(1);

    const output = await page.locator('[data-image-cropper-target="output"]').evaluate(async (input) => {
        const file = input.files[0];
        const bitmap = await createImageBitmap(file);
        const result = { name: file.name, type: file.type, width: bitmap.width, height: bitmap.height };
        bitmap.close();

        return result;
    });
    expect(output).toEqual({ name: 'image.webp', type: 'image/webp', width: 100, height: 50 });
    expect(await page.evaluate(() => window.submitFileCounts)).toEqual([0, 1]);
});

test('only the newest file finishes loading when two selections overlap', async ({ page }) => {
    await page.addInitScript(() => {
        const createImageBitmap = window.createImageBitmap.bind(window);
        window.createImageBitmap = (file, options) => {
            const decoding = createImageBitmap(file, options);

            if (file.name !== 'first.png') {
                return decoding;
            }

            return new Promise((resolve, reject) => {
                window.releaseFirst = () => decoding.then((bitmap) => {
                    window.firstDecoded = true;
                    resolve(bitmap);
                }, reject);
            });
        };
    });
    await loadController(page, 'image-cropper', imageCropper());
    const source = page.locator('[data-image-cropper-target="source"]');
    await source.setInputFiles(await sourceImage(page, { name: 'first.png' }));
    await expect.poll(() => page.evaluate(() => typeof window.releaseFirst)).toBe('function');
    await source.setInputFiles(await sourceImage(page, { name: 'second.png', width: 600, height: 300 }));
    await expect(page.locator('[data-image-cropper-target="editor"]')).toBeVisible();

    const area = page.locator('[data-image-cropper-target="area"]');
    await area.focus();
    await page.keyboard.press('-');
    await expect(page.locator('[data-image-cropper-target="status"]')).toHaveText('594 × 297 pixels');

    await page.evaluate(() => window.releaseFirst());
    await expect.poll(() => page.evaluate(() => window.firstDecoded)).toBe(true);
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    await page.keyboard.press('-');

    await expect(page.locator('[data-image-cropper-target="status"]')).toHaveText('588 × 294 pixels');
});

test('a second submit while encoding waits instead of starting another export', async ({ page }) => {
    await page.addInitScript(() => {
        window.encodeCount = 0;
        window.pendingBlobs = [];
        const toBlob = HTMLCanvasElement.prototype.toBlob;
        HTMLCanvasElement.prototype.toBlob = function (callback, ...args) {
            if (args[0] !== 'image/webp') {
                toBlob.call(this, callback, ...args);

                return;
            }

            window.encodeCount += 1;
            toBlob.call(this, (blob) => window.pendingBlobs.push(() => callback(blob)), ...args);
        };
    });
    await loadController(page, 'image-cropper', imageCropper());
    await page.locator('[data-image-cropper-target="source"]').setInputFiles(await sourceImage(page));
    await expect(page.locator('[data-image-cropper-target="editor"]')).toBeVisible();
    await page.evaluate(() => {
        window.submitFileCounts = [];
        document.querySelector('form').addEventListener('submit', (event) => {
            event.preventDefault();
            window.submitFileCounts.push(document.querySelector('[data-image-cropper-target="output"]').files.length);
        });
    });

    await page.getByRole('button', { name: 'Save' }).click();
    await expect.poll(() => page.evaluate(() => window.pendingBlobs.length)).toBe(1);
    await page.getByRole('button', { name: 'Save' }).click();
    await expect.poll(() => page.evaluate(() => window.submitFileCounts.length)).toBe(2);
    await expect.poll(() => page.evaluate(() => window.encodeCount)).toBe(1);

    await page.evaluate(() => window.pendingBlobs.splice(0).forEach((release) => release()));
    await expect.poll(() => page.evaluate(() => window.submitFileCounts.at(-1))).toBe(1);
    expect(await page.evaluate(() => window.encodeCount)).toBe(1);
});
