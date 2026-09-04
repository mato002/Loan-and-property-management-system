@php
    $accent = $branding['colour'] ?? '#0f766e';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Landlord Statement — {{ $landlord->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #0f172a; margin: 0; }
        .header { border-bottom: 3px solid {{ $accent }}; padding-bottom: 12px; margin-bottom: 16px; }
        .company { font-size: 16pt; font-weight: bold; color: {{ $accent }}; }
        .meta { margin-top: 8px; font-size: 9pt; color: #475569; line-height: 1.5; }
        .summary { margin: 16px 0; display: table; width: 100%; table-layout: fixed; }
        .summary-box { display: table-cell; width: 20%; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; vertical-align: top; }
        .summary-label { font-size: 8pt; color: #64748b; text-transform: uppercase; }
        .summary-value { font-size: 11pt; font-weight: bold; margin-top: 4px; word-break: break-word; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f1f5f9; text-align: left; font-size: 8pt; text-transform: uppercase; padding: 6px 8px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .section-title { font-size: 11pt; font-weight: bold; margin: 18px 0 8px; color: #0f172a; }
        .footer { margin-top: 20px; font-size: 8pt; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; line-height: 1.5; }
        .toolbar { margin-bottom: 16px; }
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

    <div class="header">
        <div class="company">{{ $branding['company_name'] ?? 'Property Manager' }}</div>
        <div class="meta">
            <strong>Landlord statement</strong> — {{ $landlord->name }}
            @if ($landlord->email)
                · {{ $landlord->email }}
            @elseif ($landlord->phone)
                · {{ $landlord->phone }}
            @endif
            <br>
            Period: {{ $periodLabel }} · Generated {{ $generatedAt }}
            @if (($commissionPct ?? null) !== null)
                · Commission basis: {{ number_format((float) $commissionPct, 2) }}%
            @endif
        </div>
    </div>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-label">Properties</div>
            <div class="summary-value">{{ $totals['properties'] ?? 0 }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Ownership %</div>
            <div class="summary-value">{{ number_format((float) ($totals['ownership_sum'] ?? 0), 2) }}%</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Owner share</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Pending share</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['pending_share'] ?? 0)) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Agent earnings</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)) }}</div>
        </div>
    </div>

    <div class="section-title">Property breakdown</div>
    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th class="num">Ownership %</th>
                <th class="num">Owner share</th>
                <th class="num">Pending share</th>
                <th class="num">Agent earning</th>
                <th>Last collection</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($propertyBreakdown as $row)
                <tr>
                    <td>{{ $row['property_name'] ?? '—' }}</td>
                    <td class="num">{{ number_format((float) ($row['ownership_percent'] ?? 0), 2) }}%</td>
                    <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['owner_share'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['pending_share'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['agent_earning'] ?? 0)) }}</td>
                    <td>{{ ! empty($row['last_paid_at']) ? \Illuminate\Support\Carbon::parse((string) $row['last_paid_at'])->format('Y-m-d') : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No linked properties for this landlord in the selected period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Owner share is calculated from collected rent in the selected period, adjusted by ownership percentage.
        Pending share reflects outstanding invoices at period end. Agent earnings apply property commission rates.
    </div>

    @if ($autoPrint ?? false)
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>
