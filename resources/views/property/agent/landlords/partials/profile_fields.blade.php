@php
    $profile = $landlordProfile ?? null;
@endphp

<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4 space-y-3">
    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Registration & tax details</h4>
    <p class="text-xs text-slate-500 dark:text-slate-400">Optional fields used during legacy imports — capture them here when onboarding manually.</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Legacy landlord code</label>
            <input type="text" name="legacy_landlord_code" value="{{ old('legacy_landlord_code', $profile?->legacy_landlord_code) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Import reference code" />
            @error('legacy_landlord_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">ID / registration number</label>
            <input type="text" name="id_number" value="{{ old('id_number', $profile?->id_number) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('id_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">KRA PIN</label>
            <input type="text" name="kra_pin" value="{{ old('kra_pin', $profile?->kra_pin) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('kra_pin')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Postal / physical address</label>
            <input type="text" name="address_line" value="{{ old('address_line', $profile?->address_line) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('address_line')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
