/**
 * The fields of a form as it would submit them, those joining it from
 * outside with a form attribute included, as one string to compare: two
 * snapshots differ when the form would send something else. Without the
 * form there is nothing to send.
 *
 * @param {HTMLFormElement | null} form
 */
export const snapshot = (form) => new URLSearchParams(
    [...new FormData(form ?? undefined)].map(([name, value]) => [name, String(value)]),
).toString();
