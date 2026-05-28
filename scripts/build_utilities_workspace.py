#!/usr/bin/env python3
"""Generate utilities operations workspace blade partials."""
from pathlib import Path

base = Path('resources/views/property/agent/revenue/utilities')
orig_path = Path('resources/views/property/agent/revenue/utilities.blade.php')

# Read from git if current file is already overwritten and small
if orig_path.stat().st_size < 10000:
    import subprocess
    orig_text = subprocess.check_output(
        ['git', 'show', 'HEAD:resources/views/property/agent/revenue/utilities.blade.php']
    ).decode('utf-8')
else:
    orig_text = orig_path.read_text(encoding='utf-8')

orig = orig_text.splitlines()

extra_methods = """
                setTab(tab) {
                    this.activeTab = tab;
                    try { sessionStorage.setItem('utility_ops_tab', tab); } catch (e) {}
                },
                updateBulkFilledCount() {
                    const root = this.$refs.bulkReadingsRoot;
                    if (!root) { this.bulkFilledCount = 0; return; }
                    let n = 0;
                    root.querySelectorAll('[data-bulk-current]').forEach((el) => {
                        if (el instanceof HTMLInputElement && el.value !== '' && Number(el.value) >= 0) n++;
                    });
                    this.bulkFilledCount = n;
                },
                bulkRowVisible(label) {
                    const q = String(this.bulkFilter || '').trim().toLowerCase();
                    return !q || String(label || '').toLowerCase().includes(q);
                },
                async openPenaltyPreview() {
                    if (!this.penaltyPreviewUrl) return;
                    this.penaltyModalOpen = true;
                    this.penaltyLoading = true;
                    this.penaltyError = null;
                    this.penaltyRows = [];
                    this.penaltyTotal = 0;
                    try {
                        const res = await fetch(this.penaltyPreviewUrl, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) throw new Error('Preview failed');
                        const data = await res.json();
                        this.penaltyRows = data.rows || [];
                        this.penaltyTotal = Number(data.total_penalty || 0);
                        this.penaltyTotalDisplay = String(data.total_penalty_display || '');
                    } catch (e) {
                        this.penaltyError = e?.message || 'Could not load preview';
                    } finally {
                        this.penaltyLoading = false;
                    }
                },
                closePenaltyModal() { this.penaltyModalOpen = false; },"""

body = '\n'.join(orig[115:303])
body = body.replace(
    'showAddChargeForm: @js($utilityCreateFormHasErrors),\n                showWaterReadingForm: @js($utilityCreateFormHasErrors),',
    "activeTab: @js($utilityCreateFormHasErrors ? 'readings' : 'overview'),\n                penaltyModalOpen: false,\n                penaltyLoading: false,\n                penaltyRows: [],\n                penaltyTotal: 0,\n                penaltyTotalDisplay: '',\n                penaltyError: null,\n                bulkFilter: '',\n                bulkFilledCount: 0,\n                penaltyPreviewUrl: @js(route('property.revenue.utilities.water_penalties.preview', [], true)),",
)
body = body.replace('                showBulkWaterReadings: false,\n', '')
if 'setTab(tab)' not in body:
    body = body.replace('                isReadingRecorded(unitId) {', extra_methods + '\n                isReadingRecorded(unitId) {')

init_line = orig[303].replace(
    "x-init=\"if (!$store.utilityUi) { Alpine.store('utilityUi', { showBillingActions: false, showWaterReadingsTable: false, showReadiness: true }); } ",
    "x-init=\"try { const s = sessionStorage.getItem('utility_ops_tab'); if (s && ['overview','readings','billing','charges'].includes(s)) activeTab = s; } catch (e) {} ",
)

toolbar = (base / '_toolbar.blade.php')
toolbar.write_text("""<form method="get" action="{{ route('property.revenue.utilities', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Search label or unit…" class="w-full min-w-0 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]" />
    <select name="charge_type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        <option value="">Type: All</option>
        <option value="water" @selected(($filters['charge_type'] ?? '') === 'water')>Water</option>
        <option value="electricity" @selected(($filters['charge_type'] ?? '') === 'electricity')>Electricity</option>
        <option value="service" @selected(($filters['charge_type'] ?? '') === 'service')>Service</option>
        <option value="garbage" @selected(($filters['charge_type'] ?? '') === 'garbage')>Garbage</option>
        <option value="other" @selected(($filters['charge_type'] ?? '') === 'other')>Other</option>
    </select>
    <input type="month" name="month" value="{{ $filters['month'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]" />
    <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        <option value="id" @selected(($filters['sort'] ?? 'id') === 'id')>Sort: ID</option>
        <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Added date</option>
        <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Amount</option>
        <option value="label" @selected(($filters['sort'] ?? '') === 'label')>Label</option>
        <option value="billing_month" @selected(($filters['sort'] ?? '') === 'billing_month')>Billing month</option>
    </select>
    <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
        <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
    </select>
    <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        @foreach ([10, 30, 50, 100, 200] as $size)
            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
        @endforeach
    </select>
    <button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-700 min-h-[44px]">Apply</button>
    <a href="{{ route('property.revenue.utilities', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 min-h-[44px] inline-flex items-center">Reset</a>
    @include('property.agent.partials.export_dropdown', [
        'csvUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'csv']), false),
        'xlsUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'xls']), false),
        'pdfUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'pdf']), false),
    ])
</form>
""", encoding='utf-8')

overview = '\n'.join(orig[559:627])
overview = overview.replace('x-show="$store.utilityUi?.showReadiness" x-cloak ', '')
overview = overview.replace('class="mt-6 ', 'class="')
(base / '_tab_overview.blade.php').write_text(overview, encoding='utf-8')

(base / '_readings_list.blade.php').write_text(Path(base / '_readings_list.blade.php').read_text(encoding='utf-8') if (base / '_readings_list.blade.php').exists() else '', encoding='utf-8')

readings_list = """@php
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

        <div class="md:hidden space-y-2">
            @forelse ($waterReadings ?? collect() as $r)
                <x-property.utility.reading-card :reading="$r" :anomalies="$readingAnomalies[$r->id] ?? []" selectable />
            @empty
                <p class="text-sm text-slate-500 py-6 text-center">No readings match filters.</p>
            @endforelse
        </div>

        <x-property.responsive.table-wrapper class="hidden md:block">
            <table class="min-w-full border-collapse text-sm">
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
                                    <span class="font-semibold tabular-nums">{{ \\App\\Services\\Property\\PropertyMoney::kes((float) $r->amount) }}</span>
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
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">No readings yet.</td></tr>
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
"""
(base / '_readings_list.blade.php').write_text(readings_list.replace('\\\\App\\\\', '\\App\\'), encoding='utf-8')

charges_list = """<div class="space-y-4">
    <div class="md:hidden space-y-2">
        @forelse ($charges as $c)
            <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $c->label }}</p>
                        <p class="text-xs text-slate-500">{{ $c->unit->property->name ?? '—' }} · {{ $c->unit->label ?? '—' }}</p>
                    </div>
                    <p class="font-bold text-slate-900 tabular-nums">{{ \\App\\Services\\Property\\PropertyMoney::kes((float) $c->amount) }}</p>
                </div>
                <p class="mt-2 text-xs text-slate-600">{{ $c->created_at->format('Y-m-d') }} · {{ $c->notes ?: '—' }}</p>
                <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" class="mt-2" data-swal-confirm="Delete this charge line?">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs font-semibold text-rose-600">Remove</button>
                </form>
            </article>
        @empty
            <p class="text-sm text-slate-500 py-8 text-center">No charge lines yet.</p>
        @endforelse
    </div>

    <x-property.responsive.table-wrapper class="hidden md:block">
        <table class="min-w-full border-collapse text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Label</th>
                    <th class="px-3 py-2">Unit</th>
                    <th class="px-3 py-2">Usage</th>
                    <th class="px-3 py-2">Added</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Notes</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charges as $c)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                        <td class="px-3 py-2 font-medium">{{ $c->label }}</td>
                        <td class="px-3 py-2">{{ $c->unit->property->name ?? '—' }} / {{ $c->unit->label ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                            @if (($c->units_consumed ?? null) !== null || ($c->rate_per_unit ?? null) !== null || ($c->fixed_charge ?? null) !== null)
                                U {{ number_format((float) ($c->units_consumed ?? 0), 3) }} · R {{ number_format((float) ($c->rate_per_unit ?? 0), 2) }} · F {{ number_format((float) ($c->fixed_charge ?? 0), 2) }}
                            @else — @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600">{{ $c->created_at->format('Y-m-d') }}</td>
                        <td class="px-3 py-2 tabular-nums font-semibold">{{ \\App\\Services\\Property\\PropertyMoney::kes((float) $c->amount) }}</td>
                        <td class="px-3 py-2 text-slate-600 max-w-xs truncate">{{ $c->notes ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" data-swal-confirm="Delete this charge line?">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No utility charges yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-property.responsive.table-wrapper>

    @if (method_exists($charges, 'links'))
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
            <p>Showing {{ $charges->firstItem() ?? 0 }}–{{ $charges->lastItem() ?? 0 }} of {{ $charges->total() }}</p>
            {{ $charges->links() }}
        </div>
    @endif
</div>
"""
(base / '_charges_list.blade.php').write_text(charges_list.replace('\\\\App\\\\', '\\App\\'), encoding='utf-8')

capture = '\n'.join(orig[436:556])
capture = capture.replace('        <div x-show="showWaterReadingForm" x-cloak class="lg:col-span-2 ', '        <div class="')
capture = capture.replace('        </div>\n        </div>\n        </div>', '        </div>')

bulk_mobile = """
                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" x-model="bulkFilter" placeholder="Filter units…" class="flex-1 min-w-[140px] rounded-lg border border-slate-200 text-sm px-3 py-2 min-h-[44px]" />
                    <span class="text-xs font-semibold text-teal-800 tabular-nums" x-text="`${bulkFilledCount} filled`"></span>
                </div>
                <div x-ref="bulkReadingsRoot" class="utility-bulk-grid" x-init="$nextTick(() => updateBulkFilledCount())">
                    @foreach ($waterUnitOptions as $unit)
                        <div
                            x-show="Number(selectedWaterPropertyId) === {{ (int) $unit['property_id'] }} && bulkRowVisible(@js($unit['label']))"
                            x-cloak
                            class="utility-bulk-card"
                            :class="{ 'is-recorded': isReadingRecorded({{ (int) $unit['id'] }}) }"
                        >
                            <p class="text-sm font-semibold text-slate-900">{{ $unit['label'] }}</p>
                            <label class="block text-[10px] font-semibold uppercase text-slate-500">Previous</label>
                            <input type="number" step="0.001" min="0" name="previous_readings[{{ (int) $unit['id'] }}]" data-water-bulk-prev="{{ (int) $unit['id'] }}" value="{{ old('previous_readings.'.(int) $unit['id']) }}" class="w-full rounded-lg border border-slate-200 text-sm px-2 py-2 min-h-[44px]" />
                            <label class="block text-[10px] font-semibold uppercase text-slate-500 mt-1">Current</label>
                            <input type="number" step="0.001" min="0" name="current_readings[{{ (int) $unit['id'] }}]" data-bulk-current value="{{ old('current_readings.'.(int) $unit['id']) }}" @input="updateBulkFilledCount()" class="w-full rounded-lg border border-slate-200 text-sm px-2 py-2 min-h-[44px]" placeholder="Reading" />
                        </div>
                    @endforeach
                </div>
                <div class="utility-bulk-table-wrap">"""

capture = capture.replace(
    '                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">',
    bulk_mobile,
)
capture = capture.replace(
    '                    </table>\n                </div>',
    '                    </table>\n                </div>\n                </div>',
)
capture = capture.replace(
    "name=\"current_readings[{{ (int) $unit['id'] }}]\"\n                                            value=",
    "name=\"current_readings[{{ (int) $unit['id'] }}]\" data-bulk-current @input=\"updateBulkFilledCount()\"\n                                            value=",
)

charge_form = '\n'.join(orig[350:434])
charge_form = charge_form.replace('<div x-show="showAddChargeForm" x-cloak>\n', '')
if charge_form.endswith('        </div>'):
    charge_form = charge_form[: -len('        </div>')]

billing = '\n'.join(orig[628:649])
billing = billing.replace('    <div x-show="$store.utilityUi?.showBillingActions" x-cloak class="mt-6 ', '    <div class="')
billing = billing.replace(
    '<button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">Apply overdue water penalties</button>',
    """<button type="button" @click="openPenaltyPreview()" class="rounded-lg bg-amber-100 border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-200 min-h-[44px]">Preview penalties</button>
                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700 min-h-[44px]" data-swal-confirm="Apply overdue water penalties now?">Apply penalties</button>""",
)

workspace_parts = [
    '<div',
    '            x-data="{',
    body,
    '            }"',
    '            ' + init_line.strip(),
    '            class="utility-ops-shell space-y-4"',
    '        >',
    """            @if (! empty($opsKpis))
                <x-property.utility.compact-kpi-strip :items="$opsKpis" />
            @endif

            <nav class="utility-ops-tabbar" aria-label="Utility operations">
                <button type="button" class="utility-ops-tab" :class="activeTab === 'overview' ? 'is-active' : ''" @click="setTab('overview')"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Overview</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'readings' ? 'is-active' : ''" @click="setTab('readings')"><i class="fa-solid fa-droplet" aria-hidden="true"></i> Readings</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'billing' ? 'is-active' : ''" @click="setTab('billing')"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Billing</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'charges' ? 'is-active' : ''" @click="setTab('charges')"><i class="fa-solid fa-list" aria-hidden="true"></i> Charges</button>
            </nav>""",
    "            <div x-show=\"activeTab === 'overview'\" x-cloak class=\"space-y-4\">",
    "                @include('property.agent.revenue.utilities._tab_overview')",
    """                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-center text-xs font-bold text-slate-800 min-h-[48px] flex items-center justify-center">Ledger</a>
                    <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" class="rounded-xl border border-teal-200 bg-teal-50 px-3 py-3 text-center text-xs font-bold text-teal-900 min-h-[48px] flex items-center justify-center">Reconcile</a>
                    <a href="{{ route('property.revenue.utilities.periods', absolute: false) }}" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-3 text-center text-xs font-bold text-indigo-900 min-h-[48px] flex items-center justify-center">Periods</a>
                    <button type="button" @click="setTab('readings')" class="rounded-xl bg-cyan-600 px-3 py-3 text-center text-xs font-bold text-white min-h-[48px]">Capture readings</button>
                </div>""",
    '            </div>',
    "            <div x-show=\"activeTab === 'readings'\" x-cloak class=\"space-y-4\">",
    capture,
    "                @include('property.agent.revenue.utilities._readings_list')",
    '            </div>',
    "            <div x-show=\"activeTab === 'billing'\" x-cloak>",
    billing,
    '            </div>',
    "            <div x-show=\"activeTab === 'charges'\" x-cloak class=\"space-y-4\">",
    charge_form,
    "                @include('property.agent.revenue.utilities._charges_list')",
    '            </div>',
    """            <div class="utility-sticky-bar md:hidden">
                <div class="utility-sticky-bar-inner">
                    <button type="button" @click="setTab('readings')" class="utility-sticky-btn bg-cyan-600 text-white">Readings</button>
                    <button type="button" @click="setTab('billing')" class="utility-sticky-btn bg-emerald-600 text-white">Bill</button>
                    <button type="button" @click="openPenaltyPreview()" class="utility-sticky-btn bg-amber-600 text-white">Penalties</button>
                    <button type="button" @click="setTab('charges')" class="utility-sticky-btn bg-slate-700 text-white">Charges</button>
                </div>
            </div>

            <x-property.utility.penalty-preview-modal />
        </div>""",
]
(base / '_workspace.blade.php').write_text('\n'.join(workspace_parts), encoding='utf-8')

main = """@include('property.agent.revenue.utilities._setup')

<x-property.workspace
    title="Water & Utility Operations"
    subtitle="Capture meter readings, run billing, and manage charge lines — utility AR stays separate from core rent."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="[]"
    empty-title="No utility charges"
    empty-hint="Use the workspace tabs to capture readings and manage charge lines."
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 min-h-[44px]">Ledger</a>
        <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl border border-teal-300 bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-900 hover:bg-teal-100 min-h-[44px]">Reconciliation</a>
        <a href="{{ route('property.revenue.utilities.periods', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-900 hover:bg-indigo-100 min-h-[44px]">Period closing</a>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.revenue.utilities._toolbar')
    </x-slot>

    <x-slot name="above">
        @include('property.agent.revenue.utilities._workspace')
    </x-slot>
</x-property.workspace>
"""
orig_path.write_text(main, encoding='utf-8')

setup_path = base / '_setup.blade.php'
setup = setup_path.read_text(encoding='utf-8')
if '$wrFilterActiveCount' not in setup:
    setup = setup.replace(
        '$filterActiveCount = collect([',
        "$wrFilterActiveCount = collect([\n        $filters['wr_q'] ?? '',\n        $filters['wr_month'] ?? '',\n        $filters['wr_status'] ?? '',\n        (int) ($filters['wr_property_id'] ?? 0) > 0 ? '1' : '',\n    ])->filter(fn ($v) => trim((string) $v) !== '')->count();\n    $filterActiveCount = collect([",
    )
setup_path.write_text(setup, encoding='utf-8')

for f in base.glob('_extract_*.txt'):
    f.unlink(missing_ok=True)

print('Done')
