/**
 * Single authoritative workspace hydration lifecycle for #property-main.
 * Coalesces turbo:frame-load and turbo:load so Alpine, flash, tabs, and UI run once per navigation.
 */

import { closeAllPropertyDropdowns } from './property-dropdown-cleanup';
import { recoverPropertyScrollState } from './property-modal-manager';
import { setupPropertyPaymentReversal } from './property-payment-reversal';
import { setupPropertyWorkspaceTabs } from './property-workspace-tabs';
import { setupPropertyWorkspaceUi } from './property-workspace-ui';

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
        reconcilePropertyFrameWithBrowserUrl(frame, {
            force: source === 'popstate' || source.startsWith('pageshow'),
        });

        setupPropertyWorkspaceUi({ target: frame });

        if (isNewNavigation) {
            setupPropertyWorkspaceTabs(frame);
            scrollPropertyMainToTop(frame);
            lastScrollResetNavigationKey = navigationKey;
        }
        wirePropertyFrameNavigation(frame);
        wirePropertyFrameNavigation(document);

        if (window.Alpine?.initTree) {
            window.Alpine.initTree(frame);
        }
        setupPropertyPaymentReversal(frame);
    } finally {
        workspaceHydrating = false;
        syncHydratingWindowFlag();
        onHydrationComplete(frame);
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
