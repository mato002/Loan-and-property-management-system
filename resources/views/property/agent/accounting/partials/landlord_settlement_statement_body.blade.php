@php
    $s = $settlement;
    $unitStats = $s['unit_stats'] ?? [];
    $unitTotals = $s['unit_totals'] ?? [];
    $feePct = rtrim(rtrim(number_format((float) ($s['commission_percent'] ?? 0), 2, '.', ''), '0'), '.');
    $kes = static fn (float $n): string => number_format($n, 2, '.', ',');
@endphp

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
            <th rowspan="2" class="left">Tenant / resident</th>
            <th rowspan="2">Per month</th>
            <th colspan="3" class="group">Balance B/F</th>
            <th colspan="3" class="group">Amount invoiced</th>
            <th colspan="3" class="group">Amount received</th>
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

            <h2>Deductions / payments &amp; disbursement</h2>
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
