<x-property.workspace
    title="Upload bank / M-Pesa statement"
    subtitle=""
    back-route="property.revenue.payments"
>
    <x-slot name="actions">
        <a href="{{ route('property.equity.unmatched') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unmatched queue</a>
        <a href="{{ route('property.accounting.cash_bank.reconciliation') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cash &amp; Bank</a>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-data="{ uploadOpen: @js($errors->any()), processing: false }" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Bank statement uploads</h2>
            <p class="mt-1 text-sm text-slate-600">Import a statement and review its matches.</p>
        </div>
        <button type="button" @click="uploadOpen = true" class="mt-3 inline-flex items-center justify-center gap-2 rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 sm:mt-0">
            <span aria-hidden="true">＋</span> Upload statement
        </button>

        <div x-show="uploadOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[8000] flex items-center justify-center bg-slate-950/55 p-3 sm:p-6" @keydown.escape.window="if (!processing) uploadOpen = false" @click.self="if (!processing) uploadOpen = false" role="dialog" aria-modal="true" aria-labelledby="statement-upload-title">
            <section class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="statement-upload-title" class="text-base font-semibold text-slate-900">Upload bank / M-Pesa statement</h2>
                        <p class="mt-1 text-xs text-slate-500">Select the provider and statement file.</p>
                    </div>
                    <button type="button" @click="uploadOpen = false" :disabled="processing" class="rounded-lg px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close">&times;</button>
                </div>

                <form method="POST" action="{{ route('property.revenue.statements.store') }}" enctype="multipart/form-data" class="space-y-5 p-5 sm:p-6" @submit="processing = true">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-slate-600">Provider
                            <select name="provider" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal text-slate-900">
                                @foreach (($providers ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(old('provider', 'auto') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">Statement file <span class="text-rose-600">*</span>
                            <input type="file" name="statement_file" required accept=".csv,.txt,.pdf,.xls,.xlsx" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:h-10 file:border-0 file:bg-teal-700 file:px-3 file:font-semibold file:text-white">
                        </label>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="recover_missing" value="0">
                        <input type="checkbox" name="recover_missing" value="1" class="rounded border-slate-300" @checked(old('recover_missing', '1') === '1')>
                        Recover unmatched credits
                    </label>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" @click="uploadOpen = false" :disabled="processing" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" :disabled="processing" class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:cursor-wait disabled:opacity-70">
                            <svg x-show="processing" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8v8H4z" fill="currentColor"></path></svg>
                            <span x-text="processing ? 'Importing statement…' : 'Upload & import'"></span>
                        </button>
                    </div>
                </form>

                <div x-show="processing" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-white/95 p-6" role="status" aria-live="polite">
                    <div class="w-full max-w-sm text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-teal-50 text-teal-800">
                            <svg class="h-7 w-7 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                        </div>
                        <p class="mt-4 text-base font-semibold text-slate-900">Processing statement</p>
                        <p class="mt-1 text-sm text-slate-600">Reading transactions and checking existing payments. This may take a little while.</p>
                        <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-1/3 animate-pulse rounded-full bg-teal-700"></div></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-900">Recent uploads</h2>
            <form method="get" action="{{ route('property.revenue.statements.index', false) }}" data-turbo="false" class="flex flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Bank, account, or file" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Filter</button>
                @if (($filters['q'] ?? '') !== '')
                    <a href="{{ route('property.revenue.statements.index', false) }}" class="text-sm font-medium text-slate-600 hover:underline">Clear</a>
                @endif
                @include('property.agent.partials.export_dropdown', [
                    'route' => 'property.revenue.statements.index',
                    'query' => request()->except(['export', 'page']),
                ])
            </form>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Bank</th>
                        <th class="px-3 py-2">Account</th>
                        <th class="px-3 py-2">Period</th>
                        <th class="px-3 py-2">Credits</th>
                        <th class="px-3 py-2">File</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse (($statements ?? collect()) as $row)
                        <tr>
                            <td class="px-3 py-2 font-medium text-slate-900">{{ $row->bank_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $row->account_no ?: $row->account_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $row->periodLabel() }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row->total_credit) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $row->source_filename ?: '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('property.revenue.statements.show', $row) }}" class="font-semibold text-blue-700 hover:underline">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">No statements uploaded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($statements, 'links'))
            <div class="mt-3">{{ $statements->links() }}</div>
        @endif
    </div>
</x-property.workspace>
