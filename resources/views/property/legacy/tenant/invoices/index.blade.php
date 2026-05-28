<x-property-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('property.tenant.home') }}" class="text-gray-400 hover:text-teal-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-semibold text-gray-900">My invoices</h1>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center gap-3">
                    <form method="get" class="flex items-center gap-2">
                        <select name="status" class="rounded-lg border-gray-300 text-sm">
                            <option value="">All statuses</option>
                            @foreach (['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'] as $s)
                                <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-lg bg-teal-600 text-white px-4 py-2 text-sm font-semibold hover:bg-teal-700">Filter</button>
                    </form>
                    <a href="{{ route('property.tenant.payments.pay') }}" class="ml-auto rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-700">
                        Pay bills
                    </a>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 text-left">Invoice</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Period</th>
                            <th class="px-4 py-3 text-left">Due</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($invoices as $inv)
                            @php
                                $total = (float) ($inv->total_amount ?? $inv->amount);
                                $balance = max(0, $total - (float) $inv->amount_paid);
                                $badge = match ($inv->status) {
                                    'paid' => 'bg-emerald-100 text-emerald-700',
                                    'partial' => 'bg-amber-100 text-amber-700',
                                    'overdue' => 'bg-red-100 text-red-700',
                                    'cancelled' => 'bg-slate-200 text-slate-600',
                                    'draft' => 'bg-slate-100 text-slate-700',
                                    default => 'bg-blue-100 text-blue-700',
                                };
                            @endphp
                            <tr>
                                <td class="px-4 py-3"><a class="text-teal-700 hover:underline font-medium" href="{{ route('property.tenant.invoices.show', $inv->id) }}">{{ $inv->invoice_no }}</a></td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ ucfirst((string) $inv->invoice_type) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $inv->billing_period ?: optional($inv->issue_date)->format('Y-m') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ optional($inv->due_date)->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">KES {{ number_format($total, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium @if ($balance > 0) text-red-700 @endif">KES {{ number_format($balance, 2) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ ucfirst($inv->status) }}</span></td>
                                <td class="px-4 py-3 text-right text-sm">
                                    @if ($balance > 0 && $inv->status !== 'cancelled')
                                        <a class="text-emerald-700 hover:underline mr-2" href="{{ route('property.tenant.payments.pay', ['invoice_id' => $inv->id]) }}">Pay</a>
                                    @endif
                                    <a class="text-teal-700 hover:underline" href="{{ route('property.tenant.invoices.pdf', $inv->id) }}" target="_blank">PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-gray-200">{{ $invoices->links() }}</div>
            </div>
        </div>
    </div>
</x-property-layout>
