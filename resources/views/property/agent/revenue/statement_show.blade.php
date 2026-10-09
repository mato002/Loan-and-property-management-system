<x-property.workspace
    workspace="collections"
    title="Statement · {{ $statement->bank_name }}"
    subtitle="{{ $statement->account_name }} · {{ $statement->periodLabel() }}"
    back-route="property.revenue.statements.index"
    :stats="[
        ['label' => 'Matched', 'value' => (string) ($counts['matched'] ?? 0)],
        ['label' => 'Unmatched', 'value' => (string) ($counts['unmatched'] ?? 0)],
        ['label' => 'Bank only', 'value' => (string) ($counts['bank_only'] ?? 0)],
        ['label' => 'Credits', 'value' => \App\Services\Property\PropertyMoney::kes((float) $statement->total_credit)],
    ]"
    data-turbo-permanent
>
    <x-slot name="actions">
        <form method="POST" action="{{ route('property.revenue.statements.auto_assign', $statement) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex rounded-xl bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Auto-assign safe matches</button>
        </form>
        <form method="POST" action="{{ route('property.revenue.statements.enrich_payers', $statement) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex rounded-xl border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-900 hover:bg-sky-100">Fill missing phones &amp; names</button>
        </form>
        <form method="POST" action="{{ route('property.revenue.statements.recover', $statement) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex rounded-xl bg-amber-700 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-800">Recover missing → Unmatched</button>
        </form>
        <a href="{{ route('property.equity.unmatched') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open Unmatched</a>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        @php
            $pageSize = (string) ($filters['per_page'] ?? 100);
            $tabQuery = array_filter(['q' => $filters['q'] ?? '', 'per_page' => $pageSize]);
        @endphp
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === '' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }}">All</a>
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement, 'status' => 'unmatched'] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'unmatched' ? 'bg-amber-700 text-white' : 'bg-slate-100 text-slate-700' }}">Unmatched</a>
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement, 'status' => 'matched'] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'matched' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-700' }}">Matched</a>
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement, 'status' => 'bank_only'] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'bank_only' ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-700' }}">Bank only</a>
        <form method="get" action="{{ route('property.revenue.statements.show', $statement, false) }}" data-turbo="false" class="ml-auto flex flex-wrap items-center gap-2">
            @if ($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, phone, or payer" class="h-10 min-w-[14rem] rounded-lg border border-slate-300 px-3 text-sm">
            <select name="per_page" data-server-page-size="1" class="h-10 rounded-lg border border-slate-300 bg-white px-2 text-sm" onfocus="this.dataset.userChange='1'" onchange="if (this.dataset.userChange === '1') { this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit(); }">
                @foreach (($perPageOptions ?? []) as $option)
                    <option value="{{ $option['value'] }}" @selected($pageSize === (string) $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Filter</button>
            @include('property.agent.partials.export_dropdown', [
                'route' => 'property.revenue.statements.show',
                'routeParams' => ['statement' => $statement->id],
                'query' => request()->except(['export', 'page']),
            ])
        </form>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Reference</th>
                    <th class="px-3 py-2">Phone</th>
                    <th class="px-3 py-2">Payer on bank</th>
                    <th class="px-3 py-2">Tenant / paid to</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($lines as $line)
                    @php
                        $tenantId = $line->matchedTenantId();
                        $tenantAccount = $line->matchedTenantAccount();
                        $tenantUnit = $line->matchedUnitLabel();
                        $tenantName = $line->matchedTenantName();
                        $phone = $line->displayPhone();
                        $paymentId = (int) ($line->pm_payment_id ?? 0);
                    @endphp
                    <tr data-line-row="{{ $line->id }}">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $line->txn_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <div class="font-mono text-xs font-semibold text-slate-900">{{ $line->reference ?: '—' }}</div>
                            @if ($line->line_type === 'mpesa_c2b')
                                <div class="mt-0.5 text-[11px] text-slate-400">M-Pesa match key</div>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($phone !== '')
                                <div class="text-xs font-semibold text-slate-900"><x-phone-link :value="$phone" /></div>
                                @if ($line->phone && trim((string) $line->phone) !== trim($phone))
                                    <div class="mt-0.5"><x-phone-link :value="$line->phone" class="text-[11px] text-slate-400" /></div>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $line->counterparty ?: '—' }}</td>
                        <td data-col="tenant" class="px-3 py-2">
                            @if ($tenantAccount !== '' || $tenantName !== '')
                                @if ($tenantAccount !== '')
                                    <div class="font-mono text-xs font-semibold text-slate-900">{{ $tenantAccount }}</div>
                                @endif
                                @if ($tenantUnit !== '')
                                    <div class="text-xs text-slate-500">{{ $tenantUnit }}</div>
                                @endif
                                @if ($tenantName !== '')
                                    @if ($tenantId)
                                        <a href="{{ route('property.tenants.show', $tenantId) }}" class="text-sm font-medium text-blue-700 hover:underline">{{ $tenantName }}</a>
                                    @else
                                        <div class="text-sm text-slate-700">{{ $tenantName }}</div>
                                    @endif
                                @endif
                            @elseif ((string) $line->match_status === 'bank_only' && trim((string) $line->paid_to_name) !== '')
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Paid to</div>
                                <div class="text-sm font-medium text-slate-900">{{ $line->paid_to_name }}</div>
                                @if (trim((string) $line->paid_to_note) !== '')
                                    <div class="text-xs text-slate-500">{{ $line->paid_to_note }}</div>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums {{ $line->direction === 'debit' ? 'text-rose-700' : 'text-emerald-800' }}">
                            {{ $line->direction === 'debit' ? '−' : '+' }}{{ \App\Services\Property\PropertyMoney::kes((float) $line->amount) }}
                        </td>
                        <td data-col="status" class="px-3 py-2">
                            @php $shownStatus = $line->displayMatchStatus(); @endphp
                            <span class="text-xs font-semibold {{ $shownStatus === 'Matched' || $shownStatus === 'Landlord' ? 'text-emerald-700' : ($shownStatus === 'Unmatched' ? 'text-amber-700' : 'text-slate-500') }}">
                                {{ $shownStatus }}
                            </span>
                        </td>
                        <td data-col="actions" class="px-3 py-2 text-right">
                            <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                @if (! $line->isAllocatedToTenant() && $line->direction === 'credit' && $line->line_type === 'mpesa_c2b')
                                    <form method="POST" action="{{ route('property.revenue.statements.lines.assign', [$statement, $line]) }}" data-statement-assign data-line-id="{{ $line->id }}" data-line-phone="{{ $phone }}" data-line-counterparty="{{ $line->counterparty }}" class="flex items-center gap-1">
                                        @csrf
                                        <input type="hidden" name="tenant_id" value="">
                                        <div class="relative flex items-center">
                                            <input type="text" data-tenant-query data-auto-submit="off" autocomplete="off" placeholder="Tenant, account, or phone" required class="h-8 w-52 rounded-lg border border-slate-300 px-2 pr-6 text-xs focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition-all" />
                                            <span data-search-spinner class="pointer-events-none absolute right-1.5 hidden text-slate-400">
                                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            </span>
                                        </div>
                                        <button type="submit" class="rounded-lg bg-emerald-700 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-800 transition-colors whitespace-nowrap">Assign</button>
                                    </form>
                                @elseif ($paymentId > 0)
                                    <a href="{{ route('property.payments.receipt.show', $paymentId) }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-900 hover:bg-emerald-100">
                                        View receipt
                                    </a>
                                @elseif ($tenantId)
                                    <a href="{{ route('property.tenants.show', $tenantId) }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        Open tenant
                                    </a>
                                @elseif ((string) $line->match_status === 'bank_only')
                                    <form method="POST" action="{{ route('property.revenue.statements.lines.payee', [$statement, $line]) }}" data-statement-payee class="flex flex-nowrap items-center justify-end gap-1">
                                        @csrf
                                        <select name="paid_to_kind" data-payee-kind class="h-8 rounded-lg border border-slate-300 bg-white px-2 text-xs">
                                            <option value="landlord" @selected(($line->paid_to_kind ?: 'landlord') === 'landlord')>Landlord</option>
                                            <option value="bank_charge" @selected($line->paid_to_kind === 'bank_charge')>Bank charge</option>
                                            <option value="other" @selected($line->paid_to_kind === 'other')>Other</option>
                                        </select>
                                        <input type="hidden" name="landlord_id" value="{{ $line->paid_to_landlord_id }}">
                                        <div class="relative flex items-center">
                                            <input type="text" data-landlord-query autocomplete="off" placeholder="Landlord" value="{{ $line->paid_to_kind === 'landlord' ? $line->paid_to_name : '' }}" class="h-8 w-40 rounded-lg border border-slate-300 px-2 pr-6 text-xs focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                                            <span data-search-spinner class="pointer-events-none absolute right-1.5 hidden text-slate-400">
                                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            </span>
                                        </div>
                                        <input type="text" name="paid_to_name" value="{{ $line->paid_to_kind === 'other' ? $line->paid_to_name : '' }}" placeholder="Who was paid" class="h-8 w-36 rounded-lg border border-slate-300 px-2 text-xs">
                                        <input type="text" name="paid_to_note" value="{{ $line->paid_to_note }}" placeholder="Cheque / note" class="h-8 w-28 rounded-lg border border-slate-300 px-2 text-xs">
                                        <button type="submit" class="rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-800">Save</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-slate-500">No lines in this group.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $lines->links() }}</div>
</x-property.workspace>

<script data-turbo-permanent>
    (function () {
        const tenants = @json($assignTenants ?? []);
        const landlords = @json($assignLandlords ?? []);
        let menu = null;

        function closeMenu() {
            if (menu) {
                menu.remove();
                menu = null;
            }
        }

        function suggestions(typed) {
            const needle = typed.trim().toLowerCase();
            if (needle === '') {
                return [];
            }
            return tenants.filter((tenant) => (tenant.label || '').toLowerCase().includes(needle)).slice(0, 12);
        }

        function choose(query, hidden, tenant) {
            hidden.value = String(tenant.id);
            query.value = tenant.label;
            query.setCustomValidity('');
            closeMenu();
        }

        function openMenu(query, hidden) {
            closeMenu();
            const items = suggestions(query.value);
            if (items.length === 0) {
                return;
            }
            const box = document.createElement('div');
            box.setAttribute('data-tenant-menu', '1');
            box.className = 'fixed z-[80] max-h-60 w-72 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg';
            const rect = query.getBoundingClientRect();
            box.style.top = (rect.bottom + 4) + 'px';
            box.style.left = Math.max(8, rect.right - 288) + 'px';
            items.forEach((tenant) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'block w-full px-3 py-1.5 text-left text-xs text-slate-800 hover:bg-emerald-50';
                button.textContent = tenant.label;
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    choose(query, hidden, tenant);
                });
                box.appendChild(button);
            });
            document.body.appendChild(box);
            menu = box;
        }

        function initTenantDropdowns() {
            document.querySelectorAll('[data-statement-assign]').forEach((form) => {
                const query = form.querySelector('[data-tenant-query]');
                const hidden = form.querySelector('[name="tenant_id"]');
                if (!query || !hidden) {
                    return;
                }
                query.addEventListener('input', () => {
                    hidden.value = '';
                    openMenu(query, hidden);
                });
                query.addEventListener('focus', () => openMenu(query, hidden));
                query.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeMenu();
                    }
                });
                form.addEventListener('submit', (event) => {
                    const typed = (query.value || '').trim().toLowerCase();
                    const match = tenants.find((tenant) => (tenant.label || '').toLowerCase() === typed)
                        || (hidden.value ? tenants.find((tenant) => String(tenant.id) === hidden.value) : null);
                    if (!match) {
                        event.preventDefault();
                        query.setCustomValidity('Choose a tenant from the list.');
                        query.reportValidity();
                        return;
                    }
                    query.setCustomValidity('');
                    hidden.value = String(match.id);
                });
            });
        }

        function payeeFields(form) {
            const kind = form.querySelector('[data-payee-kind]')?.value || 'landlord';
            const landlord = form.querySelector('[data-landlord-query]');
            const other = form.querySelector('[name="paid_to_name"]');
            if (landlord) {
                landlord.hidden = kind !== 'landlord';
            }
            if (other) {
                other.hidden = kind !== 'other';
            }
        }

        function initLandlordDropdowns() {
            document.querySelectorAll('[data-statement-payee]').forEach((form) => {
                const query = form.querySelector('[data-landlord-query]');
                const hidden = form.querySelector('[name="landlord_id"]');
                const kind = form.querySelector('[data-payee-kind]');
                payeeFields(form);
                kind?.addEventListener('change', () => {
                    if (hidden && kind.value !== 'landlord') {
                        hidden.value = '';
                    }
                    payeeFields(form);
                });
                if (!query || !hidden) {
                    return;
                }
                query.addEventListener('input', () => {
                    hidden.value = '';
                    closeMenu();
                    const needle = query.value.trim().toLowerCase();
                    const items = needle === '' ? [] : landlords.filter((row) => (row.label || '').toLowerCase().includes(needle)).slice(0, 12);
                    if (items.length === 0) {
                        return;
                    }
                    const box = document.createElement('div');
                    box.setAttribute('data-tenant-menu', '1');
                    box.className = 'fixed z-[80] max-h-60 w-72 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg';
                    const rect = query.getBoundingClientRect();
                    box.style.top = (rect.bottom + 4) + 'px';
                    box.style.left = Math.max(8, rect.right - 288) + 'px';
                    items.forEach((landlord) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'block w-full px-3 py-1.5 text-left text-xs text-slate-800 hover:bg-emerald-50';
                        button.textContent = landlord.label;
                        button.addEventListener('mousedown', (event) => {
                            event.preventDefault();
                            hidden.value = String(landlord.id);
                            query.value = landlord.label;
                            query.setCustomValidity('');
                            closeMenu();
                        });
                        box.appendChild(button);
                    });
                    document.body.appendChild(box);
                    menu = box;
                });
                query.addEventListener('focus', () => {
                    if (query.value.trim() !== '') {
                        query.dispatchEvent(new Event('input'));
                    }
                });
                query.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeMenu();
                    }
                });
                form.addEventListener('submit', (event) => {
                    const typed = (query.value || '').trim().toLowerCase();
                    const match = landlords.find((row) => (row.label || '').toLowerCase() === typed)
                        || (hidden.value ? landlords.find((row) => String(row.id) === hidden.value) : null);
                    if (kind.value === 'landlord' && !match) {
                        event.preventDefault();
                        query.setCustomValidity('Choose a landlord from the list.');
                        query.reportValidity();
                        return;
                    }
                    query.setCustomValidity('');
                    if (kind.value === 'landlord') {
                        hidden.value = String(match?.id ?? '');
                    }
                });
            });
        }

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-tenant-menu]') && !event.target.closest('[data-tenant-query]') && !event.target.closest('[data-landlord-query]')) {
                closeMenu();
            }
        });

        initTenantDropdowns();
        initLandlordDropdowns();

        document.addEventListener('turbo:render', () => {
            initTenantDropdowns();
            initLandlordDropdowns();
        });
    })();
</script>
