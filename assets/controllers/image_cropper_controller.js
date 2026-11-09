import { Controller } from '@hotwired/stimulus';

const PREVIEW_WIDTH = 480;

/*
 * Picks the part of an image to keep, at the aspect ratio of the output, and
 * puts it in the form at the output size, encoded as WebP.
 *
 * The crop is held in pixels of the image as it is oriented, and drawn over
 * it in percentages, so the area follows the image when the layout changes.
 * It never covers fewer pixels than the output, since images are never
 * enlarged, nor leaves the image.
 */
export default class extends Controller {
    static targets = ['source', 'output', 'error', 'editor', 'stage', 'area', 'status', 'preview'];

    static values = {
        width: Number,
        height: Number,
        quality: Number,
        tooSmall: String,
        unreadable: String,
        unsupported: String,
        sizeText: String,
    };

    connect() {
        this.ratio = this.widthValue / this.heightValue;
        this.previewTarget.width = PREVIEW_WIDTH;
        this.previewTarget.height = Math.round(PREVIEW_WIDTH / this.ratio);

        this.form = this.element.closest('form');
        this.submit = this.submit.bind(this);
        this.form?.addEventListener('submit', this.submit);
    }

    disconnect() {
        this.form?.removeEventListener('submit', this.submit);
        this.clear();
    }

    async load() {
        this.clear();
        const file = this.sourceTarget.files[0];

        if (!file) {
            return;
        }

        let bitmap;

        try {
            // Turned as the camera says, as the <img> shows it.
            bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            this.fail(this.unreadableValue);

            return;
        }

        if (bitmap.width < this.widthValue || bitmap.height < this.heightValue) {
            bitmap.close();
            this.fail(this.tooSmallValue);

            return;
        }

        this.bitmap = bitmap;
        this.url = URL.createObjectURL(file);
        // Only an image with a file is valid markup, so it exists while there is one.
        this.image = document.createElement('img');
        this.image.className = 'block h-auto max-h-[55dvh] w-auto max-w-full';
        this.image.alt = '';
        this.image.src = this.url;
        this.stageTarget.prepend(this.image);

        // The largest crop that fits, centred.
        this.maxWidth = Math.min(bitmap.width, Math.floor(bitmap.height * this.ratio));
        this.crop = {
            x: (bitmap.width - this.maxWidth) / 2,
            y: (bitmap.height - this.maxWidth / this.ratio) / 2,
            width: this.maxWidth,
        };
        this.editorTarget.hidden = false;
        this.update();
    }

    /* Moving and resizing the area. */

    startMove(event) {
        this.startDrag(event, 'move');
    }

    startResize(event) {
        // The handle sits inside the area, which would otherwise start a move.
        event.stopPropagation();
        this.startDrag(event, 'resize');
    }

    startDrag(event, mode) {
        if (!this.crop || event.button !== 0) {
            return;
        }

        event.preventDefault();
        this.areaTarget.focus({ preventScroll: true });
        this.areaTarget.setPointerCapture(event.pointerId);
        this.dragging = { mode, x: event.clientX, y: event.clientY, crop: { ...this.crop } };
    }

    drag(event) {
        if (!this.dragging) {
            return;
        }

        // Pointer pixels into image pixels.
        const scale = this.bitmap.width / this.stageTarget.clientWidth;
        const dx = (event.clientX - this.dragging.x) * scale;
        const dy = (event.clientY - this.dragging.y) * scale;
        const start = this.dragging.crop;

        if (this.dragging.mode === 'move') {
            this.crop = { ...start, x: start.x + dx, y: start.y + dy };
        } else {
            // The top left corner stays put; the handle follows the pointer along the ratio.
            const widest = Math.min(this.maxWidth, this.bitmap.width - start.x, (this.bitmap.height - start.y) * this.ratio);
            const width = Math.min(widest, start.width + Math.max(dx, dy * this.ratio));
            this.crop = { ...start, width };
        }

        this.update();
    }

    endDrag(event) {
        if (this.dragging && this.areaTarget.hasPointerCapture(event.pointerId)) {
            this.areaTarget.releasePointerCapture(event.pointerId);
        }

        this.dragging = null;
    }

    /**
     * The arrows move the area and + and - resize it, by a hundredth of the
     * image per key, a tenth with Shift.
     */
    nudge(event) {
        const moves = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
        // Shift turns = into + on most layouts.
        const resizes = { '+': 1, '=': 1, '-': -1, _: -1 };
        const move = moves[event.key];
        const resize = resizes[event.key];

        if (!this.crop || (!move && !resize)) {
            return;
        }

        event.preventDefault();
        const step = this.bitmap.width / (event.shiftKey ? 10 : 100);

        if (move) {
            this.crop = { ...this.crop, x: this.crop.x + move[0] * step, y: this.crop.y + move[1] * step };
        } else {
            this.resizeAroundCentre(this.crop.width + resize * step);
        }

        this.update();

        if (resize) {
            this.announceSize();
        }
    }

    resizeAroundCentre(width) {
        const centreX = this.crop.x + this.crop.width / 2;
        const centreY = this.crop.y + this.crop.width / this.ratio / 2;
        this.crop = { x: centreX - width / 2, y: centreY - width / this.ratio / 2, width };
    }

    announceSize() {
        const width = Math.round(this.crop.width);
        this.statusTarget.textContent = this.sizeTextValue
            .replace('%width%', String(width))
            .replace('%height%', String(Math.round(width / this.ratio)));
    }

    /* Drawing. */

    update() {
        this.clamp();
        const { x, y, width } = this.crop;
        const height = width / this.ratio;
        const style = this.areaTarget.style;
        style.left = `${(x / this.bitmap.width) * 100}%`;
        style.top = `${(y / this.bitmap.height) * 100}%`;
        style.width = `${(width / this.bitmap.width) * 100}%`;
        style.height = `${(height / this.bitmap.height) * 100}%`;

        // Any change makes the crop already put in the form stale.
        this.outputTarget.value = '';
        this.exported = false;
        this.schedulePreview();
    }

    clamp() {
        const width = Math.min(this.maxWidth, Math.max(this.widthValue, this.crop.width));
        const height = width / this.ratio;
        this.crop = {
            width,
            x: Math.min(this.bitmap.width - width, Math.max(0, this.crop.x)),
            y: Math.min(this.bitmap.height - height, Math.max(0, this.crop.y)),
        };
    }

    schedulePreview() {
        if (this.previewFrame) {
            return;
        }

        this.previewFrame = requestAnimationFrame(() => {
            this.previewFrame = null;

            if (!this.crop) {
                return;
            }

            const canvas = this.previewTarget;
            const context = canvas.getContext('2d');
            const { x, y, width } = this.crop;
            context.imageSmoothingQuality = 'high';
            context.drawImage(this.bitmap, x, y, width, width / this.ratio, 0, 0, canvas.width, canvas.height);
        });
    }

    /* Sending the crop. */

    submit(event) {
        // Without an image, the required field has already stopped the form.
        if (!this.crop || this.exported) {
            return;
        }

        event.preventDefault();
        const submitter = event.submitter;

        this.encode().then((blob) => {
            if (!blob || blob.type !== 'image/webp') {
                this.fail(this.unsupportedValue);

                return;
            }

            const files = new DataTransfer();
            files.items.add(new File([blob], 'image.webp', { type: 'image/webp' }));
            this.outputTarget.files = files.files;
            this.exported = true;
            this.form.requestSubmit(submitter);
        });
    }

    /**
     * Halves the crop until it is less than twice the output, then draws it
     * at the output size: one big step down would alias, and not every
     * browser honours imageSmoothingQuality.
     */
    encode() {
        const { x, y, width } = this.crop;
        let canvas = this.canvas(Math.round(width), Math.round(width / this.ratio));
        canvas.getContext('2d').drawImage(this.bitmap, x, y, width, width / this.ratio, 0, 0, canvas.width, canvas.height);

        while (canvas.width / 2 >= this.widthValue) {
            canvas = this.scaled(canvas, Math.round(canvas.width / 2), Math.round(canvas.height / 2));
        }

        const output = this.scaled(canvas, this.widthValue, this.heightValue);

        return new Promise((resolve) => {
            output.toBlob(resolve, 'image/webp', this.qualityValue);
        });
    }

    scaled(source, width, height) {
        const canvas = this.canvas(width, height);
        const context = canvas.getContext('2d');
        context.imageSmoothingQuality = 'high';
        context.drawImage(source, 0, 0, width, height);

        return canvas;
    }

    canvas(width, height) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        return canvas;
    }

    /* State. */

    fail(message) {
        this.clear();
        this.sourceTarget.value = '';
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }

    clear() {
        if (this.url) {
            URL.revokeObjectURL(this.url);
            this.url = null;
        }

        this.bitmap?.close();
        this.bitmap = null;
        this.crop = null;
        this.dragging = null;
        this.exported = false;
        this.outputTarget.value = '';
        this.image?.remove();
        this.image = null;
        this.editorTarget.hidden = true;
        this.errorTarget.hidden = true;
        this.errorTarget.textContent = '';
        this.statusTarget.textContent = '';
    }
}
