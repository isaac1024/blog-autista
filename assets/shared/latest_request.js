/**
 * One kind of request at a time, such as a search: starting one aborts the
 * one before, so only the latest answer is ever seen and the network drops
 * the others. Every one of them aborts with its owner too, such as the signal
 * of a controller that disconnects.
 */
export class LatestRequest {
    /** @type {AbortController|null} */
    #current;

    /** @param {AbortSignal} owner */
    constructor(owner) {
        this.owner = owner;
        this.#current = null;
        owner.addEventListener('abort', () => this.#current?.abort(), { once: true });
    }

    /** The signal of a new request, aborting the one before. */
    next() {
        this.#current?.abort();
        this.#current = new AbortController();

        if (this.owner.aborted) {
            this.#current.abort();
        }

        return this.#current.signal;
    }
}
