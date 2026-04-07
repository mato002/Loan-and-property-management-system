<x-property.workspace
    title="Trial balance"
    subtitle="Debit and credit totals by account as at a selected date."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No balances yet"
    empty-hint="Add entries first to generate a trial balance."
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.reports.trial_balance.export', ['q' => $filters['q'] ?? null, 'from' => $filters['from'] ?? null, 'as_at' => $filters['as_at'] ?? null, 'category' => $filters['category'] ?? null]),
            'xlsUrl' => route('property.accounting.reports.trial_balance.export', ['q' => $filters['q'] ?? null, 'from' => $filters['from'] ?? null, 'as_at' => $filters['as_at'] ?? null, 'category' => $filters['category'] ?? null, 'format' => 'xls']),
            'pdfUrl' => route('property.accounting.reports.trial_balance.export', ['q' => $filters['q'] ?? null, 'from' => $filters['from'] ?? null, 'as_at' => $filters['as_at'] ?? null, 'category' => $filters['category'] ?? null, 'format' => 'pdf']),
        ])
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.reports.trial_balance') }}" class="flex flex-wrap gap-2 w-full">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="as_at" value="{{ $filters['as_at'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <select name="category" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                <option value="">Type: All</option>
                @foreach (($categoryOptions ?? []) as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search account…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-72" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                <option value="account" @selected(($filters['sort'] ?? '') === 'account')>Sort: Account</option>
                <option value="category" @selected(($filters['sort'] ?? '') === 'category')>Sort: Type</option>
                <option value="debit" @selected(($filters['sort'] ?? '') === 'debit')>Sort: Debit</option>
                <option value="credit" @selected(($filters['sort'] ?? '') === 'credit')>Sort: Credit</option>
                <option value="balance" @selected(($filters['sort'] ?? '') === 'balance')>Sort: Balance</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                <option value="asc" @selected(($filters['dir'] ?? 'asc') === 'asc')>Asc</option>
                <option value="desc" @selected(($filters['dir'] ?? '') === 'desc')>Desc</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 50) === $size)>Rows: {{ $size }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="only_imbalanced" value="1" @checked(($filters['only_imbalanced'] ?? '0') === '1') />
                Only out-of-balance
            </label>
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Search</button>
            <a href="{{ route('property.accounting.reports.trial_balance') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
        </form>
    </x-slot>
    @if (!($isBalanced ?? true))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
            <p class="text-sm font-semibold">Trial balance is out of balance by {{ \App\Services\Property\PropertyMoney::kes((float) ($difference ?? 0)) }}.</p>
            <p class="mt-1 text-xs">Check recent journal postings, reversals, and account-type mapping.</p>
            <div class="mt-2">
                <a
                    href="{{ route('property.accounting.audit_trail', ['from' => $filters['from'] ?? null, 'to' => $filters['as_at'] ?? null]) }}"
                    class="inline-flex items-center rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                >
                    Investigate difference in audit trail
                </a>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-emerald-800 text-sm font-medium">
            Balanced as at {{ $filters['as_at'] ?? now()->toDateString() }}.
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white dark:bg-gray-800/80 p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-slate-600 dark:text-slate-300">As at <strong>{{ $filters['as_at'] ?? now()->toDateString() }}</strong>{{ !empty($filters['from']) ? ' (from '.$filters['from'].')' : '' }}</span>
            <div class="flex items-center gap-4">
                <span>Total Debit: <strong>{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['debit'] ?? 0)) }}</strong></span>
                <span>Total Credit: <strong>{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['credit'] ?? 0)) }}</strong></span>
                <span>Difference: <strong class="{{ (($totals['difference'] ?? 0) == 0.0) ? 'text-emerald-700' : 'text-rose-700' }}">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['difference'] ?? 0)) }}</strong></span>
            </div>
        </div>
    </div>

    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>

