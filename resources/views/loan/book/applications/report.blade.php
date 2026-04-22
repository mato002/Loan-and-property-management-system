<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Pipeline view</a>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Ref, client, product..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Stage</label>
                    <select name="stage" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($stages ?? []) as $k => $lbl)
                            <option value="{{ $k }}" @selected(($stage ?? '') === $k)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $b)
                            <option value="{{ $b }}" @selected(($branch ?? '') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.app_loans_report') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.app_loans_report', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.app_loans_report', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.app_loans_report', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">All applications (report)</h2>
                <p class="text-xs text-slate-500 mt-1">{{ $applications->total() }} row(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Ref</th>
                            <th class="px-5 py-3">Client #</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Term</th>
                            <th class="px-5 py-3">Stage</th>
                            <th class="px-5 py-3">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($applications as $app)
                            @php
                                $sourceLabel = match ((string) ($app->submission_source ?? '')) {
                                    'tenant_portal' => 'Tenant portal',
                                    'landlord_portal' => 'Landlord portal',
                                    'manual_internal' => 'Manual/Internal',
                                    default => (function () use ($app) {
                                        $notes = strtolower((string) ($app->notes ?? ''));
                                        return str_contains($notes, 'tenant portal')
                                            ? 'Tenant portal'
                                            : (str_contains($notes, 'landlord portal') ? 'Landlord portal' : 'Manual/Internal');
                                    })(),
                                };
                            @endphp
                            <tr
                                class="cursor-pointer hover:bg-slate-50/80"
                                role="link"
                                tabindex="0"
                                onclick="if (event.target.closest('a, button, input, select, textarea, form, label, summary, details')) return; window.location.href='{{ route('loan.book.applications.show', $app) }}';"
                                onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label, summary, details')) { event.preventDefault(); window.location.href='{{ route('loan.book.applications.show', $app) }}'; }"
                            >
                                <td class="px-5 py-3 font-mono text-xs">
                                    <a href="{{ route('loan.book.applications.show', $app) }}" class="text-indigo-600 hover:underline">
                                        {{ $app->reference }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $app->loanClient?->client_number ?? '—' }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">
                                    @if ($app->loanClient)
                                        <a href="{{ route('loan.clients.show', $app->loanClient) }}" class="text-[#2f4f4f] hover:underline">
                                            {{ $app->loanClient->full_name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $app->product_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $sourceLabel }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $app->amount_requested, 2) }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $app->term_months }} mo</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $app->stage) }}</td>
                                <td class="px-5 py-3 text-slate-500 tabular-nums text-xs">{{ $app->submitted_at?->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">No data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $applications->withQueryString()->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
