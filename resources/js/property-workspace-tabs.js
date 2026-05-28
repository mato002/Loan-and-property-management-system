/**
 * Workspace tab bar: distinguish horizontal scroll drags from intentional clicks,
 * and keep the active tab visible after Turbo frame swaps.
 */

const SCROLL_DRAG_THRESHOLD_PX = 8;
const NAV_SELECTOR = '.property-workspace-tabs-primary, .property-workspace-tabs-sub';

function bindScrollableTabNav(nav) {
    if (!(nav instanceof HTMLElement) || nav.dataset.workspaceTabsBound === '1') {
        return;
    }

    nav.dataset.workspaceTabsBound = '1';

    let activePointerId = null;
    let startX = 0;
    let startY = 0;
    let dragged = false;

    nav.addEventListener(
        'pointerdown',
        (event) => {
            if (event.button !== 0) {
                return;
            }
            activePointerId = event.pointerId;
            startX = event.clientX;
            startY = event.clientY;
            dragged = false;
        },
        { passive: true },
    );

    nav.addEventListener(
        'pointermove',
        (event) => {
            if (activePointerId !== event.pointerId) {
                return;
            }
            const dx = Math.abs(event.clientX - startX);
            const dy = Math.abs(event.clientY - startY);
            if (dx > SCROLL_DRAG_THRESHOLD_PX || dy > SCROLL_DRAG_THRESHOLD_PX) {
                dragged = true;
            }
        },
        { passive: true },
    );

    const resetPointer = () => {
        activePointerId = null;
    };

    nav.addEventListener('pointerup', resetPointer);
    nav.addEventListener('pointercancel', resetPointer);
    nav.addEventListener('pointerleave', (event) => {
        if (activePointerId === event.pointerId) {
            resetPointer();
        }
    });

    nav.addEventListener(
        'click',
        (event) => {
            if (!dragged) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            dragged = false;
        },
        true,
    );
}

function scrollActiveTabIntoView(nav) {
    if (!(nav instanceof HTMLElement)) {
        return;
    }

    const active = nav.querySelector('[aria-current="page"]');
    if (!(active instanceof HTMLElement)) {
        return;
    }

    try {
        active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'instant' });
    } catch {
        active.scrollIntoView({ inline: 'center', block: 'nearest' });
    }
}

export function setupPropertyWorkspaceTabs(root = document) {
    const scope = root instanceof HTMLElement ? root : document;

    scope.querySelectorAll(NAV_SELECTOR).forEach((nav) => {
        bindScrollableTabNav(nav);
        scrollActiveTabIntoView(nav);
    });
}

function bindWorkspaceTabListeners() {
    document.addEventListener('DOMContentLoaded', () => setupPropertyWorkspaceTabs(document));
    document.addEventListener('turbo:load', () => setupPropertyWorkspaceTabs(document));
    document.addEventListener('turbo:frame-load', (event) => {
        setupPropertyWorkspaceTabs(event.target instanceof HTMLElement ? event.target : document);
    });
}

bindWorkspaceTabListeners();
