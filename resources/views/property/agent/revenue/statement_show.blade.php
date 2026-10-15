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
>
    <x-slot name="actions">
        <form method="POST" action="{{ route('property.revenue.statements.auto_assign', $statement) }}" data-turbo="false" class="inline">
            @csrf
            <button type="submit" class="inline-flex rounded-xl bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Auto-assign safe matches</button>
        </form>
        <form method="POST" action="{{ route('property.revenue.statements.enrich_payers', $statement) }}" data-turbo="false" class="inline">
            @csrf
            <button type="submit" class="inline-flex rounded-xl border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-900 hover:bg-sky-100">Fill missing phones &amp; names</button>
        </form>
        <form method="POST" action="{{ route('property.revenue.statements.recover', $statement) }}" data-turbo="false" class="inline">
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

    <form id="statement-bulk-reversal" method="POST" action="{{ route('property.payments.bank_statement.bulk_reverse_matches') }}" data-turbo-frame="property-main" data-swal-title="Reverse the selected bank-statement matches?" data-swal-confirm="This reverses each selected imported payment, releases active invoice allocations and related credits, and returns its linked statement lines to Unmatched. No bank refunds will be initiated." data-swal-confirm-text="Reverse selected">
        @csrf
        <input type="hidden" name="statement_id" value="{{ $statement->id }}">
    </form>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-100 bg-rose-50/70 px-4 py-3">
        <p class="text-sm text-slate-700">Select bank-statement matches to reverse. <span id="statement-reversal-count" class="font-semibold">0 selected</span></p>
        <button type="submit" form="statement-bulk-reversal" id="statement-bulk-reverse-button" disabled class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-50">Reverse selected matches</button>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        @php
            $pageSize = (string) ($filters['per_page'] ?? 100);
            $tabQuery = array_filter(['q' => $filters['q'] ?? '', 'per_page' => $pageSize]);
        @endphp
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === '' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }}">All</a>
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement, 'status' => 'unmatched'] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'unmatched' ? 'bg-amber-700 text-white' : 'bg-slate-100 text-slate-700' }}">Unmatched</a>
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement, 'status' => 'matched'] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'matched' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-700' }}">Matched</a>
        <a href="{{ route('property.revenue.statements.show', array_filter(['statement' => $statement, 'status' => 'bank_only'] + $tabQuery)) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'bank_only' ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-700' }}">Bank only</a>
        <form method="get" action="{{ route('property.revenue.statements.show', $statement, false) }}" class="ml-auto flex flex-wrap items-center gap-2">
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
        <table class="w-full table-fixed text-sm">
            <colgroup>
                <col style="width: 3%"><col style="width: 8%"><col style="width: 12%"><col style="width: 10%"><col style="width: 10%"><col style="width: 12%"><col style="width: 10%"><col style="width: 7%"><col style="width: 28%">
            </colgroup>
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-2 py-2 text-center"><input type="checkbox" id="statement-select-page" class="rounded border-slate-300 text-rose-700 focus:ring-rose-600" aria-label="Select eligible bank-statement matches on this page"></th>
                    <th class="px-2 py-2">Date</th>
                    <th class="px-2 py-2">Reference</th>
                    <th class="px-2 py-2">Phone</th>
                    <th class="px-2 py-2">Payer</th>
                    <th class="px-2 py-2">Tenant / paid to</th>
                    <th class="px-2 py-2">Amount</th>
                    <th class="px-2 py-2">Status</th>
                    <th class="px-2 py-2">Actions</th>
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
                        $canReverseBankPayment = $line->payment
                            && strtolower(trim((string) $line->payment->channel)) === 'bank_statement'
                            && $line->payment->status === \App\Models\PmPayment::STATUS_COMPLETED
                            && ! in_array($line->payment->reversal_status, [\App\Models\PmPayment::REVERSAL_STATUS_PENDING, \App\Models\PmPayment::REVERSAL_STATUS_REVERSED], true);
                    @endphp
                    <tr data-line-row="{{ $line->id }}">
                        <td class="px-2 py-2 text-center">
                            @if ($canReverseBankPayment)
                                <input type="checkbox" value="{{ $paymentId }}" data-statement-reversal-checkbox class="rounded border-slate-300 text-rose-700 focus:ring-rose-600" aria-label="Select bank-statement payment {{ $line->reference ?: $paymentId }}">
                            @endif
                        </td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs">{{ $line->txn_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-2 py-2 break-all">
                            <div class="font-mono text-xs font-semibold text-slate-900">{{ $line->reference ?: '—' }}</div>
                            @if ($line->line_type === 'mpesa_c2b')
                                <div class="mt-0.5 text-[11px] text-slate-400">M-Pesa match key</div>
                            @endif
                        </td>
                        <td class="px-2 py-2 break-words">
                            @if ($phone !== '')
                                <div class="text-xs font-semibold text-slate-900"><x-phone-link :value="$phone" /></div>
                                @if ($line->phone && trim((string) $line->phone) !== trim($phone))
                                    <div class="mt-0.5"><x-phone-link :value="$line->phone" class="text-[11px] text-slate-400" /></div>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-2 py-2 break-words text-xs">{{ $line->counterparty ?: '—' }}</td>
                        <td data-col="tenant" class="px-2 py-2 break-words">
                            @if (data_get($line->payment?->meta, 'split_payment'))
                                @php $splitRecipients = (array) data_get($line->payment?->meta, 'split_recipients', []); @endphp
                                <div class="text-xs font-semibold text-emerald-800">Split across {{ count($splitRecipients) }} tenants</div>
                                @foreach ($splitRecipients as $recipient)
                                    <div class="text-xs text-slate-700">
                                        {{ $recipient['name'] ?? 'Tenant' }} · {{ \App\Services\Property\PropertyMoney::kes((float) ($recipient['amount'] ?? 0)) }}
                                    </div>
                                @endforeach
                            @elseif ($tenantAccount !== '' || $tenantName !== '')
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
                        <td class="px-2 py-2 whitespace-nowrap tabular-nums text-xs {{ $line->direction === 'debit' ? 'text-rose-700' : 'text-emerald-800' }}">
                            {{ $line->direction === 'debit' ? '−' : '+' }}{{ \App\Services\Property\PropertyMoney::kes((float) $line->amount) }}
                        </td>
                        <td data-col="status" class="px-2 py-2 break-words">
                            @php $shownStatus = $line->displayMatchStatus(); @endphp
                            <span class="text-xs font-semibold {{ in_array($shownStatus, ['Matched', 'Landlord', 'Employee', 'Vendor'], true) ? 'text-emerald-700' : ($shownStatus === 'Unmatched' ? 'text-amber-700' : 'text-slate-500') }}">
                                {{ $shownStatus }}
                            </span>
                        </td>
                        <td data-col="actions" class="px-2 py-2 text-left">
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if (! $line->isAllocatedToTenant() && $line->direction === 'credit' && $line->line_type === 'mpesa_c2b')
                                    <form method="POST" action="{{ route('property.revenue.statements.lines.assign', [$statement, $line]) }}" data-statement-assign data-line-id="{{ $line->id }}" data-line-phone="{{ $phone }}" data-line-counterparty="{{ $line->counterparty }}" class="flex flex-wrap items-center gap-1">
                                        @csrf
                                        <input type="hidden" name="tenant_id" value="">
                                        <div class="relative flex items-center">
                                            <input type="text" data-tenant-query data-search-url="{{ route('property.revenue.statements.search.tenants', [], false) }}" data-auto-submit="off" autocomplete="off" placeholder="Tenant, account, or phone" required class="h-8 w-40 max-w-full rounded-lg border border-slate-300 px-2 pr-6 text-xs focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition-all" />
                                            <span data-search-spinner class="pointer-events-none absolute right-1.5 hidden text-slate-400">
                                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            </span>
                                        </div>
                                        <button type="submit" class="rounded-lg bg-emerald-700 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-800 transition-colors whitespace-nowrap">Assign</button>
                                        <button
                                            type="button"
                                            data-open-statement-split
                                            data-split-url="{{ route('property.revenue.statements.lines.assign', [$statement, $line]) }}"
                                            data-split-amount="{{ number_format((float) $line->amount, 2, '.', '') }}"
                                            data-split-reference="{{ $line->reference }}"
                                            class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-800 hover:bg-emerald-50 whitespace-nowrap"
                                        >Split</button>
                                    </form>
                                @elseif ($paymentId > 0)
                                    <a href="{{ route('property.payments.receipt.show', $paymentId) }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-900 hover:bg-emerald-100">
                                        View receipt
                                    </a>
                                    @if ($line->payment && strtolower(trim((string) $line->payment->channel)) === 'bank_statement' && $line->payment->status === \App\Models\PmPayment::STATUS_COMPLETED && ! in_array($line->payment->reversal_status, [\App\Models\PmPayment::REVERSAL_STATUS_PENDING, \App\Models\PmPayment::REVERSAL_STATUS_REVERSED], true))
                                        <form method="POST" action="{{ route('property.payments.bank_statement.reverse_match', $line->payment) }}" data-swal-title="Reverse this bank-statement match?" data-swal-confirm="This reverses the imported payment, releases its active invoice allocations and tenant credit, and returns linked statement lines to Unmatched. It does not initiate a bank refund." data-swal-confirm-text="Reverse match" class="inline">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-rose-300 bg-white px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reverse match</button>
                                        </form>
                                    @elseif ($line->payment?->reversal_status === \App\Models\PmPayment::REVERSAL_STATUS_PENDING)
                                        <span class="text-xs text-amber-700">Reversal pending</span>
                                    @elseif ($line->payment?->reversal_status === \App\Models\PmPayment::REVERSAL_STATUS_REVERSED)
                                        <span class="text-xs text-slate-400">Reversed</span>
                                    @endif
                                @elseif ($tenantId)
                                    <a href="{{ route('property.tenants.show', $tenantId) }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        Open tenant
                                    </a>
                                @elseif ((string) $line->match_status === 'bank_only')
                                    <form method="POST" action="{{ route('property.revenue.statements.lines.payee', [$statement, $line]) }}" data-statement-payee class="flex flex-wrap items-center gap-1">
                                        @csrf
                                        <select name="paid_to_kind" data-payee-kind class="h-8 rounded-lg border border-slate-300 bg-white px-2 text-xs">
                                            <option value="landlord" @selected(($line->paid_to_kind ?: 'landlord') === 'landlord')>Landlord</option>
                                            <option value="employee" @selected($line->paid_to_kind === 'employee')>Employee</option>
                                            <option value="vendor" @selected($line->paid_to_kind === 'vendor')>Vendor</option>
                                            <option value="bank_charge" @selected($line->paid_to_kind === 'bank_charge')>Bank charge</option>
                                            <option value="other" @selected($line->paid_to_kind === 'other')>Other</option>
                                        </select>
                                        <input type="hidden" name="landlord_id" value="{{ $line->paid_to_landlord_id }}">
                                        <input type="hidden" name="employee_id" value="{{ $line->paid_to_employee_id }}">
                                        <input type="hidden" name="vendor_id" value="{{ $line->paid_to_vendor_id }}">
                                        <div class="relative flex items-center">
                                            <input type="text" data-payee-query autocomplete="off" placeholder="Choose payee" value="{{ in_array($line->paid_to_kind, ['landlord', 'employee', 'vendor'], true) ? $line->paid_to_name : '' }}" class="h-8 w-36 max-w-full rounded-lg border border-slate-300 px-2 pr-6 text-xs focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                                            <span data-search-spinner class="pointer-events-none absolute right-1.5 hidden text-slate-400">
                                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            </span>
                                        </div>
                                        <input type="text" name="paid_to_name" value="{{ $line->paid_to_kind === 'other' ? $line->paid_to_name : '' }}" placeholder="Who was paid" class="h-8 w-28 max-w-full rounded-lg border border-slate-300 px-2 text-xs">
                                        <input type="text" name="paid_to_note" value="{{ $line->paid_to_note }}" placeholder="Cheque / note" class="h-8 w-24 max-w-full rounded-lg border border-slate-300 px-2 text-xs">
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
                        <td colspan="9" class="px-3 py-8 text-center text-slate-500">No lines in this group.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $lines->links() }}</div>

    <div data-statement-split-modal class="fixed inset-0 z-[7000] hidden items-center justify-center bg-slate-950/50 p-4" aria-hidden="true">
        <section role="dialog" aria-modal="true" aria-labelledby="statement-split-title" class="w-full max-w-2xl rounded-2xl bg-white p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="statement-split-title" class="text-lg font-bold text-slate-900">Split bank receipt</h2>
                    <p class="mt-1 text-sm text-slate-600">Assign this one receipt across tenants. The amounts must add up to the full transaction.</p>
                </div>
                <button type="button" data-close-statement-split class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100" aria-label="Close">×</button>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm">
                <div>Receipt <span data-split-reference-label class="font-mono font-semibold"></span></div>
                <div>Total <span data-split-total-label class="font-bold tabular-nums"></span></div>
            </div>
            <form method="POST" data-statement-split data-search-url="{{ route('property.revenue.statements.search.tenants', [], false) }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="split" value="1">
                <div data-split-rows class="max-h-[50vh] space-y-2 overflow-y-auto pr-1"></div>
                <button type="button" data-add-split-recipient class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">+ Add tenant</button>
                <p data-split-total-status class="text-sm font-medium text-amber-700" aria-live="polite"></p>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" data-close-statement-split class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" data-submit-statement-split disabled class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Split &amp; post receipts</button>
                </div>
            </form>
        </section>
    </div>
</x-property.workspace>

<script>
    (function () {
        const landlords = @json($assignLandlords ?? []);
        const employees = @json($assignEmployees ?? []);
        const vendors = @json($assignVendors ?? []);
        let menu = null;

        function closeMenu() {
            if (menu) {
                menu.remove();
                menu = null;
            }
        }

        function initDropdowns() {
            function payeeFields(form) {
                const kind = form.querySelector('[data-payee-kind]')?.value || 'landlord';
                const query = form.querySelector('[data-payee-query]');
                const other = form.querySelector('[name="paid_to_name"]');
                if (query) {
                    query.hidden = !['landlord', 'employee', 'vendor'].includes(kind);
                    query.placeholder = kind === 'employee' ? 'Choose employee' : kind === 'vendor' ? 'Choose vendor' : 'Choose landlord';
                }
                if (other) {
                    other.hidden = kind !== 'other';
                }
            }

            document.querySelectorAll('[data-statement-payee]').forEach((form) => {
                const query = form.querySelector('[data-payee-query]');
                const hiddenByKind = {
                    landlord: form.querySelector('[name="landlord_id"]'),
                    employee: form.querySelector('[name="employee_id"]'),
                    vendor: form.querySelector('[name="vendor_id"]'),
                };
                const kind = form.querySelector('[data-payee-kind]');
                payeeFields(form);
                kind?.addEventListener('change', () => {
                    Object.entries(hiddenByKind).forEach(([payeeKind, input]) => { if (input && payeeKind !== kind.value) input.value = ''; });
                    if (query) query.value = '';
                    payeeFields(form);
                });
                if (!query) {
                    return;
                }
                query.addEventListener('input', () => {
                    const selectedHidden = hiddenByKind[kind.value];
                    if (selectedHidden) selectedHidden.value = '';
                    closeMenu();
                    const needle = query.value.trim().toLowerCase();
                    const source = kind.value === 'employee' ? employees : kind.value === 'vendor' ? vendors : landlords;
                    const items = needle === '' ? [] : source.filter((row) => (row.label || '').toLowerCase().includes(needle)).slice(0, 12);
                    if (items.length === 0) {
                        return;
                    }
                    const box = document.createElement('div');
                    box.setAttribute('data-tenant-menu', '1');
                    box.className = 'fixed z-[80] max-h-60 w-72 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg';
                    const rect = query.getBoundingClientRect();
                    box.style.top = (rect.bottom + 4) + 'px';
                    box.style.left = Math.max(8, rect.right - 288) + 'px';
                    items.forEach((payee) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'block w-full px-3 py-1.5 text-left text-xs text-slate-800 hover:bg-emerald-50';
                        button.textContent = payee.label;
                        button.addEventListener('mousedown', (event) => {
                            event.preventDefault();
                            if (selectedHidden) selectedHidden.value = String(payee.id);
                            query.value = payee.label;
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
                    const source = kind.value === 'employee' ? employees : kind.value === 'vendor' ? vendors : landlords;
                    const hidden = hiddenByKind[kind.value];
                    const typed = (query.value || '').trim().toLowerCase();
                    const match = source.find((row) => (row.label || '').toLowerCase() === typed)
                        || (hidden?.value ? source.find((row) => String(row.id) === hidden.value) : null);
                    if (['landlord', 'employee', 'vendor'].includes(kind.value) && !match) {
                        event.preventDefault();
                        query.setCustomValidity('Choose a payee from the list.');
                        query.reportValidity();
                        return;
                    }
                    query.setCustomValidity('');
                    if (hidden) {
                        hidden.value = String(match?.id ?? '');
                    }
                });
            });
        }

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-tenant-menu]') && !event.target.closest('[data-tenant-query]') && !event.target.closest('[data-payee-query]')) {
                closeMenu();
            }
        });

        initDropdowns();
        document.addEventListener('turbo:render', initDropdowns);
        document.addEventListener('turbo:frame-render', initDropdowns);
    })();
</script>
