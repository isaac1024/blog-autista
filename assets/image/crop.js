/*
 * The geometry of cropping an image to the output of the blog: where the
 * crop starts, how the pointer and the keys move and resize it, how far it
 * may go, where it is drawn, and how it steps down to the output size.
 *
 * A crop is held in pixels of the image as it is oriented; its height
 * follows from the ratio of the output. It never covers fewer pixels than
 * the output, since images are never enlarged, nor leaves the image.
 */

/** @typedef {{width: number, height: number}} Size */

/**
 * @typedef {object} Crop
 * @property {number} x
 * @property {number} y
 * @property {number} width
 */

/**
 * What a crop is cut from and to: the image, the ratio of the output, and
 * the narrowest and widest a crop may be.
 *
 * @typedef {object} Frame
 * @property {Size} image
 * @property {number} ratio
 * @property {number} minWidth
 * @property {number} maxWidth
 */

/** Keys that move the crop, by one step each way. @type {Record<string, [number, number]>} */
const MOVES = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

/** Keys that resize it; Shift turns = into + on most layouts. @type {Record<string, number>} */
const RESIZES = { '+': 1, '=': 1, '-': -1, _: -1 };

/**
 * Whether an image has pixels enough for the output.
 *
 * @param {Size} image
 * @param {Size} output
 */
export const coversOutput = (image, output) => image.width >= output.width && image.height >= output.height;

/**
 * The frame of an image cropped to an output.
 *
 * @param {Size} image
 * @param {Size} output
 * @returns {Frame}
 */
export const frameOf = (image, output) => {
    const ratio = output.width / output.height;

    return { image, ratio, minWidth: output.width, maxWidth: Math.min(image.width, Math.floor(image.height * ratio)) };
};

/**
 * The largest crop that fits, centred.
 *
 * @param {Frame} frame
 * @returns {Crop}
 */
export const largestCrop = ({ image, ratio, maxWidth }) => ({
    x: (image.width - maxWidth) / 2,
    y: (image.height - maxWidth / ratio) / 2,
    width: maxWidth,
});

/**
 * The crop brought back within its bounds: no narrower than the output, no
 * wider than the image allows, and inside the image.
 *
 * @param {Crop} crop
 * @param {Frame} frame
 * @returns {Crop}
 */
export const clamp = (crop, { image, ratio, minWidth, maxWidth }) => {
    const width = Math.min(maxWidth, Math.max(minWidth, crop.width));

    return {
        width,
        x: Math.min(image.width - width, Math.max(0, crop.x)),
        y: Math.min(image.height - width / ratio, Math.max(0, crop.y)),
    };
};

/**
 * The crop dragged by the pointer, in pixels of the image: moved whole, or
 * resized from its handle, its top left corner staying put and its size
 * following the pointer along the ratio, never past the image.
 *
 * @param {Crop} start
 * @param {'move'|'resize'} mode
 * @param {{dx: number, dy: number}} delta
 * @param {Frame} frame
 * @returns {Crop}
 */
export const dragged = (start, mode, delta, frame) => DRAGS[mode](start, delta, frame);

/** @type {Record<'move'|'resize', (start: Crop, delta: {dx: number, dy: number}, frame: Frame) => Crop>} */
const DRAGS = {
    move: (start, { dx, dy }) => ({ ...start, x: start.x + dx, y: start.y + dy }),
    resize: (start, { dx, dy }, { image, ratio, maxWidth }) => {
        const widest = Math.min(maxWidth, image.width - start.x, (image.height - start.y) * ratio);

        return { ...start, width: Math.min(widest, start.width + Math.max(dx, dy * ratio)) };
    },
};

/**
 * The crop at a new width, around the same centre.
 *
 * @param {Crop} crop
 * @param {number} width
 * @param {number} ratio
 * @returns {Crop}
 */
export const resizedAroundCentre = (crop, width, ratio) => {
    const centreX = crop.x + crop.width / 2;
    const centreY = crop.y + crop.width / ratio / 2;

    return { x: centreX - width / 2, y: centreY - width / ratio / 2, width };
};

/**
 * What a key does to the crop: the arrows move it and + and - resize it
 * around its centre, by a hundredth of the image, a tenth with Shift. Null
 * for any other key.
 *
 * @param {Crop} crop
 * @param {{key: string, shiftKey: boolean}} press
 * @param {Frame} frame
 * @returns {{crop: Crop, resized: boolean}|null}
 */
export const nudged = (crop, { key, shiftKey }, { image, ratio }) => {
    const move = MOVES[key];
    const resize = RESIZES[key];
    const step = image.width / (shiftKey ? 10 : 100);

    if (move) {
        return { crop: { ...crop, x: crop.x + move[0] * step, y: crop.y + move[1] * step }, resized: false };
    }

    if (resize) {
        return { crop: resizedAroundCentre(crop, crop.width + resize * step, ratio), resized: true };
    }

    return null;
};

/**
 * Where the crop is drawn over the image, in percentages of it, so it
 * follows the image when the layout changes.
 *
 * @param {Crop} crop
 * @param {Frame} frame
 */
export const areaOf = ({ x, y, width }, { image, ratio }) => ({
    left: (x / image.width) * 100,
    top: (y / image.height) * 100,
    width: (width / image.width) * 100,
    height: (width / ratio / image.height) * 100,
});

/**
 * The size the crop is, in whole pixels, as the editor is told it.
 *
 * @param {Crop} crop
 * @param {number} ratio
 * @returns {Size}
 */
export const sizeOf = (crop, ratio) => {
    const width = Math.round(crop.width);

    return { width, height: Math.round(width / ratio) };
};

/**
 * The sizes the crop is drawn at on its way to the output: its own, then
 * halved while it stays at least twice the output, since one big step down
 * would alias and not every browser honours imageSmoothingQuality. The
 * output size itself comes last, from the smallest of these.
 *
 * @param {Crop} crop
 * @param {number} ratio
 * @param {number} outputWidth
 * @returns {Size[]}
 */
export const steps = (crop, ratio, outputWidth) => {
    const sizes = [{ width: Math.round(crop.width), height: Math.round(crop.width / ratio) }];
    let last = sizes[0];

    while (last.width / 2 >= outputWidth) {
        last = { width: Math.round(last.width / 2), height: Math.round(last.height / 2) };
        sizes.push(last);
    }

    return sizes;
};
