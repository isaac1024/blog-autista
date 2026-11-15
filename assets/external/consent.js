/*
 * Whether the reader agreed to load the content of each provider, as kept in
 * their browser: the provider, by its name, and the moment they agreed.
 * Nothing else is kept, and none of it ever leaves the browser. A browser
 * that keeps nothing, or refuses to, is taken as one that never agreed.
 */

const KEY = 'external-consent';

/**
 * @param {Storage | undefined} storage
 * @returns {Record<string, string>}
 */
const read = (storage) => {
    try {
        // Without storage, or with nothing kept, this throws: null has no
        // entries. A list or a text kept has entries of its own, by their
        // position, which no provider is named after.
        const kept = JSON.parse(String(/** @type {Storage} */ (storage).getItem(KEY)));

        return Object.fromEntries(Object.entries(kept).filter(([, moment]) => typeof moment === 'string'));
    } catch {
        return {};
    }
};

/**
 * @param {Storage | undefined} storage
 * @param {Record<string, string>} consents
 */
const write = (storage, consents) => {
    try {
        /** @type {Storage} */ (storage).setItem(KEY, JSON.stringify(consents));
    } catch {
        // A browser without storage, or that refuses to keep it, asks again
        // on the next page.
    }
};

/**
 * Whether the reader agreed to load the content of the provider.
 *
 * @param {Storage | undefined} storage
 * @param {string} provider
 */
export const hasConsent = (storage, provider) => Object.hasOwn(read(storage), provider);

/**
 * Keeps that the reader agreed to load the content of the provider, now.
 *
 * @param {Storage | undefined} storage
 * @param {string} provider
 * @param {Date} now
 */
export const grantConsent = (storage, provider, now) => {
    write(storage, { ...read(storage), [provider]: now.toISOString() });
};

/**
 * Forgets that the reader agreed to load the content of the provider.
 *
 * @param {Storage | undefined} storage
 * @param {string} provider
 */
export const revokeConsent = (storage, provider) => {
    const consents = read(storage);
    delete consents[provider];
    write(storage, consents);
};
