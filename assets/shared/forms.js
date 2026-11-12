/**
 * Changing a field from a script fires no input event, so tell its form watchers.
 *
 * @param {HTMLElement} element
 */
export function notifyForm(element) {
    element.dispatchEvent(new Event('input', { bubbles: true }));
}
