import { PropertyFormModal as PropertyFormModalConfig } from './property-form-modal-config';

const FRAME_ID = PropertyFormModalConfig.FRAME_ID;
const HOST_ROOT_ID = 'property-form-modal-host';

/** @type {{ handleOpen: (detail: { url?: string; title?: string }) => void; handleClose: () => void } | null} */
let propertyFormModalHostApi = null;

/** @type {Array<{ url: string; title: string }>} */
const pendingFormModalOpens = [];

function getPropertyFormModalHostElement() {
    const host = document.getElementById(HOST_ROOT_ID);

    return host instanceof HTMLElement ? host : null;
}

function registerPropertyFormModalHostApi(api) {
    propertyFormModalHostApi = api;
    window.__propertyFormModalHostApi = api;

    while (pendingFormModalOpens.length > 0) {
        const next = pendingFormModalOpens.shift();
        if (next) {
            api.handleOpen(next);
        }
    }
}

function clearPendingPropertyFormModalOpens() {
    pendingFormModalOpens.length = 0;
}

function clearPropertyFormModalHostApi() {
    propertyFormModalHostApi = null;
    window.__propertyFormModalHostApi = null;
}

/** Re-bind Alpine on the layout edit/create shell when the host lost its component after Turbo. */
function ensurePropertyFormModalHost() {
    if (propertyFormModalHostApi?.handleOpen) {
        return;
    }

    const host = getPropertyFormModalHostElement();
    if (!host || !window.Alpine?.initTree) {
        return;
    }

    window.Alpine.initTree(host);
}

function runPropertyFormModalHost(method, detail) {
    const api = propertyFormModalHostApi ?? window.__propertyFormModalHostApi;
    if (api && typeof api[method] === 'function') {
        api[method](detail);

        return true;
    }

    ensurePropertyFormModalHost();

    queueMicrotask(() => {
        const retry = propertyFormModalHostApi ?? window.__propertyFormModalHostApi;
        if (retry && typeof retry[method] === 'function') {
            retry[method](detail);
        }
    });

    return false;
}

function bindPropertyFormModalHostEventsOnce() {
    if (window.__propertyFormModalHostEventsBound) {
        return;
    }
    window.__propertyFormModalHostEventsBound = true;

    document.addEventListener('turbo:frame-load', (event) => {
        if (!(event.target instanceof HTMLElement) || event.target.id !== 'property-main') {
            return;
        }
        clearPendingPropertyFormModalOpens();
        if (!propertyFormModalHostApi?.handleOpen) {
            ensurePropertyFormModalHost();
        }
    });

    document.addEventListener('turbo:load', () => {
        ensurePropertyFormModalHost();
    });

    document.addEventListener('DOMContentLoaded', () => {
        ensurePropertyFormModalHost();
    });
}

function activateScripts(root) {
    if (!(root instanceof Element)) {
        return;
    }
    root.querySelectorAll('script').forEach((oldScript) => {
        const script = document.createElement('script');
        [...oldScript.attributes].forEach((attr) => {
            script.setAttribute(attr.name, attr.value);
        });
        script.textContent = oldScript.textContent;
        oldScript.replaceWith(script);
    });
}

function prepareForms(root) {
    if (!(root instanceof Element)) {
        return;
    }
    root.querySelectorAll('form').forEach((form) => {
        form.setAttribute('data-turbo-frame', FRAME_ID);
        if (form.querySelector(`input[name="${PropertyFormModalConfig.INPUT_NAME}"]`)) {
            return;
        }
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = PropertyFormModalConfig.INPUT_NAME;
        input.value = '1';
        form.prepend(input);
    });
}

function reloadPropertyMain() {
    const main = document.querySelector('turbo-frame#property-main');
    if (main && typeof main.reload === 'function') {
        main.reload();
    }
}

function closePropertyFormModal() {
    runPropertyFormModalHost('handleClose');
}

function openPropertyFormModal({ url, title = 'Edit' }) {
    if (!url) {
        return;
    }

    window.PropertyModalManager?.closeAll?.('property-form-modal-open');

    const detail = { url, title };
    const api = propertyFormModalHostApi ?? window.__propertyFormModalHostApi;

    if (api?.handleOpen) {
        api.handleOpen(detail);

        return;
    }

    pendingFormModalOpens.push(detail);
    ensurePropertyFormModalHost();
}

function inferTitleFromLink(link) {
    const explicit = link.getAttribute('data-property-form-modal-title');
    if (explicit) {
        return explicit;
    }
    const text = (link.textContent || '').trim();
    if (text.length > 0 && text.length < 80) {
        return text;
    }

    return 'Edit';
}

function shouldOpenFormModal(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }
    if (link.dataset.propertyFormModal === 'off') {
        return false;
    }
    if (link.target === '_blank' || link.hasAttribute('download')) {
        return false;
    }
    if (link.dataset.propertyFormModal === '1' || link.hasAttribute('data-property-form-modal')) {
        return true;
    }

    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#')) {
        return false;
    }

    try {
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return false;
        }
        if (!PropertyFormModalConfig.isPropertyCrudFormPath(url.pathname)) {
            return false;
        }
        if (link.closest('#property-shell-sidebar, #property-shell-header, #property-shell-footer')) {
            return false;
        }

        return true;
    } catch {
        return false;
    }
}

function bindPropertyFormModalLinks() {
    document.addEventListener(
        'click',
        (event) => {
            const rawTarget = event.target;
            const target =
                rawTarget instanceof Element
                    ? rawTarget
                    : rawTarget?.parentElement instanceof Element
                      ? rawTarget.parentElement
                      : null;
            if (!target) {
                return;
            }
            const link = target.closest('a[href]');
            if (!(link instanceof HTMLAnchorElement) || !shouldOpenFormModal(link)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            openPropertyFormModal({
                url: link.href,
                title: inferTitleFromLink(link),
            });
        },
        true,
    );
}

function registerPropertyFormModalAlpine() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('propertyFormModalHost', () => ({
            open: false,
            title: '',
            loading: false,
            error: '',
            async loadUrl(url) {
                this.loading = true;
                this.error = '';
                const host = this.$refs.frameHost;
                if (host) {
                    host.innerHTML = '';
                }

                try {
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'text/html',
                            'Turbo-Frame': FRAME_ID,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error(`Could not load form (${response.status}).`);
                    }

                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    let source = doc.querySelector(`turbo-frame#${FRAME_ID}`);

                    if (!source) {
                        source = doc.querySelector('.property-form-modal-content');
                    }

                    if (!source || !(host instanceof HTMLElement)) {
                        throw new Error('Form response was invalid.');
                    }

                    host.innerHTML = source.innerHTML;
                    prepareForms(host);
                    activateScripts(host);
                    if (window.Alpine?.initTree) {
                        window.Alpine.initTree(host);
                    }
                } catch (error) {
                    console.error('[PropertyFormModal] load failed', error);
                    this.error =
                        error instanceof Error ? error.message : 'Could not load form.';
                } finally {
                    this.loading = false;
                }
            },
            handleOpen(detail) {
                const url = detail?.url;
                const nextTitle = detail?.title || 'Edit';
                if (!url) {
                    return;
                }
                clearPendingPropertyFormModalOpens();
                window.PropertyModalManager?.closeAll?.('property-form-modal-open');
                this.title = nextTitle;
                this.open = true;
                void this.loadUrl(url);
            },
            handleClose() {
                this.open = false;
                this.loading = false;
                this.error = '';
                const host = this.$refs.frameHost;
                if (host) {
                    host.innerHTML = '';
                }
                window.PropertyModalManager?.unregister?.(PropertyFormModalConfig.HOST_MODAL_ID);
            },
            init() {
                registerPropertyFormModalHostApi({
                    handleOpen: (detail) => this.handleOpen(detail),
                    handleClose: () => this.handleClose(),
                });
            },
            destroy() {
                clearPropertyFormModalHostApi();
            },
        }));
    });
}

function bindTurboFrameHooks() {
    document.addEventListener('turbo:frame-load', (event) => {
        const frame = event.target;
        if (!(frame instanceof Element) || frame.id !== FRAME_ID) {
            return;
        }
        if (frame.querySelector('[data-property-form-modal-success]')) {
            closePropertyFormModal();
            reloadPropertyMain();
            return;
        }
        prepareForms(frame);
        activateScripts(frame);
        if (window.Alpine?.initTree) {
            window.Alpine.initTree(frame);
        }
    });
}

window.PropertyFormModal = {
    open: openPropertyFormModal,
    close: closePropertyFormModal,
    ensureHost: ensurePropertyFormModalHost,
    FRAME_ID,
};

bindPropertyFormModalHostEventsOnce();
bindPropertyFormModalLinks();
registerPropertyFormModalAlpine();
bindTurboFrameHooks();

export function isPropertyFormModalLink(link) {
    return shouldOpenFormModal(link);
}

export { openPropertyFormModal, closePropertyFormModal, ensurePropertyFormModalHost, FRAME_ID };
