/**
 * Workspace tab bar: distinguish horizontal scroll drags from intentional clicks,
 * and keep the active tab visible after Turbo frame swaps.
 */

import { notePendingWorkspaceNavigation } from './property-frame-reconciliation';

/** Horizontal movement must exceed this before a gesture counts as scroll-drag. */
const HORIZONTAL_DRAG_THRESHOLD_PX = 16;
/** Nav scrollLeft must change by at least this to suppress the click (real scroll). */
const SCROLL_DELTA_THRESHOLD_PX = 3;
/** Vertical wobble below this never cancels a tab click on horizontal strips. */
const VERTICAL_WOBBLE_MAX_PX = 28;
const NAV_SELECTOR = '.property-workspace-tabs-primary, .property-workspace-tabs-sub';

/**
 * @param {HTMLElement} nav
 * @param {number} dx
 * @param {number} dy
 * @param {number} scrollDelta
 */
function shouldSuppressTabClick(nav, dx, dy, scrollDelta) {
    if (scrollDelta >= SCROLL_DELTA_THRESHOLD_PX) {
        return true;
    }

    if (dx < HORIZONTAL_DRAG_THRESHOLD_PX) {
        return false;
    }

    if (dy >= VERTICAL_WOBBLE_MAX_PX) {
        return true;
    }

    return dx > dy * 1.35;
}

function isTabNavScrolling(nav) {
    return nav instanceof HTMLElement && nav.dataset.workspaceTabsScrolling === '1';
}

function bindScrollableTabNav(nav) {
    if (!(nav instanceof HTMLElement) || nav.dataset.workspaceTabsBound === '1') {
        return;
    }

    nav.dataset.workspaceTabsBound = '1';

    /** @type {number | undefined} */
    let scrollIdleTimer;

    nav.addEventListener(
        'scroll',
        () => {
            nav.dataset.workspaceTabsScrolling = '1';
            window.clearTimeout(scrollIdleTimer);
            scrollIdleTimer = window.setTimeout(() => {
                delete nav.dataset.workspaceTabsScrolling;
            }, 180);
        },
        { passive: true },
    );

    /** @type {{ pointerId: number, startX: number, startY: number, startScrollLeft: number } | null} */
    let gesture = null;
    let suppressNextClick = false;

    nav.addEventListener(
        'pointerdown',
        (event) => {
            if (event.button !== 0) {
                return;
            }

            const tabLink = event.target?.closest?.('a.property-workspace-tab, a.property-workspace-subtab');
            if (tabLink instanceof HTMLAnchorElement && tabLink.href) {
                notePendingWorkspaceNavigation(tabLink.href);
            }

            suppressNextClick = false;
            gesture = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                startScrollLeft: nav.scrollLeft,
            };
        },
        { passive: true },
    );

    nav.addEventListener(
        'pointermove',
        (event) => {
            if (!gesture || gesture.pointerId !== event.pointerId) {
                return;
            }
            // Passive tracking only — decision happens on pointerup so small moves never stick.
        },
        { passive: true },
    );

    const finishGesture = (event) => {
        if (!gesture || gesture.pointerId !== event.pointerId) {
            return;
        }

        const dx = Math.abs(event.clientX - gesture.startX);
        const dy = Math.abs(event.clientY - gesture.startY);
        const scrollDelta = Math.abs(nav.scrollLeft - gesture.startScrollLeft);

        suppressNextClick = shouldSuppressTabClick(nav, dx, dy, scrollDelta);
        gesture = null;
    };

    nav.addEventListener('pointerup', finishGesture);
    nav.addEventListener('pointercancel', finishGesture);

    nav.addEventListener(
        'click',
        (event) => {
            if (isTabNavScrolling(nav)) {
                const tabLink = event.target?.closest?.('a.property-workspace-tab, a.property-workspace-subtab');
                if (tabLink instanceof HTMLAnchorElement) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                }

                return;
            }

            if (!suppressNextClick) {
                return;
            }

            const tabLink = event.target?.closest?.('a.property-workspace-tab, a.property-workspace-subtab');
            if (!(tabLink instanceof HTMLAnchorElement)) {
                suppressNextClick = false;

                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            suppressNextClick = false;
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

export function setupPropertyWorkspaceTabs(root = document) {
    const scope = root instanceof HTMLElement ? root : document;

    scope.querySelectorAll(NAV_SELECTOR).forEach((nav) => {
        bindScrollableTabNav(nav);
        scrollActiveTabIntoView(nav);
    });
}

// Hydration is scheduled once per navigation from property-workspace-hydration.js.
