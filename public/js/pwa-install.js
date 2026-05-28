/**
 * PWA install prompt for mobile and desktop (Chrome, Edge, Safari).
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
    document.getElementById('pwa-install-fab')?.classList.add('hidden');
    document.getElementById('pwa-install-ios-panel')?.classList.add('hidden');
    document.getElementById('pwa-install-desktop-panel')?.classList.add('hidden');
}

function showFab() {
    if (isStandalone() || isDismissed()) {
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
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // Non-fatal: install UI may still work on some browsers
        });
    });
}

let deferredInstallPrompt = null;

function wireInstallFab() {
    const btn = document.getElementById('pwa-install-btn');
    const dismiss = document.getElementById('pwa-install-dismiss');
    const iosPanel = document.getElementById('pwa-install-ios-panel');
    const iosClose = document.getElementById('pwa-install-ios-close');
    const desktopPanel = document.getElementById('pwa-install-desktop-panel');
    const desktopClose = document.getElementById('pwa-install-desktop-close');

    updateInstallLabels();

    dismiss?.addEventListener('click', dismissPrompt);
    iosClose?.addEventListener('click', () => iosPanel?.classList.add('hidden'));
    desktopClose?.addEventListener('click', () => desktopPanel?.classList.add('hidden'));

    btn?.addEventListener('click', async () => {
        if (isIos()) {
            iosPanel?.classList.remove('hidden');
            return;
        }

        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            hideUi();
            return;
        }

        if (isDesktopSafari() || isDesktop()) {
            desktopPanel?.classList.remove('hidden');
        }
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showFab();
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideUi();
    });

    if ((isIos() || isDesktopSafari()) && !isStandalone()) {
        showFab();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireInstallFab);
} else {
    wireInstallFab();
}

registerServiceWorker();

if (isStandalone()) {
    hideUi();
}
