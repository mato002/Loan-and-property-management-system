<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unmatched Payments - Print</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 16px; }
        h1 { margin: 0 0 4px 0; font-size: 22px; }
        p.meta { margin: 0 0 12px 0; color: #475569; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        td.amount, th.amount { text-align: right; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 10px;">
        <button onclick="window.print()">Print</button>
    </div>
    <h1>Unmatched Payments</h1>
    <p class="meta">Generated: {{ now()->format('Y-m-d H:i:s') }} | Rows: {{ $items->count() }}</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Transaction</th>
                <th class="amount">Amount</th>
                <th>Account</th>
                <th>Phone</th>
                <th>Source</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ optional($item->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $item->transaction_id }}</td>
                    <td class="amount">{{ number_format((float) $item->amount, 2) }}</td>
                    <td>{{ $item->account_number ?: '—' }}</td>
                    <td>{{ $item->phone ?: '—' }}</td>
                    <td>
                        @if (($item->payment_method ?? '') === 'sms_forwarder')
                            SMS Forwarder
                        @else
                            Equity
                        @endif
                    </td>
                    <td>{{ $item->reason }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No unmatched transactions.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

