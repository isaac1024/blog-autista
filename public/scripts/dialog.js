/**
 * Progressive enhancement for the shared modal component.
 *
 * Uses the native <dialog> element, so focus trapping, the Escape key and the
 * inert background come from the platform. This script only opens the dialog
 * from its trigger and closes it when the backdrop is clicked.
 *
 * The guard keeps the listener unique when several modals share the page and
 * therefore the script tag.
 */
(() => {
    const root = document.documentElement;

    if (root.dataset.dialogEnhanced === 'true') {
        return;
    }

    root.dataset.dialogEnhanced = 'true';

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const trigger = event.target.closest('[data-dialog-open]');

        if (trigger instanceof HTMLElement) {
            const dialog = document.getElementById(trigger.dataset.dialogOpen ?? '');

            if (dialog instanceof HTMLDialogElement && !dialog.open) {
                dialog.showModal();

                const autofocus = dialog.querySelector('[data-dialog-autofocus]');

                if (autofocus instanceof HTMLElement) {
                    autofocus.focus();
                }
            }

            return;
        }

        if (!(event.target instanceof HTMLDialogElement) || !event.target.hasAttribute('data-dialog')) {
            return;
        }

        const bounds = event.target.getBoundingClientRect();
        const outside = event.clientX < bounds.left
            || event.clientX > bounds.right
            || event.clientY < bounds.top
            || event.clientY > bounds.bottom;

        if (outside) {
            event.target.close();
        }
    });
})();
