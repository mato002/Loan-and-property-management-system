<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Market rent (KES)</label>
        <input type="number" name="market_rent" value="{{ old('market_rent', $unit->market_rent ?? '') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Listing / target rent" />
        @error('market_rent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Legacy area (sq ft)</label>
        <input type="number" name="legacy_area" value="{{ old('legacy_area', $unit->legacy_area ?? '') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('legacy_area')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Floor</label>
        <input type="text" name="floor" value="{{ old('floor', $unit->floor ?? '') }}" maxlength="32" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Ground, 1, 2" />
        @error('floor')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Available from</label>
        <input type="date" name="available_from" value="{{ old('available_from', optional($unit->available_from ?? null)?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('available_from')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <input type="hidden" name="furnished" value="0" />
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" name="furnished" value="1" @checked(old('furnished', $unit->furnished ?? false)) class="rounded border-slate-300" />
            Furnished unit
        </label>
        @error('furnished')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>
