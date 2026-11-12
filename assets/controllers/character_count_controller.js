import { Controller } from '@hotwired/stimulus';
import { countMessage } from '../form/character_count.js';

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
        this.outputTarget.textContent = countMessage(this.fieldTarget.value, {
            min: this.minValue,
            max: this.maxValue,
            short: this.shortValue,
            fits: this.fitsValue,
            long: this.longValue,
        });
    }
}
