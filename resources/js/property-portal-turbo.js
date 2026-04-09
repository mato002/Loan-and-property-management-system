import '@hotwired/turbo';

const PROPERTY_MAIN_FRAME_ID = 'property-main';

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

function syncPropertyPortalNav(frame) {
    const routeEl = frame?.querySelector?.('#property-main-route');
    const routeName = routeEl?.dataset?.routeName ?? '';

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

    document.querySelectorAll('[data-property-nav-section]').forEach((section) => {
        if (!section.querySelector('a[aria-current="page"]')) {
            return;
        }
        if (window.Alpine && typeof window.Alpine.$data === 'function') {
            try {
                window.Alpine.$data(section).open = true;
            } catch {
                // ignore
            }
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

document.addEventListener('turbo:frame-load', (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }
    syncPropertyPortalNav(frame);
    scrollPropertyMainToTop(frame);
    if (window.Alpine?.initTree) {
        window.Alpine.initTree(frame);
    }
    if (typeof window.__runSwalFlash === 'function') {
        window.__runSwalFlash();
    }
});

// If a frame navigation returns 403/401, the response usually won't include the expected <turbo-frame>.
// Convert it to a full-page visit so the user sees the error page instead of a console exception.
document.addEventListener('turbo:before-fetch-response', (event) => {
    const fetchResponse = event.detail?.fetchResponse;
    const status = fetchResponse?.response?.status;
    if (status !== 401 && status !== 403) {
        return;
    }

    const url = fetchResponse?.response?.url;
    if (!url || !window.Turbo?.visit) {
        return;
    }

    event.preventDefault();
    window.Turbo.visit(url, { action: 'replace' });
});

// Some navigations / redirects can resolve as full Turbo visits instead of a frame-load.
// Ensure Alpine and flash handlers are re-initialized in that case too.
document.addEventListener('turbo:load', () => {
    const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);
    if (!(frame instanceof HTMLElement)) {
        return;
    }
    syncPropertyPortalNav(frame);
    scrollPropertyMainToTop(frame);
    if (window.Alpine?.initTree) {
        window.Alpine.initTree(frame);
    }
    if (typeof window.__runSwalFlash === 'function') {
        window.__runSwalFlash();
    }
});
