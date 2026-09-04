@php
    $s = $settlement;
    $unitStats = $s['unit_stats'] ?? [];
    $accent = $branding['colour'] ?? '#0f766e';
    $company = $branding['company_name'] ?? 'Property Manager';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Landlord Settlement — {{ $s['property_name'] ?? '' }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5pt; color: #0f172a; margin: 0; }
        .header { border-bottom: 3px solid {{ $accent }}; padding-bottom: 10px; margin-bottom: 14px; }
        .company { font-size: 15pt; font-weight: bold; color: {{ $accent }}; }
        .meta { margin-top: 6px; font-size: 8.5pt; color: #475569; line-height: 1.45; }
        h2 { font-size: 11pt; margin: 14px 0 8px; color: #0f172a; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .grid td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        .box { border: 1px solid #e2e8f0; padding: 8px 10px; background: #f8fafc; }
        .box dt { font-size: 7.5pt; text-transform: uppercase; color: #64748b; }
        .box dd { margin: 2px 0 8px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #f1f5f9; text-align: left; font-size: 7.5pt; text-transform: uppercase; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .summary-row td { font-weight: bold; background: #f8fafc; }
        .deduction { color: #b91c1c; }
        .credit { color: #047857; }
        .footer { margin-top: 16px; font-size: 7.5pt; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .net-due { font-size: 12pt; font-weight: bold; color: {{ $accent }}; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company }}</div>
        <div class="meta">
            <strong>Landlord settlement statement</strong><br>
            {{ $s['property_name'] ?? '—' }} · {{ $s['period_label'] ?? '—' }}<br>
            Landlord: {{ $s['landlord_name'] ?? '—' }} · {{ $s['ownership_percent'] ?? 0 }}% ownership · {{ $s['commission_percent'] ?? 0 }}% management fee<br>
            Generated {{ $generatedAt ?? now()->format('d M Y H:i') }}
        </div>
    </div>

    <table class="grid"><tr>
        <td>
            <div class="box">
                <h2 style="margin-top:0;">Occupancy</h2>
                <dl>
                    <dt>Occupied</dt><dd>{{ $unitStats['units_occupied'] ?? 0 }}</dd>
                    <dt>Vacant</dt><dd>{{ $unitStats['units_vacant'] ?? 0 }}</dd>
                    <dt>Owner occupied</dt><dd>{{ $unitStats['units_owner_occupied'] ?? 0 }}</dd>
                    <dt>On notice</dt><dd>{{ $unitStats['units_notice'] ?? 0 }}</dd>
                </dl>
            </div>
        </td>
        <td>
            <div class="box">
                <h2 style="margin-top:0;">Collections (owner share)</h2>
                <table class="data">
                    <tr><td>Rent</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['owner_collected']['rent'] ?? 0)) }}</td></tr>
                    <tr><td>Garbage</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['owner_collected']['garbage'] ?? 0)) }}</td></tr>
                    <tr><td>Water</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['owner_collected']['water'] ?? 0)) }}</td></tr>
                    @if ((float) ($s['owner_collected']['other'] ?? 0) > 0)
                        <tr><td>Other</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['owner_collected']['other'] ?? 0)) }}</td></tr>
                    @endif
                    <tr class="summary-row"><td>Management fee</td><td class="num deduction">− {{ \App\Services\Property\PropertyMoney::kes((float) ($s['management_fee'] ?? 0)) }}</td></tr>
                    <tr class="summary-row"><td>Net collected</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['net_collected'] ?? 0)) }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <h2>Landlord ledger</h2>
    <table class="data">
        <tr>
            <td>Balance brought forward</td>
            <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['balance_brought_forward'] ?? 0)) }}</td>
        </tr>
        <tr>
            <td>Period credits</td>
            <td class="num credit">+ {{ \App\Services\Property\PropertyMoney::kes((float) ($s['period_credits'] ?? 0)) }}</td>
        </tr>
        <tr>
            <td>Period debits</td>
            <td class="num deduction">− {{ \App\Services\Property\PropertyMoney::kes((float) ($s['period_debits'] ?? 0)) }}</td>
        </tr>
        <tr class="summary-row">
            <td>Net amount due</td>
            <td class="num net-due">{{ \App\Services\Property\PropertyMoney::kes((float) ($s['net_amount_due'] ?? 0)) }}</td>
        </tr>
    </table>

    @if (! empty($s['deductions']))
        <h2>Period deductions</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($s['deductions'] as $deduction)
                    <tr>
                        <td>{{ $deduction['occurred_at'] ?? '—' }}</td>
                        <td>{{ $deduction['description'] ?? 'Deduction' }}</td>
                        <td class="num deduction">− {{ \App\Services\Property\PropertyMoney::kes((float) ($deduction['amount'] ?? 0)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Unit collections</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Unit</th>
                <th>Tenant</th>
                <th>Status</th>
                <th class="num">Rent</th>
                <th class="num">Garbage</th>
                <th class="num">Water</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($s['unit_lines'] ?? [] as $line)
                <tr>
                    <td>{{ $line['unit_label'] ?? '—' }}</td>
                    <td>{{ $line['tenant_name'] ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', (string) ($line['unit_status'] ?? '—'))) }}</td>
                    <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['rent_received'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_received'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['water_received'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['total_received'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No unit collections in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        This statement is generated from posted collections and landlord ledger entries. Net amount due reflects balance after management fees and period deductions.
    </div>
</body>
</html>
