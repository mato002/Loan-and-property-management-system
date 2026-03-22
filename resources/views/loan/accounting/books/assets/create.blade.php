<x-loan-layout>
    <x-loan.page title="Register asset" subtitle="">
        <x-slot name="actions"><a href="{{ route('loan.accounting.company_assets.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a></x-slot>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            <form method="post" action="{{ route('loan.accounting.company_assets.store') }}">
                @csrf
                <div><label class="text-xs font-semibold text-slate-600">Asset code</label><input name="asset_code" value="{{ old('asset_code') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm font-mono"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Name</label><input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Category</label><input name="category" value="{{ old('category') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Location</label><input name="location" value="{{ old('location') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Branch</label><input name="branch" value="{{ old('branch') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Acquired on</label><input name="acquired_on" type="date" value="{{ old('acquired_on') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Cost</label><input name="cost" type="number" step="0.01" min="0" value="{{ old('cost', 0) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Net book value</label><input name="net_book_value" type="number" step="0.01" min="0" value="{{ old('net_book_value') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['active' => 'Active', 'disposed' => 'Disposed', 'under_maintenance' => 'Under maintenance'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', 'active') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
