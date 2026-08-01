/**
 * List-page create/edit modals — survive Turbo #property-main swaps.
 * Header CTAs use data-property-modal-open; capture-phase fallback when Alpine @click is stale.
 */

export function openPropertyPageModal(host, key) {
    if (!(host instanceof HTMLElement) || !key || !window.Alpine?.$data) {
        return false;
    }

    try {
        const data = window.Alpine.$data(host);
        if (!data || !Object.prototype.hasOwnProperty.call(data, key)) {
            return false;
        }

        data[key] = true;

        return true;
    } catch {
        return false;
    }
}

let delegatedBound = false;

function handlePropertyPageModalOpen(event) {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const button = target.closest('[data-property-modal-open]');
    if (!(button instanceof HTMLElement)) {
        return;
    }

    const host = button.closest('[data-property-page-modals]');
    if (!(host instanceof HTMLElement)) {
        return;
    }

    const key = button.getAttribute('data-property-modal-open');
    if (!key) {
        return;
    }

    openPropertyPageModal(host, key);
}

/** Capture-phase opener — registered before property-modal-manager backdrop guard. */
export function bindPropertyPageModalTriggers() {
    if (delegatedBound) {
        return;
    }
    delegatedBound = true;

    document.addEventListener('click', handlePropertyPageModalOpen, true);
}

/** Ensure page modal Alpine roots are initialized inside #property-main. */
export function ensurePropertyPageModalsInitialized(root = document) {
    if (!window.Alpine?.initTree) {
        return;
    }

    const scope = root instanceof Document ? root : root;
    const hosts = scope instanceof Document
        ? scope.querySelectorAll('#property-main [data-property-page-modals]')
        : scope.querySelectorAll('[data-property-page-modals]');

    hosts.forEach((host) => {
        if (!(host instanceof HTMLElement)) {
            return;
        }

        try {
            if (window.Alpine.$data(host)) {
                return;
            }
        } catch {
            // not initialized yet
        }

        window.Alpine.initTree(host);
    });
}

/** Close outgoing page modal flags before Turbo replaces #property-main. */
export function resetPageModalFlagsBeforeFrameSwap(frame) {
    if (!(frame instanceof HTMLElement) || frame.id !== 'property-main') {
        return;
    }

    if (!window.Alpine?.$data) {
        return;
    }

    frame.querySelectorAll('[data-property-page-modals]').forEach((host) => {
        try {
            const data = window.Alpine.$data(host);
            if (!data) {
                return;
            }

            Object.keys(data).forEach((key) => {
                if (key.startsWith('show') && typeof data[key] === 'boolean') {
                    data[key] = false;
                }
            });
        } catch {
            // ignore teardown errors on partially initialized trees
        }
    });

    try {
        sessionStorage.removeItem('property.leases.createFormOpen');
    } catch {
        // ignore private mode
    }
}

bindPropertyPageModalTriggers();

document.addEventListener('alpine:initialized', () => {
    ensurePropertyPageModalsInitialized();
});
