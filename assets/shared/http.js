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
