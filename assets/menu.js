/*
 * The main menu of the header, in line wherever it fits. The page comes with
 * it folded under its button, which works without scripts; this shows it in
 * line instead, in one row, while its entries fit beside the name of the
 * blog and the rest of the bar, and folds it again when they don't: as the
 * window narrows, or the reader makes the text bigger. The header tells
 * which, in data-menu, so the search shows its icon alone beside the menu in
 * line. Whether the menu is in line or folded, the bar keeps its height.
 */
for (const header of /** @type {NodeListOf<HTMLElement>} */ (document.querySelectorAll('[data-menu]'))) {
    const inline = header.querySelector('[data-menu-inline]');

    // A header with no menu has neither of its two ways of showing it.
    if (!(inline instanceof HTMLElement)) {
        continue;
    }

    const folded = /** @type {HTMLDetailsElement} */ (header.querySelector('[data-menu-folded]'));

    const list = /** @type {HTMLElement} */ (inline.firstElementChild);

    const arrange = () => {
        // Measured in line, where the folded button and the name of the
        // search leave room.
        header.dataset.menu = 'inline';
        inline.classList.remove('hidden');
        folded.classList.add('hidden');
        const fits = list.scrollWidth <= inline.clientWidth;

        header.dataset.menu = fits ? 'inline' : 'folded';
        inline.classList.toggle('hidden', !fits);
        folded.classList.toggle('hidden', fits);

        if (fits) {
            folded.open = false;
        }
    };

    // The bar changes size with the window and with the size of the text,
    // and the list in line with the size of the text alone.
    const observer = new ResizeObserver(arrange);
    observer.observe(header);
    observer.observe(list);
}
