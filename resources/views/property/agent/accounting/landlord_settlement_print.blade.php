@php
    $s = $settlement;
    $unitStats = $s['unit_stats'] ?? [];
    $unitTotals = $s['unit_totals'] ?? [];
    $branding = $branding ?? \App\Support\Property\PropertyWorkspaceBranding::documentSnapshot();
    $accent = $branding['colour'] ?? '#0f766e';
    $feePct = rtrim(rtrim(number_format((float) ($s['commission_percent'] ?? 0), 2, '.', ''), '0'), '.');
    $kes = static fn (float $n): string => number_format($n, 2, '.', ',');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PROPERTY ACCOUNT STATEMENT — {{ $s['property_name'] ?? '' }}</title>
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
    </style>
</head>
<body>
    @include('property.partials.document_letterhead', [
        'branding' => $branding,
        'title' => null,
        'subtitle' => null,
        'meta' => null,
        'variant' => 'pdf',
    ])

    <h1>PROPERTY ACCOUNT STATEMENT — FINAL</h1>

    <table class="meta">
        <tr>
            <td class="label">Landlord:</td>
            <td><strong>{{ $s['landlord_name'] ?? '—' }}</strong></td>
            <td class="label">Property:</td>
            <td><strong>{{ $s['property_name'] ?? '—' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Period:</td>
            <td>
                <strong>{{ $s['period_label'] ?? '—' }}</strong>
                @if (! empty($s['period_range_label']))
                    &nbsp;({{ $s['period_range_label'] }})
                @endif
            </td>
            <td class="label">Ownership:</td>
            <td>{{ rtrim(rtrim(number_format((float) ($s['ownership_percent'] ?? 0), 2, '.', ''), '0'), '.') }}%</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th rowspan="2" class="left">Unit</th>
                <th rowspan="2" class="left">Tenant names</th>
                <th rowspan="2">Rent / month</th>
                <th colspan="3" class="group">Balance B/F</th>
                <th colspan="3" class="group">Monthly expenditure</th>
                <th colspan="3" class="group">Total paid</th>
            </tr>
            <tr>
                <th>Rent</th><th>Garbage</th><th>Water</th>
                <th>Rent</th><th>Garbage</th><th>Water</th>
                <th>Rent</th><th>Garbage</th><th>Water</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($s['unit_lines'] ?? [] as $line)
                <tr>
                    <td class="left">{{ $line['unit_label'] ?? '—' }}</td>
                    <td class="left">{{ $line['tenant_name'] ?? '—' }}</td>
                    <td class="num">{{ $kes((float) ($line['rent_per_month'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['rent_bf'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['garbage_bf'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['water_bf'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['rent_billed'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['garbage_billed'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['water_billed'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['rent_received'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['garbage_received'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($line['water_received'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="left">No units on this property.</td></tr>
            @endforelse
            @if (! empty($s['unit_lines']))
                <tr class="totals">
                    <td class="left" colspan="2">TOTALS</td>
                    <td class="num">{{ $kes((float) ($unitTotals['rent_per_month'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['rent_bf'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['garbage_bf'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['water_bf'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['rent_billed'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['garbage_billed'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['water_billed'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['rent_received'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['garbage_received'] ?? 0)) }}</td>
                    <td class="num">{{ $kes((float) ($unitTotals['water_received'] ?? 0)) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="occ">
        OCCUPIED UNITS: {{ (int) ($unitStats['units_occupied'] ?? 0) }}
        &nbsp;|&nbsp;
        VACANT UNITS: {{ (int) ($unitStats['units_vacant'] ?? 0) }}
    </p>

    <table class="two-col">
        <tr>
            <td>
                <h2>Additions</h2>
                <table class="line-table">
                    @forelse ($s['additions'] ?? [] as $addition)
                        <tr>
                            <td>{{ $addition['description'] ?? 'Addition' }}</td>
                            <td class="num credit">{{ $kes((float) ($addition['amount'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">No additions in this period.</td></tr>
                    @endforelse
                    <tr>
                        <td><strong>TOTAL ADDITIONS</strong></td>
                        <td class="num"><strong>{{ $kes((float) ($s['additions_total'] ?? 0)) }}</strong></td>
                    </tr>
                </table>

                <h2>Deductions</h2>
                <table class="line-table">
                    @forelse ($s['deductions'] ?? [] as $deduction)
                        <tr>
                            <td>{{ $deduction['description'] ?? 'Deduction' }}</td>
                            <td class="num deduction">{{ $kes((float) ($deduction['amount'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">No deductions in this period.</td></tr>
                    @endforelse
                    <tr>
                        <td><strong>TOTAL DEDUCTIONS</strong></td>
                        <td class="num"><strong>{{ $kes((float) ($s['deductions_total'] ?? 0)) }}</strong></td>
                    </tr>
                </table>
            </td>
            <td>
                <h2>Statement summary</h2>
                <table class="summary">
                    <tr>
                        <td>Rent received</td>
                        <td class="num">{{ $kes((float) ($s['rent_received'] ?? ($s['collected']['rent'] ?? 0))) }}</td>
                    </tr>
                    <tr>
                        <td>Total utility</td>
                        <td class="num">{{ $kes((float) ($s['utility_received'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td>Less management fee ({{ $feePct }}%)</td>
                        <td class="num deduction">− {{ $kes((float) ($s['management_fee'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td>Less other expenses</td>
                        <td class="num deduction">− {{ $kes((float) ($s['other_expenses'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td>Add total additions</td>
                        <td class="num credit">+ {{ $kes((float) ($s['additions_total'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td>Less total deductions</td>
                        <td class="num deduction">− {{ $kes((float) ($s['deductions_total'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td>Balance B/F</td>
                        <td class="num">{{ $kes((float) ($s['balance_brought_forward'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td>Net amount due</td>
                        <td class="num net-due">{{ $kes((float) ($s['net_amount_due'] ?? 0)) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="signs">
        <tr>
            <td>PREPARED BY<div class="line">Name / Signature</div></td>
            <td>APPROVED BY<div class="line">Name / Signature</div></td>
            <td>AUTHORISED BY<div class="line">Name / Signature</div></td>
            <td>RECEIVED BY<div class="line">Name / Date / Signature</div></td>
        </tr>
    </table>

    <div class="footer">
        Generated {{ $generatedAt ?? now()->format('d M Y H:i') }}.
        Figures from posted invoices, collections, deposits, and landlord ledger for this property.
    </div>
</body>
</html>
