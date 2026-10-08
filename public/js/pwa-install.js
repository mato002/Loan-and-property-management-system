/**
 * PWA install prompt for the public website and the property portal.
 */
const CONTEXT = document.documentElement.dataset.pwaContext || 'public';
const DISMISS_KEY = `gaitho-pwa-install-dismissed-until-${CONTEXT}`;
const DISMISS_DAYS = 7;

function isStandalone() {
    return (
        window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: window-controls-overlay)').matches
        || window.navigator.standalone === true
    );
}

function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function isDesktop() {
    const mobileUa = /android|iphone|ipad|ipod|mobile/i.test(navigator.userAgent);
    return !mobileUa && window.matchMedia('(min-width: 768px)').matches;
}

function isDesktopSafari() {
    return isDesktop() && /^((?!chrome|android|crios|fxios|edg).)*safari/i.test(navigator.userAgent);
}

function isDismissed() {
    try {
        const until = Number(localStorage.getItem(DISMISS_KEY) || '0');
        return until > Date.now();
    } catch {
        return false;
    }
}

function suppressInstallOffer() {
    document.documentElement.classList.add('pwa-install-suppressed');
}

function revealInstallOffer() {
    document.documentElement.classList.remove('pwa-install-suppressed');
}

function dismissPrompt() {
    try {
        const until = Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000;
        localStorage.setItem(DISMISS_KEY, String(until));
    } catch {
        // ignore
    }
    hideUi();
}

function hideUi() {
    suppressInstallOffer();
    document.getElementById('pwa-install-fab')?.classList.add('hidden');
    document.getElementById('pwa-install-ios-panel')?.classList.add('hidden');
    document.getElementById('pwa-install-desktop-panel')?.classList.add('hidden');
}

function showInstallUi() {
    if (isStandalone() || isDismissed()) {
        hideUi();
        return;
    }

    revealInstallOffer();
    updateInstallLabels();

    if (CONTEXT === 'public') {
        return;
    }

    document.getElementById('pwa-install-fab')?.classList.remove('hidden');
}

function updateInstallLabels() {
    const titleEl = document.getElementById('pwa-install-title');
    const subtitleEl = document.getElementById('pwa-install-subtitle');
    const iconMobile = document.getElementById('pwa-install-icon-mobile');
    const iconDesktop = document.getElementById('pwa-install-icon-desktop');

    if (isDesktop()) {
        if (titleEl?.dataset.desktopTitle) {
            titleEl.textContent = titleEl.dataset.desktopTitle;
        }
        if (subtitleEl?.dataset.desktopSubtitle) {
            subtitleEl.textContent = subtitleEl.dataset.desktopSubtitle;
        }
        iconMobile?.classList.add('hidden');
        iconDesktop?.classList.remove('hidden');
    } else {
        iconMobile?.classList.remove('hidden');
        iconDesktop?.classList.add('hidden');
    }
}

function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const swUrl = document.documentElement.dataset.pwaSw || new URL('/sw.js', window.location.origin).href;
    const scope = new URL('./', swUrl).href;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register(swUrl, { scope }).catch(() => {
            // Non-fatal: the install instructions can still be shown.
        });
    });
}

let deferredInstallPrompt = null;

async function runInstall() {
    if (isIos()) {
        document.getElementById('pwa-install-ios-panel')?.classList.remove('hidden');
        return;
    }

    if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        const choice = await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        if (choice.outcome === 'accepted') {
            hideUi();
        }
        return;
    }

    document.getElementById('pwa-install-desktop-panel')?.classList.remove('hidden');
}

function wireInstallFab() {
    const dismiss = document.getElementById('pwa-install-dismiss');
    const iosClose = document.getElementById('pwa-install-ios-close');
    const desktopClose = document.getElementById('pwa-install-desktop-close');

    dismiss?.addEventListener('click', dismissPrompt);
    document.getElementById('pwa-install-banner-dismiss')?.addEventListener('click', dismissPrompt);
    iosClose?.addEventListener('click', () => {
        document.getElementById('pwa-install-ios-panel')?.classList.add('hidden');
    });
    desktopClose?.addEventListener('click', () => {
        document.getElementById('pwa-install-desktop-panel')?.classList.add('hidden');
    });

    document.getElementById('pwa-install-btn')?.addEventListener('click', runInstall);
    document.getElementById('pwa-install-banner-btn')?.addEventListener('click', runInstall);
    document.querySelectorAll('[data-pwa-install-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', runInstall);
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallUi();
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideUi();
    });

    showInstallUi();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireInstallFab);
} else {
    wireInstallFab();
}

registerServiceWorker();
