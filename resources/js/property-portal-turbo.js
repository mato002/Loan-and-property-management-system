import * as Turbo from '@hotwired/turbo';
import { closeAllPropertyDropdowns } from './property-dropdown-cleanup';
import { recoverPropertyScrollState } from './property-modal-manager';
import { setupPropertyWorkspaceTabs } from './property-workspace-tabs';

const PROPERTY_MAIN_FRAME_ID = 'property-main';

/** Guard flag so frame redirects do not recurse through turbo:before-visit. */
let routingViaMainFrame = false;

/** Delay before showing the loading bar (avoids flash on fast navigations). */
const FRAME_LOADING_DELAY_MS = 160;
const WORKSPACE_LOADING_DELAY_MS = 120;
let frameLoadingTimer = null;
let workspaceLoadingTimer = null;

function getPropertyMainFrame() {
    const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);
    return frame instanceof HTMLElement ? frame : null;
}

function getWorkspaceLoadingEl() {
    return document.getElementById('property-workspace-loading');
}

function getGlobalProgressEl() {
    return document.getElementById('property-global-nav-progress');
}

function getWorkspaceErrorEl() {
    return document.getElementById('property-workspace-error');
}

function clearPropertyFrameLoading() {
    window.clearTimeout(frameLoadingTimer);
    frameLoadingTimer = null;
    getPropertyMainFrame()?.removeAttribute('data-property-loading');
}

function schedulePropertyFrameLoading(frame) {
    if (!frame || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    window.clearTimeout(frameLoadingTimer);
    frame.removeAttribute('data-property-loading');
    frameLoadingTimer = window.setTimeout(() => {
        frame.setAttribute('data-property-loading', '');
    }, FRAME_LOADING_DELAY_MS);
}

function showWorkspaceLoading() {
    window.clearTimeout(workspaceLoadingTimer);
    getGlobalProgressEl()?.setAttribute('data-active', '');
    workspaceLoadingTimer = window.setTimeout(() => {
        getWorkspaceLoadingEl()?.setAttribute('data-active', '');
    }, WORKSPACE_LOADING_DELAY_MS);
}

function hideWorkspaceLoading() {
    window.clearTimeout(workspaceLoadingTimer);
    workspaceLoadingTimer = null;
    getWorkspaceLoadingEl()?.removeAttribute('data-active');
    getGlobalProgressEl()?.removeAttribute('data-active');
}

function hideWorkspaceError() {
    const el = getWorkspaceErrorEl();
    if (!el) {
        return;
    }
    el.removeAttribute('data-active');
    el.innerHTML = '';
}

function showWorkspaceError(message, retryUrl = null) {
    hideWorkspaceLoading();
    clearPropertyFrameLoading();
    const el = getWorkspaceErrorEl();
    if (!el) {
        return;
    }
    const safeMessage = message || 'This page could not be loaded.';
    el.innerHTML = `
        <div class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm max-w-lg">
            <p class="text-sm font-semibold text-rose-800">Could not load workspace</p>
            <p class="mt-1 text-sm text-slate-600">${safeMessage}</p>
            ${retryUrl ? `<button type="button" class="mt-4 inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700" data-property-retry-url="${retryUrl}">Try again</button>` : ''}
        </div>
    `;
    el.setAttribute('data-active', '');
    el.querySelector('[data-property-retry-url]')?.addEventListener('click', () => {
        hideWorkspaceError();
        visitPropertyMainFrame(retryUrl);
    }, { once: true });
}

function routeNameMatches(current, pattern) {
    if (!current || !pattern) {
        return false;
    }
    if (pattern.endsWith('.*')) {
        const prefix = pattern.slice(0, -2);

        return current === prefix || current.startsWith(`${prefix}.`);
    }

    return current === pattern;
}

function syncPropertyHeaderTitle(title) {
    const headerTitle = document.querySelector('.property-topbar [data-property-header-title]');
    if (!(headerTitle instanceof HTMLElement)) {
        return;
    }
    const next = String(title || '').trim();
    if (next === '') {
        headerTitle.classList.add('hidden');
        headerTitle.textContent = '';
        return;
    }
    headerTitle.textContent = next;
    headerTitle.classList.remove('hidden');
}

function syncPropertyPortalNav(frame) {
    const routeEl = frame?.querySelector?.('#property-main-route');
    const routeName = routeEl?.dataset?.routeName ?? '';
    const pageTitle = routeEl?.dataset?.pageTitle ?? '';

    syncPropertyHeaderTitle(pageTitle);

    document.querySelectorAll('a[data-property-nav]').forEach((a) => {
        const raw = a.getAttribute('data-property-nav') || '';
        const patterns = raw.split('|').map((s) => s.trim()).filter(Boolean);
        const active = Boolean(routeName && patterns.some((p) => routeNameMatches(routeName, p)));
        if (active) {
            a.setAttribute('aria-current', 'page');
        } else {
            a.removeAttribute('aria-current');
        }
    });

    document.querySelectorAll('[data-property-nav-aggregate]').forEach((el) => {
        const raw = el.getAttribute('data-property-nav-aggregate') || '';
        const patterns = raw.split('|').map((s) => s.trim()).filter(Boolean);
        const active = Boolean(routeName && patterns.some((p) => routeNameMatches(routeName, p)));
        if (active) {
            el.setAttribute('data-section-active', '');
        } else {
            el.removeAttribute('data-section-active');
        }
    });
}

function scrollPropertyMainToTop(frame) {
    if (frame?.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    const main = frame.closest('main');
    if (main) {
        main.scrollTop = 0;
    }
}

function isPropertyShellUrl(url) {
    if (url.origin !== window.location.origin) {
        return false;
    }
    const path = url.pathname;

    return path.startsWith('/property/') || path.startsWith('/profile');
}

function shouldUseMainFrame(element) {
    if (!(element instanceof HTMLElement)) {
        return false;
    }
    if (element.dataset.turbo === 'false') {
        return false;
    }
    const frameTarget = element.getAttribute('data-turbo-frame');
    if (frameTarget === '_top') {
        return false;
    }

    return true;
}

/**
 * Navigate only the #property-main frame — sidebar/header/footer stay mounted.
 */
function visitPropertyMainFrame(url) {
    routingViaMainFrame = true;
    hideWorkspaceError();
    try {
        Turbo.visit(url, { frame: PROPERTY_MAIN_FRAME_ID });
    } finally {
        routingViaMainFrame = false;
    }
}

/** Shared helper for inline scripts (workspace row clicks, export menus, etc.). */
function visitPropertyUrl(href) {
    if (!href) {
        return;
    }
    try {
        const url = new URL(href, window.location.href);
        if (!isPropertyShellUrl(url)) {
            window.location.href = href;
            return;
        }
        visitPropertyMainFrame(url.toString());
    } catch {
        window.location.href = href;
    }
}

window.visitPropertyMain = visitPropertyUrl;

function wirePropertyFrameNavigation(root = document) {
    if (!(root instanceof Document || root instanceof Element)) {
        return;
    }

    const scope = root instanceof Document ? root : root;

    scope.querySelectorAll('a[href]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }
        if (!shouldUseMainFrame(link) || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        if (link.hasAttribute('data-turbo-frame')) {
            return;
        }
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
            return;
        }
        try {
            if (!isPropertyShellUrl(new URL(href, window.location.href))) {
                return;
            }
        } catch {
            return;
        }
        link.setAttribute('data-turbo-frame', PROPERTY_MAIN_FRAME_ID);
    });

    scope.querySelectorAll('form[action]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!shouldUseMainFrame(form) || form.target === '_blank') {
            return;
        }
        if (form.hasAttribute('data-turbo-frame')) {
            return;
        }
        try {
            if (!isPropertyShellUrl(new URL(form.action || window.location.href, window.location.href))) {
                return;
            }
        } catch {
            return;
        }
        form.setAttribute('data-turbo-frame', PROPERTY_MAIN_FRAME_ID);
    });
}

function ensurePropertyFormUsesMainFrame(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if (!shouldUseMainFrame(form) || form.target === '_blank') {
        return;
    }
    if (form.hasAttribute('data-turbo-frame')) {
        return;
    }
    try {
        if (!isPropertyShellUrl(new URL(form.action || window.location.href, window.location.href))) {
            return;
        }
    } catch {
        return;
    }
    form.setAttribute('data-turbo-frame', PROPERTY_MAIN_FRAME_ID);
}

let propertyFrameReconcileToken = null;

/**
 * If the browser URL and turbo-frame route metadata disagree (stale frame after a tab click),
 * re-fetch the frame once so workspace tabs like Leases render the correct page.
 */
function reconcilePropertyFrameWithBrowserUrl(frame) {
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }

    const frameRoute = frame.querySelector('#property-main-route')?.dataset?.routeName ?? '';
    if (frameRoute === '') {
        return;
    }

    let browserPath;
    try {
        browserPath = new URL(window.location.href).pathname.replace(/\/+$/, '');
    } catch {
        return;
    }

    const mismatches = [
        {
            pathSuffix: '/tenants/leases',
            expectedRoutes: ['property.tenants.leases', 'property.tenants.expiry'],
            staleRoutes: ['property.tenants.directory'],
        },
        {
            pathSuffix: '/tenants/directory',
            expectedRoutes: ['property.tenants.directory'],
            staleRoutes: ['property.tenants.leases'],
        },
    ];

    for (const rule of mismatches) {
        if (!browserPath.endsWith(rule.pathSuffix)) {
            continue;
        }
        if (rule.expectedRoutes.includes(frameRoute)) {
            propertyFrameReconcileToken = null;

            return;
        }
        if (!rule.staleRoutes.includes(frameRoute)) {
            return;
        }

        const retryKey = `${browserPath}:${frameRoute}`;
        if (propertyFrameReconcileToken === retryKey) {
            return;
        }
        propertyFrameReconcileToken = retryKey;
        visitPropertyMainFrame(window.location.href);

        return;
    }
}

function afterMainFrameSwap(frame) {
    closeAllPropertyDropdowns();
    clearPropertyFrameLoading();
    hideWorkspaceLoading();
    hideWorkspaceError();
    clearAllStuckPropertySubmitButtons();
    recoverPropertyScrollState('turbo:frame-load');
    syncPropertyPortalNav(frame);
    reconcilePropertyFrameWithBrowserUrl(frame);
    setupPropertyWorkspaceTabs(frame);
    scrollPropertyMainToTop(frame);
    wirePropertyFrameNavigation(frame);
    wirePropertyFrameNavigation(document);
    if (window.Alpine?.initTree) {
        window.Alpine.initTree(frame);
    }
    if (typeof window.__runSwalFlash === 'function') {
        queueMicrotask(() => {
            window.setTimeout(() => {
                window.__runSwalFlash(frame);
            }, 0);
        });
    }
}

document.addEventListener('turbo:before-fetch-request', () => {
    closeAllPropertyDropdowns();
});

document.addEventListener('turbo:frame-request-started', (event) => {
    const frame = event.target;
    if (frame instanceof HTMLElement) {
        schedulePropertyFrameLoading(frame);
        if (frame.id === PROPERTY_MAIN_FRAME_ID) {
            showWorkspaceLoading();
        }
    }
});

document.addEventListener('turbo:frame-request-finished', (event) => {
    const frame = event.target;
    if (frame instanceof HTMLElement && frame.id === PROPERTY_MAIN_FRAME_ID) {
        clearPropertyFrameLoading();
        hideWorkspaceLoading();
    }
});

document.addEventListener('turbo:before-frame-render', (event) => {
    if (!(event.target instanceof HTMLElement) || event.target.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    hideWorkspaceError();
});

function markPropertyFormSubmitting(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (!(btn instanceof HTMLButtonElement) || btn.dataset.propertySubmitting === '1') {
        return;
    }
    btn.dataset.propertySubmitting = '1';
    btn.dataset.propertySubmitDefaultLabel = btn.textContent?.trim() || 'Save';
    btn.disabled = true;
    btn.textContent = 'Saving…';
}

function clearPropertyFormSubmitting(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    const btn = form.querySelector('button[type="submit"][data-property-submitting="1"]');
    if (!(btn instanceof HTMLButtonElement)) {
        return;
    }
    btn.disabled = false;
    btn.textContent = btn.dataset.propertySubmitDefaultLabel || 'Save';
    delete btn.dataset.propertySubmitting;
    delete btn.dataset.propertySubmitDefaultLabel;
}

function clearAllStuckPropertySubmitButtons() {
    document.querySelectorAll('button[type="submit"][data-property-submitting="1"]').forEach((btn) => {
        if (!(btn instanceof HTMLButtonElement)) {
            return;
        }
        const form = btn.closest('form');
        if (form instanceof HTMLFormElement) {
            clearPropertyFormSubmitting(form);
        }
    });
}

document.addEventListener('turbo:submit-start', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    ensurePropertyFormUsesMainFrame(form);
    if (form.closest(`#${PROPERTY_MAIN_FRAME_ID}`)) {
        showWorkspaceLoading();
        queueMicrotask(() => markPropertyFormSubmitting(form));
    }
});

document.addEventListener('turbo:submit-end', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement) {
        clearPropertyFormSubmitting(form);
    }
    if (event.detail?.success && typeof window.__runSwalFlash === 'function') {
        const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);
        queueMicrotask(() => {
            window.setTimeout(() => {
                window.__runSwalFlash(frame || document);
            }, 0);
        });
    }
});

document.addEventListener('turbo:fetch-request-error', (event) => {
    const formSubmission = event.detail?.formSubmission;
    const form = formSubmission?.formElement;
    if (form instanceof HTMLFormElement) {
        clearPropertyFormSubmitting(form);
        if (window.Swal) {
            window.Swal.fire({
                icon: 'error',
                title: 'Could not save',
                text: 'The server did not respond. Check your connection and try again.',
                confirmButtonColor: '#dc2626',
            });
        }
    }

    const frame = event.target;
    if (frame instanceof HTMLElement && frame.id === PROPERTY_MAIN_FRAME_ID) {
        hideWorkspaceLoading();
        clearPropertyFrameLoading();
        showWorkspaceError('Network error while loading this page.', window.location.href);
    }
});

document.addEventListener('turbo:click', (event) => {
    const link = event.target?.closest?.('a[href]');
    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }
    if (!shouldUseMainFrame(link) || link.target === '_blank' || link.hasAttribute('download')) {
        return;
    }

    let url;
    try {
        url = new URL(link.href, window.location.href);
    } catch {
        return;
    }
    if (!isPropertyShellUrl(url)) {
        return;
    }

    closeAllPropertyDropdowns();
    event.preventDefault();
    visitPropertyMainFrame(url.toString());
});

document.addEventListener('turbo:frame-missing', (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }

    event.preventDefault();
    const responseUrl = event.detail?.response?.url;
    if (responseUrl) {
        visitPropertyMainFrame(responseUrl);
        return;
    }
    showWorkspaceError('The server response did not include workspace content.', window.location.href);
});

document.addEventListener('turbo:frame-load', (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    afterMainFrameSwap(frame);
});

/**
 * Last-resort safety net: block Drive visits to in-portal URLs.
 */
document.addEventListener('turbo:before-visit', (event) => {
    if (routingViaMainFrame) {
        return;
    }

    let url;
    try {
        url = new URL(event.detail.url, window.location.href);
    } catch {
        return;
    }
    if (!isPropertyShellUrl(url)) {
        return;
    }

    event.preventDefault();
    visitPropertyMainFrame(event.detail.url);
});

document.addEventListener('turbo:load', () => {
    recoverPropertyScrollState('turbo:load');
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    window.scrollTo(0, 0);
    wirePropertyFrameNavigation();
    const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);
    if (frame instanceof HTMLElement) {
        afterMainFrameSwap(frame);
    }
});

document.addEventListener('turbo:before-fetch-response', (event) => {
    const fetchResponse = event.detail?.fetchResponse;
    const status = fetchResponse?.response?.status;
    if (status === 401 || status === 403) {
        const url = fetchResponse?.response?.url;
        if (!url) {
            return;
        }

        event.preventDefault();
        visitPropertyMainFrame(url);

        return;
    }

    if (status >= 500) {
        hideWorkspaceLoading();
        clearPropertyFrameLoading();
        const formSubmission = event.detail?.formSubmission;
        const form = formSubmission?.formElement;
        if (form instanceof HTMLFormElement) {
            clearPropertyFormSubmitting(form);
            if (window.Swal) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Could not save',
                    text: 'The server encountered an error. Please try again or contact support.',
                    confirmButtonColor: '#dc2626',
                });
            }
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    recoverPropertyScrollState('dom:ready');
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    window.scrollTo(0, 0);
    wirePropertyFrameNavigation();
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        recoverPropertyScrollState('pageshow:bfcache');
    }
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        recoverPropertyScrollState('visibility:visible');
    }
});
