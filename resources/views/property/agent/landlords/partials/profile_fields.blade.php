@php
    $profile = $landlordProfile ?? null;
@endphp

<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4 space-y-3">
    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Registration & tax details</h4>
    <p class="text-xs text-slate-500 dark:text-slate-400">Optional fields used during legacy imports — capture them here when onboarding manually.</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Legacy landlord code</label>
            <input type="text" name="legacy_landlord_code" value="{{ old('legacy_landlord_code', $profile?->legacy_landlord_code) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Import reference / EZEN code" />
            @error('legacy_landlord_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord type</label>
            <select name="landlord_type" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select…</option>
                @foreach (\App\Models\PmLandlordPortalProfile::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(old('landlord_type', $profile?->landlord_type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('landlord_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Postal / physical address</label>
            <input type="text" name="address_line" value="{{ old('address_line', $profile?->address_line) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('address_line')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Location</label>
            <input type="text" name="location" value="{{ old('location', $profile?->location) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Town / area" />
            @error('location')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4 space-y-3">
    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Payout banking</h4>
    <p class="text-xs text-slate-500 dark:text-slate-400">Used when remitting landlord funds. The landlord can also maintain these on their portal.</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bank name</label>
            <input type="text" name="bank_name" value="{{ old('bank_name', $profile?->bank_name) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('bank_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bank branch</label>
            <input type="text" name="bank_branch" value="{{ old('bank_branch', $profile?->bank_branch) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('bank_branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Account name</label>
            <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $profile?->bank_account_name) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('bank_account_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Account number</label>
            <input type="text" name="bank_account" value="{{ old('bank_account', $profile?->bank_account) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('bank_account')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">M-Pesa phone</label>
            <input type="text" name="mpesa_phone" value="{{ old('mpesa_phone', $profile?->mpesa_phone) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="07…" />
            @error('mpesa_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
