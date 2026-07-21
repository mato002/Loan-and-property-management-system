<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->invoice_no }}</title>
<style>
    @page { margin: 24mm 16mm 22mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 11pt; margin: 0; }
    .accent { color: {{ $branding['colour'] ?? '#1e40af' }}; }
    .header { border-bottom: 2px solid {{ $branding['colour'] ?? '#1e40af' }}; padding-bottom: 12px; margin-bottom: 18px; }
    .header table { width: 100%; }
    .header td { vertical-align: top; }
    .header .company-name { font-size: 18pt; font-weight: 700; }
    .header .meta { text-align: right; font-size: 10pt; color: #475569; }
    h1, h2, h3 { margin: 0 0 6px 0; }
    h1 { font-size: 22pt; color: {{ $branding['colour'] ?? '#1e40af' }}; }
    .section { margin-bottom: 14px; }
    .bill-to { background: #f8fafc; border-radius: 6px; padding: 10px 12px; }
    .grid { width: 100%; }
    .grid td { width: 50%; vertical-align: top; padding-right: 8px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th, table.items td { padding: 7px 8px; font-size: 10pt; }
    table.items thead th { background: {{ $branding['colour'] ?? '#1e40af' }}; color: #fff; text-align: left; }
    table.items tbody td { border-bottom: 1px solid #e5e7eb; }
    .num { text-align: right; }
    .totals { width: 280px; margin-left: auto; margin-top: 12px; }
    .totals td { padding: 4px 6px; font-size: 10pt; }
    .totals .label { color: #475569; }
    .totals .grand { font-size: 12pt; font-weight: 700; border-top: 2px solid {{ $branding['colour'] ?? '#1e40af' }}; padding-top: 6px; }
    .status-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 9pt; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .status-paid { background: #dcfce7; color: #15803d; }
    .status-partial { background: #fef3c7; color: #92400e; }
    .status-overdue { background: #fee2e2; color: #b91c1c; }
    .status-sent, .status-draft { background: #dbeafe; color: #1d4ed8; }
    .status-cancelled { background: #e5e7eb; color: #475569; }
    .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9pt; color: #64748b; text-align: center; }
    .small { font-size: 9pt; color: #64748b; }
</style>
</head>
<body>
<div class="header">
    <table>
        <tr>
            <td>
                @if (!empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" style="max-height:40px; max-width:160px;" alt="">
                @endif
                <div class="company-name accent">{{ $branding['company_name'] ?? 'Property Manager' }}</div>
                <div class="small">{{ $branding['address'] ?? '' }}</div>
                <div class="small">{{ $branding['phone'] ?? '' }} @if (!empty($branding['email']))  -  {{ $branding['email'] }} @endif</div>
            </td>
            <td class="meta">
                <h1>{{ $invoice->isCreditNote() ? 'CREDIT NOTE' : 'INVOICE' }}</h1>
                <div><strong>{{ $invoice->invoice_no }}</strong></div>
                <div>Issued: {{ optional($invoice->issue_date)->format('Y-m-d') ?? '—' }}</div>
                <div>Due: {{ optional($invoice->due_date)->format('Y-m-d') ?? '—' }}</div>
                <div style="margin-top:6px;">
                    <span class="status-pill status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <table class="grid">
        <tr>
            <td>
                <div class="small accent" style="font-weight:700;">BILL TO</div>
                <div class="bill-to">
                    <strong>{{ $invoice->tenant?->name ?? '—' }}</strong><br>
                    @if ($invoice->tenant?->phone) <span class="small">{{ $invoice->tenant->phone }}</span><br>@endif
                    @if ($invoice->tenant?->email) <span class="small">{{ $invoice->tenant->email }}</span><br>@endif
                </div>
            </td>
            <td>
                <div class="small accent" style="font-weight:700;">PROPERTY / UNIT</div>
                <div class="bill-to">
                    <strong>{{ $invoice->unit?->property?->name ?? '—' }}</strong><br>
                    <span class="small">Unit: {{ $invoice->unit?->label ?? '—' }}</span><br>
                    @if ($invoice->billing_period) <span class="small">Period: {{ $invoice->billing_period }}</span><br>@endif
                    @if ($invoice->invoice_type) <span class="small">Type: {{ ucfirst($invoice->invoice_type) }}</span>@endif
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="items">
    <thead>
        <tr>
            <th style="width:50%;">Description</th>
            <th class="num" style="width:10%;">Qty</th>
            <th class="num" style="width:13%;">Unit price</th>
            <th class="num" style="width:13%;">Tax</th>
            <th class="num" style="width:14%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @if ($invoice->items && $invoice->items->count() > 0)
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td>{{ $invoice->description ?: 'Property charge' }}</td>
                <td class="num">1</td>
                <td class="num">{{ number_format((float) $invoice->amount, 2) }}</td>
                <td class="num">0.00</td>
                <td class="num">{{ number_format((float) $invoice->amount, 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<table class="totals">
    @php
        $subtotal = (float) ($invoice->subtotal_amount ?? $invoice->amount);
        $tax = (float) ($invoice->tax_amount ?? 0);
        $discount = (float) ($invoice->discount_amount ?? 0);
        $total = (float) ($invoice->total_amount ?? $invoice->amount);
        $paid = (float) $invoice->amount_paid;
        $balance = max(0, $total - $paid);
    @endphp
    <tr><td class="label">Subtotal</td><td class="num">KES {{ number_format($subtotal, 2) }}</td></tr>
    @if ($discount > 0)
        <tr><td class="label">Discount</td><td class="num">- KES {{ number_format($discount, 2) }}</td></tr>
    @endif
    @if ($tax > 0)
        <tr><td class="label">Tax</td><td class="num">KES {{ number_format($tax, 2) }}</td></tr>
    @endif
    <tr><td class="label grand">Total</td><td class="num grand">KES {{ number_format($total, 2) }}</td></tr>
    @if ($paid > 0)
        <tr><td class="label">Paid</td><td class="num">KES {{ number_format($paid, 2) }}</td></tr>
        <tr><td class="label grand">Balance due</td><td class="num grand">KES {{ number_format($balance, 2) }}</td></tr>
    @endif
</table>

@if (!empty($invoice->notes))
    <div class="section" style="margin-top: 20px;">
        <div class="small accent" style="font-weight:700;">NOTES</div>
        <div class="small" style="white-space: pre-line;">{{ $invoice->notes }}</div>
    </div>
@endif

<div class="footer">
    {{ $branding['footer_note'] ?? 'Thank you for your business.' }}
    <br>
    <span class="small">Invoice {{ $invoice->invoice_no }}  -  Generated {{ now()->format('Y-m-d H:i') }}</span>
</div>
</body>
</html>
