/**
 * Listings — open Photos & listing details in a modal (not full-page).
 */

let listingPublishLoadToken = 0;

function activateListingPublishScripts(root) {
    if (!(root instanceof Element)) {
        return;
    }

    root.querySelectorAll('script').forEach((oldScript) => {
        const script = document.createElement('script');
        [...oldScript.attributes].forEach((attr) => {
            script.setAttribute(attr.name, attr.value);
        });
        script.textContent = oldScript.textContent;
        oldScript.replaceWith(script);
    });
}

function getListingPublishModal() {
    return document.getElementById('listing-publish-modal');
}

function getListingPublishSlot() {
    return document.getElementById('listing-publish-slot');
}

function highlightListingPublishRow(unitId) {
    const table = document.getElementById('vacant-roster')?.querySelector('tbody');
    if (!(table instanceof HTMLElement)) {
        return;
    }

    table.querySelectorAll('tr[data-listing-unit-id]').forEach((row) => {
        const active = Boolean(unitId) && row.getAttribute('data-listing-unit-id') === String(unitId);
        row.classList.toggle('bg-blue-50/80', active);
        row.classList.toggle('dark:bg-blue-950/30', active);
        row.classList.toggle('ring-1', active);
        row.classList.toggle('ring-inset', active);
        row.classList.toggle('ring-blue-200/80', active);
        row.classList.toggle('dark:ring-blue-800/60', active);
    });
}

function listingPublishBaseUrl() {
    const modal = getListingPublishModal();

    return modal?.getAttribute('data-listings-create-url') || '/property/listings/create';
}

function listingPublishPanelUrl(unitId) {
    return `/property/listings/vacant/${encodeURIComponent(String(unitId))}/publish-panel`;
}

function updateListingPublishUrl(unitId) {
    try {
        const base = listingPublishBaseUrl();
        const url = new URL(base, window.location.origin);
        if (unitId) {
            url.searchParams.set('selected_unit', String(unitId));
        } else {
            url.searchParams.delete('selected_unit');
        }

        const onCreatePage = window.location.pathname.includes('/listings/create');
        if (onCreatePage) {
            window.history.replaceState({}, '', url.toString());
        }
    } catch {
        // ignore malformed URLs
    }
}

function showListingPublishModal() {
    const modal = getListingPublishModal();
    if (!(modal instanceof HTMLElement)) {
        return;
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('overflow-hidden');
}

function hideListingPublishModal() {
    const modal = getListingPublishModal();
    if (!(modal instanceof HTMLElement)) {
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('overflow-hidden');
}

export async function openListingPublishPanel(url, unitId = null) {
    const slot = getListingPublishSlot();
    if (!(slot instanceof HTMLElement)) {
        window.visitPropertyMain?.(url);

        return;
    }

    const token = ++listingPublishLoadToken;
    showListingPublishModal();
    slot.innerHTML = '<p class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-gray-900 dark:text-slate-400">Loading photos &amp; listing details…</p>';

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Could not load editor (${response.status}).`);
        }

        const html = await response.text();
        if (token !== listingPublishLoadToken) {
            return;
        }

        slot.innerHTML = html;
        activateListingPublishScripts(slot);

        if (window.Alpine?.initTree) {
            window.Alpine.initTree(slot);
        }

        if (unitId) {
            highlightListingPublishRow(unitId);
            updateListingPublishUrl(unitId);
        }
    } catch (error) {
        if (token !== listingPublishLoadToken) {
            return;
        }
        console.error('[ListingPublish] load failed', error);
        slot.innerHTML = '<p class="rounded-xl border border-red-200 bg-red-50 px-4 py-6 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">Could not load the publish editor. Please try again.</p>';
    }
}

export function closeListingPublishPanel() {
    listingPublishLoadToken += 1;
    const slot = getListingPublishSlot();
    if (slot instanceof HTMLElement) {
        slot.innerHTML = '';
    }

    hideListingPublishModal();
    highlightListingPublishRow(null);
    updateListingPublishUrl(null);
}

function bindListingPublishInteractions(root = document) {
    const scope = root instanceof Document ? root : root;

    scope.querySelectorAll?.('[data-listing-publish]:not([data-listing-publish-wired])').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        link.setAttribute('data-listing-publish-wired', '1');
        link.setAttribute('data-property-form-modal', 'off');
    });
}

document.addEventListener('click', (event) => {
    const closeTrigger = event.target?.closest?.('[data-listing-publish-close]');
    if (closeTrigger) {
        event.preventDefault();
        closeListingPublishPanel();

        return;
    }

    const link = event.target?.closest?.('[data-listing-publish]');
    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const unitId = link.getAttribute('data-listing-unit-id');
    void openListingPublishPanel(link.href, unitId);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const modal = getListingPublishModal();
    if (!(modal instanceof HTMLElement) || modal.classList.contains('hidden')) {
        return;
    }

    event.preventDefault();
    closeListingPublishPanel();
});

document.addEventListener('DOMContentLoaded', () => {
    bindListingPublishInteractions(document);
    initListingPublishFromUrl();
});
document.addEventListener('turbo:load', () => {
    bindListingPublishInteractions(document);
    initListingPublishFromUrl();
});
document.addEventListener('turbo:frame-load', (event) => {
    const frame = event.target;
    if (frame instanceof HTMLElement && frame.id === 'property-main') {
        const unitId = (() => {
            try {
                return new URL(window.location.href).searchParams.get('selected_unit');
            } catch {
                return null;
            }
        })();
        const onCreate = Boolean(frame.querySelector('#vacant-roster'));
        if (!unitId || !onCreate) {
            closeListingPublishPanel();
        }
    }

    bindListingPublishInteractions(frame);
    initListingPublishFromUrl(frame);
});

function initListingPublishFromUrl(root = document) {
    const scope = root instanceof Document ? document : root;
    const onCreatePage =
        Boolean(scope.querySelector?.('#vacant-roster')) ||
        Boolean(document.getElementById('vacant-roster')) ||
        window.location.pathname.includes('/listings/create');

    if (!onCreatePage) {
        return;
    }

    try {
        const unitId = new URL(window.location.href).searchParams.get('selected_unit');
        if (!unitId) {
            return;
        }

        const modal = getListingPublishModal();
        const slot = getListingPublishSlot();
        if (!(modal instanceof HTMLElement) || !(slot instanceof HTMLElement)) {
            return;
        }

        const openForUnit = slot.querySelector?.(`[data-listing-unit-id="${CSS.escape(String(unitId))}"]`);
        if (!modal.classList.contains('hidden') && openForUnit) {
            highlightListingPublishRow(unitId);

            return;
        }

        void openListingPublishPanel(listingPublishPanelUrl(unitId), unitId);
    } catch {
        // ignore malformed URLs
    }
}
