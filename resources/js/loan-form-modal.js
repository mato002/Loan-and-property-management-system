const HOST_ID = 'loan-form-modal-host';

function isLoanCrudFormPath(pathname) {
    const path = String(pathname || '').replace(/\/+$/, '') || '/';
    if (!path.startsWith('/loan/')) {
        return false;
    }
    if (path.includes('/export') || path.includes('/print') || path.includes('/download')) {
        return false;
    }

    return /\/(edit|create)$/.test(path);
}

function hostApi() {
    return window.__loanFormModalHostApi || null;
}

function inferTitle(link) {
    const explicit = link.getAttribute('data-loan-form-modal-title');
    if (explicit) {
        return explicit;
    }
    const text = (link.textContent || '').replace(/\s+/g, ' ').trim();
    if (text.length > 0 && text.length < 80) {
        return text;
    }
    try {
        return new URL(link.href, window.location.origin).pathname.endsWith('/create')
            ? 'Create'
            : 'Edit';
    } catch {
        return 'Edit';
    }
}

function shouldOpen(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }
    if (link.dataset.loanFormModal === 'off' || link.dataset.turbo === 'false') {
        return false;
    }
    if (link.target === '_blank' || link.hasAttribute('download')) {
        return false;
    }
    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return false;
    }
    try {
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return false;
        }
        const params = url.searchParams;
        if (params.has('export') || params.has('download') || params.has('print')) {
            return false;
        }

        return isLoanCrudFormPath(url.pathname);
    } catch {
        return false;
    }
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

function extractFrameHtml(doc) {
    const frame = doc.querySelector('turbo-frame#loan-main');
    if (!(frame instanceof HTMLElement)) {
        return '';
    }
    frame.querySelectorAll('.loan-workspace-shell, #loan-main-route, [data-swal-flash]').forEach((el) => {
        el.remove();
    });
    frame.querySelectorAll('script').forEach((script) => {
        if ((script.textContent || '').includes('__laravelSwalFlash')) {
            script.remove();
        }
    });

    return frame.innerHTML.trim();
}

function queueFlashFromDocument(doc) {
    const node = doc.querySelector('[data-swal-flash]');
    if (!node) {
        return;
    }
    const raw = node.getAttribute('data-swal-flash');
    if (!raw) {
        return;
    }
    try {
        const parsed = JSON.parse(raw);
        const items = Array.isArray(parsed) ? parsed : [parsed];
        window.__laravelSwalFlash = [
            ...(Array.isArray(window.__laravelSwalFlash) ? window.__laravelSwalFlash : []),
            ...items.filter((item) => item && typeof item === 'object'),
        ];
    } catch {
        // ignore malformed flash
    }
}

function showQueuedFlash() {
    if (typeof window.__runSwalFlash === 'function') {
        window.__runSwalFlash(document);
    }
}

function openLoanFormModal(detail) {
    const api = hostApi();
    if (api?.handleOpen) {
        api.handleOpen(detail);

        return;
    }
    window.__loanFormModalPending = detail;
}

document.addEventListener(
    'click',
    (event) => {
        const rawTarget = event.target;
        const target = rawTarget instanceof Element ? rawTarget : rawTarget?.parentElement;
        if (!(target instanceof Element)) {
            return;
        }
        const link = target.closest('a[href]');
        if (!(link instanceof HTMLAnchorElement) || !shouldOpen(link)) {
            return;
        }
        if (!document.getElementById(HOST_ID)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        openLoanFormModal({ url: link.href, title: inferTitle(link) });
    },
    true,
);

document.addEventListener(
    'submit',
    (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.closest(`#${HOST_ID}`)) {
            return;
        }
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        const confirmMessage = submitter?.getAttribute('data-swal-confirm')
            || form.getAttribute('data-swal-confirm')
            || (form.getAttribute('onsubmit') || '').match(/confirm\((['"])(.*?)\1\)/)?.[2]
            || null;
        if (confirmMessage && form.dataset.swalSubmitting !== '1') {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const api = hostApi();
        if (api?.submitForm) {
            void api.submitForm(form);
        }
    },
    true,
);

document.addEventListener('alpine:init', () => {
    window.Alpine.data('loanFormModalHost', () => ({
        open: false,
        title: 'Edit',
        loading: false,
        error: '',
        init() {
            window.__loanFormModalHostApi = {
                handleOpen: (detail) => this.handleOpen(detail),
                handleClose: () => this.handleClose(),
                submitForm: (form) => this.submitForm(form),
            };
            const pending = window.__loanFormModalPending;
            if (pending?.url) {
                delete window.__loanFormModalPending;
                this.handleOpen(pending);
            }
        },
        destroy() {
            if (window.__loanFormModalHostApi) {
                delete window.__loanFormModalHostApi;
            }
        },
        handleClose() {
            this.open = false;
            this.loading = false;
            this.error = '';
            const host = this.$refs.frameHost;
            if (host) {
                host.innerHTML = '';
            }
        },
        handleOpen(detail) {
            const url = detail?.url;
            if (!url) {
                return;
            }
            this.title = detail.title || 'Edit';
            this.open = true;
            this.error = '';
            void this.loadUrl(url);
        },
        renderDocument(doc) {
            const host = this.$refs.frameHost;
            const html = extractFrameHtml(doc);
            if (!(host instanceof HTMLElement) || html === '') {
                throw new Error('This form could not be opened in a window.');
            }
            host.innerHTML = html;
            const heading = host.querySelector('h1, h2');
            const headingText = (heading?.textContent || '').replace(/\s+/g, ' ').trim();
            if (headingText && (this.title === 'Create' || this.title === 'Edit' || this.title.length < 3)) {
                this.title = headingText;
            }
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
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error(`Could not load form (${response.status}).`);
                }
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                queueFlashFromDocument(doc);
                this.renderDocument(doc);
                showQueuedFlash();
            } catch (error) {
                this.error = error instanceof Error ? error.message : 'Could not load form.';
            } finally {
                this.loading = false;
            }
        },
        async submitForm(form) {
            if (form.dataset.loanModalSubmitting === '1') {
                return;
            }
            form.dataset.loanModalSubmitting = '1';
            this.loading = true;
            this.error = '';
            try {
                const response = await fetch(form.action, {
                    method: (form.getAttribute('method') || 'post').toUpperCase() === 'GET' ? 'GET' : 'POST',
                    body: new FormData(form),
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                queueFlashFromDocument(doc);
                let finalPath = '';
                try {
                    finalPath = new URL(response.url, window.location.origin).pathname;
                } catch {
                    finalPath = '';
                }
                const stayOnForm = !response.ok || isLoanCrudFormPath(finalPath);
                if (stayOnForm) {
                    this.renderDocument(doc);
                    showQueuedFlash();

                    return;
                }
                this.handleClose();
                showQueuedFlash();
                if (window.Turbo?.visit) {
                    window.Turbo.visit(response.url, { action: 'replace' });
                } else {
                    window.location.assign(response.url);
                }
            } catch (error) {
                this.error = error instanceof Error ? error.message : 'Could not save.';
            } finally {
                delete form.dataset.loanModalSubmitting;
                this.loading = false;
            }
        },
    }));
});
