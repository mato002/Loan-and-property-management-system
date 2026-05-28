@php($brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name', 'Property Management System'))
@php($logoRaw = trim((string) \App\Models\PropertyPortalSetting::getValue('company_logo_url', '')))
@php($logoUrl = $logoRaw !== '' ? ((str_starts_with($logoRaw, 'http://') || str_starts_with($logoRaw, 'https://') || str_starts_with($logoRaw, '/')) ? $logoRaw : \Illuminate\Support\Facades\Storage::url($logoRaw)) : null)
<x-property-layout>
    <x-slot name="header">Receipt #RCP-PAY-{{ $payment->id }}</x-slot>

    <x-property.page
        title="{{ $brandName }} Receipt"
        subtitle="Proof of payment for receipt #RCP-PAY-{{ $payment->id }}."
        class="max-w-5xl mx-auto"
    >
        <x-slot name="actions">
            <a
                href="{{ route('property.payments.receipt.download', $payment) }}"
                data-turbo="false"
                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >Download</a>
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
            >Print / Save PDF</button>
        </x-slot>

        <div class="relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700 bg-gradient-to-r from-blue-50 via-indigo-50 to-white dark:from-slate-900/60 dark:via-slate-900/50 dark:to-slate-800/50 p-6">
            <div class="pointer-events-none absolute -top-12 -left-10 h-36 w-52 rounded-full bg-blue-200/40 blur-2xl"></div>
            <div class="pointer-events-none absolute -top-12 -right-10 h-28 w-40 rounded-full bg-indigo-200/40 blur-2xl"></div>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo" class="h-10 w-10 rounded-xl border border-blue-100 bg-white object-contain p-1 shadow">
                        @else
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-xs font-extrabold tracking-widest text-white shadow">PM</span>
                        @endif
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment receipt</p>
                    </div>
                    <h2 class="mt-1 text-3xl font-black tracking-wide text-indigo-900 dark:text-indigo-200">INVOICE</h2>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Billing To</p>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $payment->tenant?->name ?? '—' }}</p>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Receipt No</p>
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">RCP-PAY-{{ $payment->id }}</p>
                    <p class="mt-2 text-xs text-slate-500">Date: {{ $payment->paid_at?->format('d M Y') ?? now()->format('d M Y') }}</p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 px-3 py-1 font-semibold">Brand: {{ $brandName }}</span>
                <span class="inline-flex items-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 px-3 py-1 font-semibold">Total: KES {{ number_format((float) $payment->amount, 2) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Tenant</p>
                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $payment->tenant?->name ?? '—' }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $payment->tenant?->email ?? '—' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Payment details</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">Channel: <span class="font-semibold uppercase">{{ $payment->channel }}</span></p>
                <p class="text-sm text-slate-700 dark:text-slate-200">Reference: <span class="font-semibold">{{ $payment->external_ref ?: '—' }}</span></p>
                <p class="text-sm text-slate-700 dark:text-slate-200">Paid at: <span class="font-semibold">{{ $payment->paid_at?->format('Y-m-d H:i:s') ?? '—' }}</span></p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 overflow-hidden">
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-blue-600 text-left text-xs font-semibold uppercase tracking-wide text-white border-b border-blue-700">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Allocated amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payment->allocations as $allocation)
                        <tr class="border-t border-slate-100 dark:border-slate-700/80">
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $allocation->invoice?->invoice_no ?? ('INV-'.$allocation->pm_invoice_id) }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">KES {{ number_format((float) $allocation->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-slate-500 dark:text-slate-400">No allocations recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-900/30 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Payment method</p>
                <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ strtoupper((string) $payment->channel) }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Account ref: {{ $payment->external_ref ?: '—' }}</p>
            </div>
            <div class="rounded-2xl border border-blue-100 dark:border-blue-900/40 bg-blue-50/60 dark:bg-blue-950/20 p-4">
                <div class="flex items-center justify-between text-sm text-slate-600 dark:text-slate-300">
                    <span>Subtotal</span>
                    <span>KES {{ number_format((float) $payment->amount, 2) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between text-sm text-slate-600 dark:text-slate-300">
                    <span>Tax</span>
                    <span>0.00</span>
                </div>
                <div class="mt-3 border-t border-blue-200 dark:border-blue-900/50 pt-3 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wide text-blue-700 dark:text-blue-300 font-semibold">Grand total</span>
                    <span class="text-2xl font-black text-slate-900 dark:text-white">KES {{ number_format((float) $payment->amount, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
            <p class="text-xs text-slate-500 dark:text-slate-400">This document confirms receipt of payment. Keep it for your records and account reconciliation.</p>
            <div class="hidden md:flex items-end gap-1" aria-hidden="true">
                <span class="block h-4 w-3 rounded-t bg-blue-200 dark:bg-blue-900/50"></span>
                <span class="block h-7 w-3 rounded-t bg-blue-300 dark:bg-blue-800/60"></span>
                <span class="block h-5 w-3 rounded-t bg-indigo-200 dark:bg-indigo-900/50"></span>
                <span class="block h-8 w-3 rounded-t bg-indigo-300 dark:bg-indigo-800/60"></span>
            </div>
        </div>
    </x-property.page>
</x-property-layout>

