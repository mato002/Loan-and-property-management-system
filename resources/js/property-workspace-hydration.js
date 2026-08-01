/**
 * Single authoritative workspace hydration lifecycle for #property-main.
 * Coalesces turbo:frame-load and turbo:load so Alpine, flash, tabs, and UI run once per navigation.
 */

import { closeAllPropertyDropdowns } from './property-dropdown-cleanup';
import { ensurePropertyFormModalHost } from './property-form-modal';
import { recoverPropertyScrollState } from './property-modal-manager';
import { setupPropertyPaymentReversal } from './property-payment-reversal';
import { setupPropertyWorkspaceTabs } from './property-workspace-tabs';
import { setupPropertyWorkspaceUi } from './property-workspace-ui';

import { bootPropertyDashboard } from './property-dashboard';
import { ensurePropertyPageModalsInitialized } from './property-page-modals';

export const PROPERTY_MAIN_FRAME_ID = 'property-main';
const HYDRATION_GEN_ATTR = 'data-property-hydration-gen';

let hydrationGeneration = 0;
let lastHydratedGeneration = null;
let pendingHydrationGen = null;
let workspaceHydrating = false;
/** @type {string|null} */
let lastScrollResetNavigationKey = null;

function propertyNavigationKey() {
    try {
        const url = new URL(window.location.href);

        return `${url.pathname.replace(/\/+$/, '')}${url.search}`;
    } catch {
        return '';
    }
}

export function isPropertyWorkspaceHydrating() {
    return workspaceHydrating || pendingHydrationGen !== null;
}

/**
 * Bind Alpine on #property-main after Turbo swaps (inline modals, x-data CTAs).
 */
export function hydratePropertyMainAlpine(frame) {
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    if (!window.Alpine?.initTree) {
        return;
    }

    requestAnimationFrame(() => {
        ensurePropertyPageModalsInitialized(frame);
    });
}

function syncHydratingWindowFlag() {
    window.__propertyWorkspaceHydrating = isPropertyWorkspaceHydrating();
}

function propertyNavDebug(payload) {
    if (window.__PROPERTY_DEBUG_NAV !== true) {
        return;
    }
    console.debug('[PropertyNav:hydration]', payload);
}

function getFrameRouteMeta(frame) {
    const routeEl = frame?.querySelector?.('#property-main-route');

    return {
        routeName: routeEl?.dataset?.routeName ?? '',
        pageTitle: routeEl?.dataset?.pageTitle ?? '',
    };
}

function readFrameHydrationGen(frame) {
    if (!(frame instanceof HTMLElement)) {
        return 0;
    }

    const raw = frame.getAttribute(HYDRATION_GEN_ATTR);

    return raw ? Number(raw) : 0;
}

/**
 * Call from turbo:before-frame-render when #property-main is about to swap.
 */
export function bumpPropertyHydrationGeneration(frame) {
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }

    hydrationGeneration += 1;
    frame.setAttribute(HYDRATION_GEN_ATTR, String(hydrationGeneration));
    propertyNavDebug({
        action: 'bump-generation',
        generation: hydrationGeneration,
        frameId: frame.id,
    });
}

function assignInitialHydrationGeneration(frame) {
    if (readFrameHydrationGen(frame) > 0) {
        return readFrameHydrationGen(frame);
    }

    hydrationGeneration += 1;
    frame.setAttribute(HYDRATION_GEN_ATTR, String(hydrationGeneration));

    return hydrationGeneration;
}

function shouldSkipHydration(frame, source, generation) {
    if (lastHydratedGeneration === generation) {
        propertyNavDebug({
            action: 'skip',
            source,
            generation,
            frameId: frame.id,
            route: getFrameRouteMeta(frame).routeName,
            path: window.location.pathname,
            reason: 'already-hydrated-generation',
        });

        return true;
    }

    if (pendingHydrationGen === generation) {
        propertyNavDebug({
            action: 'skip',
            source,
            generation,
            frameId: frame.id,
            route: getFrameRouteMeta(frame).routeName,
            path: window.location.pathname,
            reason: 'hydration-already-scheduled',
        });

        return true;
    }

    return false;
}

function runDeferredHydrationTasks(frame, hooks) {
    const {
        reconcilePropertyFrameWithBrowserUrl = () => {},
        wirePropertyFrameNavigation = () => {},
        setupPropertyWorkspaceTabs: setupTabs = () => {},
        setupPropertyPaymentReversal: setupReversal = () => {},
        onHydrationComplete = () => {},
    } = hooks;

    const navigationKey = propertyNavigationKey();
    const isNewNavigation = navigationKey !== '' && navigationKey !== lastScrollResetNavigationKey;

    if (isNewNavigation) {
        setupTabs(frame);
        lastScrollResetNavigationKey = navigationKey;
    }

    wirePropertyFrameNavigation(frame);
    reconcilePropertyFrameWithBrowserUrl(frame, { force: false });
    setupReversal(frame);
    ensurePropertyFormModalHost();
    bootPropertyDashboard(frame);
    onHydrationComplete(frame);
}

function scheduleDeferredHydrationTasks(frame, hooks) {
    const run = () => runDeferredHydrationTasks(frame, hooks);

    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(run, { timeout: 120 });

        return;
    }

    window.setTimeout(run, 0);
}

/**
 * Workspace hydration body — callers must pass through schedulePropertyWorkspaceHydration.
 */
export function runPropertyWorkspaceHydration(frame, source, hooks = {}) {
    const {
        clearPropertyFrameLoading = () => {},
        hideWorkspaceLoading = () => {},
        hideWorkspaceError = () => {},
        clearAllStuckPropertySubmitButtons = () => {},
        syncPropertyPortalNav = () => {},
        reconcilePropertyFrameWithBrowserUrl = () => {},
        scrollPropertyMainToTop = () => {},
        wirePropertyFrameNavigation = () => {},
        onHydrationComplete = () => {},
    } = hooks;

    const { routeName } = getFrameRouteMeta(frame);

    propertyNavDebug({
        action: 'run',
        source,
        generation: readFrameHydrationGen(frame),
        frameId: frame.id,
        route: routeName,
        path: window.location.pathname,
    });

    workspaceHydrating = true;

    try {
        closeAllPropertyDropdowns();
        clearPropertyFrameLoading();
        hideWorkspaceLoading();
        hideWorkspaceError();
        clearAllStuckPropertySubmitButtons();
        recoverPropertyScrollState(source);

        const navigationKey = propertyNavigationKey();
        const isNewNavigation = navigationKey !== '' && navigationKey !== lastScrollResetNavigationKey;

        syncPropertyPortalNav(frame);
        setupPropertyWorkspaceUi({ target: frame });

        if (isNewNavigation) {
            scrollPropertyMainToTop(frame);
        }

        hydratePropertyMainAlpine(frame);

        scheduleDeferredHydrationTasks(frame, {
            reconcilePropertyFrameWithBrowserUrl,
            wirePropertyFrameNavigation,
            setupPropertyWorkspaceTabs,
            setupPropertyPaymentReversal,
            onHydrationComplete,
        });
    } finally {
        workspaceHydrating = false;
        syncHydratingWindowFlag();
    }
}

/**
 * Schedule hydration once per frame generation; safe to call from turbo:frame-load and turbo:load.
 */
export function schedulePropertyWorkspaceHydration(frame, source, hooks = {}) {
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }

    const generation = readFrameHydrationGen(frame) || assignInitialHydrationGeneration(frame);

    if (shouldSkipHydration(frame, source, generation)) {
        // Hydration coalescing must never skip Alpine — list-page modals break without re-init.
        queueMicrotask(() => {
            hydratePropertyMainAlpine(frame);
        });

        return;
    }

    pendingHydrationGen = generation;
    syncHydratingWindowFlag();

    queueMicrotask(() => {
        pendingHydrationGen = null;
        syncHydratingWindowFlag();

        if (lastHydratedGeneration === generation) {
            propertyNavDebug({
                action: 'skip',
                source,
                generation,
                frameId: frame.id,
                route: getFrameRouteMeta(frame).routeName,
                path: window.location.pathname,
                reason: 'coalesced-duplicate',
            });

            return;
        }

        lastHydratedGeneration = generation;
        runPropertyWorkspaceHydration(frame, source, hooks);
    });
}

/**
 * Full-page / bfcache revisits: allow the next hydrate even if generation unchanged.
 */
export function resetPropertyHydrationGuard(reason = 'manual') {
    lastHydratedGeneration = null;
    pendingHydrationGen = null;
    if (reason === 'popstate' || reason.startsWith('pageshow')) {
        lastScrollResetNavigationKey = null;
    }
    syncHydratingWindowFlag();
    propertyNavDebug({ action: 'reset-guard', reason });
}
