import * as Turbo from '@hotwired/turbo';
import { isPropertyFormModalLink } from './property-form-modal';
import { closeAllPropertyDropdowns } from './property-dropdown-cleanup';
import { recoverPropertyScrollState } from './property-modal-manager';
import { wireAutoFilterForms } from './property-auto-filter';
import { resetPropertyFlashDedupe, schedulePropertyFlash } from './property-flash-lifecycle';
import {
    notePendingWorkspaceNavigation,
    reconcilePropertyFrameWithBrowserUrl as runPropertyFrameReconciliation,
    resetPropertyFrameReconcileToken,
    scheduleDeferredPropertyFrameReconciliation,
} from './property-frame-reconciliation';
import {
    bumpPropertyHydrationGeneration,
    PROPERTY_MAIN_FRAME_ID,
    resetPropertyHydrationGuard,
    schedulePropertyWorkspaceHydration,
} from './property-workspace-hydration';

/** Guard flag so frame redirects do not recurse through turbo:before-visit. */
let routingViaMainFrame = false;

/** Avoid reload loops when the shell is already being restored. */
let propertyPortalShellRecoveryInFlight = false;

/** Delay before dimming the frame — keep high so fast Turbo swaps feel instant. */
const FRAME_LOADING_DELAY_MS = 600;
const WORKSPACE_LOADING_FAILSAFE_MS = 120_000;
let frameLoadingTimer = null;
let workspaceLoadingFailsafeTimer = null;

function isBypassTurboUrl(url) {
    const path = url.pathname.toLowerCase();
    const params = url.searchParams;

    if (params.has('export') || params.has('download') || params.has('print')) {
        return true;
    }

    const format = (params.get('format') || '').toLowerCase();
    if (['csv', 'xls', 'xlsx', 'pdf', 'word', 'doc'].includes(format)) {
        return true;
    }

    if (path.includes('/export') || path.includes('/print') || path.includes('/download')) {
        return true;
    }

    return /\.(csv|xls|xlsx|pdf|doc|docx)$/i.test(path);
}

function isBypassTurboLink(element) {
    if (!(element instanceof HTMLAnchorElement)) {
        return false;
    }

    if (element.dataset.turbo === 'false') {
        return true;
    }

    if (element.hasAttribute('download') || element.target === '_blank') {
        return true;
    }

    const href = element.getAttribute('href');
    if (! href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return false;
    }

    try {
        return isBypassTurboUrl(new URL(href, window.location.href));
    } catch {
        return false;
    }
}

function wirePropertyBypassLinks(root = document) {
    const scope = root instanceof Document ? root : root;

    scope.querySelectorAll('a[href]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement) || ! isBypassTurboLink(link)) {
            return;
        }

        link.setAttribute('data-turbo', 'false');
        link.removeAttribute('data-turbo-frame');
    });
}

function showPropertyFrameSkeleton(frame) {
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }

    hidePropertyFrameSkeleton(frame);

    const template = document.getElementById('property-frame-skeleton-template');
    const skeleton = template?.content?.firstElementChild?.cloneNode(true);
    if (skeleton instanceof HTMLElement) {
        frame.appendChild(skeleton);
    }
}

function hidePropertyFrameSkeleton(frame) {
    if (!(frame instanceof HTMLElement)) {
        return;
    }

    frame.querySelector('[data-property-frame-skeleton]')?.remove();
}

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
    const frame = getPropertyMainFrame();
    frame?.removeAttribute('data-property-loading');
    if (frame) {
        hidePropertyFrameSkeleton(frame);
    }
}

function schedulePropertyFrameLoading(frame) {
    if (!frame || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    window.clearTimeout(frameLoadingTimer);
    frame.removeAttribute('data-property-loading');
    frameLoadingTimer = window.setTimeout(() => {
        frame.setAttribute('data-property-loading', '');
        showPropertyFrameSkeleton(frame);
    }, FRAME_LOADING_DELAY_MS);
}

function showWorkspaceLoading() {
    getGlobalProgressEl()?.setAttribute('data-active', '');
    window.clearTimeout(workspaceLoadingFailsafeTimer);
    workspaceLoadingFailsafeTimer = window.setTimeout(() => {
        hideWorkspaceLoading();
        clearPropertyFrameLoading();
    }, WORKSPACE_LOADING_FAILSAFE_MS);
}

function hideWorkspaceLoading() {
    window.clearTimeout(workspaceLoadingFailsafeTimer);
    workspaceLoadingFailsafeTimer = null;
    getWorkspaceLoadingEl()?.removeAttribute('data-active');
    getGlobalProgressEl()?.removeAttribute('data-active');
}

function hideWorkspaceError() {
    const el = getWorkspaceErrorEl();
    const main = document.getElementById('property-workspace-main');
    main?.removeAttribute('data-workspace-error-active');
    if (!el) {
        return;
    }
    el.removeAttribute('data-active');
    el.innerHTML = '';
}

function isIgnorableFrameFetchError(detail) {
    const error = detail?.error;
    if (error?.name === 'AbortError') {
        return true;
    }
    const message = String(error?.message || '').toLowerCase();
    if (message.includes('abort') || message.includes('cancel')) {
        return true;
    }

    return false;
}

function showWorkspaceError(message, retryUrl = null) {
    hideWorkspaceLoading();
    clearPropertyFrameLoading();
    const el = getWorkspaceErrorEl();
    const main = document.getElementById('property-workspace-main');
    if (!el) {
        return;
    }
    const safeMessage = message || 'This page could not be loaded.';
    el.innerHTML = `
        <div class="pointer-events-auto rounded-2xl border border-rose-200 bg-white p-5 shadow-lg max-w-lg w-full">
            <p class="text-sm font-semibold text-rose-800">Could not load workspace</p>
            <p class="mt-1 text-sm text-slate-600">${safeMessage}</p>
            ${retryUrl ? `<button type="button" class="mt-4 inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700" data-property-retry-url="${retryUrl}">Try again</button>` : ''}
        </div>
    `;
    el.setAttribute('data-active', '');
    main?.setAttribute('data-workspace-error-active', '');
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
        // Workspace tabs use server-side tabIsActive (route + query); JS pattern match would double-highlight (e.g. Leases + Expiry).
        if (a.classList.contains('property-workspace-tab') || a.classList.contains('property-workspace-subtab')) {
            return;
        }

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

function isPropertyPortalGuestUrl(url) {
    const path = url.pathname.replace(/\/+$/, '').toLowerCase();

    return /\/property\/(tenant|landlord)\/login$/i.test(path);
}

function isPropertyPortalGuestPage(url = window.location.href) {
    if (document.documentElement?.getAttribute('data-pwa-context') === 'portal-guest') {
        return true;
    }

    try {
        return isPropertyPortalGuestUrl(new URL(url, window.location.href));
    } catch {
        return false;
    }
}

function isPropertyShellUrl(url) {
    if (url.origin !== window.location.origin) {
        return false;
    }

    if (isPropertyPortalGuestUrl(url)) {
        return false;
    }

    const path = url.pathname;

    return path.startsWith('/property/') || path.startsWith('/profile');
}

/** Full portal layout (head assets + sidebar/header + #property-main) must be present. */
function hasPropertyPortalShell() {
    return document.documentElement?.getAttribute('data-pwa-context') === 'portal'
        && document.getElementById('property-shell-header') instanceof HTMLElement
        && document.getElementById(PROPERTY_MAIN_FRAME_ID) instanceof HTMLElement;
}

/** Tailwind is bundled in app.css — when it drops, sidebar stays width:0 and the page looks "unstyled". */
function isPortalStylesheetActive() {
    const probe = document.createElement('div');
    probe.className = 'hidden';
    probe.style.position = 'absolute';
    probe.style.pointerEvents = 'none';
    document.documentElement.appendChild(probe);
    const active = window.getComputedStyle(probe).display === 'none';
    probe.remove();

    return active;
}

/** Print header must stay hidden on screen; visible chrome means layout CSS dropped. */
function isPropertyPrintChromeVisibleOnScreen() {
    if (window.matchMedia('print').matches) {
        return false;
    }

    const el = document.querySelector('.property-print-only');
    if (!(el instanceof HTMLElement)) {
        return false;
    }

    const style = window.getComputedStyle(el);

    return style.display !== 'none' && style.visibility !== 'hidden';
}

function isBuiltAppStylesheetLoaded() {
    const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).filter((link) => {
        if (!(link instanceof HTMLLinkElement)) {
            return false;
        }

        return /\/build\/assets\/app-[^/?#]+\.css(?:[?#]|$)/i.test(link.href);
    });

    if (links.length === 0) {
        return true;
    }

    return links.every((link) => {
        if (link.disabled) {
            return false;
        }

        try {
            return link.sheet instanceof CSSStyleSheet;
        } catch {
            return false;
        }
    });
}

function isPortalPresentationHealthy() {
    if (!isPortalStylesheetActive()) {
        return false;
    }

    if (isPropertyPrintChromeVisibleOnScreen()) {
        return false;
    }

    if (document.readyState === 'complete' && !isBuiltAppStylesheetLoaded()) {
        return false;
    }

    return true;
}

function recoverPropertyPortalDocument(url = window.location.href) {
    if (propertyPortalShellRecoveryInFlight) {
        return;
    }
    propertyPortalShellRecoveryInFlight = true;
    window.location.assign(url);
}

function ensurePropertyPortalShell(url = window.location.href) {
    if (isPropertyPortalGuestPage(url)) {
        return true;
    }

    if (!isPropertyShellUrl(new URL(url, window.location.href))) {
        return true;
    }
    if (!hasPropertyPortalShell() || !isPortalPresentationHealthy()) {
        recoverPropertyPortalDocument(url);

        return false;
    }

    return true;
}

function isPortalAuthFailureResponse(status, responseUrl) {
    if (status === 401 || status === 403 || status === 419) {
        return true;
    }

    if (!responseUrl) {
        return false;
    }

    try {
        const path = new URL(responseUrl, window.location.href).pathname.replace(/\/+$/, '');

        return path === '/login' || path.endsWith('/login');
    } catch {
        return false;
    }
}

const PORTAL_SHELL_WATCH_MS = 90_000;
let portalShellWatchTimer = null;
let portalStylesheetRecoveryBound = false;

function bindPortalStylesheetRecovery() {
    if (portalStylesheetRecoveryBound) {
        return;
    }
    portalStylesheetRecoveryBound = true;

    document.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
        if (!(link instanceof HTMLLinkElement)) {
            return;
        }

        if (!/\/build\/assets\/app-[^/?#]+\.css(?:[?#]|$)/i.test(link.href)) {
            return;
        }

        link.addEventListener('error', () => {
            if (isPropertyPortalGuestPage()) {
                return;
            }

            if (!isPropertyShellUrl(new URL(window.location.href, window.location.href))) {
                return;
            }

            recoverPropertyPortalDocument(window.location.href);
        });
    });
}

function startPortalShellWatch() {
    if (document.documentElement?.getAttribute('data-pwa-context') !== 'portal') {
        return;
    }

    if (isPropertyPortalGuestPage()) {
        return;
    }

    if (portalShellWatchTimer !== null) {
        return;
    }

    portalShellWatchTimer = window.setInterval(() => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        try {
            if (isPropertyShellUrl(new URL(window.location.href, window.location.href))) {
                ensurePropertyPortalShell();
            }
        } catch {
            // ignore malformed URLs
        }
    }, PORTAL_SHELL_WATCH_MS);
}

function bootPropertyPortalShellGuards() {
    if (isPropertyPortalGuestPage()) {
        return;
    }

    bindPortalStylesheetRecovery();
    startPortalShellWatch();

    try {
        if (isPropertyShellUrl(new URL(window.location.href, window.location.href))) {
            ensurePropertyPortalShell();
        }
    } catch {
        // ignore malformed URLs
    }
}

/** Hub shell paths redirect server-side — Turbo frame visits need the concrete tab URL. */
function resolvePropertyNavUrl(href) {
    try {
        const url = new URL(href, window.location.href);
        const path = url.pathname.replace(/\/+$/, '');

        if (path.endsWith('/property/listings')) {
            url.pathname = `${path}/create`;

            return url.toString();
        }
    } catch {
        // ignore malformed URLs
    }

    return href;
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
    if (!ensurePropertyPortalShell(url)) {
        return;
    }

    routingViaMainFrame = true;
    hideWorkspaceError();
    try {
        let forceFresh = false;
        try {
            const parsed = new URL(url, window.location.href);
            forceFresh = parsed.searchParams.has('selected_unit');
        } catch {
            // ignore malformed URLs
        }

        Turbo.visit(resolvePropertyNavUrl(url), {
            frame: PROPERTY_MAIN_FRAME_ID,
            action: 'advance',
            ...(forceFresh ? { shouldCacheSnapshot: () => false } : {}),
        });
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

    wirePropertyBypassLinks(scope);

    scope.querySelectorAll('a[href]:not([data-property-nav-wired])').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }
        if (isBypassTurboLink(link) || ! shouldUseMainFrame(link) || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        if (link.hasAttribute('data-turbo-frame')) {
            link.setAttribute('data-property-nav-wired', '1');
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
        link.setAttribute('data-property-nav-wired', '1');
    });

    scope.querySelectorAll('form[action]:not([data-property-nav-wired])').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!shouldUseMainFrame(form) || form.target === '_blank') {
            return;
        }
        if (form.hasAttribute('data-turbo-frame')) {
            form.setAttribute('data-property-nav-wired', '1');
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
        form.setAttribute('data-property-nav-wired', '1');
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

/** Re-fetch #property-main when browser URL and frame route metadata disagree (Phase 2B). */
function reconcilePropertyFrameWithBrowserUrl(frame, options = {}) {
    runPropertyFrameReconciliation(frame, visitPropertyMainFrame, options);
}

const workspaceHydrationHooks = {
    clearPropertyFrameLoading,
    hideWorkspaceLoading,
    hideWorkspaceError,
    clearAllStuckPropertySubmitButtons,
    syncPropertyPortalNav,
    reconcilePropertyFrameWithBrowserUrl,
    scrollPropertyMainToTop,
    wirePropertyFrameNavigation,
    onHydrationComplete: (frame) => {
        wireAutoFilterForms(frame);
        if (frame instanceof HTMLElement && frame.querySelector('[data-swal-flash]')) {
            schedulePropertyFlash(frame, 'hydration:complete', { force: true });
        }
    },
};

function afterMainFrameSwap(frame, source) {
    schedulePropertyWorkspaceHydration(frame, source, workspaceHydrationHooks);
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
        const fetchResponse = event.detail?.fetchResponse;
        const status = fetchResponse?.response?.status;
        if (!fetchResponse || (typeof status === 'number' && status >= 200 && status < 400)) {
            hideWorkspaceError();
        }
    }
});

document.addEventListener('turbo:before-frame-render', (event) => {
    if (!(event.target instanceof HTMLElement) || event.target.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    hideWorkspaceError();
    hideWorkspaceLoading();
    clearPropertyFrameLoading();
    bumpPropertyHydrationGeneration(event.target);
});

document.addEventListener('turbo:frame-render', (event) => {
    if (!(event.target instanceof HTMLElement) || event.target.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    hideWorkspaceLoading();
    clearPropertyFrameLoading();
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
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    clearPropertyFormSubmitting(form);

    if (!form.closest(`#${PROPERTY_MAIN_FRAME_ID}`)) {
        return;
    }

    hideWorkspaceLoading();
    clearPropertyFrameLoading();
    recoverPropertyScrollState('turbo:submit-end');

    if (event.detail?.success) {
        resetPropertyFlashDedupe('turbo:submit-end');
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
        if (isIgnorableFrameFetchError(event.detail)) {
            return;
        }
        showWorkspaceError('Network error while loading this page.', window.location.href);
    }
});

document.addEventListener('turbo:click', (event) => {
    if (window.PropertyModalManager?.getStack?.()?.length > 0) {
        const target = event.target;
        if (!(target instanceof Element) || !target.closest('[data-property-modal-panel]')) {
            event.preventDefault();
            event.stopPropagation();

            return;
        }
    }

    const link = event.target?.closest?.('a[href]');
    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }
    if (isBypassTurboLink(link) || ! shouldUseMainFrame(link) || link.target === '_blank' || link.hasAttribute('download')) {
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

    if (isPropertyFormModalLink(link)) {
        return;
    }

    if (link.classList.contains('property-workspace-tab') || link.classList.contains('property-workspace-subtab')) {
        notePendingWorkspaceNavigation(url.toString());
    }

    closeAllPropertyDropdowns();
    showWorkspaceLoading();
    event.preventDefault();
    visitPropertyMainFrame(resolvePropertyNavUrl(url.toString()));
});

document.addEventListener('turbo:frame-missing', (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }

    event.preventDefault();
    const responseUrl = event.detail?.response?.url || window.location.href;
    if (!hasPropertyPortalShell()) {
        recoverPropertyPortalDocument(responseUrl);

        return;
    }
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
    afterMainFrameSwap(frame, 'turbo:frame-load');
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
    bootPropertyPortalShellGuards();
    const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);
    if (frame instanceof HTMLElement) {
        afterMainFrameSwap(frame, 'turbo:load');
    }
});

document.addEventListener('turbo:before-fetch-response', (event) => {
    const fetchResponse = event.detail?.fetchResponse;
    const status = fetchResponse?.response?.status;
    const responseUrl = fetchResponse?.response?.url || '';

    if (isPortalAuthFailureResponse(status, responseUrl)) {
        event.preventDefault();
        recoverPropertyPortalDocument(responseUrl || window.location.href);

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
    bootPropertyPortalShellGuards();
    const frame = getPropertyMainFrame();
    if (frame) {
        afterMainFrameSwap(frame, 'dom:ready');
    }
});

window.addEventListener('popstate', () => {
    resetPropertyFlashDedupe('popstate');
    resetPropertyHydrationGuard('popstate');
    const frame = getPropertyMainFrame();
    if (frame) {
        resetPropertyFrameReconcileToken('popstate');
        reconcilePropertyFrameWithBrowserUrl(frame, { force: true });
    }
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        resetPropertyHydrationGuard('pageshow:bfcache');
        resetPropertyFrameReconcileToken('pageshow:bfcache');
        resetPropertyFlashDedupe('pageshow:bfcache');
        recoverPropertyScrollState('pageshow:bfcache');

        if (!ensurePropertyPortalShell()) {
            return;
        }

        const frame = getPropertyMainFrame();
        if (frame) {
            reconcilePropertyFrameWithBrowserUrl(frame, { force: true });
            afterMainFrameSwap(frame, 'pageshow:bfcache');
        }
    }
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') {
        return;
    }

    recoverPropertyScrollState('visibility:visible');

    try {
        if (!isPropertyPortalGuestPage() && isPropertyShellUrl(new URL(window.location.href, window.location.href))) {
            ensurePropertyPortalShell();
        }
    } catch {
        // ignore malformed URLs
    }
});
