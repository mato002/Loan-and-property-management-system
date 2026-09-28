@php
    $s = $settlement ?? null;
    $stats = $s ? [
        ['label' => 'Net amount due', 'value' => \App\Services\Property\PropertyMoney::kes((float) $s['net_amount_due']), 'hint' => 'After fees & deductions'],
        ['label' => 'Rent received', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($s['rent_received'] ?? 0)), 'hint' => 'Period collections'],
        ['label' => 'Management fee', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($s['management_fee'] ?? 0)), 'hint' => ($s['commission_percent'] ?? 0).'% commission'],
        ['label' => 'Balance B/F', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($s['balance_brought_forward'] ?? 0)), 'hint' => 'Landlord ledger'],
    ] : [
        ['label' => 'Settlements', 'value' => '—', 'hint' => 'Select property, landlord & month'],
    ];

    $unitStats = $s['unit_stats'] ?? [];
    $unitTotals = $s['unit_totals'] ?? [];
    $tableRows = collect($s['unit_lines'] ?? [])->map(fn ($line) => [
        (string) ($line['unit_label'] ?? '—'),
        (string) ($line['tenant_name'] ?? '—'),
        \App\Services\Property\PropertyMoney::kes((float) ($line['rent_per_month'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['rent_bf'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_bf'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['water_bf'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['rent_billed'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_billed'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['water_billed'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['rent_received'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_received'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($line['water_received'] ?? 0)),
    ])->all();
@endphp

<x-property.workspace
    title="Landlord settlements"
    subtitle="Property account statement: unit Bal B/F, monthly charges, paid, additions, deductions, and net remittance."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="[
        'Unit',
        'Tenant names',
        'Rent / month',
        'B/F Rent',
        'B/F Garbage',
        'B/F Water',
        'Exp Rent',
        'Exp Garbage',
        'Exp Water',
        'Paid Rent',
        'Paid Garbage',
        'Paid Water',
    ]"
    :table-rows="$tableRows"
    :empty-title="$s ? 'No units on this property' : 'Choose filters to preview settlement'"
    :empty-hint="$s ? 'Add units to the property to build the statement matrix.' : 'Property + landlord + month.'"
>
    <x-slot name="actions">
        @if ($s)
            @php
                $exportQuery = array_filter([
                    'property_id' => $filters['property_id'] ?? null,
                    'landlord_id' => $filters['landlord_id'] ?? null,
                    'month' => $filters['month'] ?? null,
                ]);
            @endphp
            <a href="{{ route('property.accounting.payables.landlord_settlements', array_merge($exportQuery, ['export' => 'pdf'])) }}" data-turbo="false" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Export PDF</a>
            <a href="{{ route('property.accounting.payables.landlord_settlements', array_merge($exportQuery, ['export' => 'csv'])) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Export CSV</a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print</button>
        @endif
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.landlord_settlements') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600">Property</label>
                <select name="property_id" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[12rem]">
                    <option value="">Select property…</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Landlord</label>
                <select name="landlord_id" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[12rem]" @disabled((int) ($filters['property_id'] ?? 0) <= 0)>
                    <option value="">Select landlord…</option>
                    @foreach ($landlords as $landlord)
                        <option value="{{ $landlord->id }}" @selected((int) ($filters['landlord_id'] ?? 0) === (int) $landlord->id)>
                            {{ $landlord->name }} ({{ rtrim(rtrim(number_format((float) $landlord->ownership_percent, 2, '.', ''), '0'), '.') }}%)
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Month</label>
                <input type="month" name="month" value="{{ $filters['month'] ?? now()->format('Y-m') }}" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Preview</button>
        </form>
    </x-slot>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if (! empty($settlementError))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ $settlementError }}</div>
    @endif

    @if ($s)
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
            <h3 class="font-semibold text-slate-900">{{ $s['property_name'] }} — {{ $s['period_label'] }}</h3>
            <p class="mt-1 text-slate-600">
                Landlord: {{ $s['landlord_name'] }}
                · {{ $s['period_range_label'] ?? '' }}
                · {{ $s['ownership_percent'] }}% ownership
                · {{ $s['commission_percent'] }}% management fee
            </p>
            <p class="mt-2 text-xs font-semibold text-slate-700">
                OCCUPIED UNITS: {{ (int) ($unitStats['units_occupied'] ?? 0) }}
                &nbsp;|&nbsp;
                VACANT UNITS: {{ (int) ($unitStats['units_vacant'] ?? 0) }}
            </p>
            @if (! empty($s['agreed_pay_day']))
                <p class="mt-1 text-xs text-indigo-700">Agreed pay day: {{ $s['agreed_pay_day'] }}@if (! empty($s['next_agreed_pay_date'])) · Next: {{ \Carbon\Carbon::parse($s['next_agreed_pay_date'])->format('d M Y') }}@endif</p>
            @endif
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <h3 class="font-semibold text-slate-900">Additions</h3>
                <ul class="mt-3 space-y-1">
                    @forelse ($s['additions'] ?? [] as $addition)
                        <li class="flex justify-between gap-4">
                            <span>{{ $addition['description'] ?? 'Addition' }}</span>
                            <span class="font-medium text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($addition['amount'] ?? 0)) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No additions in this period.</li>
                    @endforelse
                </ul>
                <div class="mt-3 flex justify-between border-t border-slate-100 pt-2 font-semibold">
                    <span>Total additions</span>
                    <span>{{ \App\Services\Property\PropertyMoney::kes((float) ($s['additions_total'] ?? 0)) }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <h3 class="font-semibold text-slate-900">Deductions</h3>
                <ul class="mt-3 space-y-1">
                    @forelse ($s['deductions'] ?? [] as $deduction)
                        <li class="flex justify-between gap-4">
                            <span>{{ $deduction['description'] ?? 'Deduction' }}</span>
                            <span class="font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($deduction['amount'] ?? 0)) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No deductions in this period.</li>
                    @endforelse
                </ul>
                <div class="mt-3 flex justify-between border-t border-slate-100 pt-2 font-semibold">
                    <span>Total deductions</span>
                    <span>{{ \App\Services\Property\PropertyMoney::kes((float) ($s['deductions_total'] ?? 0)) }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <h3 class="font-semibold text-slate-900">Statement summary</h3>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between"><dt>Rent received</dt><dd class="font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['rent_received'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between"><dt>Total utility</dt><dd class="font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['utility_received'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between"><dt>Less management fee ({{ $s['commission_percent'] ?? 0 }}%)</dt><dd class="font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($s['management_fee'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between"><dt>Less other expenses</dt><dd class="font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($s['other_expenses'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between"><dt>Add total additions</dt><dd class="font-medium text-emerald-700">+ {{ \App\Services\Property\PropertyMoney::kes((float) ($s['additions_total'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between"><dt>Less total deductions</dt><dd class="font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($s['deductions_total'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between"><dt>Balance B/F</dt><dd class="font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['balance_brought_forward'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="font-semibold">Net amount due</dt><dd class="font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['net_amount_due'] ?? 0)) }}</dd></div>
                </dl>
            </div>
        </div>

        @if (! empty($s['open_advances']))
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Open advance payments</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($s['open_advances'] as $advance)
                        <li class="flex justify-between gap-4">
                            <span>{{ $advance['description'] ?? 'Advance' }}@if (! empty($advance['agreed_pay_date'])) · due {{ \Carbon\Carbon::parse($advance['agreed_pay_date'])->format('d M Y') }}@endif</span>
                            <span class="font-medium text-amber-800">{{ \App\Services\Property\PropertyMoney::kes((float) ($advance['amount'] ?? 0)) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-slate-500">Total open advances: {{ \App\Services\Property\PropertyMoney::kes((float) ($s['open_advances_total'] ?? 0)) }} · <a href="{{ route('property.accounting.payables.landlord_advances', ['property_id' => $s['property_id'], 'landlord_id' => $s['landlord_id'], 'status' => 'open']) }}" class="text-indigo-700 hover:text-indigo-800">Manage advances</a></p>
            </div>
        @endif

        @if ((float) ($s['net_amount_due'] ?? 0) > 0)
            <form method="post" action="{{ route('property.accounting.payables.landlord_settlements.payout') }}" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 flex flex-wrap items-center justify-between gap-3">
                @csrf
                <input type="hidden" name="property_id" value="{{ $s['property_id'] }}" />
                <input type="hidden" name="landlord_id" value="{{ $s['landlord_id'] }}" />
                <input type="hidden" name="month" value="{{ $s['period_month'] }}" />
                <div class="text-sm text-indigo-950">
                    Create draft payout for <strong>{{ \App\Services\Property\PropertyMoney::kes((float) $s['net_amount_due']) }}</strong>
                    to {{ $s['landlord_name'] }} ({{ $s['property_name'] }}, {{ $s['period_label'] }}).
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create draft payout</button>
            </form>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                No remittance due for this period (ledger closing balance is zero or negative).
            </div>
        @endif
    @endif
</x-property.workspace>
