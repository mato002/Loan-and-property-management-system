@php
    $doc = $doc ?? \App\Support\Property\PropertyWorkspaceBranding::documentSnapshot($brandingAgentUserId ?? null);
    $brandName = $doc['company_name'];
    $logoSrc = (string) (($doc['logo_embed'] ?? '')
        ?: \App\Support\Property\PropertyWorkspaceBranding::embeddableLogoSrc($doc)
        ?: ($doc['logo_url'] ?? ''));
    $palette = \App\Support\Property\PropertyBrandPalette::tokens(
        \App\Support\Property\PropertyBrandPalette::resolve('portal')
    );
    $primary = $doc['colour'] ?? $palette['primary'];
    $primaryHover = $palette['primary_hover'];
    $primarySoft = $palette['primary_soft'];
    $onPrimary = $palette['on_primary'];
    $method = \App\Support\Property\PmPaymentPresentation::paymentMethod($payment, strtoupper((string) $payment->channel));
    $ref = \App\Support\Property\PmPaymentPresentation::transactionRef($payment, (string) ($payment->external_ref ?: '—'));
    $contactLine = (string) ($doc['contact_line'] ?? '');
    $address = trim((string) ($doc['address'] ?? ''));
    $phone = trim((string) ($doc['phone'] ?? ''));
    $email = trim((string) ($doc['email'] ?? ''));
    $regNo = trim((string) ($doc['contact_reg_no'] ?? ''));
    $autoPrint = (bool) ($autoPrint ?? request()->boolean('print'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt RCP-PAY-{{ $payment->id }} — {{ $brandName }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: 'Segoe UI', Arial, Helvetica, sans-serif; color: #0f172a; margin: 0; background: #e8edf2; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { max-width: 840px; margin: 0 auto; padding: 14px 16px 0; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
        .toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn { display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #0f172a; cursor: pointer; }
        .btn-primary { background: {{ $primary }}; border-color: {{ $primary }}; color: {{ $onPrimary }}; }
        .sheet { max-width: 840px; margin: 12px auto 28px; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06); overflow: hidden; }
        .accent { height: 5px; background: {{ $primary }}; }
        .hero { padding: 22px 26px 16px; border-bottom: 2px solid {{ $primary }}; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; }
        .brand-wrap { display: flex; align-items: flex-start; gap: 12px; }
        .logo-image { max-height: 52px; max-width: 160px; object-fit: contain; display: block; }
        .company-name { margin: 0; font-size: 18px; font-weight: 800; color: {{ $primary }}; letter-spacing: -0.02em; }
        .company-meta { margin-top: 4px; font-size: 11px; color: #64748b; line-height: 1.5; }
        .doc-label { margin: 10px 0 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em; color: #64748b; }
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
        .footer-brand { margin-top: 18px; padding-top: 12px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 11px; color: #64748b; }
        .footer-brand strong { color: #0f172a; }
        @media print {
            @page { size: A4; margin: 8mm; }
            body { background: #ffffff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; border-radius: 0; box-shadow: none; border: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div style="font-size:12px;color:#64748b;">Payment receipt <strong style="color:#0f172a;">RCP-PAY-{{ $payment->id }}</strong></div>
        <div class="toolbar-actions">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
            <a class="btn" href="{{ route('property.payments.receipt.show', $payment) }}">Back to receipt</a>
        </div>
    </div>

    <div class="sheet">
        <div class="accent"></div>
        <div class="hero">
            <div class="header">
                <div>
                    <div class="brand-wrap">
                        @if ($logoSrc !== '')
                            <img src="{{ $logoSrc }}" alt="{{ $brandName }}" class="logo-image">
                        @endif
                        <div>
                            <p class="company-name">{{ $brandName }}</p>
                            <div class="company-meta">
                                @if ($contactLine !== '')
                                    <div>{{ $contactLine }}</div>
                                @else
                                    @if ($address !== '')<div>{{ $address }}</div>@endif
                                    @if ($phone !== '' || $email !== '')
                                        <div>{{ collect([$phone, $email])->filter()->implode(' · ') }}</div>
                                    @endif
                                    @if ($regNo !== '')<div>Reg: {{ $regNo }}</div>@endif
                                @endif
                            </div>
                            <p class="doc-label">Payment receipt</p>
                        </div>
                    </div>
                </div>
                <div class="meta">
                    <div>Receipt No: <strong>RCP-PAY-{{ $payment->id }}</strong></div>
                    <div>Date: {{ $payment->paid_at?->format('d M Y') ?? now()->format('d M Y') }}</div>
                    <div>Tenant: <strong style="font-family:inherit;color:#0f172a;">{{ $payment->tenant?->name ?? '—' }}</strong></div>
                </div>
            </div>
            <span class="chip">Total: {{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</span>
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
                        <strong>Paid at:</strong> {{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}<br>
                        <strong>Origin:</strong> {{ \App\Support\Property\PmPaymentPresentation::originLabel($payment) }}
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
                                <td class="amount">{{ \App\Services\Property\PropertyMoney::kes((float) $allocation->amount) }}</td>
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
                    <div class="row"><span>Allocated</span><span>{{ \App\Services\Property\PropertyMoney::kes((float) ($allocatedTotal ?? $payment->allocations->sum('amount'))) }}</span></div>
                    @if (($creditCreated ?? 0) > 0)
                        <div class="row"><span>Tenant credit</span><span>{{ \App\Services\Property\PropertyMoney::kes((float) $creditCreated) }}</span></div>
                    @endif
                    <div class="grand">
                        <span>Grand total</span>
                        <strong>{{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</strong>
                    </div>
                </div>
            </div>
            <div class="terms">
                This document confirms receipt of payment. Keep it for your records and future account reconciliation.
            </div>
            <div class="footer-brand">
                Issued by <strong>{{ $brandName }}</strong>
                @if ($phone !== '' || $email !== '')
                    · {{ collect([$phone, $email])->filter()->implode(' · ') }}
                @endif
            </div>
        </div>
    </div>
    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
