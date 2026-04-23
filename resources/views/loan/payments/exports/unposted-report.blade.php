<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14px 16px 24px 16px; }
        body { font-family: "Segoe UI", DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 10px; margin: 0; }
        .report { position: relative; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .header td { vertical-align: top; }
        .brand-left { width: 56%; }
        .brand-right { width: 44%; text-align: right; font-size: 9px; line-height: 1.35; color: #475569; }
        .logo { max-width: 44px; max-height: 44px; vertical-align: middle; margin-right: 8px; }
        .company-name { font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.1; }
        .tagline { font-size: 10px; color: #64748b; margin-top: 2px; }
        .rule { border-top: 3px solid #1a5f7a; margin: 6px 0 8px; }
        .title { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .meta { font-size: 9px; color: #64748b; margin-bottom: 8px; }

        .tiles { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 10px; }
        .tile { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; }
        .tile-label { font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 3px; }
        .tile-value { font-size: 16px; font-weight: 700; color: #0f172a; }

        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .data th, .data td { border: 1px solid #d1d5db; padding: 5px 6px; vertical-align: top; word-break: break-word; }
        .data th { background: #1a5f7a; color: #fff; font-size: 8px; text-transform: uppercase; text-align: left; letter-spacing: 0.03em; }
        .data tbody tr:nth-child(even) td { background: #f8fafc; }
        .ref { font-weight: 700; color: #1e3a8a; }
        .amount { text-align: right; font-weight: 700; }
        .status-pill { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .status-unposted, .status-unmatched { background: #fef3c7; color: #92400e; }
        .status-posted { background: #dcfce7; color: #166534; }
        .status-default { background: #e2e8f0; color: #334155; }

        tfoot td { background: #f1f5f9; font-weight: 700; }
        .right { text-align: right; }

        .footer-left {
            position: fixed;
            left: 16px;
            bottom: 8px;
            font-size: 8px;
            font-style: italic;
            color: #6b7280;
        }
        .footer-right {
            position: fixed;
            right: 16px;
            bottom: 8px;
            font-size: 8px;
            color: #6b7280;
        }
        .footer-right:after { content: "Page " counter(page) " of " counter(pages); }
    </style>
</head>
<body>
    <div class="report">
        <table class="header">
            <tr>
                <td class="brand-left">
                    @if ($logoSrc !== '')
                        <img src="{{ $logoSrc }}" alt="Company logo" class="logo">
                    @endif
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="tagline">{{ $tagline }}</div>
                </td>
                <td class="brand-right">
                    @foreach ($contactLines as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                </td>
            </tr>
        </table>
        <div class="rule"></div>

        <div class="title">UNPOSTED PAYMENTS REPORT</div>
        <div class="meta">Generated at: {{ $generatedAt->format('M j, Y, g:i A') }}</div>

        <table class="tiles">
            <tr>
                <td>
                    <div class="tile">
                        <div class="tile-label">Total Transactions</div>
                        <div class="tile-value">{{ number_format($totalTransactions) }}</div>
                    </div>
                </td>
                <td>
                    <div class="tile">
                        <div class="tile-label">Total Amount (KES)</div>
                        <div class="tile-value">{{ number_format($totalAmount, 2) }}</div>
                    </div>
                </td>
                <td>
                    <div class="tile">
                        <div class="tile-label">Attention Required</div>
                        <div class="tile-value">{{ number_format($attentionRequired) }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="data">
            <thead>
                <tr>
                    <th style="width: 12%;">Reference</th>
                    <th style="width: 12%;">Date & Time</th>
                    <th style="width: 12%;">Channel</th>
                    <th style="width: 11%;">Receipt Code</th>
                    <th style="width: 16%;">Client Name</th>
                    <th style="width: 12%;">Unit / Loan #</th>
                    <th style="width: 10%;" class="right">Amount (KES)</th>
                    <th style="width: 11%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $statusClass = match ($row['status']) {
                            'unposted', 'unmatched' => 'status-unposted',
                            'posted', 'processed' => 'status-posted',
                            default => 'status-default',
                        };
                    @endphp
                    <tr>
                        <td class="ref">{{ $row['reference'] !== '' ? $row['reference'] : '—' }}</td>
                        <td>{{ $row['date_time'] !== '' ? $row['date_time'] : '—' }}</td>
                        <td>{{ $row['channel'] !== '' ? $row['channel'] : '—' }}</td>
                        <td>{{ $row['receipt'] !== '' ? $row['receipt'] : '—' }}</td>
                        <td>{{ $row['client_name'] !== '' ? $row['client_name'] : '—' }}</td>
                        <td>{{ $row['loan_number'] !== '' ? $row['loan_number'] : '—' }}</td>
                        <td class="amount">{{ number_format((float) $row['amount'], 2) }}</td>
                        <td><span class="status-pill {{ $statusClass }}">{{ $row['status'] !== '' ? $row['status'] : 'unknown' }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center; color:#64748b; padding:12px;">No records available.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6">GRAND TOTAL</td>
                    <td class="right">{{ number_format($totalAmount, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer-left">
        This is a system-generated report from Pradytec AI. Generated on: {{ $generatedAt->format('M j, Y, g:i A') }}.
    </div>
    <div class="footer-right"></div>
</body>
</html>
