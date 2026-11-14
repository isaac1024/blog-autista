/*
 * The only script of the public site. Once its reader sees the page, it tells
 * the site, once: what it shows, by its kind and its id, such as a post, the
 * address of the page they came from, the campaign of the link, if any, and
 * the time zone of the browser, which says the country without looking the
 * reader up. It sets no cookie and keeps
 * nothing in the browser.
 *
 * A page prerendered, or opened in a tab never looked at, isn't seen, so it
 * waits; one shown again from the back-forward cache was already told.
 */
const script = document.querySelector('script[data-type][data-content][data-visit]');

if (script instanceof HTMLScriptElement) {
    // The script was found by both of them.
    const { type, content, visit } = /** @type {{type: string, content: string, visit: string}} */ (script.dataset);
    let sent = false;

    const send = () => {
        if (sent || ('prerendering' in document && document.prerendering) || document.visibilityState !== 'visible') {
            return;
        }

        sent = true;
        const campaign = new URLSearchParams(window.location.search);
        let zone = '';

        try {
            zone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch {
            // A browser that tells no time zone counts in no country.
        }

        const body = new URLSearchParams({
            type,
            content,
            referrer: document.referrer,
            source: campaign.get('utm_source') || '',
            medium: campaign.get('utm_medium') || '',
            zone,
        });

        if (!navigator.sendBeacon || !navigator.sendBeacon(visit, body)) {
            fetch(visit, { method: 'POST', body, keepalive: true, credentials: 'omit' }).catch(() => {});
        }
    };

    document.addEventListener('visibilitychange', send);
    document.addEventListener('prerenderingchange', send);
    send();
}
