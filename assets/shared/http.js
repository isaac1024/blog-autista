/**
 * Reads a JSON endpoint, refusing HTTP errors just like network failures.
 * The endpoint's caller owns the shape of the decoded value.
 *
 * @param {URL} url
 * @param {AbortSignal} signal
 * @returns {Promise<unknown>}
 */
export async function fetchJson(url, signal) {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return response.json();
}

/**
 * The decoded answer of a JSON endpoint, or null when there is none: a
 * network failure, an HTTP error and an aborted request alike.
 *
 * @param {URL} url
 * @param {AbortSignal} signal
 * @returns {Promise<unknown>}
 */
export async function answerOf(url, signal) {
    try {
        return await fetchJson(url, signal);
    } catch {
        return null;
    }
}
