@php
    $lease = $lease ?? null;
    $variationOptions = ['New Lease', 'Renewal', 'Revision'];
    $currentVariation = old('lease_variation_type', $lease?->lease_variation_type);
@endphp

<div class="sm:col-span-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/30 p-3 space-y-3">
    <div>
        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lease register details</h4>
        <p class="mt-0.5 text-xs text-slate-500">Same columns as the active tenants register (variation, term length, review date).</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Lease variation</label>
            <select name="lease_variation_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Not set</option>
                @foreach ($variationOptions as $option)
                    <option value="{{ $option }}" @selected($currentVariation === $option)>{{ $option }}</option>
                @endforeach
                @if ($currentVariation && ! in_array($currentVariation, $variationOptions, true))
                    <option value="{{ $currentVariation }}" selected>{{ $currentVariation }}</option>
                @endif
            </select>
            @error('lease_variation_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Escalation / review start</label>
            <input type="date" name="escalation_review_start" value="{{ old('escalation_review_start', optional($lease?->escalation_review_start)?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('escalation_review_start')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Lease period (days)</label>
            <input type="number" name="lease_period_days" value="{{ old('lease_period_days', $lease?->lease_period_days) }}" min="0" max="65535" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. 365" />
            @error('lease_period_days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Days to expire</label>
            <input type="number" name="days_to_expire" value="{{ old('days_to_expire', $lease?->days_to_expire) }}" min="0" max="65535" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Auto from end date if blank" />
            <p class="mt-1 text-xs text-slate-500">Leave blank to calculate from the lease end date.</p>
            @error('days_to_expire')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
