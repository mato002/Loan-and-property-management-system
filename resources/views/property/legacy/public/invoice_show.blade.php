@php
    $total = (float) ($invoice->total_amount ?? $invoice->amount);
    $paid = (float) $invoice->amount_paid;
    $balance = max(0, $total - $paid);
    $statusBadge = match ($invoice->status) {
        'paid' => 'bg-emerald-100 text-emerald-700',
        'partial' => 'bg-amber-100 text-amber-700',
        'overdue' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-slate-200 text-slate-600',
        'draft' => 'bg-slate-100 text-slate-700',
        default => 'bg-blue-100 text-blue-700',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-3xl mx-auto p-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-3 p-6 border-b border-slate-100">
            <div>
                @if (!empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" class="h-10 mb-2" alt="">
                @endif
                <h1 class="text-2xl font-bold" style="color: {{ $branding['colour'] ?? '#1e40af' }}">{{ $branding['company_name'] ?? 'Property Manager' }}</h1>
                <p class="text-xs text-slate-500">{{ $branding['address'] ?? '' }}</p>
                @if (!empty($branding['phone']) || !empty($branding['email']))
                    <p class="text-xs text-slate-500">{{ $branding['phone'] ?? '' }} @if(!empty($branding['email'])) ┬╖ {{ $branding['email'] }}@endif</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $invoice->isCreditNote() ? 'Credit note' : 'Invoice' }}</p>
                <p class="text-2xl font-bold text-slate-900">{{ $invoice->invoice_no }}</p>
                <p class="text-sm text-slate-600">Issued: {{ optional($invoice->issue_date)->format('Y-m-d') ?? '—' }}</p>
                <p class="text-sm text-slate-600">Due: {{ optional($invoice->due_date)->format('Y-m-d') ?? '—' }}</p>
                <span class="inline-block mt-2 rounded-full px-3 py-1 text-xs font-semibold uppercase {{ $statusBadge }}">{{ $invoice->status }}</span>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 p-6 border-b border-slate-100">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase">Bill to</p>
                <p class="font-medium text-slate-800">{{ $invoice->tenant?->name ?? '—' }}</p>
                @if ($invoice->tenant?->phone) <p class="text-xs text-slate-500">{{ $invoice->tenant->phone }}</p> @endif
                @if ($invoice->tenant?->email) <p class="text-xs text-slate-500">{{ $invoice->tenant->email }}</p> @endif
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase">Property / Unit</p>
                <p class="font-medium text-slate-800">{{ $invoice->unit?->property?->name ?? '—' }}</p>
                <p class="text-xs text-slate-500">Unit: {{ $invoice->unit?->label ?? '—' }}</p>
                @if ($invoice->billing_period) <p class="text-xs text-slate-500">Period: {{ $invoice->billing_period }}</p> @endif
            </div>
        </div>

        <div class="p-6">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Unit price</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($invoice->items as $item)
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $item->description }}</td>
                            <td class="px-3 py-2 text-right text-slate-600">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                            <td class="px-3 py-2 text-right text-slate-600">{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium text-slate-800">{{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $invoice->description ?: 'Property charge' }}</td>
                            <td class="px-3 py-2 text-right text-slate-600">1</td>
                            <td class="px-3 py-2 text-right text-slate-600">{{ number_format((float) $invoice->amount, 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium text-slate-800">{{ number_format((float) $invoice->amount, 2) }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-slate-600">Total</span><span class="font-semibold tabular-nums">KES {{ number_format($total, 2) }}</span></div>
                @if ($paid > 0)
                    <div class="flex justify-between"><span class="text-slate-600">Paid</span><span class="tabular-nums">KES {{ number_format($paid, 2) }}</span></div>
                    <div class="flex justify-between text-red-700 font-bold border-t border-slate-200 pt-1"><span>Balance due</span><span class="tabular-nums">KES {{ number_format($balance, 2) }}</span></div>
                @endif
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                @php
                    $pdfUrl = $pdfUrl ?? ($invoice->share_token ? route('property.invoices.public.pdf', ['token' => $invoice->share_token]) : null);
                @endphp
                @if (! empty($payUrl) && $balance > 0 && $invoice->status !== 'cancelled')
                    <a href="{{ $payUrl }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-700">
                        Pay this invoice
                    </a>
                @endif
                @if ($pdfUrl)
                    <a href="{{ $pdfUrl }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-blue-600 text-white px-4 py-2 text-sm font-semibold hover:bg-blue-700">
                        Download PDF
                    </a>
                @endif
                <a href="javascript:window.print()" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print</a>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 text-center text-xs text-slate-500">
            {{ $branding['footer_note'] ?? 'Thank you for your business.' }}
        </div>
    </div>
</div>
</body>
</html>
