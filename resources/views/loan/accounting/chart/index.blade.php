@php
    $co = $chartOverview ?? [];
    $fmtN = fn (int $n) => number_format($n);
    $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
    $chartBase = rtrim(route('loan.accounting.chart.index'), '/');
@endphp

<x-loan-layout>
    <x-loan.page title="Chart of Accounts" subtitle="Maintain your general ledger codes, map operational wallets (M-Pesa, cash, savings), and wire default debit/credit pairs for salary advances, loan ledger, and interest flows. Everything below stays in sync with journal posting and books of account.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.journal.index') }}" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-800 shadow-sm hover:bg-indigo-100 transition-colors">Journal</a>
            <a href="{{ route('loan.accounting.reports.hub') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reports</a>
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books</a>
            <a href="{{ route('loan.accounting.books.chart_rules') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Guidance</a>
            @include('loan.accounting.partials.export_buttons')
        </x-slot>

        <x-slot name="banner">
            <div class="rounded-2xl border border-violet-200/90 bg-gradient-to-br from-violet-100/50 via-white to-cyan-50/60 p-5 sm:p-6 shadow-md">
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 pb-4 border-b border-violet-200/50">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-indigo-700 text-white shadow-lg shrink-0" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-violet-800">Books of account · GL control</p>
                            <p class="text-sm text-slate-700 mt-1 max-w-xl">Snapshot of your chart: type mix, wallet coverage, rule completeness, and recent journal volume.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/90 border border-slate-200 px-3 py-1.5 font-medium shadow-sm">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
                            {{ now()->format('l, M j, Y') }}
                        </span>
                        <span class="rounded-full bg-white/90 border border-violet-200 px-3 py-1.5 font-semibold text-violet-900 tabular-nums">{{ $fmtN((int) ($co['accounts_total'] ?? 0)) }} codes</span>
                        <span class="rounded-full bg-emerald-100 border border-emerald-200 px-3 py-1.5 font-semibold text-emerald-900 tabular-nums">{{ $fmtN((int) ($co['accounts_active'] ?? 0)) }} active</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mt-5">
                    <div class="rounded-xl bg-white border border-cyan-200/80 p-3.5 shadow-sm">
                        <p class="text-[10px] font-bold text-cyan-800 uppercase tracking-wider">Total GL</p>
                        <p class="text-xl font-bold text-slate-900 tabular-nums mt-0.5">{{ $fmtN((int) ($co['accounts_total'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl bg-white border border-emerald-200/80 p-3.5 shadow-sm">
                        <p class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">Active</p>
                        <p class="text-xl font-bold text-emerald-700 tabular-nums mt-0.5">{{ $fmtN((int) ($co['accounts_active'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl bg-white border border-amber-200/80 p-3.5 shadow-sm">
                        <p class="text-[10px] font-bold text-amber-900 uppercase tracking-wider">Inactive</p>
                        <p class="text-xl font-bold text-amber-800 tabular-nums mt-0.5">{{ $fmtN((int) ($co['accounts_inactive'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl bg-white border border-indigo-200/80 p-3.5 shadow-sm">
                        <p class="text-[10px] font-bold text-indigo-900 uppercase tracking-wider">Cash flag</p>
                        <p class="text-xl font-bold text-indigo-700 tabular-nums mt-0.5">{{ $fmtN((int) ($co['cash_accounts'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl bg-white border border-violet-200/80 p-3.5 shadow-sm">
                        <p class="text-[10px] font-bold text-violet-900 uppercase tracking-wider">Journal 30d</p>
                        <p class="text-xl font-bold text-violet-700 tabular-nums mt-0.5">{{ $fmtN((int) ($co['journal_entries_30d'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl bg-white border border-fuchsia-200/80 p-3.5 shadow-sm">
                        <p class="text-[10px] font-bold text-fuchsia-900 uppercase tracking-wider">Rules OK</p>
                        <p class="text-xl font-bold text-fuchsia-800 tabular-nums mt-0.5">{{ $fmtN((int) ($co['rules_mapped'] ?? 0)) }}<span class="text-sm font-semibold text-slate-400">/{{ $fmtN((int) ($co['rules_total'] ?? 0)) }}</span></p>
                    </div>
                </div>

                <div class="grid lg:grid-cols-5 gap-4 mt-5">
                    <div class="lg:col-span-3 rounded-xl border border-white/80 bg-white/90 backdrop-blur-sm p-4 shadow-inner min-h-[220px]">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <h3 class="text-sm font-bold text-slate-900">Accounts by type</h3>
                            <span class="text-[10px] font-semibold uppercase text-slate-500">Bar chart</span>
                        </div>
                        <div class="h-48 relative">
                            <canvas id="chartCoaByType" aria-label="Accounts by type"></canvas>
                        </div>
                    </div>
                    <div class="lg:col-span-2 flex flex-col gap-4">
                        <div class="rounded-xl border border-violet-200 bg-white/95 p-4 shadow-sm flex-1">
                            <div class="flex justify-between items-baseline gap-2">
                                <h3 class="text-sm font-bold text-slate-900">Wallet mappings</h3>
                                <span class="text-lg font-bold text-violet-700 tabular-nums">{{ (int) ($co['wallet_pct'] ?? 0) }}%</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 mb-3">{{ $fmtN((int) ($co['wallet_filled'] ?? 0)) }} of {{ $fmtN((int) ($co['wallet_total'] ?? 0)) }} slots</p>
                            <div class="h-3 w-full rounded-full bg-violet-100 overflow-hidden border border-violet-200/60">
                                <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500" style="width: {{ min(100, (int) ($co['wallet_pct'] ?? 0)) }}%"></div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-cyan-200 bg-white/95 p-4 shadow-sm flex-1">
                            <div class="flex justify-between items-baseline gap-2">
                                <h3 class="text-sm font-bold text-slate-900">Posting rules</h3>
                                <span class="text-lg font-bold text-cyan-700 tabular-nums">{{ (int) ($co['rules_pct'] ?? 0) }}%</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 mb-3">Debit &amp; credit both assigned</p>
                            <div class="h-3 w-full rounded-full bg-cyan-100 overflow-hidden border border-cyan-200/60">
                                <div class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-emerald-500" style="width: {{ min(100, (int) ($co['rules_pct'] ?? 0)) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot>

        @include('loan.accounting.partials.flash')
        @error('delete')
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
        @enderror

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Active</label>
                    <select name="active" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="" @selected(($active ?? '') === '')>All</option>
                        <option value="1" @selected(($active ?? '') === '1')>Active</option>
                        <option value="0" @selected(($active ?? '') === '0')>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Code/name…" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.accounting.chart.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

        <div
            class="space-y-6"
            x-data="{
                selectedAccountId: null,
                filters: { asset: true, liability: true, equity: true, income: true, expense: true },
                chartBase: @js($chartBase),
                toggleType(t) { this.filters[t] = !this.filters[t]; },
                typeVisible(type) { return this.filters[type] === true; },
                accountVisible(a) { return this.typeVisible(a.account_type); },
                editHref() {
                    return this.selectedAccountId ? this.chartBase + '/' + this.selectedAccountId + '/edit' : '#';
                }
            }"
        >
            <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
                {{-- Left column --}}
                <div class="space-y-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('loan.accounting.chart.create') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100">
                                <span class="text-base leading-none">+</span> Create
                            </a>
                            <a
                                x-bind:href="editHref()"
                                x-bind:class="selectedAccountId ? '' : 'pointer-events-none opacity-40'"
                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                Edit Acc
                            </a>
                            <form
                                method="post"
                                x-bind:action="selectedAccountId ? chartBase + '/' + selectedAccountId : '#'"
                                class="inline"
                                data-swal-confirm="Remove this account? It must have no journal lines."
                                @submit="if (!selectedAccountId) { $event.preventDefault(); }"
                            >
                                @csrf
                                @method('delete')
                                <button
                                    type="submit"
                                    x-bind:disabled="!selectedAccountId"
                                    x-bind:class="selectedAccountId ? '' : 'opacity-40 cursor-not-allowed'"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Remove
                                </button>
                            </form>
                        </div>

                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Account categories</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                            @foreach ($typeOrder as $type)
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none">
                                    <input type="checkbox" class="rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" x-model="filters['{{ $type }}']">
                                    <span class="uppercase font-medium">{{ $type }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4 max-h-56 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50/50">
                            <table class="min-w-full text-sm">
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($accounts as $a)
                                        <tr
                                            class="hover:bg-white/80"
                                            x-show="accountVisible({ account_type: '{{ $a->account_type }}' })"
                                            x-cloak
                                        >
                                            <td class="px-3 py-2 w-10">
                                                <input
                                                    type="radio"
                                                    name="chart_pick"
                                                    value="{{ $a->id }}"
                                                    class="border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]"
                                                    x-model.number="selectedAccountId"
                                                >
                                            </td>
                                            <td class="px-2 py-2 font-mono text-xs text-slate-600">{{ $a->code }}</td>
                                            <td class="px-2 py-2 text-slate-800">{{ $a->name }}</td>
                                            <td class="px-2 py-2 text-slate-500 capitalize text-xs">{{ $a->account_type }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No accounts yet. Use Create to add codes.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-bold text-slate-900">Set wallet accounts</h2>
                        <p class="text-xs text-slate-500 mt-1">Link each operational wallet role to a chart account (e.g. M-Pesa bulk, cash on hand).</p>
                        <form method="post" action="{{ route('loan.accounting.chart.wallet_slots.update') }}" class="mt-4 space-y-3">
                            @csrf
                            @foreach ($walletSlots as $slot)
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                                    <label class="text-sm text-slate-700 sm:w-52 shrink-0 font-medium">
                                        <span class="text-slate-400 font-normal tabular-nums">{{ $loop->iteration }}.</span>
                                        {{ $slot['label'] }}
                                    </label>
                                    <select
                                        name="slots[{{ $slot['key'] }}]"
                                        class="flex-1 min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]"
                                    >
                                        <option value="">— Select —</option>
                                        @foreach ($selectAccounts as $acc)
                                            <option value="{{ $acc->id }}" @selected((int) ($slot['account_id'] ?? 0) === $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                            <div class="pt-2">
                                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                    Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Right column: Accounting rules --}}
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">Accounting rules</h2>
                    <p class="text-xs text-slate-500 mt-1">Default debit and credit accounts used when posting these flows. For <span class="font-medium text-slate-700">Loan Ledger</span>, set <span class="font-medium text-slate-700">debit</span> to your main cash/bank account and <span class="font-medium text-slate-700">credit</span> to the loan portfolio / receivable account. Loan pay-ins post Dr cash · Cr credit; disbursements post Dr credit · Cr cash. Map wallet slots (transactional / cash) if you leave the cash side blank.</p>

                    <div class="mt-4 rounded-lg border border-slate-100 overflow-hidden">
                        <div class="grid grid-cols-12 gap-0 bg-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-600 px-3 py-2">
                            <div class="col-span-4">Rule</div>
                            <div class="col-span-4">Debit</div>
                            <div class="col-span-4">Credit</div>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach ($postingRules as $rule)
                                @if ($rule->is_editable)
                                    <form method="post" action="{{ route('loan.accounting.chart.posting_rules.update', $rule) }}" class="contents">
                                        @csrf
                                        @method('patch')
                                        <div class="grid grid-cols-12 items-center gap-y-2 px-3 py-3 bg-white even:bg-slate-50/80">
                                            <div class="col-span-12 sm:col-span-4 flex items-center gap-2 text-sm font-medium text-slate-800">
                                                <span class="text-slate-400 tabular-nums">{{ $loop->iteration }}.</span>
                                                {{ $rule->label }}
                                                <button type="submit" class="ml-auto sm:ml-1 text-blue-600 hover:text-blue-800 p-1" title="Save row">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                            </div>
                                            <div class="col-span-12 sm:col-span-4 sm:pr-2">
                                                <select name="debit_account_id" class="w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-800">
                                                    <option value="">—</option>
                                                    @foreach ($selectAccounts as $acc)
                                                        <option value="{{ $acc->id }}" @selected($rule->debit_account_id === $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-span-12 sm:col-span-4">
                                                <select name="credit_account_id" class="w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-800">
                                                    <option value="">—</option>
                                                    @foreach ($selectAccounts as $acc)
                                                        <option value="{{ $acc->id }}" @selected($rule->credit_account_id === $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </form>
                                @else
                                    <div class="grid grid-cols-12 items-center gap-y-2 px-3 py-3 bg-white even:bg-slate-50/80">
                                        <div class="col-span-12 sm:col-span-4 flex items-center gap-2 text-sm font-medium text-slate-800">
                                            <span class="text-slate-400 tabular-nums">{{ $loop->iteration }}.</span>
                                            {{ $rule->label }}
                                        </div>
                                        <div class="col-span-12 sm:col-span-4 text-sm text-slate-700 sm:pr-2">
                                            {{ $rule->debitAccount?->name ?? '—' }}
                                        </div>
                                        <div class="col-span-12 sm:col-span-4 text-sm text-slate-700">
                                            {{ $rule->creditAccount?->name ?? '—' }}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-loan.page>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;
            const el = document.getElementById('chartCoaByType');
            if (!el) return;
            const payload = @json($co['type_chart'] ?? ['labels' => [], 'values' => []]);
            const labels = payload.labels || [];
            const values = (payload.values || []).map(function (v) { return Number(v) || 0; });
            const colors = ['#06b6d4', '#8b5cf6', '#f59e0b', '#22c55e', '#f43f5e'];
            const borderColors = ['#0891b2', '#6d28d9', '#d97706', '#15803d', '#e11d48'];
            new Chart(el, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Accounts',
                        data: values,
                        backgroundColor: labels.map(function (_, i) { return colors[i % colors.length]; }),
                        borderColor: labels.map(function (_, i) { return borderColors[i % borderColors.length]; }),
                        borderWidth: 2,
                        borderRadius: 10,
                        minBarLength: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    const n = ctx.parsed.y;
                                    return ' ' + n + ' account' + (n === 1 ? '' : 's');
                                },
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#475569', font: { weight: '500' } },
                            grid: { color: 'rgba(15, 23, 42, 0.06)' },
                            border: { display: false },
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#475569', font: { size: 11, weight: '600' } },
                            border: { display: false },
                        },
                    },
                },
            });
        });
    </script>
</x-loan-layout>
