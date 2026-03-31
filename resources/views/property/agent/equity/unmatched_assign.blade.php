<x-property-layout>
    <x-slot name="header">Assign Unposted Payment</x-slot>

    <x-property.page
        title="Assign Unposted Payment"
        subtitle="Select a tenant and post this transaction to their account."
    >
        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">Transaction</div>
                <div class="mt-1 text-lg font-black text-slate-900">{{ $item->transaction_id }}</div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs text-slate-500">Amount</div>
                        <div class="font-bold text-slate-900">{{ number_format((float) $item->amount, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Date</div>
                        <div class="font-bold text-slate-900">{{ optional($item->created_at)->format('Y-m-d H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Account</div>
                        <div class="font-bold text-slate-900">{{ $item->account_number ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Phone</div>
                        <div class="font-bold text-slate-900">{{ $item->phone ?: '—' }}</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-xs text-slate-500">Reason</div>
                    <div class="text-sm font-semibold text-slate-800">{{ $item->reason }}</div>
                </div>
            </div>

            <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <form method="POST" action="{{ route('property.equity.unmatched.assign', $item) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="text-xs font-bold text-slate-600 uppercase tracking-widest">Assign to tenant</label>
                        <select name="tenant_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" required>
                            <option value="">Select tenant…</option>
                            @foreach ($tenants as $t)
                                <option value="{{ $t->id }}">
                                    {{ $t->name }}{{ $t->account_number ? ' • '.$t->account_number : '' }}{{ $t->phone ? ' • '.$t->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('tenant_id')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 uppercase tracking-widest">Internal note (optional)</label>
                        <input
                            type="text"
                            name="note"
                            value="{{ old('note') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm"
                            placeholder="e.g. Verified with tenant via call"
                        >
                        @error('note')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <a
                            href="{{ route('property.equity.unmatched') }}"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        >
                            Back
                        </a>
                        <button
                            type="submit"
                            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"
                        >
                            Assign & Post Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </x-property.page>
</x-property-layout>

