<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt RCP-PAY-{{ $payment->id }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; margin: 0; background: #f3f4f6; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { max-width: 840px; margin: 26px auto; background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .hero { position: relative; padding: 26px; background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 60%, #ffffff 100%); border-bottom: 1px solid #dbeafe; overflow: hidden; }
        .hero:before { content: ""; position: absolute; top: -42px; left: -50px; width: 210px; height: 150px; background: radial-gradient(circle at center, rgba(59, 130, 246, 0.18), rgba(99, 102, 241, 0.06) 60%, transparent 75%); border-radius: 50%; }
        .hero:after { content: ""; position: absolute; right: -48px; top: -42px; width: 170px; height: 130px; background: radial-gradient(circle at center, rgba(99, 102, 241, 0.14), rgba(147, 197, 253, 0.06) 65%, transparent 75%); border-radius: 50%; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; }
        .brand-wrap { position: relative; z-index: 1; display: flex; align-items: center; gap: 12px; }
        .logo-mark { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(145deg, #1d4ed8, #4338ca); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; letter-spacing: 0.08em; box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25); }
        .brand { font-size: 24px; font-weight: 800; color: #1e3a8a; letter-spacing: 0.03em; }
        .title { margin: 4px 0 0; font-size: 36px; letter-spacing: 0.06em; font-weight: 900; color: #312e81; }
        .meta { position: relative; z-index: 1; text-align: right; font-size: 12px; color: #475569; line-height: 1.8; max-width: 42%; word-break: break-word; }
        .meta strong { color: #1d4ed8; }
        .chips { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
        .chip { font-size: 11px; font-weight: 700; border-radius: 999px; padding: 6px 12px; }
        .chip-total { background: #dbeafe; color: #1d4ed8; }
        .chip-ref { background: #e0e7ff; color: #4338ca; }
        .content { padding: 22px 26px 26px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }
        .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 6px; }
        .text { font-size: 13px; line-height: 1.6; color: #0f172a; word-break: break-word; }
        .text strong { color: #1e293b; }
        .table-wrap { margin-top: 4px; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; word-break: break-word; }
        th { background: #2563eb; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; font-size: 11px; }
        tbody tr + tr td { border-top: 1px solid #e2e8f0; }
        .totals { margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .summary { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 14px; background: #f8fafc; }
        .amount-card { border: 1px solid #dbeafe; border-radius: 12px; padding: 14px; background: #eff6ff; }
        .row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: #334155; margin-bottom: 8px; }
        .grand { margin-top: 10px; padding-top: 10px; border-top: 1px solid #bfdbfe; display: flex; justify-content: space-between; align-items: center; }
        .grand span { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #1d4ed8; font-weight: 700; }
        .grand strong { font-size: 24px; color: #0f172a; }
        .footer-note { margin-top: 14px; display: grid; grid-template-columns: 1fr 120px; gap: 12px; align-items: end; }
        .terms { font-size: 12px; color: #64748b; line-height: 1.6; }
        .city { height: 54px; display: flex; align-items: flex-end; gap: 6px; justify-content: flex-end; }
        .city span { display: block; width: 18px; background: linear-gradient(180deg, #dbeafe, #c7d2fe); border-radius: 4px 4px 0 0; }
        .city span:nth-child(1) { height: 20px; }
        .city span:nth-child(2) { height: 34px; }
        .city span:nth-child(3) { height: 24px; }
        .city span:nth-child(4) { height: 42px; }
        .logo-image { width: 40px; height: 40px; object-fit: contain; border-radius: 10px; background: #ffffff; border: 1px solid #dbeafe; padding: 4px; box-sizing: border-box; }
        @media print {
            @page { size: A4; margin: 6mm; }
            body { background: #ffffff; }
            .sheet { margin: 0; border-radius: 0; box-shadow: none; border: 0; max-width: none; }
            .hero { padding: 16px; }
            .content { padding: 12px 14px 14px; }
            .header { gap: 10px; }
            .brand { font-size: 20px; }
            .title { font-size: 26px; margin-top: 2px; }
            .meta { font-size: 11px; line-height: 1.5; max-width: 46%; }
            .chips { margin-top: 8px; gap: 6px; }
            .chip { padding: 4px 8px; font-size: 10px; }
            .grid { gap: 8px; margin-bottom: 8px; }
            .box { padding: 10px; }
            .label { font-size: 10px; }
            .text { font-size: 11px; line-height: 1.35; }
            th, td { padding: 8px 9px; font-size: 11px; }
            th { font-size: 10px; }
            .totals { margin-top: 10px; gap: 8px; }
            .summary, .amount-card { padding: 10px; }
            .row { font-size: 11px; margin-bottom: 5px; }
            .grand { margin-top: 6px; padding-top: 6px; }
            .grand strong { font-size: 18px; }
            .footer-note { margin-top: 8px; gap: 8px; }
            .terms { font-size: 10px; line-height: 1.35; }
            .city { height: 36px; }
            .city span { width: 12px; }
        }
    </style>
</head>
<body>
    @php($brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name', 'Property Management System'))
    @php($logoRaw = trim((string) \App\Models\PropertyPortalSetting::getValue('company_logo_url', '')))
    @php($logoUrl = $logoRaw !== '' ? ((str_starts_with($logoRaw, 'http://') || str_starts_with($logoRaw, 'https://') || str_starts_with($logoRaw, '/')) ? $logoRaw : \Illuminate\Support\Facades\Storage::url($logoRaw)) : null)
    <div class="sheet">
        <div class="hero">
            <div class="header">
                <div>
                    <div class="brand-wrap">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo" class="logo-image">
                        @else
                            <span class="logo-mark">PM</span>
                        @endif
                        <div class="brand">{{ $brandName }}</div>
                    </div>
                    <p class="title">INVOICE</p>
                </div>
                <div class="meta">
                    <div>Billing To: <strong>{{ $payment->tenant?->name ?? '—' }}</strong></div>
                    <div>Receipt No: <strong>RCP-PAY-{{ $payment->id }}</strong></div>
                    <div>Date: {{ $payment->paid_at?->format('d M Y') ?? now()->format('d M Y') }}</div>
                </div>
            </div>
            <div class="chips">
                <span class="chip chip-ref">Channel: {{ strtoupper((string) $payment->channel) }}</span>
                <span class="chip chip-total">Total: KES {{ number_format((float) $payment->amount, 2) }}</span>
            </div>
        </div>
        <div class="content">
            <div class="grid">
                <div class="box">
                    <div class="label">Tenant</div>
                    <div class="text">
                        <strong>{{ $payment->tenant?->name ?? '—' }}</strong><br>
                        {{ $payment->tenant?->email ?? '—' }}
                    </div>
                </div>
                <div class="box">
                    <div class="label">Payment details</div>
                    <div class="text">
                        <strong>Reference:</strong> {{ $payment->external_ref ?: '—' }}<br>
                        <strong>Paid at:</strong> {{ $payment->paid_at?->format('Y-m-d H:i:s') ?? '—' }}
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Allocated amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payment->allocations as $allocation)
                            <tr>
                                <td>{{ $allocation->invoice?->invoice_no ?? ('INV-'.$allocation->pm_invoice_id) }}</td>
                                <td>KES {{ number_format((float) $allocation->amount, 2) }}</td>
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
                        <strong>{{ strtoupper((string) $payment->channel) }}</strong><br>
                        Account Ref: {{ $payment->external_ref ?: '—' }}
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
            <div class="footer-note">
                <div class="terms">
                    This document confirms receipt of payment. Keep it for your records and future account reconciliation.
                </div>
                <div class="city" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

