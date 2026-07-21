{{-- Inline create form (lazy-loaded) — toggled by #open-lease-create-modal, not a modal overlay. --}}
<div
    id="lease-create-panel"
    class="mt-4 @unless($openLeaseCreateModal ?? false) hidden @endunless"
    aria-live="polite"
>
    <p
        id="lease-create-loading"
        class="@unless($openLeaseCreateModal ?? false) hidden @endunless rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm"
    >
        Loading lease form…
    </p>
    <p id="lease-create-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></p>
    <turbo-frame
        id="lease-create-modal"
        data-create-url="{{ route('property.leases.create_form', absolute: false) }}"
        class="block w-full max-w-3xl"
    ></turbo-frame>
</div>

<script>
    window.initLeaseCreateModalShell = window.initLeaseCreateModalShell || function () {
        const panel = document.getElementById('lease-create-panel');
        const frame = document.getElementById('lease-create-modal');
        const loadingEl = document.getElementById('lease-create-loading');
        const errorEl = document.getElementById('lease-create-error');
        const toggleButton = document.getElementById('open-lease-create-modal');
        if (!panel || !frame) {
            return;
        }

        const createUrl = frame.dataset.createUrl || '';

        const setLoading = (active) => {
            loadingEl?.classList.toggle('hidden', !active);
        };

        const showError = (message) => {
            if (!(errorEl instanceof HTMLElement)) {
                return;
            }
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        };

        const clearError = () => {
            if (!(errorEl instanceof HTMLElement)) {
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
            if (!createUrl || (frame.dataset.loaded === '1' && frame.innerHTML.trim() !== '')) {
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

                if (!response.ok) {
                    throw new Error(`Could not load lease form (${response.status}). Refresh the page and try again.`);
                }

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const source = doc.querySelector('turbo-frame#lease-create-modal');

                if (!source) {
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

        const showPanel = () => {
            panel.classList.remove('hidden');
            void loadCreateForm();
            window.requestAnimationFrame(() => {
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        };

        const hidePanel = () => {
            panel.classList.add('hidden');
            setLoading(false);
            clearError();
            frame.innerHTML = '';
            delete frame.dataset.loaded;
        };

        window.openLeaseCreateModal = showPanel;
        window.closeLeaseCreateModal = hidePanel;

        if (panel.dataset.shellBound !== '1') {
            panel.dataset.shellBound = '1';
            toggleButton?.addEventListener('click', () => {
                if (panel.classList.contains('hidden')) {
                    showPanel();
                } else {
                    hidePanel();
                }
            });
            panel.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }
                if (target.closest('[data-lease-create-close]')) {
                    hidePanel();
                }
            });
        }

        if (@json((bool) ($openLeaseCreateModal ?? false))) {
            showPanel();
        }
    };

    if (!window.__leaseCreateModalShellBound) {
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
