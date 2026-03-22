<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.account_balances') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to balances
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ $action }}" class="px-5 py-6 space-y-4">
                @csrf
                @if ($method === 'patch')
                    @method('patch')
                @endif
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Account name</label>
                    <input id="name" name="name" value="{{ old('name', $account->name) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="account_type" class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <input id="account_type" name="account_type" value="{{ old('account_type', $account->account_type) }}" placeholder="e.g. Bank, Mobile money" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('account_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="currency" class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                        <input id="currency" name="currency" value="{{ old('currency', $account->currency) }}" required class="w-full rounded-lg border-slate-200 text-sm uppercase" />
                        @error('currency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="balance" class="block text-xs font-semibold text-slate-600 mb-1">Balance</label>
                        <input id="balance" name="balance" type="number" step="0.01" min="0" value="{{ old('balance', $account->balance) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('balance')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        {{ $method === 'patch' ? 'Update account' : 'Save account' }}
                    </button>
                    <a href="{{ route('loan.financial.account_balances') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
