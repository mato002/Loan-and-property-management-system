{{-- Lazy-loaded Assign lease modal (shared by property + tenant hubs). --}}
@php
    $leaseModalOpenByDefault = (bool) ($openLeaseCreateModal ?? false);
@endphp

<x-property.modal
    show="showLeaseCreateForm"
    close="showLeaseCreateForm = false"
    name="lease-create-hub"
    title="Assign lease"
    max-width="4xl"
>
    <div
        id="lease-create-panel"
        class="w-full min-w-0"
        aria-live="polite"
        x-init="
            const boot = () => window.__leaseCreateEnsureForm?.();
            if (showLeaseCreateForm) boot();
            $watch('showLeaseCreateForm', (open) => {
                if (open) boot();
                else window.__leaseCreateResetForm?.();
            });
        "
    >
        <p
            id="lease-create-loading"
            class="hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm"
        >
            Loading lease form…
        </p>
        <p id="lease-create-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></p>
        <turbo-frame
            id="lease-create-modal"
            data-create-url="{{ $leaseCreateFormUrl ?? route('property.leases.create_form', absolute: false) }}"
            class="block w-full"
        ></turbo-frame>
    </div>
</x-property.modal>

<script>
    window.initLeaseCreateModalShell = window.initLeaseCreateModalShell || function () {
        const frame = document.getElementById('lease-create-modal');
        const loadingEl = document.getElementById('lease-create-loading');
        const errorEl = document.getElementById('lease-create-error');
        if (! frame) {
            return;
        }

        const createUrl = frame.dataset.createUrl || '';
        const pageRoot = () => document.querySelector('[data-property-page-modals]');

        const setLeaseModalOpen = (open) => {
            const root = pageRoot();
            if (root && window.Alpine?.$data) {
                try {
                    window.Alpine.$data(root).showLeaseCreateForm = open;
                    return;
                } catch (e) {}
            }
        };

        const setLoading = (active) => {
            loadingEl?.classList.toggle('hidden', ! active);
        };

        const showError = (message) => {
            if (! (errorEl instanceof HTMLElement)) {
                return;
            }
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        };

        const clearError = () => {
            if (! (errorEl instanceof HTMLElement)) {
                return;
            }
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        };

        const activateInjectedScripts = (root) => {
            root.querySelectorAll('script').forEach((oldScript) => {
                const script = document.createElement('script');
                [...oldScript.attributes].forEach((attr) => {
                    script.setAttribute(attr.name, attr.value);
                });
                script.textContent = oldScript.textContent;
                oldScript.replaceWith(script);
            });
        };

        const loadCreateForm = async () => {
            if (! createUrl || (frame.dataset.loaded === '1' && frame.innerHTML.trim() !== '')) {
                return;
            }

            clearError();
            setLoading(true);

            try {
                const response = await fetch(createUrl, {
                    headers: {
                        Accept: 'text/html',
                        'Turbo-Frame': 'lease-create-modal',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    throw new Error(`Could not load lease form (${response.status}). Refresh the page and try again.`);
                }

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const source = doc.querySelector('turbo-frame#lease-create-modal');

                if (! source) {
                    throw new Error('Lease form response was invalid. Refresh the page and try again.');
                }

                frame.innerHTML = source.innerHTML;
                frame.dataset.loaded = '1';
                activateInjectedScripts(frame);

                if (typeof window.initLeaseFormLogic === 'function') {
                    window.initLeaseFormLogic();
                }
                if (window.Alpine?.initTree) {
                    window.Alpine.initTree(frame);
                }
            } catch (error) {
                console.error('Failed to load lease create form', error);
                showError(error instanceof Error ? error.message : 'Could not load lease form.');
            } finally {
                setLoading(false);
            }
        };

        const resetForm = () => {
            setLoading(false);
            clearError();
            frame.innerHTML = '';
            delete frame.dataset.loaded;
        };

        window.__leaseCreateEnsureForm = loadCreateForm;
        window.__leaseCreateResetForm = resetForm;

        window.openLeaseCreateModal = () => {
            setLeaseModalOpen(true);
            void loadCreateForm();
        };
        window.closeLeaseCreateModal = () => {
            setLeaseModalOpen(false);
            resetForm();
        };

        const panel = document.getElementById('lease-create-panel');
        if (panel && panel.dataset.shellBound !== '1') {
            panel.dataset.shellBound = '1';
            panel.addEventListener('click', (event) => {
                const target = event.target;
                if (! (target instanceof Element)) {
                    return;
                }
                if (target.closest('[data-lease-create-close]')) {
                    window.closeLeaseCreateModal();
                }
            });
        }

        document.getElementById('open-lease-create-modal')?.addEventListener('click', (event) => {
            event.preventDefault();
            window.openLeaseCreateModal();
        });

        if (@json($leaseModalOpenByDefault)) {
            window.openLeaseCreateModal();
        }
    };

    if (! window.__leaseCreateModalShellBound) {
        window.__leaseCreateModalShellBound = true;
        document.addEventListener('DOMContentLoaded', window.initLeaseCreateModalShell);
        document.addEventListener('turbo:load', window.initLeaseCreateModalShell);
        document.addEventListener('turbo:frame-load', (event) => {
            const frame = event.target;
            if (frame && frame.id === 'property-main') {
                window.initLeaseCreateModalShell();
            }
        });
    }
</script>
