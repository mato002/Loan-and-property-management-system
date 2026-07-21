/**
 * Workspace table row navigation and dropdown rebind after Turbo frame swaps.
 */

import { setupPropertyDropdownUi } from './property-dropdown-cleanup';

const interactiveSelector =
    'a, button, input, select, textarea, label, summary, details, [role="button"], [data-row-ignore-click]';

const PROPERTY_ROW_NAV_ATTR = 'data-property-row-nav-bound';

function workspaceScopeRoot(event) {
    const target = event?.target;
    if (target instanceof Element && target.id === 'property-main') {
        return target;
    }

    return document.getElementById('property-main') || document;
}

function navigatePropertyRow(href) {
    if (!href) {
        return;
    }

    if (typeof window.closeAllPropertyDropdowns === 'function') {
        window.closeAllPropertyDropdowns();
    }

    if (typeof window.visitPropertyMain === 'function') {
        window.visitPropertyMain(href);
    } else {
        window.location.href = href;
    }
}

function bindPropertyRowNavigation(scopeRoot) {
    if (!(scopeRoot instanceof Element) || scopeRoot.getAttribute(PROPERTY_ROW_NAV_ATTR) === '1') {
        return;
    }

    scopeRoot.setAttribute(PROPERTY_ROW_NAV_ATTR, '1');

    scopeRoot.addEventListener('click', (clickEvent) => {
        const row = clickEvent.target?.closest?.('tr[data-row-href], article[data-row-href]');
        if (!row || !scopeRoot.contains(row)) {
            return;
        }
        if (clickEvent.target?.closest?.(interactiveSelector)) {
            return;
        }

        const href = row.getAttribute('data-row-href');
        if (!href) {
            return;
        }

        clickEvent.preventDefault();
        navigatePropertyRow(href);
    });

    scopeRoot.addEventListener('keydown', (keyEvent) => {
        if (keyEvent.key !== 'Enter' && keyEvent.key !== ' ') {
            return;
        }

        const row = keyEvent.target?.closest?.('tr[data-row-href], article[data-row-href]');
        if (!row || !scopeRoot.contains(row)) {
            return;
        }
        if (keyEvent.target?.closest?.(interactiveSelector)) {
            return;
        }

        const href = row.getAttribute('data-row-href');
        if (!href) {
            return;
        }

        keyEvent.preventDefault();
        navigatePropertyRow(href);
    });
}

function ensurePropertyRowNavigationOnBoot() {
    const frame = document.getElementById('property-main');
    if (frame instanceof HTMLElement) {
        bindPropertyRowNavigation(frame);
    }
}

export function setupPropertyWorkspaceUi(event) {
    const scopeRoot = workspaceScopeRoot(event);

    bindPropertyRowNavigation(scopeRoot);
    setupPropertyDropdownUi(scopeRoot);
}

document.addEventListener('DOMContentLoaded', ensurePropertyRowNavigationOnBoot);
document.addEventListener('turbo:load', ensurePropertyRowNavigationOnBoot);
