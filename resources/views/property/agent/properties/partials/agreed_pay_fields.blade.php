@props([
    'agreedPayDay' => null,
    'agreedPayNotes' => null,
    'settlementBasis' => 'collected',
    'commissionBasis' => 'all_collected',
    'providerCostPayer' => 'agency',
    'advancesAllowed' => true,
    'compact' => false,
])

<div @class(['space-y-3', 'sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0' => ! $compact])>
    @if ($compact)
        <input type="hidden" name="agreed_pay_notes" value="{{ old('agreed_pay_notes', $agreedPayNotes) }}" />
    @endif
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord payment basis</label>
        <select name="settlement_basis" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            <option value="collected" @selected(old('settlement_basis', $settlementBasis) === 'collected')>Rent and charges collected</option>
            <option value="guaranteed_rent" @selected(old('settlement_basis', $settlementBasis) === 'guaranteed_rent')>Agreed rent, including vacant units</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Commission applies to</label>
        <select name="commission_basis" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            <option value="all_collected" @selected(old('commission_basis', $commissionBasis) === 'all_collected')>All collected rent and charges</option>
            <option value="rent_only" @selected(old('commission_basis', $commissionBasis) === 'rent_only')>Rent only</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Service provider costs</label>
        <select name="provider_cost_payer" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            <option value="agency" @selected(old('provider_cost_payer', $providerCostPayer) === 'agency')>Agency pays and deducts costs</option>
            <option value="landlord" @selected(old('provider_cost_payer', $providerCostPayer) === 'landlord')>Landlord pays providers</option>
        </select>
    </div>
    <div>
        <input type="hidden" name="advances_allowed" value="0" />
        <label class="mt-5 flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="advances_allowed" value="1" @checked((bool) old('advances_allowed', $advancesAllowed)) class="rounded border-slate-300" />
            Allow advance payments
        </label>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agreed pay day</label>
        <select name="agreed_pay_day" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            <option value="">Not set</option>
            @for ($day = 1; $day <= 28; $day++)
                <option value="{{ $day }}" @selected((int) old('agreed_pay_day', $agreedPayDay ?? 0) === $day)>
                    {{ $day }}{{ in_array($day % 10, [1, 2, 3], true) && ! in_array($day, [11, 12, 13], true) ? match ($day % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd' } : 'th' }} of month
                </option>
            @endfor
        </select>
        @error('agreed_pay_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    @unless ($compact)
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Pay schedule notes</label>
            <input
                type="text"
                name="agreed_pay_notes"
                value="{{ old('agreed_pay_notes', $agreedPayNotes) }}"
                placeholder="e.g. Pay after rent collected"
                class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
            />
            @error('agreed_pay_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endunless
</div>
