import * as Turbo from '@hotwired/turbo';
import { wireAutoFilterForms } from './property-auto-filter';

export const LOAN_MAIN_FRAME_ID = 'loan-main';

/** Guard flag so frame redirects do not recurse through turbo:before-visit. */
let routingViaMainFrame = false;

const FRAME_LOADING_DELAY_MS = 160;
const WORKSPACE_LOADING_DELAY_MS = 120;
let frameLoadingTimer = null;
let workspaceLoadingTimer = null;

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

function isLoanShellUrl(url) {
    if (url.origin !== window.location.origin) {
        return false;
    }

    const path = url.pathname.replace(/\/+$/, '') || '/';

    if (path.startsWith('/loan/')) {
        return true;
    }

    // `/dashboard` redirects into the loan workspace for most roles.
    return path === '/dashboard' && hasLoanPortalShell();
}

/** Avoid redirect hops when navigating inside the loan shell. */
function resolveLoanNavUrl(href) {
    try {
        const url = new URL(href, window.location.href);
        const path = url.pathname.replace(/\/+$/, '') || '/';

        if (path === '/dashboard' && hasLoanPortalShell()) {
            url.pathname = '/loan/dashboard';

            return url.toString();
        }
    } catch {
        // ignore malformed URLs
    }

    return href;
}

function hasLoanPortalShell() {
    return document.getElementById('loan-shell-header') instanceof HTMLElement
        && document.getElementById(LOAN_MAIN_FRAME_ID) instanceof HTMLElement;
}

function wireLoanBypassLinks(root = document) {
    const scope = root instanceof Document ? root : root;

    scope.querySelectorAll('a[href]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement) || ! isBypassTurboLink(link)) {
            return;
        }

        link.setAttribute('data-turbo', 'false');
        link.removeAttribute('data-turbo-frame');
    });
}

function getLoanMainFrame() {
    const frame = document.getElementById(LOAN_MAIN_FRAME_ID);
    return frame instanceof HTMLElement ? frame : null;
}

function getWorkspaceLoadingEl() {
    return document.getElementById('loan-workspace-loading');
}

function getGlobalProgressEl() {
    return document.getElementById('loan-global-nav-progress');
}

function getWorkspaceErrorEl() {
    return document.getElementById('loan-workspace-error');
}

function clearLoanFrameLoading() {
    window.clearTimeout(frameLoadingTimer);
    frameLoadingTimer = null;
    getLoanMainFrame()?.removeAttribute('data-loan-loading');
}

function scheduleLoanFrameLoading(frame) {
    if (!frame || frame.id !== LOAN_MAIN_FRAME_ID) {
        return;
    }
    window.clearTimeout(frameLoadingTimer);
    frame.removeAttribute('data-loan-loading');
    frameLoadingTimer = window.setTimeout(() => {
        frame.setAttribute('data-loan-loading', '');
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
    clearLoanFrameLoading();
    const el = getWorkspaceErrorEl();
    if (!el) {
        return;
    }
    const safeMessage = message || 'This page could not be loaded.';
    el.innerHTML = `
        <div class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm max-w-lg">
            <p class="text-sm font-semibold text-rose-800">Could not load workspace</p>
            <p class="mt-1 text-sm text-slate-600">${safeMessage}</p>
            ${retryUrl ? `<button type="button" class="mt-4 inline-flex items-center rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800" data-loan-retry-url="${retryUrl}">Try again</button>` : ''}
        </div>
    `;
    el.setAttribute('data-active', '');
    el.querySelector('[data-loan-retry-url]')?.addEventListener('click', () => {
        hideWorkspaceError();
        visitLoanMainFrame(retryUrl);
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

function syncLoanPortalNav(frame) {
    const routeEl = frame?.querySelector?.('#loan-main-route');
    const routeName = routeEl?.dataset?.routeName ?? '';

    document.querySelectorAll('a[data-loan-nav]').forEach((a) => {
        const raw = a.getAttribute('data-loan-nav') || '';
        const patterns = raw.split('|').map((s) => s.trim()).filter(Boolean);
        const active = Boolean(routeName && patterns.some((p) => routeNameMatches(routeName, p)));
        const isHeaderLink = a.closest('#loan-shell-header') !== null;
        const isSidebarLink = a.closest('#loan-shell-sidebar') !== null;
        const isQuickLink = a.classList.contains('loan-quick-link');

        if (active) {
            a.setAttribute('aria-current', 'page');
            if (isHeaderLink) {
                a.classList.add('bg-[#2f4f4f]', 'text-white', 'shadow-sm', 'ring-1', 'ring-[#2f4f4f]/20');
                a.classList.remove('text-slate-600', 'hover:bg-slate-100', 'hover:text-slate-900');
            } else if (isSidebarLink) {
                a.classList.add('bg-white/15', 'text-white', 'font-semibold');
                a.classList.remove('text-[#8db1af]', 'hover:text-white', 'hover:bg-white/5');
            } else if (isQuickLink) {
                a.classList.add('bg-[#0f766e]', 'text-white', 'shadow-sm');
                a.classList.remove('text-slate-600', 'hover:bg-slate-100', 'hover:text-[#2f4f4f]');
            }
        } else {
            a.removeAttribute('aria-current');
            if (isHeaderLink) {
                a.classList.remove('bg-[#2f4f4f]', 'text-white', 'shadow-sm', 'ring-1', 'ring-[#2f4f4f]/20');
                a.classList.add('text-slate-600', 'hover:bg-slate-100', 'hover:text-slate-900');
            } else if (isSidebarLink) {
                a.classList.remove('bg-white/15', 'text-white', 'font-semibold');
                a.classList.add('text-[#8db1af]', 'hover:text-white', 'hover:bg-white/5');
            } else if (isQuickLink) {
                a.classList.remove('bg-[#0f766e]', 'text-white', 'shadow-sm');
                a.classList.add('text-slate-600', 'hover:bg-slate-100', 'hover:text-[#2f4f4f]');
            }
        }
    });

    document.querySelectorAll('[data-loan-nav-aggregate]').forEach((el) => {
        const raw = el.getAttribute('data-loan-nav-aggregate') || '';
        const patterns = raw.split('|').map((s) => s.trim()).filter(Boolean);
        const active = Boolean(routeName && patterns.some((p) => routeNameMatches(routeName, p)));
        if (active) {
            el.setAttribute('data-section-active', '');
        } else {
            el.removeAttribute('data-section-active');
        }
    });
}

function scrollLoanMainToTop(frame) {
    if (frame?.id !== LOAN_MAIN_FRAME_ID) {
        return;
    }
    const main = frame.closest('main');
    if (main) {
        main.scrollTop = 0;
    }
}

function shouldUseMainFrame(element) {
    if (!(element instanceof HTMLElement)) {
        return false;
    }
    if (element.dataset.turbo === 'false') {
        return false;
    }
    if (element.getAttribute('data-turbo-frame') === '_top') {
        return false;
    }

    return true;
}

function visitLoanMainFrame(url) {
    if (!hasLoanPortalShell()) {
        window.location.assign(url);
        return;
    }

    routingViaMainFrame = true;
    hideWorkspaceError();
    try {
        Turbo.visit(resolveLoanNavUrl(url), { frame: LOAN_MAIN_FRAME_ID });
    } finally {
        routingViaMainFrame = false;
    }
}

function visitLoanUrl(href) {
    if (!href) {
        return;
    }
    try {
        const url = new URL(href, window.location.href);
        if (!isLoanShellUrl(url)) {
            window.location.href = href;
            return;
        }
        visitLoanMainFrame(resolveLoanNavUrl(url.toString()));
    } catch {
        window.location.href = href;
    }
}

window.visitLoanMain = visitLoanUrl;

function wireLoanFrameNavigation(root = document) {
    if (!(root instanceof Document || root instanceof Element)) {
        return;
    }

    const scope = root instanceof Document ? root : root;

    wireLoanBypassLinks(scope);

    scope.querySelectorAll('a[href]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }
        if (isBypassTurboLink(link) || ! shouldUseMainFrame(link) || link.target === '_blank' || link.hasAttribute('download')) {
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
            if (!isLoanShellUrl(new URL(href, window.location.href))) {
                return;
            }
        } catch {
            return;
        }
        link.setAttribute('data-turbo-frame', LOAN_MAIN_FRAME_ID);
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
            if (!isLoanShellUrl(new URL(form.action || window.location.href, window.location.href))) {
                return;
            }
        } catch {
            return;
        }
        form.setAttribute('data-turbo-frame', LOAN_MAIN_FRAME_ID);
    });
}

function ensureLoanFormUsesMainFrame(form) {
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
        if (!isLoanShellUrl(new URL(form.action || window.location.href, window.location.href))) {
            return;
        }
    } catch {
        return;
    }
    form.setAttribute('data-turbo-frame', LOAN_MAIN_FRAME_ID);
}

function afterMainFrameSwap(frame) {
    const mainFrame = frame instanceof HTMLElement ? frame : getLoanMainFrame();

    wireLoanFrameNavigation(document);
    wireAutoFilterForms(mainFrame instanceof HTMLElement ? mainFrame : document);
    syncLoanPortalNav(mainFrame);
    scrollLoanMainToTop(mainFrame);
    hideWorkspaceLoading();
    clearLoanFrameLoading();

    if (mainFrame instanceof HTMLElement && window.Alpine?.initTree) {
        window.Alpine.initTree(mainFrame);
    }
}

document.addEventListener('turbo:frame-request-started', (event) => {
    const frame = event.target;
    if (frame instanceof HTMLElement) {
        scheduleLoanFrameLoading(frame);
        if (frame.id === LOAN_MAIN_FRAME_ID) {
            showWorkspaceLoading();
        }
    }
});

document.addEventListener('turbo:frame-request-finished', (event) => {
    const frame = event.target;
    if (frame instanceof HTMLElement && frame.id === LOAN_MAIN_FRAME_ID) {
        clearLoanFrameLoading();
        hideWorkspaceLoading();
    }
});

document.addEventListener('turbo:before-frame-render', (event) => {
    if (event.target instanceof HTMLElement && event.target.id === LOAN_MAIN_FRAME_ID) {
        hideWorkspaceError();
    }
});

document.addEventListener('turbo:frame-render', (event) => {
    if (event.target instanceof HTMLElement && event.target.id === LOAN_MAIN_FRAME_ID) {
        hideWorkspaceLoading();
        clearLoanFrameLoading();
    }
});

document.addEventListener('turbo:submit-start', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    ensureLoanFormUsesMainFrame(form);
    if (form.closest(`#${LOAN_MAIN_FRAME_ID}`)) {
        showWorkspaceLoading();
    }
});

document.addEventListener('turbo:submit-end', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.closest(`#${LOAN_MAIN_FRAME_ID}`)) {
        return;
    }
    hideWorkspaceLoading();
    clearLoanFrameLoading();
});

document.addEventListener('turbo:fetch-request-error', (event) => {
    const frame = event.target;
    if (frame instanceof HTMLElement && frame.id === LOAN_MAIN_FRAME_ID) {
        hideWorkspaceLoading();
        clearLoanFrameLoading();
        showWorkspaceError('Network error while loading this page.', window.location.href);
    }
});

document.addEventListener('turbo:click', (event) => {
    const link = event.target?.closest?.('a[href]');
    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }
    if (isBypassTurboLink(link) || !shouldUseMainFrame(link) || link.target === '_blank' || link.hasAttribute('download')) {
        return;
    }

    let url;
    try {
        url = new URL(link.href, window.location.href);
    } catch {
        return;
    }
    if (!isLoanShellUrl(url)) {
        return;
    }

    event.preventDefault();
    visitLoanMainFrame(resolveLoanNavUrl(url.toString()));
});

document.addEventListener('turbo:frame-missing', (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLElement) || frame.id !== LOAN_MAIN_FRAME_ID) {
        return;
    }

    event.preventDefault();
    const responseUrl = event.detail?.response?.url || window.location.href;
    if (!hasLoanPortalShell()) {
        window.location.assign(responseUrl);
        return;
    }
    if (responseUrl) {
        visitLoanMainFrame(responseUrl);
        return;
    }
    showWorkspaceError('The server response did not include workspace content.', window.location.href);
});

document.addEventListener('turbo:frame-load', (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLElement) || frame.id !== LOAN_MAIN_FRAME_ID) {
        return;
    }
    afterMainFrameSwap(frame);
});

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
    if (!isLoanShellUrl(url)) {
        return;
    }

    event.preventDefault();
    visitLoanMainFrame(resolveLoanNavUrl(event.detail.url));
});

document.addEventListener('turbo:load', () => {
    wireLoanFrameNavigation();
    const frame = getLoanMainFrame();
    if (frame) {
        afterMainFrameSwap(frame);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    wireLoanFrameNavigation();
    const frame = getLoanMainFrame();
    if (frame) {
        afterMainFrameSwap(frame);
    }
});
