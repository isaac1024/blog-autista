import { Controller } from '@hotwired/stimulus';

const PREVIEW_WIDTH = 480;

/**
 * Where the crop sits, in pixels of the oriented image; its height follows
 * from the ratio of the output.
 *
 * @typedef {object} Crop
 * @property {number} x
 * @property {number} y
 * @property {number} width
 */

/**
 * @typedef {object} Drag
 * @property {'move'|'resize'} mode
 * @property {number} x
 * @property {number} y
 * @property {Crop} crop
 */

/**
 * @typedef {object} Properties
 * @property {number} ratio
 * @property {HTMLFormElement|null} form
 * @property {number} [loadRequest]
 * @property {ImageBitmap|null} bitmap
 * @property {string|null} url
 * @property {HTMLImageElement|null} image
 * @property {number} maxWidth
 * @property {Crop|null} crop
 * @property {Drag|null} dragging
 * @property {number|null} [previewFrame]
 * @property {boolean} exported
 * @property {boolean} [exporting]
 * @property {HTMLInputElement} sourceTarget
 * @property {HTMLInputElement} outputTarget
 * @property {HTMLElement} errorTarget
 * @property {HTMLElement} editorTarget
 * @property {HTMLElement} stageTarget
 * @property {HTMLElement} areaTarget
 * @property {HTMLElement} statusTarget
 * @property {HTMLCanvasElement} previewTarget
 * @property {number} widthValue
 * @property {number} heightValue
 * @property {number} qualityValue
 * @property {string} tooSmallValue
 * @property {string} unreadableValue
 * @property {string} unsupportedValue
 * @property {string} sizeTextValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/** @type {Record<string, [number, number]>} */
const MOVES = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

// Shift turns = into + on most layouts.
/** @type {Record<string, number>} */
const RESIZES = { '+': 1, '=': 1, '-': -1, _: -1 };

/**
 * The 2D context of a canvas, which a canvas only lacks once it holds another kind.
 *
 * @param {HTMLCanvasElement} canvas
 */
const context2d = (canvas) => {
    const context = canvas.getContext('2d');

    if (context === null) {
        throw new Error('The canvas has no 2D context');
    }

    return context;
};

/*
 * Picks the part of an image to keep, at the aspect ratio of the output, and
 * puts it in the form at the output size, encoded as WebP.
 *
 * The crop is held in pixels of the image as it is oriented, and drawn over
 * it in percentages, so the area follows the image when the layout changes.
 * It never covers fewer pixels than the output, since images are never
 * enlarged, nor leaves the image.
 */
export default class extends BaseController {
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
        const request = this.loadRequest;
        const file = this.sourceTarget.files?.[0];

        if (!file) {
            return;
        }

        /** @type {ImageBitmap} */
        let bitmap;

        try {
            // Turned as the camera says, as the <img> shows it.
            bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            if (request === this.loadRequest) {
                this.fail(this.unreadableValue);
            }

            return;
        }

        // Another file was chosen, or the controller was cleared, meanwhile.
        if (request !== this.loadRequest) {
            bitmap.close();

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
        const image = document.createElement('img');
        image.className = 'block h-auto max-h-[55dvh] w-auto max-w-full';
        image.alt = '';
        image.src = this.url;
        this.stageTarget.prepend(image);
        this.image = image;

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

    /** @param {PointerEvent} event */
    startMove(event) {
        this.startDrag(event, 'move');
    }

    /** @param {PointerEvent} event */
    startResize(event) {
        // The handle sits inside the area, which would otherwise start a move.
        event.stopPropagation();
        this.startDrag(event, 'resize');
    }

    /**
     * @param {PointerEvent} event
     * @param {Drag['mode']} mode
     */
    startDrag(event, mode) {
        if (!this.crop || event.button !== 0) {
            return;
        }

        event.preventDefault();
        this.areaTarget.focus({ preventScroll: true });
        this.areaTarget.setPointerCapture(event.pointerId);
        this.dragging = { mode, x: event.clientX, y: event.clientY, crop: { ...this.crop } };
    }

    /** @param {PointerEvent} event */
    drag(event) {
        if (!this.dragging) {
            return;
        }

        const { bitmap } = this.loaded();
        // Pointer pixels into image pixels.
        const scale = bitmap.width / this.stageTarget.clientWidth;
        const dx = (event.clientX - this.dragging.x) * scale;
        const dy = (event.clientY - this.dragging.y) * scale;
        const start = this.dragging.crop;

        if (this.dragging.mode === 'move') {
            this.crop = { ...start, x: start.x + dx, y: start.y + dy };
        } else {
            // The top left corner stays put; the handle follows the pointer along the ratio.
            const widest = Math.min(this.maxWidth, bitmap.width - start.x, (bitmap.height - start.y) * this.ratio);
            const width = Math.min(widest, start.width + Math.max(dx, dy * this.ratio));
            this.crop = { ...start, width };
        }

        this.update();
    }

    /** @param {PointerEvent} event */
    endDrag(event) {
        if (this.dragging && this.areaTarget.hasPointerCapture(event.pointerId)) {
            this.areaTarget.releasePointerCapture(event.pointerId);
        }

        this.dragging = null;
    }

    /**
     * The arrows move the area and + and - resize it, by a hundredth of the
     * image per key, a tenth with Shift.
     *
     * @param {KeyboardEvent} event
     */
    nudge(event) {
        const move = MOVES[event.key];
        const resize = RESIZES[event.key];

        if (!this.crop || (!move && !resize)) {
            return;
        }

        event.preventDefault();
        const { bitmap, crop } = this.loaded();
        const step = bitmap.width / (event.shiftKey ? 10 : 100);

        if (move) {
            this.crop = { ...crop, x: crop.x + move[0] * step, y: crop.y + move[1] * step };
        } else {
            this.resizeAroundCentre(crop.width + resize * step);
        }

        this.update();

        if (resize) {
            this.announceSize();
        }
    }

    /** @param {number} width */
    resizeAroundCentre(width) {
        const { crop } = this.loaded();
        const centreX = crop.x + crop.width / 2;
        const centreY = crop.y + crop.width / this.ratio / 2;
        this.crop = { x: centreX - width / 2, y: centreY - width / this.ratio / 2, width };
    }

    announceSize() {
        const width = Math.round(this.loaded().crop.width);
        this.statusTarget.textContent = this.sizeTextValue
            .replace('%width%', String(width))
            .replace('%height%', String(Math.round(width / this.ratio)));
    }

    /* Drawing. */

    update() {
        this.clamp();
        const { bitmap, crop: { x, y, width } } = this.loaded();
        const height = width / this.ratio;
        const style = this.areaTarget.style;
        style.left = `${(x / bitmap.width) * 100}%`;
        style.top = `${(y / bitmap.height) * 100}%`;
        style.width = `${(width / bitmap.width) * 100}%`;
        style.height = `${(height / bitmap.height) * 100}%`;

        // Any change makes the crop already put in the form stale.
        this.outputTarget.value = '';
        this.exported = false;
        this.schedulePreview();
    }

    clamp() {
        const { bitmap, crop } = this.loaded();
        const width = Math.min(this.maxWidth, Math.max(this.widthValue, crop.width));
        const height = width / this.ratio;
        this.crop = {
            width,
            x: Math.min(bitmap.width - width, Math.max(0, crop.x)),
            y: Math.min(bitmap.height - height, Math.max(0, crop.y)),
        };
    }

    schedulePreview() {
        if (this.previewFrame) {
            return;
        }

        this.previewFrame = requestAnimationFrame(() => {
            this.previewFrame = null;

            if (!this.bitmap || !this.crop) {
                return;
            }

            const canvas = this.previewTarget;
            const context = context2d(canvas);
            const { x, y, width } = this.crop;
            context.imageSmoothingQuality = 'high';
            context.drawImage(this.bitmap, x, y, width, width / this.ratio, 0, 0, canvas.width, canvas.height);
        });
    }

    /* Sending the crop. */

    /** @param {SubmitEvent} event */
    async submit(event) {
        // Without an image, the required field has already stopped the form.
        if (!this.form || !this.crop || this.exported) {
            return;
        }

        // An export already running must not let the form go without its crop.
        event.preventDefault();

        if (this.exporting) {
            return;
        }

        this.exporting = true;
        const request = this.loadRequest;
        const submitter = event.submitter;

        try {
            const blob = await this.encode();

            // A new file owns the form now; this crop belongs to the old one.
            if (request !== this.loadRequest) {
                return;
            }

            if (!blob || blob.type !== 'image/webp') {
                this.fail(this.unsupportedValue);

                return;
            }

            const files = new DataTransfer();
            files.items.add(new File([blob], 'image.webp', { type: 'image/webp' }));
            this.outputTarget.files = files.files;
            this.exported = true;
            this.form.requestSubmit(submitter);
        } catch {
            if (request === this.loadRequest) {
                this.fail(this.unsupportedValue);
            }
        } finally {
            if (request === this.loadRequest) {
                this.exporting = false;
            }
        }
    }

    /**
     * Halves the crop until it is less than twice the output, then draws it
     * at the output size: one big step down would alias, and not every
     * browser honours imageSmoothingQuality.
     *
     * @returns {Promise<Blob|null>}
     */
    encode() {
        const { bitmap, crop: { x, y, width } } = this.loaded();
        let canvas = this.canvas(Math.round(width), Math.round(width / this.ratio));
        context2d(canvas).drawImage(bitmap, x, y, width, width / this.ratio, 0, 0, canvas.width, canvas.height);

        while (canvas.width / 2 >= this.widthValue) {
            canvas = this.scaled(canvas, Math.round(canvas.width / 2), Math.round(canvas.height / 2));
        }

        const output = this.scaled(canvas, this.widthValue, this.heightValue);

        return new Promise((resolve) => {
            output.toBlob(resolve, 'image/webp', this.qualityValue);
        });
    }

    /**
     * @param {HTMLCanvasElement} source
     * @param {number} width
     * @param {number} height
     */
    scaled(source, width, height) {
        const canvas = this.canvas(width, height);
        const context = context2d(canvas);
        context.imageSmoothingQuality = 'high';
        context.drawImage(source, 0, 0, width, height);

        return canvas;
    }

    /**
     * @param {number} width
     * @param {number} height
     */
    canvas(width, height) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        return canvas;
    }

    /* State. */

    /** The image being cropped and its crop, for the steps that only run while there is one. */
    loaded() {
        if (!this.bitmap || !this.crop) {
            throw new Error('No image is being cropped');
        }

        return { bitmap: this.bitmap, crop: this.crop };
    }

    /** @param {string} message */
    fail(message) {
        this.clear();
        this.sourceTarget.value = '';
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }

    clear() {
        // Invalidates any decoding or encoding that belongs to the old image.
        this.loadRequest = (this.loadRequest ?? 0) + 1;

        if (this.url) {
            URL.revokeObjectURL(this.url);
            this.url = null;
        }

        this.bitmap?.close();
        this.bitmap = null;
        this.crop = null;
        this.dragging = null;
        this.exported = false;
        this.exporting = false;
        this.outputTarget.value = '';
        this.image?.remove();
        this.image = null;
        this.editorTarget.hidden = true;
        this.errorTarget.hidden = true;
        this.errorTarget.textContent = '';
        this.statusTarget.textContent = '';
    }
}
