@php
    $readingAnomalies = $readingAnomalies ?? [];
@endphp
<div class="space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recorded readings</h3>
        <form method="get" action="{{ route('property.revenue.utilities', absolute: false) }}" class="flex flex-wrap items-end gap-2 w-full sm:w-auto">
            <input type="search" name="wr_q" value="{{ $filters['wr_q'] ?? '' }}" placeholder="Search unit…" class="flex-1 min-w-[140px] rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]" />
            <input type="month" name="wr_month" value="{{ $filters['wr_month'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]" />
            <select name="wr_status" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]">
                <option value="">All statuses</option>
                <option value="recorded" @selected(($filters['wr_status'] ?? '') === 'recorded')>Recorded</option>
                <option value="invoiced" @selected(($filters['wr_status'] ?? '') === 'invoiced')>Invoiced</option>
            </select>
            <select name="wr_property_id" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px] max-w-[160px]">
                <option value="0">All properties</option>
                @foreach(($wrProperties ?? []) as $p)
                    <option value="{{ (int) $p->id }}" @selected((int) ($filters['wr_property_id'] ?? 0) === (int) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}" />
            <input type="hidden" name="charge_type" value="{{ $filters['charge_type'] ?? '' }}" />
            <input type="hidden" name="month" value="{{ $filters['month'] ?? '' }}" />
            <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white min-h-[44px]">Filter</button>
        </form>
    </div>

    <form method="post" action="{{ route('property.revenue.utilities.water_readings.bulk_action') }}" class="space-y-2">
        @csrf
        <div class="flex flex-wrap items-center gap-2">
            <select name="action" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]">
                <option value="delete">Delete selected (uninvoiced)</option>
            </select>
            <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white min-h-[44px]">Bulk action</button>
            @error('reading_ids')<p class="text-xs text-red-600 w-full">{{ $message }}</p>@enderror
        </div>

        <x-property.responsive.table-wrapper>
            <table class="property-erp-table min-w-full border-collapse text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 w-10"></th>
                        <th class="px-3 py-2">Month</th>
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Prev</th>
                        <th class="px-3 py-2">Curr</th>
                        <th class="px-3 py-2">Used</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($waterReadings ?? collect() as $r)
                        @php $signals = $readingAnomalies[$r->id] ?? []; @endphp
                        <tr class="border-t border-slate-100 {{ collect($signals)->contains(fn ($s) => ($s['severity'] ?? '') === 'critical') ? 'bg-red-50/40' : (collect($signals)->contains(fn ($s) => ($s['severity'] ?? '') === 'warning') ? 'bg-amber-50/30' : '') }}">
                            <td class="px-3 py-2"><input type="checkbox" name="reading_ids[]" value="{{ (int) $r->id }}" @disabled($r->pm_invoice_id !== null) class="h-4 w-4 rounded" /></td>
                            <td class="px-3 py-2">{{ $r->billing_month }}</td>
                            <td class="px-3 py-2">{{ $r->unit->property->name ?? '—' }} / {{ $r->unit->label ?? '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ number_format((float) $r->previous_reading, 3) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ number_format((float) $r->current_reading, 3) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ number_format((float) $r->units_used, 3) }}</td>
                            <td class="px-3 py-2">
                                <div class="space-y-1">
                                    <span class="font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</span>
                                    @if ($r->invoice)
                                        <x-property.utility.invoice-allocation-bar
                                            :amount="(float) $r->invoice->amount"
                                            :paid="(float) $r->invoice->amount_paid"
                                            :invoice-no="(string) $r->invoice->invoice_no"
                                            :invoice-id="(int) $r->invoice->id"
                                            :status="ucfirst((string) $r->invoice->status)"
                                            class="max-w-xs"
                                        />
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <span class="text-xs font-semibold uppercase">{{ ucfirst((string) $r->status) }}</span>
                                @if ($signals !== [])
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($signals as $signal)
                                            @include('property.agent.partials.utility_anomaly_badge', ['anomaly' => $signal])
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">
                                @if ($r->pm_invoice_id === null)
                                    <details class="text-xs">
                                        <summary class="cursor-pointer font-semibold text-indigo-700 hover:underline">Edit</summary>
                                        <form method="post" action="{{ route('property.revenue.utilities.water_readings.update', $r, false) }}" class="mt-2 space-y-2 min-w-[12rem] rounded-lg border border-slate-200 bg-slate-50 p-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="block text-[10px] font-semibold uppercase text-slate-500">Previous</label>
                                            <input type="number" step="0.001" min="0" name="previous_reading" value="{{ (float) $r->previous_reading }}" class="w-full rounded border border-slate-200 text-sm px-2 py-1" />
                                            <label class="block text-[10px] font-semibold uppercase text-slate-500">Current</label>
                                            <input type="number" step="0.001" min="0" name="current_reading" value="{{ (float) $r->current_reading }}" required class="w-full rounded border border-slate-200 text-sm px-2 py-1" />
                                            <label class="block text-[10px] font-semibold uppercase text-slate-500">Rate / unit</label>
                                            <input type="number" step="0.01" min="0" name="rate_per_unit" value="{{ (float) $r->rate_per_unit }}" required class="w-full rounded border border-slate-200 text-sm px-2 py-1" />
                                            <label class="block text-[10px] font-semibold uppercase text-slate-500">Fixed</label>
                                            <input type="number" step="0.01" min="0" name="fixed_charge" value="{{ (float) $r->fixed_charge }}" class="w-full rounded border border-slate-200 text-sm px-2 py-1" />
                                            <button type="submit" class="w-full rounded bg-indigo-600 px-2 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Save</button>
                                        </form>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">Invoiced</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">No readings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-property.responsive.table-wrapper>
    </form>

    @if (method_exists($waterReadings ?? null, 'links'))
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
            <p>Showing {{ $waterReadings->firstItem() ?? 0 }}–{{ $waterReadings->lastItem() ?? 0 }} of {{ $waterReadings->total() }}</p>
            {{ $waterReadings->links() }}
        </div>
    @endif
</div>
