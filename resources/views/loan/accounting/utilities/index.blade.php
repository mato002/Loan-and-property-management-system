<x-loan-layout>
    <x-loan.page title="Utility payments" subtitle="Electricity, water, internet, and other recurring bills.">
        <x-slot name="actions">
            @include('loan.accounting.partials.export_buttons')
            <a href="{{ route('loan.accounting.utilities.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Record payment</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Type</label>
                    <select name="utility_type" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach(($utilityTypes ?? []) as $t)
                            <option value="{{ $t }}" @selected(($type ?? '') === (string) $t)>{{ str_replace('_', ' ', $t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Method</label>
                    <select name="payment_method" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach(($paymentMethods ?? []) as $m)
                            <option value="{{ $m }}" @selected(($method ?? '') === (string) $m)>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Provider</label>
                    <input type="text" name="provider" value="{{ $provider ?? '' }}" placeholder="Search…" class="h-10 w-56 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Per page</label>
                    <select name="per_page" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        @foreach ([10, 30, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? request('per_page', 20)) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.accounting.utilities.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

        <form method="post" action="{{ route('loan.accounting.utilities.bulk') }}" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden" data-swal-confirm="Apply bulk action to selected utility payments?">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3"><input type="checkbox" onclick="document.querySelectorAll('.util-row').forEach(cb=>cb.checked=this.checked)"></th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Provider</th>
                            <th class="px-5 py-3">Paid on</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3"><input type="checkbox" name="ids[]" value="{{ $r->id }}" class="util-row"></td>
                                <td class="px-5 py-3 text-slate-800 capitalize">{{ str_replace('_', ' ', $r->utility_type) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $r->provider ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $r->paid_on->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">{{ $r->currency }} {{ number_format((float) $r->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $r->payment_method }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.accounting.utilities.edit', $r) }}" class="text-indigo-600 font-medium text-sm mr-2">Edit</a>
                                    <button
                                        type="submit"
                                        formaction="{{ route('loan.accounting.utilities.destroy', $r) }}"
                                        formmethod="post"
                                        name="_method"
                                        value="delete"
                                        class="text-red-600 font-medium text-sm"
                                        data-swal-confirm="Remove this record?"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No utility payments recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100">
                <div class="flex items-center gap-2">
                    <select name="action" class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-700">
                        <option value="">Bulk action</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="h-9 rounded-lg bg-red-600 px-3 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-red-700">Apply</button>
                </div>
                @if ($rows->hasPages())
                    <div>{{ $rows->links() }}</div>
                @endif
            </div>
        </form>
    </x-loan.page>
</x-loan-layout>
