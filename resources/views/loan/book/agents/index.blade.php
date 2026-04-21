<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_agents.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Add agent</a>
        </x-slot>

        <form
            method="get"
            class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            x-data="{
                timer: null,
                autoSubmit() { this.$el.requestSubmit(); },
                autoSubmitDebounced(delay = 450) {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.$el.requestSubmit(), delay);
                }
            }"
        >
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Name or phone..." @input="autoSubmitDebounced(500)" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $b)
                            <option value="{{ $b }}" @selected(($branch ?? '') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Month</label>
                    <input type="month" name="month" value="{{ $month ?? now()->format('Y-m') }}" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Select day</label>
                    <select name="day" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All days</option>
                        @foreach (range(1, 31) as $d)
                            <option value="{{ $d }}" @selected((int) ($day ?? 0) === $d)>{{ str_pad((string) $d, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Active</label>
                    <select name="active" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        <option value="1" @selected(($active ?? '') === '1')>Yes</option>
                        <option value="0" @selected(($active ?? '') === '0')>No</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 20, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.collection_agents.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.collection_agents.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.collection_agents.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.collection_agents.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total agents</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $agents->total() }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Current page</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $agents->count() }}</p>
            </div>
            <div class="rounded-xl border border-[#264040] bg-[#2f4f4f] p-4 text-white shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8db1af]">Collection setup</p>
                <p class="mt-1 text-lg font-semibold">Collection agents</p>
                <p class="mt-1 text-xs text-[#d4e4e3]">Field and internal collectors mapped to branches and employees.</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">{{ $monthLabel ?? 'Collection agents report' }}</h2>
                <p class="text-xs text-slate-500">{{ $agents->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Agent Name</th>
                            <th class="px-5 py-3">Branches</th>
                            <th class="px-5 py-3">Portfolios</th>
                            <th class="px-5 py-3 text-right">Loanbook</th>
                            <th class="px-5 py-3 text-right">Assigned Loans</th>
                            <th class="px-5 py-3 text-right">{{ $monthShortLabel ?? now()->format('M') }} Collections</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($agents as $agent)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 text-slate-500 tabular-nums">{{ (($agents->currentPage() - 1) * $agents->perPage()) + $loop->iteration }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $agent->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $agent->branch ?: 'None' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $agent->employee?->full_name ?? 'None' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((int) ($agent->loanbook_lines ?? 0)) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((int) ($agent->assigned_loans ?? 0)) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($agent->month_collections ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.book.collection_agents.edit', $agent) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Manage</a>
                                    @if ($agent->employee?->full_name)
                                        <a href="{{ route('loan.book.loans.index', ['q' => $agent->employee->full_name]) }}" class="text-teal-700 font-medium text-sm hover:underline">View Loans</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">No collection agents yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($agents->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $agents->withQueryString()->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
