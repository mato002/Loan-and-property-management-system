<x-loan-layout>
    <x-loan.page title="New account" subtitle="Add a chart line.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.chart.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-lg overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.chart.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-xs font-semibold text-slate-600 mb-1">Code</label>
                    <input id="code" name="code" value="{{ old('code') }}" required class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                    @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
                            <option value="{{ $t }}" @selected(old('account_type') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                    @error('account_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
