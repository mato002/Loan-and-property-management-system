@php
    $accent = $branding['colour'] ?? '#0f766e';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Utility Statement — {{ $tenant->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #0f172a; margin: 0; }
        .header { border-bottom: 3px solid {{ $accent }}; padding-bottom: 12px; margin-bottom: 16px; }
        .company { font-size: 16pt; font-weight: bold; color: {{ $accent }}; }
        .meta { margin-top: 8px; font-size: 9pt; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f1f5f9; text-align: left; font-size: 8pt; text-transform: uppercase; padding: 6px 8px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .summary { margin: 16px 0; display: table; width: 100%; }
        .summary-box { display: table-cell; width: 25%; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .summary-label { font-size: 8pt; color: #64748b; text-transform: uppercase; }
        .summary-value { font-size: 11pt; font-weight: bold; margin-top: 4px; }
        .footer { margin-top: 20px; font-size: 8pt; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $branding['company_name'] ?? 'Property Manager' }}</div>
        <div class="meta">
            <strong>Utility statement</strong> — {{ $tenant->name }}
            @if ($tenant->phone) · {{ $tenant->phone }} @endif
            <br>
            Generated {{ $generatedAt }}
            @if (($filters['from'] ?? '') || ($filters['to'] ?? ''))
                · Period {{ $filters['from'] ?: 'start' }} to {{ $filters['to'] ?: 'today' }}
            @endif
        </div>
    </div>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-label">Current balance</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes((float) $currentBalance) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Opening</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes((float) ($ledger['opening_balance'] ?? 0)) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Period debits</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes((float) ($ledger['total_debit'] ?? 0)) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Closing</div>
            <div class="summary-value">{{ \App\Services\Property\PropertyMoney::kes((float) ($ledger['closing_balance'] ?? 0)) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ledger['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['type_label'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="num">{{ $row['debit_display'] }}</td>
                    <td class="num">{{ $row['credit_display'] }}</td>
                    <td class="num">{{ $row['balance_display'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Utility ledger covers water and mixed utility invoices, penalties, payments, reversals, and credit applications.
        Trace source documents in the agent portal for journal batch links.
    </div>
</body>
</html>
