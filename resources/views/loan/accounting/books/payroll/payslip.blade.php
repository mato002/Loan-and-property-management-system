<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $line->employee->full_name }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; color: #1e293b; }
        h1 { font-size: 1.25rem; margin-bottom: 0.25rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.875rem; }
        th, td { text-align: left; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: #64748b; font-size: 0.8125rem; }
    </style>
</head>
<body>
    <h1>Payslip</h1>
    <p class="muted">{{ $period->label ?? 'Pay period' }} · {{ $period->period_start->format('Y-m-d') }} to {{ $period->period_end->format('Y-m-d') }}</p>
    <p><strong>{{ $line->employee->full_name }}</strong><br>
    <span class="muted">{{ $line->employee->employee_number }} @if($line->employee->email) · {{ $line->employee->email }} @endif</span></p>
    @if ($line->payslip_number)
        <p class="muted">Payslip reference: {{ $line->payslip_number }}</p>
    @endif
    <table>
        <tr><th>Description</th><th class="num">Amount (KES)</th></tr>
        <tr><td>Gross pay</td><td class="num">{{ number_format((float) $line->gross_pay, 2) }}</td></tr>
        <tr><td>Deductions</td><td class="num">({{ number_format((float) $line->deductions, 2) }})</td></tr>
        <tr><th>Net pay</th><th class="num">{{ number_format((float) $line->net_pay, 2) }}</th></tr>
    </table>
    @if ($line->notes)
        <p class="muted" style="margin-top:1rem;">{{ $line->notes }}</p>
    @endif
    <p class="muted" style="margin-top:2rem;">Generated {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
