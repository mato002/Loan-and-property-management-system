<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.investors_list') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to investors
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-2xl">
            <form method="post" action="{{ $action }}" class="px-5 py-6 space-y-4">
                @csrf
                @if ($method === 'patch')
                    @method('patch')
                @endif
                <div>
                    <label for="investment_package_id" class="block text-xs font-semibold text-slate-600 mb-1">Package</label>
                    <select id="investment_package_id" name="investment_package_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">— None —</option>
                        @foreach ($packages as $pkg)
                            <option value="{{ $pkg->id }}" @selected((string) old('investment_package_id', $investor->investment_package_id) === (string) $pkg->id)>{{ $pkg->name }}</option>
                        @endforeach
                    </select>
                    @error('investment_package_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input id="name" name="name" value="{{ old('name', $investor->name) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $investor->email) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $investor->phone) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="committed_amount" class="block text-xs font-semibold text-slate-600 mb-1">Committed amount</label>
                        <input id="committed_amount" name="committed_amount" type="number" step="0.01" min="0" value="{{ old('committed_amount', $investor->committed_amount) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('committed_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="accrued_interest" class="block text-xs font-semibold text-slate-600 mb-1">Accrued interest</label>
                        <input id="accrued_interest" name="accrued_interest" type="number" step="0.01" min="0" value="{{ old('accrued_interest', $investor->accrued_interest ?? 0) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('accrued_interest')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="maturity_date" class="block text-xs font-semibold text-slate-600 mb-1">Maturity date</label>
                    <input id="maturity_date" name="maturity_date" type="date" value="{{ old('maturity_date', optional($investor->maturity_date)->format('Y-m-d')) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('maturity_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        {{ $method === 'patch' ? 'Update investor' : 'Save investor' }}
                    </button>
                    <a href="{{ route('loan.financial.investors_list') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
