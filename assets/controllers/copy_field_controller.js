import { Controller } from '@hotwired/stimulus';

/*
 * Copies the value of a read-only field and says so in a status the screen
 * reader announces. When the browser won't let it copy, the value is left
 * selected for the editor to copy it by hand.
 */
export default class extends Controller {
    static targets = ['value', 'status'];

    static values = {
        copied: String,
        failed: String,
    };

    select() {
        this.valueTarget.select();
    }

    async copy() {
        // Cleared first, so the same message is announced again on every copy.
        this.statusTarget.textContent = '';

        try {
            await navigator.clipboard.writeText(this.valueTarget.value);
            this.statusTarget.textContent = this.copiedValue;
        } catch {
            this.valueTarget.focus();
            this.select();
            this.statusTarget.textContent = this.failedValue;
        }
    }
}
