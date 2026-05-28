/**
 * Workspace table row navigation and dropdown rebind after Turbo frame swaps.
 */

import { setupPropertyDropdownUi } from './property-dropdown-cleanup';

const interactiveSelector =
    'a, button, input, select, textarea, label, summary, details, [role="button"], [data-row-ignore-click]';

function workspaceScopeRoot(event) {
    const target = event?.target;
    if (target instanceof Element && target.id === 'property-main') {
        return target;
    }

    return document.getElementById('property-main') || document;
}

export function setupPropertyWorkspaceUi(event) {
    const scopeRoot = workspaceScopeRoot(event);

    scopeRoot.querySelectorAll('tr[data-row-href], article[data-row-href]').forEach((row) => {
        if (row.dataset.rowHrefBound === '1') {
            return;
        }

        const href = row.getAttribute('data-row-href');
        if (!href) {
            return;
        }

        row.dataset.rowHrefBound = '1';

        row.addEventListener('click', (clickEvent) => {
            if (clickEvent.target?.closest?.(interactiveSelector)) {
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
        });

        row.addEventListener('keydown', (keyEvent) => {
            if (keyEvent.key !== 'Enter' && keyEvent.key !== ' ') {
                return;
            }
            if (keyEvent.target?.closest?.(interactiveSelector)) {
                return;
            }
            keyEvent.preventDefault();
            if (typeof window.closeAllPropertyDropdowns === 'function') {
                window.closeAllPropertyDropdowns();
            }
            if (typeof window.visitPropertyMain === 'function') {
                window.visitPropertyMain(href);
            } else {
                window.location.href = href;
            }
        });
    });

    setupPropertyDropdownUi(scopeRoot);
}

function bindWorkspaceUiListeners() {
    document.addEventListener('DOMContentLoaded', setupPropertyWorkspaceUi);
    document.addEventListener('turbo:load', setupPropertyWorkspaceUi);
    document.addEventListener('turbo:frame-load', setupPropertyWorkspaceUi);
}

bindWorkspaceUiListeners();
