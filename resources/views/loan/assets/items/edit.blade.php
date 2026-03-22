<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.assets.items.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-3xl">
            <form method="post" action="{{ route('loan.assets.items.update', $item) }}" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="loan_asset_category_id" class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                        <select id="loan_asset_category_id" name="loan_asset_category_id" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('loan_asset_category_id', $item->loan_asset_category_id) == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('loan_asset_category_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="loan_asset_measurement_unit_id" class="block text-xs font-semibold text-slate-600 mb-1">Measurement unit</label>
                        <select id="loan_asset_measurement_unit_id" name="loan_asset_measurement_unit_id" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}" @selected(old('loan_asset_measurement_unit_id', $item->loan_asset_measurement_unit_id) == $u->id)>{{ $u->name }} ({{ $u->abbreviation }})</option>
                            @endforeach
                        </select>
                        @error('loan_asset_measurement_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="asset_code" class="block text-xs font-semibold text-slate-600 mb-1">Asset code</label>
                        <input id="asset_code" name="asset_code" value="{{ old('asset_code', $item->asset_code) }}" required class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                        @error('asset_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name / description</label>
                        <input id="name" name="name" value="{{ old('name', $item->name) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="quantity" class="block text-xs font-semibold text-slate-600 mb-1">Quantity</label>
                        <input id="quantity" name="quantity" type="number" step="0.0001" min="0" value="{{ old('quantity', $item->quantity) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('quantity')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="unit_cost" class="block text-xs font-semibold text-slate-600 mb-1">Unit cost (optional)</label>
                        <input id="unit_cost" name="unit_cost" type="number" step="0.01" min="0" value="{{ old('unit_cost', $item->unit_cost) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('unit_cost')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                        <select id="status" name="status" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="location" class="block text-xs font-semibold text-slate-600 mb-1">Location (optional)</label>
                        <input id="location" name="location" value="{{ old('location', $item->location) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('location')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="serial_number" class="block text-xs font-semibold text-slate-600 mb-1">Serial number (optional)</label>
                        <input id="serial_number" name="serial_number" value="{{ old('serial_number', $item->serial_number) }}" class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                        @error('serial_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="acquisition_date" class="block text-xs font-semibold text-slate-600 mb-1">Acquisition date (optional)</label>
                    <input id="acquisition_date" name="acquisition_date" type="date" value="{{ old('acquisition_date', optional($item->acquisition_date)->format('Y-m-d')) }}" class="w-full rounded-lg border-slate-200 text-sm sm:max-w-xs" />
                    @error('acquisition_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $item->notes) }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Update</button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
