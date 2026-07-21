@php
    $showAdvanceFormByDefault = $showAdvanceFormByDefault ?? (
        request('form') === 'advance'
        || old('payment_form') === 'advance'
        || $errors->has('advance')
        || (old('payment_form') === 'advance' && $errors->hasAny(['pm_tenant_id', 'channel', 'amount', 'paid_at', 'external_ref', 'notes']))
    );
    $tenantsForAdvance = $tenantsForAdvance ?? collect();
    $advanceCreditsEnabled = $advanceCreditsEnabled ?? false;
    $returnTo = $returnTo ?? null;
    $alwaysOpen = $alwaysOpen ?? false;
    $hideSummary = $hideSummary ?? false;
@endphp

@if ($alwaysOpen)
    <div id="advance-payment-panel" class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-3 max-w-3xl">
        @if (! $advanceCreditsEnabled)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Tenant advance credits are not enabled. Run migrations for <code class="text-xs">pm_tenant_credit_*</code> tables.
            </div>
        @else
            @include('property.agent.revenue.partials.advance_payment_form_fields', [
                'tenantsForAdvance' => $tenantsForAdvance,
                'returnTo' => $returnTo,
            ])
        @endif
    </div>
@else
    <details id="advance-payment-panel" class="group mt-3 rounded-2xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-800 dark:bg-gray-800/80" @if($showAdvanceFormByDefault) open @endif>
        @if ($hideSummary)
            <summary class="sr-only">Advance payment form</summary>
            <div class="flex justify-end border-b border-emerald-100 px-4 py-2 dark:border-emerald-900">
                <button type="button" class="text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200" data-collapse-payment-panel="advance-payment-panel">Hide form</button>
            </div>
        @else
            <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                <i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>
                <span class="group-open:hidden">Record advance payment</span>
                <span class="hidden group-open:inline">Hide advance payment form</span>
            </summary>
        @endif

        @error('advance')<p class="mt-2 px-5 text-xs text-red-600">{{ $message }}</p>@enderror

        @if (! $advanceCreditsEnabled)
            <div class="mx-5 mb-5 mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 max-w-3xl">
                Tenant advance credits are not enabled on this database. Run migrations for <code class="text-xs">pm_tenant_credit_*</code> tables, then retry.
            </div>
        @else
            <div class="max-w-3xl px-5 pb-5 @if(! $hideSummary) mt-3 @endif">
                @include('property.agent.revenue.partials.advance_payment_form_fields', [
                    'tenantsForAdvance' => $tenantsForAdvance,
                    'returnTo' => $returnTo,
                ])
            </div>
        @endif
    </details>
@endif
