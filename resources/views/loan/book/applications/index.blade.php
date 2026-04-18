<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.app_loans_report') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Application report
            </a>
            <a href="{{ route('loan.book.applications.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Create application
            </a>
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
                        @foreach ([10, 15, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.applications.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.applications.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.applications.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.applications.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Pipeline</h2>
                    <p class="mt-1 text-xs text-slate-500 max-w-xl">Change an application’s <strong>stage</strong> here (dropdown + Update) or use <strong>Edit</strong> for the full form. <strong>View</strong> is read-only summary and next-step hints — you do not need it to move the pipeline.</p>
                </div>
                <p class="text-xs text-slate-500 shrink-0">{{ $applications->total() }} file(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Ref</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Stage</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3 text-right">Actions</th>
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
                                $sourceClass = str_contains($sourceLabel, 'portal')
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : 'bg-slate-100 text-slate-700 ring-slate-200';
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $app->reference }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $app->loanClient->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $app->product_name }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $sourceClass }}">{{ $sourceLabel }}</span>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $app->amount_requested, 2) }}</td>
                                <td class="px-5 py-3 align-top">
                                    <form method="post" action="{{ route('loan.book.applications.update_stage', $app) }}" class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-2">
                                        @csrf
                                        @method('patch')
                                        <label class="sr-only" for="stage-{{ $app->id }}">Stage for {{ $app->reference }}</label>
                                        <select id="stage-{{ $app->id }}" name="stage" class="min-w-[10rem] max-w-[12rem] rounded-lg border border-slate-200 bg-white py-1.5 px-2 text-xs font-medium text-slate-800 shadow-sm">
                                            @foreach (($stages ?? []) as $value => $label)
                                                <option value="{{ $value }}" @selected($app->stage === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-slate-900 whitespace-nowrap">Update</button>
                                    </form>
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ $app->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if (in_array($app->stage, [\App\Models\LoanBookApplication::STAGE_APPROVED, \App\Models\LoanBookApplication::STAGE_DISBURSED], true) && ! $app->loan)
                                        <a href="{{ route('loan.book.loans.create', ['application' => $app->id]) }}" class="text-emerald-700 font-semibold text-sm hover:underline mr-3">Book loan</a>
                                    @endif
                                    <a href="{{ route('loan.book.applications.show', $app) }}" class="text-slate-700 font-medium text-sm hover:underline mr-3">View</a>
                                    <a href="{{ route('loan.book.applications.edit', $app) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.book.applications.destroy', $app) }}" class="inline" data-swal-confirm="Delete this application? It must not have a loan yet.">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">No applications yet. Create one to start LoanBook.</td>
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
