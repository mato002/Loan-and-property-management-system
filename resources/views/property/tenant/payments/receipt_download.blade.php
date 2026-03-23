<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt RCP-PAY-{{ $payment->id }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; margin: 24px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .brand { font-size: 24px; font-weight: 700; color: #4f46e5; }
        .muted { color: #64748b; font-size: 12px; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; font-size: 13px; }
        th { background: #f8fafc; }
        .total { font-size: 22px; font-weight: 700; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">PrimeEstate</div>
            <div class="muted">Payment Receipt</div>
        </div>
        <div class="muted">Receipt No: RCP-PAY-{{ $payment->id }}</div>
    </div>

    <div class="box">
        <div><strong>Tenant:</strong> {{ $payment->tenant?->name ?? '—' }}</div>
        <div><strong>Channel:</strong> {{ strtoupper((string) $payment->channel) }}</div>
        <div><strong>Reference:</strong> {{ $payment->external_ref ?: '—' }}</div>
        <div><strong>Paid At:</strong> {{ $payment->paid_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
    </div>

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

    <div class="box" style="margin-top: 14px;">
        <div class="muted">Total paid</div>
        <div class="total">KES {{ number_format((float) $payment->amount, 2) }}</div>
    </div>
</body>
</html>

