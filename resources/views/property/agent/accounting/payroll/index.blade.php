<x-property.workspace
    title="Employee payroll"
    subtitle="Post payroll journals and review payroll accounting rows."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No payroll rows"
    empty-hint="Post your first payroll batch to start tracking."
>
    <x-slot name="actions">
        <a href="{{ route('property.accounting.payroll.payslips') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payslip ledger</a>
        <a href="{{ route('property.accounting.payroll.settings') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payroll settings</a>
    </x-slot>

    <x-slot name="above">
        <form method="post" action="{{ route('property.accounting.payroll.employee.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-5xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Post employee payslip</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Employee name</label>
                    <input type="text" name="employee_name" value="{{ old('employee_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('employee_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Posting date</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('entry_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Basic pay</label>
                    <input type="number" step="0.01" min="0" name="basic_pay" value="{{ old('basic_pay') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('basic_pay')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Allowances</label>
                    <input type="number" step="0.01" min="0" name="allowances" value="{{ old('allowances', 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('allowances')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Deductions</label>
                    <input type="number" step="0.01" min="0" name="deductions" value="{{ old('deductions', 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('deductions')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="lg:col-span-6">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reference (optional)</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Auto-generated when empty" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Post employee payslip</button>
        </form>

        <form method="post" action="{{ route('property.accounting.payroll.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-4xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Post payroll batch</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">This creates balanced entries: Debit <em>Payroll Expense</em> and Credit <em>Payroll Payable</em>.</p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Posting date</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('entry_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Gross payroll (KES)</label>
                    <input type="number" step="0.01" min="0.01" name="gross_amount" value="{{ old('gross_amount') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('gross_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reference</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" placeholder="e.g. PAY-{{ now()->format('Ym') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="lg:col-span-4">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional notes for this payroll posting">{{ old('description') }}</textarea>
                    @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Post payroll batch</button>
        </form>
    </x-slot>
    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>
