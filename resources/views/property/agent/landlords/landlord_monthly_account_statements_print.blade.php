@php
    $branding = $branding ?? \App\Support\Property\PropertyWorkspaceBranding::documentSnapshot();
    $accent = $branding['colour'] ?? '#0f766e';
    $settlements = $settlements ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PROPERTY ACCOUNT STATEMENT — {{ $landlord->name ?? '' }} — {{ $periodLabel ?? '' }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #0f172a; margin: 0; }
        h1 { font-size: 12pt; margin: 0 0 4px; text-align: center; letter-spacing: 0.04em; }
        h2 { font-size: 9pt; margin: 12px 0 6px; color: #0f172a; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8.5pt; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { color: #64748b; width: 72px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { border: 1px solid #94a3b8; padding: 3px 4px; vertical-align: top; }
        table.data thead th { background: #e2e8f0; font-size: 6.5pt; text-transform: uppercase; text-align: center; }
        table.data thead .group { background: #cbd5e1; }
        table.data tbody td { font-size: 7.5pt; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .left { text-align: left; }
        .totals td { font-weight: bold; background: #f8fafc; }
        .occ { margin: 6px 0 0; font-size: 8pt; font-weight: bold; }
        .two-col { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        .two-col > tbody > tr > td + td { padding: 0 0 0 8px; }
        .line-table { width: 100%; border-collapse: collapse; }
        .line-table td { border-bottom: 1px solid #e2e8f0; padding: 3px 4px; font-size: 8pt; }
        .summary { width: 100%; border-collapse: collapse; border: 1px solid #94a3b8; }
        .summary td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        .summary tr:last-child td { border-bottom: 0; font-weight: bold; background: #f1f5f9; }
        .deduction { color: #b91c1c; }
        .credit { color: #047857; }
        .net-due { color: {{ $accent }}; font-size: 10pt; }
        .signs { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .signs td { width: 25%; vertical-align: top; padding-right: 10px; font-size: 7.5pt; }
        .signs .line { margin-top: 28px; border-top: 1px solid #64748b; padding-top: 3px; color: #475569; }
        .footer { margin-top: 10px; font-size: 7pt; color: #64748b; }
        .statement-block { page-break-after: always; }
        .statement-block:last-child { page-break-after: auto; }
        .toolbar { margin-bottom: 12px; }
        @media print {
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body>
    @if (! ($autoPrint ?? false))
        <div class="toolbar">
            <button type="button" onclick="window.print()" style="padding:8px 14px;font-size:10pt;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;">Print</button>
        </div>
    @endif

    @forelse ($settlements as $settlement)
        <div class="statement-block">
            @include('property.agent.accounting.partials.landlord_settlement_statement_body', [
                'settlement' => $settlement,
                'branding' => $branding,
            ])
            <div class="footer">
                Generated {{ $generatedAt ?? now()->format('d M Y H:i') }}.
                Month statement for {{ $periodLabel ?? ($settlement['period_label'] ?? '') }} only — units, invoiced, received, additions and deductions.
            </div>
        </div>
    @empty
        <p>No linked properties for this landlord.</p>
    @endforelse

    @if ($autoPrint ?? false)
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>
