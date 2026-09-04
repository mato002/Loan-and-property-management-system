<x-property.workspace
    title="Landlord payment & fees"
    subtitle="Period landlord remittances, management fees, and payout status — like EZEN landlord payment workspace."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="[]"
    :table-rows="[]"
    :show-search="false"
    :compact-list="true"
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'route' => 'property.accounting.payables.landlord_payment_fees',
            'query' => request()->except(['export', 'format', 'page']),
        ])
        <a
            href="{{ route('property.accounting.payables.landlord_advances') }}"
            class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-800 hover:bg-indigo-100"
        >Advances &amp; pay dates</a>
        <a
            href="{{ route('property.reports.landlord.statements', request()->only(['property_id', 'landlord_id'])) }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >View &amp; print statements</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.landlord_payment_fees') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All properties</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
                <select name="landlord_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All landlords</option>
                    @foreach ($landlords as $landlord)
                        <option value="{{ $landlord->id }}" @selected((int) ($filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                <input type="month" name="month" value="{{ $filters['month'] ?? $period_month }}" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                    @foreach (['all' => 'All', 'due' => 'Due', 'draft' => 'Draft payout', 'approved' => 'Approved', 'paid' => 'Paid', 'overdrawn' => 'Overdrawn', 'settled' => 'Settled'] as $value => $label)
                        <option value="{{ $value === 'all' ? '' : $value }}" @selected(($filters['status'] ?? '') === ($value === 'all' ? '' : $value))>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Property or landlord…" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm w-44" />
            </div>
            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm mt-5">
                <input type="checkbox" name="show_zero" value="1" @checked($filters['show_zero'] ?? false) class="rounded border-slate-300" />
                Show zero rows
            </label>
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 mt-5">Search</button>
            <a href="{{ route('property.accounting.payables.landlord_payment_fees') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 mt-5">Reset</a>
        </form>
    </x-slot>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('property.accounting.payables.landlord_payment_fees.batch') }}" id="landlord-payment-fees-batch-form">
        @csrf
        <input type="hidden" name="month" value="{{ $period_month }}" />

        <div class="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 p-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Batch actions</span>
            <button type="submit" name="action" value="create_draft" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-800 hover:bg-indigo-100">Create draft payouts</button>
            <button type="submit" name="action" value="post_fees_only" class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-900 hover:bg-violet-100">Post fees only</button>
            <button type="submit" name="action" value="approve" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-900 hover:bg-amber-100">Approve selected</button>
            <button type="submit" name="action" value="pay_post" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-900 hover:bg-emerald-100">Pay &amp; post</button>
            <span class="text-xs text-slate-500 dark:text-slate-400">Period: {{ $period_label }}</span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-sm">
            <table class="min-w-[1100px] w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-3 w-10">
                            <input type="checkbox" id="lpf-select-all" class="rounded border-slate-300" aria-label="Select all rows" />
                        </th>
                        <th class="px-3 py-3">Property</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3">On</th>
                        <th class="px-3 py-3">Date prepared</th>
                        <th class="px-3 py-3">Period</th>
                        <th class="px-3 py-3 text-right">Mgt fees</th>
                        <th class="px-3 py-3 text-right">Mgt fees tax</th>
                        <th class="px-3 py-3 text-right">Amt payable</th>
                        <th class="px-3 py-3 text-right">Paid/posted</th>
                        <th class="px-3 py-3">Paid/posted on</th>
                        <th class="px-3 py-3">Agreed pay</th>
                        <th class="px-3 py-3 text-right">Open adv.</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Fees posted</th>
                        <th class="px-3 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $rowKey = $row['property_id'].'|'.$row['landlord_id'];
                            $propertyLabel = trim(($row['property_code'] !== '' ? '['.$row['property_code'].'] ' : '').($row['property_name'] ?? ''));
                            $toneClass = match ($row['status'] ?? '') {
                                'overdrawn' => 'bg-rose-50/80 dark:bg-rose-950/20',
                                'due', 'draft' => 'bg-amber-50/70 dark:bg-amber-950/15',
                                'paid' => 'bg-emerald-50/50 dark:bg-emerald-950/10',
                                default => '',
                            };
                            $statusBadge = match ($row['status'] ?? '') {
                                'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                'approved' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
                                'draft' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                                'due' => 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
                                'overdrawn' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
                            };
                        @endphp
                        <tr class="border-t border-slate-100 dark:border-slate-800 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 {{ $toneClass }}">
                            <td class="px-3 py-3">
                                <input type="checkbox" name="selection[]" value="{{ $rowKey }}" class="lpf-row-check rounded border-slate-300" />
                            </td>
                            <td class="px-3 py-3 font-medium text-slate-900 dark:text-white">
                                <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'month' => $row['period_month']]) }}" class="text-indigo-700 hover:text-indigo-800 dark:text-indigo-300">{{ $propertyLabel }}</a>
                                <div class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $row['landlord_name'] ?? '' }}</div>
                            </td>
                            <td class="px-3 py-3">{{ $row['statement_type'] ?? 'Final' }}</td>
                            <td class="px-3 py-3">{{ $row['on'] ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $row['date_prepared'] ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $row['period'] ?? '—' }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['management_fee'] ?? 0)) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-slate-400">—</td>
                            <td class="px-3 py-3 text-right tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['amount_payable'] ?? 0)) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                @if (! is_null($row['paid_posted'] ?? null))
                                    {{ \App\Services\Property\PropertyMoney::kes((float) $row['paid_posted']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3">{{ $row['paid_posted_on'] ?? '—' }}</td>
                            <td class="px-3 py-3">
                                @if (! empty($row['agreed_pay_day']))
                                    <div>{{ $row['next_agreed_pay_label'] ?? '—' }}</div>
                                    <div class="text-xs text-slate-500">Day {{ $row['agreed_pay_day'] }}</div>
                                @else
                                    <a href="{{ route('property.accounting.payables.landlord_advances', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'open' => 'schedule']) }}" class="text-xs text-indigo-700 hover:text-indigo-800">Set schedule</a>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                @if ((float) ($row['open_advance_total'] ?? 0) > 0)
                                    <a href="{{ route('property.accounting.payables.landlord_advances', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'status' => 'open']) }}" class="text-amber-800 hover:text-amber-900 dark:text-amber-200">{{ \App\Services\Property\PropertyMoney::kes((float) $row['open_advance_total']) }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge }}">{{ ucfirst((string) ($row['status'] ?? '—')) }}</span>
                            </td>
                            <td class="px-3 py-3">
                                @if (! empty($row['fees_posted']))
                                    <span class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-800 dark:bg-violet-900/40 dark:text-violet-200">Posted</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'month' => $row['period_month']]) }}" class="text-indigo-700 hover:text-indigo-800">Detail</a>
                                    <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'month' => $row['period_month'], 'export' => 'pdf']) }}" data-turbo="false" target="_blank" class="text-slate-700 hover:text-slate-900">PDF</a>
                                    @if (! empty($row['payout_id']))
                                        <a href="{{ route('property.accounting.payables.landlord_payouts', ['status' => $row['payout_status'] ?? '']) }}" class="text-emerald-700 hover:text-emerald-800">Payout #{{ $row['payout_id'] }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                No landlord payment rows for {{ $period_label }}. Try another month or enable “Show zero rows”.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    @push('scripts')
    <script>
        document.getElementById('lpf-select-all')?.addEventListener('change', function () {
            document.querySelectorAll('.lpf-row-check').forEach((el) => { el.checked = this.checked; });
        });
        document.getElementById('landlord-payment-fees-batch-form')?.addEventListener('submit', function (event) {
            const checked = this.querySelectorAll('.lpf-row-check:checked').length;
            if (checked === 0) {
                event.preventDefault();
                alert('Select at least one row.');
            }
        });
    </script>
    @endpush
</x-property.workspace>
