@php
    use App\Models\PmInvoice;

    $accent = $branding['colour'] ?? '#0f766e';
    $accentSoft = '#f0fdfa';
    $accentBorder = '#99f6e4';

    $subtotal = (float) ($invoice->subtotal_amount ?? $invoice->amount);
    $tax = (float) ($invoice->tax_amount ?? 0);
    $discount = (float) ($invoice->discount_amount ?? 0);
    $total = (float) ($invoice->total_amount ?? $invoice->amount);
    $paid = (float) $invoice->amount_paid;
    $balance = max(0, $total - $paid);
    $isCredit = $invoice->isCreditNote();
    $docLabel = $isCredit ? 'CREDIT NOTE' : 'INVOICE';

    $status = (string) $invoice->status;
    $statusClass = match ($status) {
        PmInvoice::STATUS_PAID => 'status-paid',
        PmInvoice::STATUS_PARTIAL => 'status-partial',
        PmInvoice::STATUS_OVERDUE => 'status-overdue',
        PmInvoice::STATUS_CANCELLED => 'status-cancelled',
        PmInvoice::STATUS_DRAFT => 'status-draft',
        default => 'status-sent',
    };

    $tenantAccount = $invoice->tenant?->account_number ?? '';
    $unitLabel = $invoice->unit?->label ?? '';
    $paymentRef = $invoice->invoice_no;
    if ($tenantAccount !== '') {
        $paymentRef .= ' / '.$tenantAccount;
    }
    if ($unitLabel !== '') {
        $paymentRef .= ' ('.$unitLabel.')';
    }

    $hasPaymentBlock = collect($payments ?? [])->filter(fn ($v, $k) => $k !== 'late_fee_percent' && $k !== 'grace_days' && trim((string) $v) !== '')->isNotEmpty();
    $isOverdue = $status === PmInvoice::STATUS_OVERDUE;
    $generatedAt = now()->format('d M Y, H:i');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $docLabel }} {{ $invoice->invoice_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 14mm 20mm 14mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #e8edf2;
            color: #0f172a;
            font-family: 'Segoe UI', 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            max-width: 210mm;
            margin: 0 auto;
            padding: 14px 16px 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #0f172a;
            cursor: pointer;
        }

        .btn-primary {
            background: {{ $accent }};
            border-color: {{ $accent }};
            color: #fff;
        }

        .btn:hover { opacity: 0.92; }

        .page-shell {
            max-width: 210mm;
            margin: 0 auto;
            padding: 12px 16px 28px;
        }

        .invoice-doc {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
            padding: 22px 24px 20px;
            min-height: 260mm;
        }

        .doc-top {
            display: table;
            width: 100%;
            border-bottom: 2px solid {{ $accent }};
            padding-bottom: 16px;
            margin-bottom: 18px;
        }

        .doc-top-left,
        .doc-top-right {
            display: table-cell;
            vertical-align: top;
        }

        .doc-top-right {
            width: 42%;
            text-align: right;
        }

        .logo {
            max-height: 48px;
            max-width: 180px;
            margin-bottom: 8px;
            display: block;
        }

        .company-name {
            font-size: 15pt;
            font-weight: 700;
            color: {{ $accent }};
            letter-spacing: -0.02em;
            margin: 0 0 4px;
        }

        .company-meta {
            font-size: 8.5pt;
            color: #64748b;
            line-height: 1.5;
        }

        .doc-title {
            margin: 0 0 6px;
            font-size: 26pt;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #0f172a;
            line-height: 1;
        }

        .doc-number {
            font-size: 11pt;
            font-weight: 700;
            color: {{ $accent }};
            margin-bottom: 8px;
        }

        .meta-grid {
            display: table;
            width: 100%;
            font-size: 9pt;
            color: #64748b;
        }

        .meta-grid .label {
            color: #64748b;
            padding-right: 8px;
            white-space: nowrap;
        }

        .meta-grid .value {
            color: #0f172a;
            font-weight: 600;
        }

        .status-pill {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-paid { background: #dcfce7; color: #166534; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-sent, .status-draft { background: #e0f2fe; color: #075985; }
        .status-cancelled { background: #f1f5f9; color: #475569; }

        .cards {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            border-spacing: 12px 0;
        }

        .card {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .card-title {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: {{ $accent }};
            margin-bottom: 8px;
        }

        .card strong {
            display: block;
            font-size: 11pt;
            margin-bottom: 4px;
        }

        .card-line {
            font-size: 9pt;
            color: #64748b;
            margin-bottom: 2px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        table.items thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 10px 10px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid {{ $accentBorder }};
            text-align: left;
        }

        table.items tbody td {
            padding: 10px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5pt;
            vertical-align: top;
        }

        table.items tbody tr:nth-child(even) td {
            background: #fcfdfe;
        }

        table.items tbody tr {
            page-break-inside: avoid;
        }

        .num { text-align: right; white-space: nowrap; }

        .summary-wrap {
            display: table;
            width: 100%;
            margin-bottom: 18px;
        }

        .summary-spacer {
            display: table-cell;
            width: 52%;
        }

        .summary-box {
            display: table-cell;
            width: 48%;
            vertical-align: top;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .summary-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-box td {
            padding: 7px 12px;
            font-size: 9.5pt;
        }

        .summary-box tr + tr td {
            border-top: 1px solid #e2e8f0;
        }

        .summary-box .label { color: #64748b; }
        .summary-box .num { font-weight: 600; }

        .summary-box .row-total td {
            background: #f8fafc;
            font-weight: 700;
            font-size: 10pt;
        }

        .summary-box .row-balance td {
            background: {{ $accentSoft }};
            border-top: 2px solid {{ $accent }} !important;
            font-size: 11pt;
            font-weight: 800;
            color: {{ $accent }};
        }

        .summary-box .row-balance .num {
            font-size: 13pt;
            color: {{ $accent }};
        }

        .section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: {{ $accent }};
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-body {
            font-size: 9pt;
            color: #334155;
        }

        .section-body ul {
            margin: 0;
            padding-left: 18px;
        }

        .section-body li { margin-bottom: 4px; }

        .note-muted {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 6px;
        }

        .doc-footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8pt;
            color: #64748b;
        }

        .doc-footer strong {
            color: #0f172a;
            font-weight: 600;
        }

        @media print {
            html, body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .page-shell {
                padding: 0;
                max-width: none;
            }

            .invoice-doc {
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
                min-height: auto;
            }

            table.items thead th {
                background: #f1f5f9 !important;
            }

            .summary-box .row-balance td {
                background: #f0fdfa !important;
            }
        }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <div style="font-size:12px;color:#64748b;">
        Preview · {{ $docLabel }} <strong style="color:#0f172a;">{{ $invoice->invoice_no }}</strong>
    </div>
    <div class="toolbar-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
        <a href="{{ route('property.revenue.invoices.pdf', $invoice) }}" class="btn" target="_blank" rel="noopener">Download PDF</a>
        @auth
            <a href="{{ route('property.revenue.invoices.show', $invoice) }}" class="btn">Back to invoice</a>
        @endauth
    </div>
</div>

<div class="page-shell">
    <article class="invoice-doc">

        <header class="doc-top">
            <div class="doc-top-left">
                @if (! empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" alt="" class="logo">
                @endif
                <p class="company-name">{{ $branding['company_name'] ?? 'Property Manager' }}</p>
                <div class="company-meta">
                    @if (! empty($branding['address']))
                        <div>{{ $branding['address'] }}</div>
                    @endif
                    @if (! empty($branding['phone']) || ! empty($branding['email']))
                        <div>
                            @if (! empty($branding['phone']))<span>{{ $branding['phone'] }}</span>@endif
                            @if (! empty($branding['phone']) && ! empty($branding['email']))<span> · </span>@endif
                            @if (! empty($branding['email']))<span>{{ $branding['email'] }}</span>@endif
                        </div>
                    @endif
                </div>
            </div>
            <div class="doc-top-right">
                <h1 class="doc-title">{{ $docLabel }}</h1>
                <div class="doc-number">{{ $invoice->invoice_no }}</div>
                <table class="meta-grid" style="margin-left:auto;">
                    <tr>
                        <td class="label">Issue date</td>
                        <td class="value">{{ optional($invoice->issue_date)->format('d M Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Due date</td>
                        <td class="value">{{ optional($invoice->due_date)->format('d M Y') ?? '—' }}</td>
                    </tr>
                </table>
                <span class="status-pill {{ $statusClass }}">{{ ucfirst($status) }}</span>
            </div>
        </header>

        <div class="cards">
            <div class="card">
                <div class="card-title">Bill to</div>
                <strong>{{ $invoice->tenant?->name ?? '—' }}</strong>
                @if ($invoice->tenant?->phone)
                    <div class="card-line">Phone: {{ $invoice->tenant->phone }}</div>
                @endif
                @if ($invoice->tenant?->email)
                    <div class="card-line">Email: {{ $invoice->tenant->email }}</div>
                @endif
                @if ($tenantAccount !== '')
                    <div class="card-line">Account: {{ $tenantAccount }}</div>
                @endif
            </div>
            <div class="card">
                <div class="card-title">Property / unit</div>
                <strong>{{ $invoice->unit?->property?->name ?? '—' }}</strong>
                <div class="card-line">Unit: {{ $unitLabel ?: '—' }}</div>
                @if ($invoice->invoice_type)
                    <div class="card-line">Type: {{ ucfirst(str_replace('_', ' ', (string) $invoice->invoice_type)) }}</div>
                @endif
                @if ($invoice->billing_period)
                    <div class="card-line">Billing period: {{ $invoice->billing_period }}</div>
                @endif
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:46%;">Description</th>
                    <th class="num" style="width:10%;">Qty</th>
                    <th class="num" style="width:15%;">Unit price</th>
                    <th class="num" style="width:13%;">Tax</th>
                    <th class="num" style="width:16%;">Amount (KES)</th>
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

        <div class="summary-wrap">
            <div class="summary-spacer"></div>
            <div class="summary-box">
                <table>
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="num">KES {{ number_format($subtotal, 2) }}</td>
                    </tr>
                    @if ($discount > 0)
                        <tr>
                            <td class="label">Discount</td>
                            <td class="num">- KES {{ number_format($discount, 2) }}</td>
                        </tr>
                    @endif
                    @if ($tax > 0)
                        <tr>
                            <td class="label">Tax</td>
                            <td class="num">KES {{ number_format($tax, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="row-total">
                        <td class="label">Total</td>
                        <td class="num">KES {{ number_format($total, 2) }}</td>
                    </tr>
                    @if ($paid > 0)
                        <tr>
                            <td class="label">Amount paid</td>
                            <td class="num">KES {{ number_format($paid, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="row-balance">
                        <td class="label">{{ $isCredit ? 'Credit amount' : 'Balance due' }}</td>
                        <td class="num">KES {{ number_format($isCredit ? $total : $balance, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if ($hasPaymentBlock && ! $isCredit)
            <section class="section">
                <div class="section-title">Payment instructions</div>
                <div class="section-body">
                    <ul>
                        @if (! empty($payments['mpesa_shortcode']))
                            <li><strong>M-Pesa Paybill / Till:</strong> {{ $payments['mpesa_shortcode'] }}</li>
                        @endif
                        @if (! empty($payments['trust_bank_name']) || ! empty($payments['trust_account_number']))
                            <li>
                                <strong>Bank transfer:</strong>
                                {{ $payments['trust_bank_name'] ?: 'Bank' }}
                                @if (! empty($payments['trust_account_label']))
                                    — {{ $payments['trust_account_label'] }}
                                @endif
                                @if (! empty($payments['trust_account_number']))
                                    · A/C {{ $payments['trust_account_number'] }}
                                @endif
                            </li>
                        @endif
                        <li><strong>Payment reference:</strong> {{ $paymentRef }}</li>
                        @if ($invoice->due_date)
                            <li><strong>Payment deadline:</strong> {{ $invoice->due_date->format('d M Y') }}</li>
                        @endif
                        @if (! empty($payments['payments_notes']))
                            <li>{{ $payments['payments_notes'] }}</li>
                        @endif
                    </ul>
                </div>
            </section>
        @endif

        <section class="section">
            <div class="section-title">Notes &amp; terms</div>
            <div class="section-body">
                <p style="margin:0 0 8px;">{{ $branding['footer_note'] ?? 'Thank you for your prompt payment.' }}</p>
                @if ($isOverdue)
                    <p style="margin:0 0 8px;color:#991b1b;">
                        <strong>Overdue:</strong> This invoice is past due. Please settle the outstanding balance immediately to avoid further arrears action.
                    </p>
                @elseif ($status !== PmInvoice::STATUS_PAID && $invoice->due_date)
                    <p style="margin:0 0 8px;">
                        Please pay by <strong>{{ $invoice->due_date->format('d M Y') }}</strong> to keep your account in good standing.
                    </p>
                @endif
                @if (! empty($payments['late_fee_percent']) && (float) $payments['late_fee_percent'] > 0)
                    <p class="note-muted">
                        Late payments may attract a fee of {{ $payments['late_fee_percent'] }}%
                        @if (! empty($payments['grace_days']))
                            after {{ $payments['grace_days'] }} day(s) grace from the due date.
                        @endif
                    </p>
                @endif
                @if (! empty($payments['rules_notes']))
                    <p class="note-muted">{{ $payments['rules_notes'] }}</p>
                @endif
                @if (! empty($invoice->notes))
                    <p style="margin:8px 0 0; white-space:pre-line;">{{ $invoice->notes }}</p>
                @endif
                <p class="note-muted" style="margin-top:8px;">
                    For billing disputes or payment confirmations, contact our office using the details in the header.
                </p>
            </div>
        </section>

        <footer class="doc-footer">
            <strong>{{ $branding['company_name'] ?? 'Property Manager' }}</strong>
            <br>
            {{ $docLabel }} {{ $invoice->invoice_no }} · Generated {{ $generatedAt }}
        </footer>

    </article>
</div>

<script type="text/php">
    if (isset($pdf)) {
        $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
        $font = $fontMetrics->getFont('DejaVu Sans');
        $size = 8;
        $color = [0.4, 0.45, 0.5];
        $pdf->page_text(500, 820, $text, $font, $size, $color);
    }
</script>

</body>
</html>
