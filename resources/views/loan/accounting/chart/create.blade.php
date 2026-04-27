<x-loan-layout>
    <x-loan.page title="New account" subtitle="Add a chart line.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books.chart_rules') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-lg overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.chart.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Account code is auto-generated based on account type (Assets 1000+, Liabilities 2000+, Equity 3000+, Income 4000+, Expenses 5000+).
                </div>
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="account_type" class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select id="account_type" name="account_type" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $t)
                            <option value="{{ $t }}" @selected(old('account_type', 'asset') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                    @error('account_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="income_statement_category" class="block text-xs font-semibold text-slate-600 mb-1">Income Statement Category</label>
                    <select id="income_statement_category" name="income_statement_category" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Not applicable</option>
                        <option value="revenue" @selected(old('income_statement_category') === 'revenue')>Revenue</option>
                        <option value="direct_cost" @selected(old('income_statement_category') === 'direct_cost')>Direct Cost</option>
                        <option value="operating_expense" @selected(old('income_statement_category') === 'operating_expense')>Operating Expense</option>
                        <option value="tax_expense" @selected(old('income_statement_category') === 'tax_expense')>Tax Expense</option>
                        <option value="other_income" @selected(old('income_statement_category') === 'other_income')>Other Income</option>
                        <option value="other_expense" @selected(old('income_statement_category') === 'other_expense')>Other Expense</option>
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">Required for income and expense accounts.</p>
                    @error('income_statement_category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="account_class" class="block text-xs font-semibold text-slate-600 mb-1">Account Class</label>
                    <select id="account_class" name="account_class" class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['Header', 'Detail'] as $class)
                            <option value="{{ $class }}" @selected(old('account_class', 'Detail') === $class)>{{ $class }}</option>
                        @endforeach
                    </select>
                    @error('account_class')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="parent_id" class="block text-xs font-semibold text-slate-600 mb-1">Parent Account (Header)</label>
                    <select id="parent_id" name="parent_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Top-level account</option>
                        @foreach (($headerAccounts ?? collect()) as $header)
                            <option value="{{ $header->id }}" @selected((string) old('parent_id') === (string) $header->id)>{{ $header->code }} - {{ $header->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="current_balance" class="block text-xs font-semibold text-slate-600 mb-1">Current Balance</label>
                    <input id="current_balance" type="number" step="0.01" min="0" name="current_balance" value="{{ old('current_balance', 0) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('current_balance')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="allow_overdraft" value="0" />
                    <input type="checkbox" name="allow_overdraft" value="1" class="rounded border-slate-300" @checked(old('allow_overdraft')) />
                    Allow overdraft
                </label>
                <div>
                    <label for="overdraft_limit" class="block text-xs font-semibold text-slate-600 mb-1">Overdraft Limit</label>
                    <input id="overdraft_limit" type="number" step="0.01" min="0" name="overdraft_limit" value="{{ old('overdraft_limit') }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Leave blank for unlimited when enabled" />
                    @error('overdraft_limit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_cash_account" value="0" />
                    <input type="checkbox" name="is_cash_account" value="1" class="rounded border-slate-300" @checked(old('is_cash_account')) />
                    Cash / bank account (used in cashflow from journals)
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', true)) />
                    Active
                </label>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
