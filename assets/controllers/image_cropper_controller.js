import { Controller } from '@hotwired/stimulus';
import {
    areaOf, clamp, coversOutput, dragged, frameOf, largestCrop, nudged, sizeOf, steps,
} from '../image/crop.js';

const PREVIEW_WIDTH = 480;

/** @typedef {import('../image/crop.js').Crop} Crop */
/** @typedef {import('../image/crop.js').Frame} Frame */

/**
 * @typedef {object} Drag
 * @property {'move'|'resize'} mode
 * @property {number} x
 * @property {number} y
 * @property {Crop} crop
 */

/**
 * The image being cropped: decoded, framed for the output, and the crop of it.
 *
 * @typedef {object} Cropping
 * @property {ImageBitmap} bitmap
 * @property {Frame} frame
 * @property {Crop} crop
 */

/**
 * @typedef {object} Properties
 * @property {object} [loadRequest]
 * @property {Cropping|null} cropping
 * @property {string|null} url
 * @property {HTMLImageElement|null} image
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

/**
 * The 2D context of a canvas, which a canvas only lacks once it holds
 * another kind, and these never do.
 *
 * @param {HTMLCanvasElement} canvas
 */
const context2d = (canvas) => /** @type {CanvasRenderingContext2D} */ (canvas.getContext('2d'));

/*
 * Picks the part of an image to keep, at the aspect ratio of the output, and
 * puts it in the form at the output size, encoded as WebP. The geometry of
 * the crop is image/crop.js; this controller reads the pointer and the keys,
 * and draws.
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
        this.previewTarget.width = PREVIEW_WIDTH;
        this.previewTarget.height = Math.round(PREVIEW_WIDTH * this.heightValue / this.widthValue);
    }

    disconnect() {
        this.clear();
    }

    async load() {
        this.clear();
        const request = this.loadRequest;
        // A file input always has its list of files.
        const file = /** @type {FileList} */ (this.sourceTarget.files)[0];

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

        if (!coversOutput(bitmap, this.output())) {
            bitmap.close();
            this.fail(this.tooSmallValue);

            return;
        }

        this.url = URL.createObjectURL(file);
        // Only an image with a file is valid markup, so it exists while there is one.
        const image = document.createElement('img');
        image.className = 'block h-auto max-h-[55dvh] w-auto max-w-full';
        image.alt = '';
        image.src = this.url;
        this.stageTarget.prepend(image);
        this.image = image;

        const frame = frameOf({ width: bitmap.width, height: bitmap.height }, this.output());
        this.cropping = { bitmap, frame, crop: largestCrop(frame) };
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
        if (!this.cropping || event.button !== 0) {
            return;
        }

        event.preventDefault();
        this.areaTarget.focus({ preventScroll: true });
        this.areaTarget.setPointerCapture(event.pointerId);
        this.dragging = { mode, x: event.clientX, y: event.clientY, crop: { ...this.cropping.crop } };
    }

    /** @param {PointerEvent} event */
    drag(event) {
        if (!this.dragging) {
            return;
        }

        const cropping = this.loaded();
        // Pointer pixels into image pixels.
        const scale = cropping.frame.image.width / this.stageTarget.clientWidth;
        const { mode, x, y, crop } = this.dragging;

        cropping.crop = dragged(crop, mode, { dx: (event.clientX - x) * scale, dy: (event.clientY - y) * scale }, cropping.frame);
        this.update();
    }

    /* The browser lets go of the pointer itself once it is up or cancelled. */
    endDrag() {
        this.dragging = null;
    }

    /**
     * The arrows move the area and + and - resize it; a resize is told.
     *
     * @param {KeyboardEvent} event
     */
    nudge(event) {
        if (!this.cropping) {
            return;
        }

        const nudge = nudged(this.cropping.crop, event, this.cropping.frame);

        if (nudge === null) {
            return;
        }

        event.preventDefault();
        this.cropping.crop = nudge.crop;
        this.update();

        if (nudge.resized) {
            this.announceSize();
        }
    }

    announceSize() {
        const { crop, frame } = this.loaded();
        const { width, height } = sizeOf(crop, frame.ratio);

        this.statusTarget.textContent = this.sizeTextValue
            .replace('%width%', String(width))
            .replace('%height%', String(height));
    }

    /* Drawing. */

    update() {
        const cropping = this.loaded();
        cropping.crop = clamp(cropping.crop, cropping.frame);
        const area = areaOf(cropping.crop, cropping.frame);
        const style = this.areaTarget.style;
        style.left = `${area.left}%`;
        style.top = `${area.top}%`;
        style.width = `${area.width}%`;
        style.height = `${area.height}%`;

        // Any change makes the crop already put in the form stale.
        this.outputTarget.value = '';
        this.exported = false;
        this.schedulePreview();
    }

    schedulePreview() {
        if (this.previewFrame) {
            return;
        }

        this.previewFrame = requestAnimationFrame(() => {
            this.previewFrame = null;

            // The image was cleared meanwhile.
            if (!this.cropping) {
                return;
            }

            const { bitmap, crop: { x, y, width }, frame } = this.cropping;
            const canvas = this.previewTarget;
            const context = context2d(canvas);
            context.imageSmoothingQuality = 'high';
            context.drawImage(bitmap, x, y, width, width / frame.ratio, 0, 0, canvas.width, canvas.height);
        });
    }

    /* Sending the crop. */

    /** @param {SubmitEvent} event */
    async submit(event) {
        const form = this.element.closest('form');

        // Without an image, the required field has already stopped the form.
        if (event.target !== form || !this.cropping || this.exported) {
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
            // The submission came from this very form.
            /** @type {HTMLFormElement} */ (event.target).requestSubmit(submitter);
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
     * Draws the crop at each of its steps down, then at the output size.
     *
     * @returns {Promise<Blob|null>}
     */
    encode() {
        const { bitmap, crop, frame } = this.loaded();
        const [first, ...halved] = steps(crop, frame.ratio, this.widthValue);
        let canvas = this.canvas(first.width, first.height);
        context2d(canvas).drawImage(bitmap, crop.x, crop.y, crop.width, crop.width / frame.ratio, 0, 0, canvas.width, canvas.height);

        for (const size of halved) {
            canvas = this.scaled(canvas, size.width, size.height);
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

    /** The size the crop is put in the form at. */
    output() {
        return { width: this.widthValue, height: this.heightValue };
    }

    /** The image being cropped, for the steps that only run while there is one. */
    loaded() {
        return /** @type {Cropping} */ (this.cropping);
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
        this.loadRequest = {};

        if (this.url) {
            URL.revokeObjectURL(this.url);
            this.url = null;
        }

        this.cropping?.bitmap.close();
        this.cropping = null;
        this.dragging = null;
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
