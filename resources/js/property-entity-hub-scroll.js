/**
 * Keep entity-hub tab switches (property / landlord / tenant 360) anchored in view
 * instead of jumping the workspace scroll position.
 */

import { notePendingWorkspaceNavigation } from './property-frame-reconciliation';

const ENTITY_HUB_SELECTOR = '[data-property-entity-hub]';
const ENTITY_HUB_NAV_SELECTOR = `${ENTITY_HUB_SELECTOR} nav[aria-label="Entity sections"]`;

function normalizePathname(pathname) {
    return String(pathname || '').replace(/\/+$/, '');
}

/**
 * @returns {{ pathname: string, search: string, tab: string } | null}
 */
function parseNavigationKey(key) {
    const raw = String(key || '').trim();
    if (raw === '') {
        return null;
    }

    try {
        const url = new URL(raw, window.location.origin);

        return {
            pathname: normalizePathname(url.pathname),
            search: url.search,
            tab: url.searchParams.get('tab') || '',
        };
    } catch {
        return null;
    }
}

/**
 * Same page, query changed, and the frame renders an entity hub (tab strip).
 */
export function isEntityHubTabOnlyNavigation(previousKey, currentKey, frame) {
    if (!(frame instanceof HTMLElement) || !frame.querySelector(ENTITY_HUB_SELECTOR)) {
        return false;
    }

    const previous = parseNavigationKey(previousKey);
    const current = parseNavigationKey(currentKey);
    if (!previous || !current) {
        return false;
    }

    if (previous.pathname !== current.pathname) {
        return false;
    }

    if (previous.search === current.search) {
        return false;
    }

    return previous.tab !== current.tab || current.tab !== '';
}

function propertyWorkspaceMain(frame) {
    const fromFrame = frame?.closest?.('main');
    if (fromFrame instanceof HTMLElement) {
        return fromFrame;
    }

    const byId = document.getElementById('property-workspace-main');

    return byId instanceof HTMLElement ? byId : null;
}

/**
 * Scroll #property-workspace-main so the entity hub tabs sit just below the shell header.
 */
export function scrollPropertyEntityHubIntoView(frame) {
    const main = propertyWorkspaceMain(frame);
    const hub = frame?.querySelector?.(ENTITY_HUB_SELECTOR);
    if (!(main instanceof HTMLElement) || !(hub instanceof HTMLElement)) {
        return false;
    }

    const header = document.getElementById('property-shell-header');
    const headerOffset = header instanceof HTMLElement ? header.offsetHeight : 0;
    const padding = 12;
    const mainRect = main.getBoundingClientRect();
    const hubRect = hub.getBoundingClientRect();
    const delta = hubRect.top - mainRect.top - headerOffset - padding;
    const target = Math.max(0, main.scrollTop + delta);

    main.scrollTop = target;

    return true;
}

function scrollActiveEntityHubTabIntoView(nav) {
    if (!(nav instanceof HTMLElement)) {
        return;
    }

    const active = nav.querySelector('[aria-current="page"]');
    if (!(active instanceof HTMLElement)) {
        return;
    }

    const navRect = nav.getBoundingClientRect();
    const activeRect = active.getBoundingClientRect();
    const padding = 4;
    const fullyVisible =
        activeRect.left >= navRect.left + padding && activeRect.right <= navRect.right - padding;

    if (fullyVisible) {
        return;
    }

    try {
        active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'instant' });
    } catch {
        active.scrollIntoView({ inline: 'center', block: 'nearest' });
    }
}

function bindEntityHubTabNav(nav) {
    if (!(nav instanceof HTMLElement) || nav.dataset.entityHubTabsBound === '1') {
        return;
    }

    nav.dataset.entityHubTabsBound = '1';

    nav.addEventListener(
        'pointerdown',
        (event) => {
            const tabLink = event.target?.closest?.('a[href]');
            if (tabLink instanceof HTMLAnchorElement && tabLink.href) {
                notePendingWorkspaceNavigation(tabLink.href);
            }
        },
        { passive: true },
    );

    scrollActiveEntityHubTabIntoView(nav);
}

export function setupPropertyEntityHubTabs(root = document) {
    const scope = root instanceof HTMLElement ? root : document;

    scope.querySelectorAll(ENTITY_HUB_NAV_SELECTOR).forEach((nav) => {
        bindEntityHubTabNav(nav);
    });
}
