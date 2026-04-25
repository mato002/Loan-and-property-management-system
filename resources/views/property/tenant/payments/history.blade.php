<x-property-layout>
    <x-slot name="header">Payment history</x-slot>

    <x-property.page
        title="Payment history"
        subtitle="Track pending and completed payments. Completed payments have downloadable receipts."
    >
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <a href="{{ route('property.tenant.payments.index') }}" class="text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">← Back to payments</a>
            <div class="flex gap-2 w-full sm:w-auto">
                <details class="relative w-full sm:w-auto">
                    <summary class="list-none cursor-pointer inline-flex items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 w-full sm:w-auto">
                        Export
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 min-w-[180px] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-lg p-1">
                        <a data-turbo="false" href="{{ route('property.tenant.payments.history.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">CSV</a>
                        <a data-turbo="false" href="{{ route('property.tenant.payments.history.export', array_merge(request()->query(), ['format' => 'xls'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">Excel (.xls)</a>
                        <a data-turbo="false" href="{{ route('property.tenant.payments.history.export', array_merge(request()->query(), ['format' => 'json'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">JSON</a>
                    </div>
                </details>
                <a href="{{ route('property.tenant.payments.pay') }}" class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 w-full sm:w-auto" data-turbo="false">
                    Pay now (STK)
                </a>
            </div>
        </div>

        <form method="get" action="{{ route('property.tenant.payments.history') }}" class="mt-4 grid grid-cols-1 md:grid-cols-6 gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search ref / checkout / id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2" />
            <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2">
                <option value="">All status</option>
                @foreach (['completed', 'pending', 'failed'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="channel" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2">
                <option value="">All channels</option>
                @foreach (['mpesa_stk', 'bank', 'cash', 'card', 'cheque'] as $channel)
                    <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ $channel }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2">
                <option value="date_desc" @selected(($filters['sort'] ?? 'date_desc') === 'date_desc')>Newest</option>
                <option value="date_asc" @selected(($filters['sort'] ?? '') === 'date_asc')>Oldest</option>
                <option value="amount_desc" @selected(($filters['sort'] ?? '') === 'amount_desc')>Amount high→low</option>
                <option value="amount_asc" @selected(($filters['sort'] ?? '') === 'amount_asc')>Amount low→high</option>
                <option value="status_asc" @selected(($filters['sort'] ?? '') === 'status_asc')>Status A→Z</option>
                <option value="status_desc" @selected(($filters['sort'] ?? '') === 'status_desc')>Status Z→A</option>
            </select>
            <div class="md:col-span-6 flex gap-2">
                <button type="submit" class="rounded-lg bg-teal-600 text-white text-sm px-3 py-2 font-medium hover:bg-teal-700">Apply filters</button>
                <a href="{{ route('property.tenant.payments.history') }}" class="rounded-lg border border-slate-300 dark:border-slate-600 text-sm px-3 py-2 font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Reset</a>
            </div>
        </form>

        @if (! empty($stats))
            <div class="grid gap-3 sm:grid-cols-2 mt-4">
                @foreach ($stats as $s)
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $s['label'] }}</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ $s['value'] }}</p>
                        @if (! empty($s['hint'] ?? null))
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $s['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Recent payments</p>
                <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search…" class="w-44 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2" />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            @foreach (($columns ?? []) as $col)
                                <th class="px-4 py-3 whitespace-nowrap">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse (($tableRows ?? []) as $row)
                            @php
                                $filter = mb_strtolower(implode(' ', array_map(static fn ($c) => strip_tags((string) $c), $row)));
                            @endphp
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30" data-filter-text="{{ e($filter) }}">
                                @foreach ($row as $cell)
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                        @if ($cell instanceof \Illuminate\Support\HtmlString)
                                            {!! $cell !!}
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns ?? []) }}" class="px-4 py-12 text-center">
                                    <p class="font-medium text-slate-700 dark:text-slate-200">No payments yet</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Your STK and bank entries will appear here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if (isset($payments) && method_exists($payments, 'links'))
            <div class="mt-4">
                {{ $payments->onEachSide(1)->links() }}
            </div>
        @endif
        <script>
            if (window.Swal) {
                @if (session('success'))
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: @json(session('success')),
                        timer: 2200,
                        showConfirmButton: false
                    });
                @endif
                @if ($errors->any())
                    Swal.fire({
                        icon: 'warning',
                        title: 'Attention',
                        text: @json($errors->first()),
                    });
                @endif
            }

            document.addEventListener('click', async function (e) {
                const btn = e.target.closest('[data-copy-ref]');
                if (!btn) return;
                const ref = btn.getAttribute('data-copy-ref') || '';
                if (!ref) return;
                try {
                    await navigator.clipboard.writeText(ref);
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Copied',
                            text: 'Reference copied to clipboard.',
                            timer: 1200,
                            showConfirmButton: false
                        });
                    } else {
                        const original = btn.textContent;
                        btn.textContent = 'Copied';
                        setTimeout(() => btn.textContent = original, 1200);
                    }
                } catch (err) {
                    // ignore clipboard failures
                }
            });
        </script>
    </x-property.page>
</x-property-layout>
