import { Controller } from '@hotwired/stimulus';

/**
 * @typedef {object} Properties
 * @property {HTMLInputElement} fieldTarget
 * @property {HTMLElement} outputTarget
 * @property {number} minValue
 * @property {number} maxValue
 * @property {string} shortValue
 * @property {string} fitsValue
 * @property {string} longValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/*
 * Counts the characters of a field against a recommended length and says,
 * in words, whether the text falls short, fits or runs long.
 */
export default class extends BaseController {
    static targets = ['field', 'output'];

    static values = {
        min: Number,
        max: Number,
        short: String,
        fits: String,
        long: String,
    };

    connect() {
        this.update();
    }

    update() {
        const count = [...this.fieldTarget.value.trim()].length;
        let message = this.fitsValue;

        if (count < this.minValue) {
            message = this.shortValue;
        } else if (count > this.maxValue) {
            message = this.longValue;
        }

        this.outputTarget.textContent = message
            .replace('%count%', String(count))
            .replace('%min%', String(this.minValue))
            .replace('%max%', String(this.maxValue));
    }
}
