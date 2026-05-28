<x-property.workspace
    :title="($activeTab ?? 'leases') === 'expiry' ? 'Lease expiry tracking' : 'Lease agreements'"
    :subtitle="($activeTab ?? 'leases') === 'expiry'
        ? 'Active leases ending within the next 90 days. Use the window filter to focus renewals.'
        : 'Terms, deposits, rent, and linked units.'"
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="($activeTab ?? 'leases') === 'expiry' ? ($expiryFilterTexts ?? []) : []"
    :empty-title="($activeTab ?? 'leases') === 'expiry' ? 'No upcoming expiries' : 'No leases'"
    :empty-hint="($activeTab ?? 'leases') === 'expiry'
        ? 'When leases have end dates in the next 90 days, they appear here.'
        : 'Create a lease and select vacant units; active leases mark units occupied.'"
>
    <x-slot name="above">
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('property.tenants.leases', absolute: false) }}"
                data-turbo-frame="property-main"
                class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium {{ ($activeTab ?? 'leases') === 'leases' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
            >
                All leases
            </a>
            <a
                href="{{ route('property.tenants.leases', ['tab' => 'expiry'], false) }}"
                data-turbo-frame="property-main"
                class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium {{ ($activeTab ?? 'leases') === 'expiry' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
            >
                Expiring soon
            </a>
        </div>

        @if (($activeTab ?? 'leases') === 'leases')
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Rent flow (Step 1 of 3): Allocate a unit</p>
            <p class="mt-1 text-sm text-slate-600">Create an <span class="font-semibold">Active</span> lease and select the vacant unit(s). The unit becomes <span class="font-semibold">Occupied</span> automatically.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="text-slate-500">Next:</span> Create rent bill
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="text-slate-500">Then:</span> Collect payment
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>


        <div
            id="lease-create-overlay"
            class="fixed inset-0 z-[110] hidden items-start justify-center bg-slate-900/40 px-2 pb-2 pt-16 sm:px-4 sm:pb-4 sm:pt-20"
            role="dialog"
            aria-modal="true"
            aria-labelledby="lease-create-title"
        >
            <div class="w-full max-w-3xl">
                <turbo-frame
                    id="lease-create-modal"
                    data-create-url="{{ route('property.leases.create_form', absolute: false) }}"
                ></turbo-frame>
            </div>
        </div>
        <script>
            window.initLeaseCreateModalShell = window.initLeaseCreateModalShell || function () {
                const overlay = document.getElementById('lease-create-overlay');
                const frame = document.getElementById('lease-create-modal');
                const openButton = document.getElementById('open-lease-create-modal');
                if (!overlay || !frame) {
                    return;
                }

                const createUrl = frame.dataset.createUrl || '';
                const closeModal = () => {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                    frame.removeAttribute('src');
                    frame.innerHTML = '';
                    delete frame.dataset.loaded;
                };
                const openModal = () => {
                    if (!createUrl) {
                        return;
                    }
                    overlay.classList.remove('hidden');
                    overlay.classList.add('flex');
                    frame.src = createUrl;
                    frame.dataset.loaded = '1';
                };

                window.closeLeaseCreateModal = closeModal;

                if (overlay.dataset.shellBound !== '1') {
                    overlay.dataset.shellBound = '1';
                    openButton?.addEventListener('click', openModal);
                    overlay.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof Element)) {
                            return;
                        }
                        if (target.closest('[data-lease-create-close]')) {
                            closeModal();
                        }
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                }

                if (@json((bool) ($openLeaseCreateModal ?? false))) {
                    openModal();
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
        @endif
    </x-slot>

    @if (($activeTab ?? 'leases') === 'leases')
    <x-slot name="actions">
        <button
            type="button"
            id="open-lease-create-modal"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 w-full sm:w-auto"
        >
            <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
            Create lease
        </button>
    </x-slot>
    @elseif (($activeTab ?? 'leases') === 'expiry')
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'tenants-renewal-email') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Email renewals</a>
    </x-slot>
    @endif

    <x-slot name="toolbar">
        @if (($activeTab ?? 'leases') === 'expiry')
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Window: All (90d)</option>
            <option value="within30">≤ 30 days</option>
            <option value="within60">≤ 60 days</option>
            <option value="within90">≤ 90 days</option>
        </select>
        @else
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Status: All</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="terminated">Terminated</option>
        </select>
        @endif
    </x-slot>

    @if (($activeTab ?? 'leases') === 'leases')
        @include('property.agent.partials.lease_list_row_action_form')
    @endif

    @if (isset($leasePager) && ($activeTab ?? 'leases') === 'leases')
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">
                    Showing {{ $leasePager->firstItem() ?? 0 }}-{{ $leasePager->lastItem() ?? 0 }} of {{ $leasePager->total() }} leases.
                </p>
                <div>
                    {{ $leasePager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif
</x-property.workspace>
