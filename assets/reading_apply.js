/*
 * Puts the size of the text and the theme the reader picked on the root of
 * the page before it paints, so it never flashes in the other theme. It is
 * the one script loaded in the head, blocking and plain, so it waits for
 * nothing; reading/settings.js keeps what it reads, under the same key, and
 * reading.js lets the reader change it.
 *
 * A value the stylesheet doesn't know changes nothing, so it is set as kept.
 *
 * It marks the root too, so what only works with scripts, such as the
 * button of the settings, shows from the first paint rather than moving
 * what is beside it once the scripts of the body run.
 */
document.documentElement.dataset.scripted = '';

try {
    const kept = JSON.parse(String(window.localStorage.getItem('reading')));

    // Nothing kept reads null, which has no settings: that throws, below.
    for (const [name, attribute] of [['size', 'textSize'], ['theme', 'theme']]) {
        if (typeof kept[name] === 'string') {
            document.documentElement.dataset[attribute] = kept[name];
        }
    }
} catch {
    // A browser that keeps nothing, or refuses to, reads as the system says.
}
