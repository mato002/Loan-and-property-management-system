@php
    $doc = \App\Support\Property\PropertyWorkspaceBranding::documentSnapshot();
    $brandName = $doc['company_name'];
    $logoUrl = $doc['logo_url'] !== '' ? $doc['logo_url'] : null;
    $palette = \App\Support\Property\PropertyBrandPalette::tokens(
        \App\Support\Property\PropertyBrandPalette::resolve('portal')
    );
    $primary = $palette['primary'];
    $primaryHover = $palette['primary_hover'];
    $primarySoft = $palette['primary_soft'];
    $onPrimary = $palette['on_primary'];
    $method = \App\Support\Property\PmPaymentPresentation::paymentMethod($payment, strtoupper((string) $payment->channel));
    $ref = \App\Support\Property\PmPaymentPresentation::transactionRef($payment, (string) ($payment->external_ref ?: '—'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt RCP-PAY-{{ $payment->id }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; margin: 0; background: #f8fafc; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { max-width: 840px; margin: 26px auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06); overflow: hidden; }
        .accent { height: 6px; background: {{ $primary }}; }
        .hero { padding: 24px 26px 18px; border-bottom: 1px solid #e2e8f0; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; }
        .brand-wrap { display: flex; align-items: center; gap: 12px; }
        .logo-mark { width: 42px; height: 42px; border-radius: 10px; background: {{ $primary }}; color: {{ $onPrimary }}; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; }
        .logo-image { width: 42px; height: 42px; object-fit: contain; border-radius: 10px; background: #ffffff; border: 1px solid #e2e8f0; padding: 4px; box-sizing: border-box; }
        .eyebrow { margin: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.16em; color: #64748b; }
        .brand { margin: 2px 0 0; font-size: 20px; font-weight: 800; color: #0f172a; }
        .meta { text-align: right; font-size: 12px; color: #475569; line-height: 1.7; max-width: 46%; word-break: break-word; }
        .meta strong { color: {{ $primaryHover }}; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .chip { display: inline-block; margin-top: 12px; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 6px 12px; background: {{ $primarySoft }}; color: {{ $primaryHover }}; }
        .content { padding: 20px 26px 26px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }
        .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 6px; font-weight: 700; }
        .text { font-size: 13px; line-height: 1.6; color: #0f172a; word-break: break-word; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .table-wrap { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; word-break: break-word; }
        th { background: {{ $primary }}; color: {{ $onPrimary }}; text-transform: uppercase; letter-spacing: 0.05em; font-size: 11px; }
        td.amount { text-align: right; font-weight: 700; }
        tbody tr + tr td { border-top: 1px solid #e2e8f0; }
        .totals { margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .summary { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 14px; background: #f8fafc; }
        .amount-card { border: 1px solid {{ $primary }}33; border-radius: 12px; padding: 14px; background: {{ $primarySoft }}; }
        .row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: #334155; margin-bottom: 8px; }
        .grand { margin-top: 10px; padding-top: 10px; border-top: 1px solid {{ $primary }}33; display: flex; justify-content: space-between; align-items: center; }
        .grand span { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: {{ $primaryHover }}; font-weight: 700; }
        .grand strong { font-size: 22px; color: #0f172a; }
        .terms { margin-top: 14px; font-size: 12px; color: #64748b; line-height: 1.6; }
        @media print {
            @page { size: A4; margin: 6mm; }
            body { background: #ffffff; }
            .sheet { margin: 0; border-radius: 0; box-shadow: none; border: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="accent"></div>
        <div class="hero">
            <div class="header">
                <div>
                    <div class="brand-wrap">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="logo-image">
                        @else
                            <span class="logo-mark">PH</span>
                        @endif
                        <div>
                            <p class="eyebrow">Payment receipt</p>
                            <p class="brand">{{ $brandName }}</p>
                        </div>
                    </div>
                    @if (! empty($doc['contact_line']))
                        <p style="margin:8px 0 0; font-size:11px; color:#64748b;">{{ $doc['contact_line'] }}</p>
                    @endif
                </div>
                <div class="meta">
                    <div>Receipt No: <strong>RCP-PAY-{{ $payment->id }}</strong></div>
                    <div>Date: {{ $payment->paid_at?->format('d M Y') ?? now()->format('d M Y') }}</div>
                    <div>Tenant: <strong style="font-family:inherit;color:#0f172a;">{{ $payment->tenant?->name ?? '—' }}</strong></div>
                </div>
            </div>
            <span class="chip">Total: KES {{ number_format((float) $payment->amount, 2) }}</span>
        </div>
        <div class="content">
            <div class="grid">
                <div class="box">
                    <div class="label">Tenant</div>
                    <div class="text">
                        <strong>{{ $payment->tenant?->name ?? '—' }}</strong><br>
                        @if ($payment->tenant?->account_number)
                            <span class="mono">{{ $payment->tenant->account_number }}</span><br>
                        @endif
                        {{ $payment->tenant?->email ?: ($payment->tenant?->phone ?: '—') }}
                    </div>
                </div>
                <div class="box">
                    <div class="label">Payment details</div>
                    <div class="text">
                        <strong>Method:</strong> {{ $method }}<br>
                        <strong>Reference:</strong> <span class="mono">{{ $ref }}</span><br>
                        <strong>Paid at:</strong> {{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th style="text-align:right;">Allocated amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payment->allocations as $allocation)
                            <tr>
                                <td>{{ $allocation->invoice?->invoice_no ?? ('INV-'.$allocation->pm_invoice_id) }}</td>
                                <td class="amount">KES {{ number_format((float) $allocation->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">No allocations recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="totals">
                <div class="summary">
                    <div class="label">Payment method</div>
                    <div class="text">
                        <strong>{{ $method }}</strong><br>
                        <span class="mono">{{ $ref }}</span>
                    </div>
                </div>
                <div class="amount-card">
                    <div class="row"><span>Subtotal</span><span>KES {{ number_format((float) $payment->amount, 2) }}</span></div>
                    <div class="row"><span>Tax</span><span>0.00</span></div>
                    <div class="grand">
                        <span>Grand total</span>
                        <strong>KES {{ number_format((float) $payment->amount, 2) }}</strong>
                    </div>
                </div>
            </div>
            <div class="terms">
                This document confirms receipt of payment. Keep it for your records and future account reconciliation.
            </div>
        </div>
    </div>
</body>
</html>
