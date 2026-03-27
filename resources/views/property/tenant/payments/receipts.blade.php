<x-property-layout>
    <x-slot name="header">Receipts</x-slot>

    <x-property.page
        title="Receipts"
        subtitle="Download receipts for paid invoices."
    >
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <a href="{{ route('property.tenant.payments.index') }}" class="text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">← Back to payments</a>
            <a
                href="{{ route('property.tenant.workspace.form.show', 'tenant-email-receipts') }}"
                class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
            >Email all</a>
        </div>

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
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Recent receipts</p>
                <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search…" class="w-44 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/40 text-sm px-3 py-2" />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
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
                                    <p class="font-medium text-slate-700 dark:text-slate-200">No receipts</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Receipts appear when invoices are fully paid.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
