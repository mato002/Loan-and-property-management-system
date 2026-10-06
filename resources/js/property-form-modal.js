import { PropertyFormModal as PropertyFormModalConfig } from './property-form-modal-config';

const FRAME_ID = PropertyFormModalConfig.FRAME_ID;
const HOST_ROOT_ID = 'property-form-modal-host';

/** @type {{ handleOpen: (detail: { url?: string; title?: string }) => void; handleClose: () => void; submitForm?: (form: HTMLFormElement) => Promise<void> } | null} */
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
        form.setAttribute('data-turbo', 'false');
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

function showFormModalSuccess(message) {
    const text = (message || 'Saved.').trim() || 'Saved.';
    if (typeof window.Swal?.fire === 'function') {
        void window.Swal.fire({
            icon: 'success',
            title: 'Success',
            text,
            timer: 2400,
            showConfirmButton: false,
        });

        return;
    }
    if (typeof window.__runSwalFlash === 'function') {
        window.__laravelSwalFlash = [
            ...(Array.isArray(window.__laravelSwalFlash) ? window.__laravelSwalFlash : []),
            { icon: 'success', title: 'Success', text },
        ];
        void window.__runSwalFlash(document);
    }
}

function extractSuccessMessage(root) {
    const el = root instanceof Element
        ? root.querySelector('[data-property-form-modal-success]')
        : null;
    if (!(el instanceof HTMLElement)) {
        return null;
    }
    const attr = el.getAttribute('data-property-form-modal-success-message');
    if (attr && attr.trim() !== '') {
        return attr.trim();
    }

    return (el.textContent || '').trim() || 'Saved.';
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

function resolveFormConfirmMessage(form, submitter) {
    let msg = submitter?.getAttribute('data-swal-confirm') || form.getAttribute('data-swal-confirm');
    if (msg) {
        return msg;
    }
    const onsubmit = form.getAttribute('onsubmit') || '';
    const match = onsubmit.match(/confirm\((['"])(.*?)\1\)/);

    return match?.[2] ?? null;
}

function bindPropertyFormModalSubmit() {
    if (window.__propertyFormModalSubmitBound) {
        return;
    }
    window.__propertyFormModalSubmitBound = true;

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.closest(`#${HOST_ROOT_ID}`)) {
                return;
            }

            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            const confirmMessage = resolveFormConfirmMessage(form, submitter);
            if (confirmMessage && form.dataset.swalSubmitting !== '1') {
                // Let swal-init.js handle the confirm prompt first.
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const api = propertyFormModalHostApi ?? window.__propertyFormModalHostApi;
            if (api?.submitForm) {
                void api.submitForm(form);
            }
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
            renderSource(source) {
                const host = this.$refs.frameHost;
                if (!(host instanceof HTMLElement) || !(source instanceof Element)) {
                    throw new Error('Form response was invalid.');
                }

                host.innerHTML = source.innerHTML;
                prepareForms(host);
                activateScripts(host);
                if (window.Alpine?.initTree) {
                    window.Alpine.initTree(host);
                }
            },
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

                    this.renderSource(source);
                } catch (error) {
                    console.error('[PropertyFormModal] load failed', error);
                    this.error =
                        error instanceof Error ? error.message : 'Could not load form.';
                } finally {
                    this.loading = false;
                }
            },
            async submitForm(form) {
                if (!(form instanceof HTMLFormElement) || form.dataset.propertyModalSubmitting === '1') {
                    return;
                }

                form.dataset.propertyModalSubmitting = '1';
                this.loading = true;
                this.error = '';

                try {
                    if (!form.querySelector(`input[name="${PropertyFormModalConfig.INPUT_NAME}"]`)) {
                        prepareForms(form.parentElement instanceof Element ? form.parentElement : form);
                    }

                    const method = (form.getAttribute('method') || 'post').toUpperCase() === 'GET' ? 'GET' : 'POST';
                    const response = await fetch(form.action, {
                        method,
                        body: method === 'GET' ? undefined : new FormData(form),
                        headers: {
                            Accept: 'text/html',
                            'Turbo-Frame': FRAME_ID,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        redirect: 'follow',
                    });

                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const successMessage = extractSuccessMessage(doc);

                    if (successMessage) {
                        this.handleClose();
                        showFormModalSuccess(successMessage);
                        reloadPropertyMain();

                        return;
                    }

                    let source = doc.querySelector(`turbo-frame#${FRAME_ID}`);
                    if (!source) {
                        source = doc.querySelector('.property-form-modal-content');
                    }

                    if (!source) {
                        throw new Error(
                            response.ok
                                ? 'Save completed, but the form response was unexpected. Refresh the page.'
                                : `Could not save (${response.status}).`,
                        );
                    }

                    this.renderSource(source);
                    if (typeof window.__runSwalFlash === 'function') {
                        void window.__runSwalFlash(this.$refs.frameHost);
                    }
                } catch (error) {
                    console.error('[PropertyFormModal] submit failed', error);
                    this.error = error instanceof Error ? error.message : 'Could not save.';
                } finally {
                    delete form.dataset.propertyModalSubmitting;
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
                    submitForm: (form) => this.submitForm(form),
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

        const successMessage = extractSuccessMessage(frame);
        if (successMessage) {
            closePropertyFormModal();
            showFormModalSuccess(successMessage);
            reloadPropertyMain();

            return;
        }

        prepareForms(frame);
        activateScripts(frame);
        if (window.Alpine?.initTree) {
            window.Alpine.initTree(frame);
        }
        if (typeof window.__runSwalFlash === 'function') {
            window.__runSwalFlash(frame);
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
bindPropertyFormModalSubmit();
registerPropertyFormModalAlpine();
bindTurboFrameHooks();

export function isPropertyFormModalLink(link) {
    return shouldOpenFormModal(link);
}

export { openPropertyFormModal, closePropertyFormModal, ensurePropertyFormModalHost, FRAME_ID };
