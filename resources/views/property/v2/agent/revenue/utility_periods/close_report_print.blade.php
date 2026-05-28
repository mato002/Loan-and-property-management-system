@php
    $totals = $report['totals'] ?? [];
    $kpis = $report['kpis'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Utility Close Report — {{ $billingMonth }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #0f172a; margin: 0; }
        .header { border-bottom: 3px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
        .title { font-size: 16pt; font-weight: bold; color: #0f766e; }
        .meta { margin-top: 8px; font-size: 9pt; color: #475569; }
        h2 { font-size: 11pt; margin: 18px 0 8px; color: #0f766e; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f1f5f9; text-align: left; font-size: 8pt; text-transform: uppercase; padding: 6px 8px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .summary { display: table; width: 100%; margin: 12px 0; }
        .summary-box { display: table-cell; width: 25%; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .summary-label { font-size: 8pt; color: #64748b; text-transform: uppercase; }
        .summary-value { font-size: 11pt; font-weight: bold; margin-top: 4px; }
        .footer { margin-top: 20px; font-size: 8pt; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .badge-closed { display: inline-block; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 999px; font-size: 9pt; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Utility billing period close report</div>
        <div class="meta">
            Period <strong>{{ $billingMonth }}</strong>
            <span class="badge-closed">CLOSED</span>
            @if ($period->closed_at)
                · Closed {{ $period->closed_at->format('Y-m-d H:i') }}
                @if ($period->closedBy) by {{ $period->closedBy->name }} @endif
            @endif
            <br>
            Generated {{ $report['generated_at'] ?? now()->toDateTimeString() }}
            · {{ $report['reading_count'] ?? 0 }} readings · {{ $report['invoice_count'] ?? 0 }} invoices
        </div>
    </div>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-label">Total billed</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes($totals['total_billed'] ?? 0) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Open AR</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes($totals['open_ar'] ?? 0) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Penalties</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes($totals['total_penalties'] ?? 0) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Unapplied credit</div>
            <div class="summary-value">{{ $report['credits_summary']['total_unapplied_display'] ?? \App\Services\Property\PropertyMoney::kes(0) }}</div>
        </div>
    </div>

    <h2>Portfolio totals</h2>
    <table>
        <tbody>
            <tr><td>Total collected</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes($totals['total_collected'] ?? 0) }}</td></tr>
            <tr><td>Credit applied</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes($totals['total_credited'] ?? 0) }}</td></tr>
            <tr><td>Reversed</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes($totals['total_reversed'] ?? 0) }}</td></tr>
            <tr><td>Suspense (GL 1250)</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes($totals['suspense_balance'] ?? 0) }}</td></tr>
            <tr><td>Utility AR (GL 1210)</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes($totals['utility_ar_gl'] ?? 0) }}</td></tr>
            <tr><td>GL subledger variance</td><td class="num">{{ \App\Services\Property\PropertyMoney::kes($totals['gl_subledger_variance'] ?? 0) }}</td></tr>
        </tbody>
    </table>

    @if (! empty($report['outstanding_balances']))
        <h2>Outstanding balances</h2>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Tenant</th>
                    <th>Property</th>
                    <th class="num">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['outstanding_balances'] as $row)
                    <tr>
                        <td>{{ $row['invoice_no'] ?? '—' }}</td>
                        <td>{{ $row['tenant'] ?? '—' }}</td>
                        <td>{{ $row['property'] ?? '—' }}</td>
                        <td class="num">{{ \App\Services\Property\PropertyMoney::kes($row['balance'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($report['adjustments']))
        <h2>Adjustment audit trail</h2>
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Action</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['adjustments'] as $adj)
                    <tr>
                        <td>{{ $adj['at'] ?? '—' }}</td>
                        <td>{{ str_replace('_', ' ', $adj['action'] ?? '') }}</td>
                        <td>{{ $adj['notes'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($period->close_notes)
        <h2>Close notes</h2>
        <p>{{ $period->close_notes }}</p>
    @endif

    <div class="footer">
        Utility billing period financial control · Immutable after close · Override actions are audited in utility_audit_logs.
    </div>
</body>
</html>
