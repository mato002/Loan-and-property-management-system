@php
    $doc = $doc ?? \App\Support\Property\PropertyWorkspaceBranding::documentSnapshot($brandingAgentUserId ?? null);
    $brandName = $doc['company_name'];
    $logoSrc = (string) (($doc['logo_embed'] ?? '')
        ?: \App\Support\Property\PropertyWorkspaceBranding::embeddableLogoSrc($doc)
        ?: ($doc['logo_url'] ?? ''));
    $method = \App\Support\Property\PmPaymentPresentation::paymentMethod($payment, strtoupper((string) $payment->channel));
    $ref = \App\Support\Property\PmPaymentPresentation::transactionRef($payment, (string) ($payment->external_ref ?: '—'));
    $printUrl = route('property.payments.receipt.download', ['payment' => $payment, 'print' => 1]);
@endphp

<x-property.workspace
    workspace="collections"
    title="{{ $brandName }} receipt"
    subtitle="Proof of payment · RCP-PAY-{{ $payment->id }}"
    back-route="property.revenue.payments"
>
    <x-slot name="actions">
        <a
            href="{{ route('property.payments.receipt.download', $payment) }}"
            data-turbo="false"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-[var(--brand-on-primary,#fff)] hover:opacity-95"
            style="background: var(--brand-cta, #059669);"
        >Open printable</a>
        <a
            href="{{ $printUrl }}"
            data-turbo="false"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >Print / Save PDF</a>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4 print:max-w-none">
        <div
            class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            style="border-color: color-mix(in srgb, var(--brand-primary, #059669) 18%, #e2e8f0);"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-1.5"
                style="background: var(--brand-primary, #059669);"
            ></div>

            @include('property.partials.document_letterhead', [
                'branding' => $doc,
                'title' => 'Payment receipt',
                'subtitle' => 'RCP-PAY-'.$payment->id,
                'showAccentBar' => false,
            ])

            <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-t border-slate-100 pt-4">
                <div class="text-sm text-slate-600">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Tenant</p>
                    <p class="font-semibold text-slate-900">{{ $payment->tenant?->name ?? '—' }}</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Receipt no</p>
                    <p class="font-mono text-sm font-semibold" style="color: var(--brand-primary, #047857);">RCP-PAY-{{ $payment->id }}</p>
                    <p class="mt-2 text-xs uppercase tracking-wide text-slate-500">Date</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $payment->paid_at?->format('d M Y') ?? now()->format('d M Y') }}</p>
                    <p class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                       style="background: var(--brand-primary-soft, #ecfdf5); color: var(--brand-primary-hover, #047857);">
                        {{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tenant</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $payment->tenant?->name ?? '—' }}</p>
                @if ($payment->tenant?->account_number)
                    <p class="font-mono text-xs text-slate-500">{{ $payment->tenant->account_number }}</p>
                @endif
                <p class="text-sm text-slate-500">{{ $payment->tenant?->email ?: ($payment->tenant?->phone ?: '—') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment details</p>
                <p class="mt-1 text-sm text-slate-700">Method: <span class="font-semibold">{{ $method }}</span></p>
                <p class="text-sm text-slate-700">Reference: <span class="font-mono font-semibold">{{ $ref }}</span></p>
                <p class="text-sm text-slate-700">Paid at: <span class="font-semibold">{{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}</span></p>
                <p class="text-sm text-slate-500">Origin: {{ \App\Support\Property\PmPaymentPresentation::originLabel($payment) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-[var(--brand-on-primary,#fff)]"
                       style="background: var(--brand-primary, #059669);">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3 text-right">Allocated amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payment->allocations as $allocation)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ $allocation->invoice?->invoice_no ?? ('INV-'.$allocation->pm_invoice_id) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) $allocation->amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-slate-500">No allocations recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment method</p>
                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $method }}</p>
                <p class="mt-1 font-mono text-sm text-slate-600">{{ $ref }}</p>
            </div>
            <div class="rounded-2xl border p-4 shadow-sm"
                 style="border-color: color-mix(in srgb, var(--brand-primary, #059669) 25%, #e2e8f0); background: var(--brand-primary-soft, #ecfdf5);">
                <div class="flex items-center justify-between text-sm text-slate-600">
                    <span>Allocated</span>
                    <span class="tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($allocatedTotal ?? $payment->allocations->sum('amount'))) }}</span>
                </div>
                @if (($creditCreated ?? 0) > 0)
                    <div class="mt-2 flex items-center justify-between text-sm text-slate-600">
                        <span>Tenant credit</span>
                        <span class="tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $creditCreated) }}</span>
                    </div>
                @endif
                <div class="mt-3 flex items-center justify-between border-t pt-3"
                     style="border-color: color-mix(in srgb, var(--brand-primary, #059669) 20%, #cbd5e1);">
                    <span class="text-xs font-semibold uppercase tracking-wide" style="color: var(--brand-primary-hover, #047857);">Grand total</span>
                    <span class="text-2xl font-black tabular-nums text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</span>
                </div>
            </div>
        </div>

        <p class="text-xs text-slate-500">This document confirms receipt of payment. Keep it for your records and account reconciliation. Use <span class="font-semibold">Print / Save PDF</span> for the branded letterhead copy.</p>
    </div>
</x-property.workspace>
