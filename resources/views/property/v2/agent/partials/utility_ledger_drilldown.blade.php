@php
    $links = [];
    $d = $drilldown ?? [];

    if (! empty($d['pm_invoice_id'])) {
        $links[] = ['label' => 'Invoice', 'route' => route('property.revenue.invoices.show', $d['pm_invoice_id'], false)];
    }
    if (! empty($d['pm_payment_id'])) {
        $links[] = ['label' => 'Payment', 'route' => route('property.payments.receipt.show', $d['pm_payment_id'], false)];
    }
    if (! empty($d['pm_tenant_credit_transaction_id']) && ! empty($tenantId)) {
        $links[] = ['label' => 'Credit txn', 'route' => route('property.tenants.credit.ledger', ['tenant' => $tenantId], false)];
    }
    if (! empty($d['journal_batch_id'])) {
        $links[] = ['label' => 'Journal #'.$d['journal_batch_id'], 'route' => route('property.accounting.gl.journal_batches.export', $d['journal_batch_id'], false)];
    }
@endphp

@if (count($links) === 0)
    <span class="text-xs text-slate-400">—</span>
@else
    <div class="flex flex-wrap gap-1">
        @foreach ($links as $link)
            <a href="{{ $link['route'] }}" class="inline-flex rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-indigo-600 hover:bg-indigo-50" title="{{ $link['label'] }}">{{ $link['label'] }}</a>
        @endforeach
    </div>
@endif
