/**
 * Listings create — load publish editor inline without full Turbo navigation.
 */

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
    const slot = document.getElementById('listing-publish-slot');
    return slot?.getAttribute('data-listings-create-url') || '/property/listings/create';
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
        window.history.replaceState({}, '', url.toString());
    } catch {
        // ignore malformed URLs
    }
}

export async function openListingPublishPanel(url, unitId = null) {
    const slot = document.getElementById('listing-publish-slot');
    if (!(slot instanceof HTMLElement)) {
        window.visitPropertyMain?.(url);

        return;
    }

    slot.innerHTML = '<p class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-gray-900 dark:text-slate-400">Loading publish editor…</p>';

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
        slot.innerHTML = html;
        activateListingPublishScripts(slot);

        if (window.Alpine?.initTree) {
            window.Alpine.initTree(slot);
        }

        if (unitId) {
            highlightListingPublishRow(unitId);
            updateListingPublishUrl(unitId);
        }

        requestAnimationFrame(() => {
            slot.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    } catch (error) {
        console.error('[ListingPublish] load failed', error);
        slot.innerHTML = '<p class="rounded-xl border border-red-200 bg-red-50 px-4 py-6 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">Could not load the publish editor. Please try again.</p>';
    }
}

export function closeListingPublishPanel() {
    const slot = document.getElementById('listing-publish-slot');
    if (slot instanceof HTMLElement) {
        slot.innerHTML = '';
    }

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

document.addEventListener('DOMContentLoaded', () => {
    bindListingPublishInteractions(document);
    initListingPublishFromUrl();
});
document.addEventListener('turbo:load', () => {
    bindListingPublishInteractions(document);
    initListingPublishFromUrl();
});
document.addEventListener('turbo:frame-load', (event) => {
    bindListingPublishInteractions(event.target);
    initListingPublishFromUrl(event.target);
});

function initListingPublishFromUrl(root = document) {
    const scope = root instanceof Document ? document : root;
    const slot = scope.querySelector?.('#listing-publish-slot') ?? document.getElementById('listing-publish-slot');
    if (!(slot instanceof HTMLElement) || slot.childElementCount === 0) {
        return;
    }

    try {
        const unitId = new URL(window.location.href).searchParams.get('selected_unit');
        if (unitId) {
            highlightListingPublishRow(unitId);
        }
        requestAnimationFrame(() => {
            slot.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    } catch {
        // ignore malformed URLs
    }
}
