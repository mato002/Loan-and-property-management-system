<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-2xl">
            <form method="post" action="{{ route('loan.book.applications.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="loan_client_id" class="block text-xs font-semibold text-slate-600 mb-1">Client</label>
                    <select id="loan_client_id" name="loan_client_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select client…</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('loan_client_id') == $c->id)>{{ $c->full_name }} · {{ $c->client_number }}</option>
                        @endforeach
                    </select>
                    @error('loan_client_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="product_name" class="block text-xs font-semibold text-slate-600 mb-1">Product</label>
                    <input id="product_name" name="product_name" value="{{ old('product_name') }}" required class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. SME term, Salary advance" />
                    @error('product_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="amount_requested" class="block text-xs font-semibold text-slate-600 mb-1">Amount requested</label>
                        <input id="amount_requested" name="amount_requested" type="number" step="0.01" min="0" value="{{ old('amount_requested') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount_requested')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="term_months" class="block text-xs font-semibold text-slate-600 mb-1">Term (months)</label>
                        <input id="term_months" name="term_months" type="number" min="1" value="{{ old('term_months', 12) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('term_months')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="stage" class="block text-xs font-semibold text-slate-600 mb-1">Stage</label>
                    <select id="stage" name="stage" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($stages as $value => $label)
                            <option value="{{ $value }}" @selected(old('stage', \App\Models\LoanBookApplication::STAGE_SUBMITTED) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('stage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch (optional)</label>
                    <input id="branch" name="branch" value="{{ old('branch') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="purpose" class="block text-xs font-semibold text-slate-600 mb-1">Purpose</label>
                    <textarea id="purpose" name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose') }}</textarea>
                    @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Internal notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save application</button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
