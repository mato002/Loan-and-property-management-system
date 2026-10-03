@php
    $p = $property ?? null;
    $compact = $compact ?? false;
    $inputClass = 'mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2';
    $labelClass = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
    $exemptions = old('communication_exemptions', $p?->communication_exemptions ?? []);
    if (! is_array($exemptions)) {
        $exemptions = [];
    }
    $exempt = function (string $key) use ($exemptions): bool {
        return (bool) old('exempt_'.$key, $exemptions[$key] ?? false);
    };
    $categories = ['residential' => 'Residential', 'commercial' => 'Commercial', 'mixed' => 'Mixed use', 'industrial' => 'Industrial', 'land' => 'Land'];
    $specifications = ['multi_unit' => 'Multi-unit / multi-space', 'single_unit' => 'Single unit', 'mixed' => 'Mixed'];
    $storeyTypes = ['single_storey' => 'Single storey', 'multi_storey' => 'Multi storey', 'high_rise' => 'High rise'];
    $areaUnits = ['sqm' => 'Square metres', 'sqft' => 'Square feet', 'acre' => 'Acres'];
@endphp

<div class="space-y-4 {{ $compact ? '' : 'pt-2' }}">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Identity &amp; title</p>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}">Date acquired</label>
                <input type="date" name="acquired_at" value="{{ old('acquired_at', optional($p?->acquired_at)?->format('Y-m-d')) }}" class="{{ $inputClass }}" />
                @error('acquired_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Let / manage</label>
                <select name="management_mode" class="{{ $inputClass }}">
                    <option value="managing" @selected(old('management_mode', $p?->management_mode ?? 'managing') === 'managing')>Managing</option>
                    <option value="letting" @selected(old('management_mode', $p?->management_mode ?? '') === 'letting')>Letting only</option>
                </select>
                @error('management_mode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">LR / title number</label>
                <input type="text" name="lr_number" value="{{ old('lr_number', $p?->lr_number ?? '') }}" class="{{ $inputClass }}" placeholder="e.g. Nakuru/Municipality/123" />
                @error('lr_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Category</label>
                <select name="category" class="{{ $inputClass }}">
                    <option value="">Select…</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $p?->category ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Property type</label>
                <input type="text" name="property_type" value="{{ old('property_type', $p?->property_type ?? '') }}" class="{{ $inputClass }}" placeholder="Apartment, offices, shops…" />
                @error('property_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Layout</label>
                <select name="specification" class="{{ $inputClass }}">
                    <option value="">Select…</option>
                    @foreach ($specifications as $value => $label)
                        <option value="{{ $value }}" @selected(old('specification', $p?->specification ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('specification')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Storey type</label>
                <select name="storey_type" class="{{ $inputClass }}">
                    <option value="">Select…</option>
                    @foreach ($storeyTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('storey_type', $p?->storey_type ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('storey_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">No. of floors</label>
                <input type="number" name="floors_count" min="0" max="200" value="{{ old('floors_count', $p?->floors_count ?? '') }}" class="{{ $inputClass }}" />
                @error('floors_count')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</p>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}">Country</label>
                <input type="text" name="country" value="{{ old('country', $p?->country ?? 'Kenya') }}" class="{{ $inputClass }}" />
                @error('country')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Estate / area</label>
                <input type="text" name="estate" value="{{ old('estate', $p?->estate ?? '') }}" class="{{ $inputClass }}" />
                @error('estate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Zone / region</label>
                <input type="text" name="zone" value="{{ old('zone', $p?->zone ?? '') }}" class="{{ $inputClass }}" />
                @error('zone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Latitude</label>
                <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $p?->latitude ?? '') }}" class="{{ $inputClass }}" />
                @error('latitude')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Longitude</label>
                <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $p?->longitude ?? '') }}" class="{{ $inputClass }}" />
                @error('longitude')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Area &amp; costing (building)</p>
        <p class="mt-1 text-xs text-slate-500">Unit-level area and rent stay on Units. These are building totals if you need them.</p>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}">Gross lettable area</label>
                <input type="number" step="0.01" min="0" name="gross_lettable_area" value="{{ old('gross_lettable_area', $p?->gross_lettable_area ?? '') }}" class="{{ $inputClass }}" />
                @error('gross_lettable_area')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Net lettable area</label>
                <input type="number" step="0.01" min="0" name="net_lettable_area" value="{{ old('net_lettable_area', $p?->net_lettable_area ?? '') }}" class="{{ $inputClass }}" />
                @error('net_lettable_area')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Area unit</label>
                <select name="area_unit" class="{{ $inputClass }}">
                    <option value="">Select…</option>
                    @foreach ($areaUnits as $value => $label)
                        <option value="{{ $value }}" @selected(old('area_unit', $p?->area_unit ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('area_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Rent per measure (KES)</label>
                <input type="number" step="0.01" min="0" name="rent_per_measure" value="{{ old('rent_per_measure', $p?->rent_per_measure ?? '') }}" class="{{ $inputClass }}" />
                @error('rent_per_measure')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes &amp; contact</p>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}">Notes / description</label>
                <textarea name="notes" rows="3" class="{{ $inputClass }}">{{ old('notes', $p?->notes ?? '') }}</textarea>
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Specific contact info</label>
                <textarea name="contact_info" rows="3" class="{{ $inputClass }}">{{ old('contact_info', $p?->contact_info ?? '') }}</textarea>
                @error('contact_info')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statement &amp; listing</p>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}">Statement balance cut-off day</label>
                <input type="number" min="1" max="31" name="statement_balance_cutoff_day" value="{{ old('statement_balance_cutoff_day', $p?->statement_balance_cutoff_day ?? '') }}" class="{{ $inputClass }}" placeholder="1–31" />
                @error('statement_balance_cutoff_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 pb-2">
                    <input type="hidden" name="exclude_from_fee_summary" value="0" />
                    <input type="checkbox" name="exclude_from_fee_summary" value="1" @checked(old('exclude_from_fee_summary', $p?->exclude_from_fee_summary ?? false)) class="rounded border-slate-300" />
                    Exclude from fee summary reports
                </label>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $labelClass }}">Listing notes</label>
                <textarea name="listing_notes" rows="2" class="{{ $inputClass }}">{{ old('listing_notes', $p?->listing_notes ?? '') }}</textarea>
                @error('listing_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Listing agent name</label>
                <input type="text" name="listing_agent_name" value="{{ old('listing_agent_name', $p?->listing_agent_name ?? '') }}" class="{{ $inputClass }}" />
                @error('listing_agent_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Listing contact email</label>
                <input type="email" name="listing_contact_email" value="{{ old('listing_contact_email', $p?->listing_contact_email ?? '') }}" class="{{ $inputClass }}" />
                @error('listing_contact_email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Listing contact phone</label>
                <input type="text" name="listing_contact_phone" value="{{ old('listing_contact_phone', $p?->listing_contact_phone ?? '') }}" class="{{ $inputClass }}" />
                @error('listing_contact_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Listing min rent (KES)</label>
                <input type="number" step="0.01" min="0" name="listing_min_rent" value="{{ old('listing_min_rent', $p?->listing_min_rent ?? '') }}" class="{{ $inputClass }}" />
            </div>
            <div>
                <label class="{{ $labelClass }}">Listing max rent (KES)</label>
                <input type="number" step="0.01" min="0" name="listing_max_rent" value="{{ old('listing_max_rent', $p?->listing_max_rent ?? '') }}" class="{{ $inputClass }}" />
            </div>
            <div>
                <label class="{{ $labelClass }}">Listing min service charge</label>
                <input type="number" step="0.01" min="0" name="listing_min_service_charge" value="{{ old('listing_min_service_charge', $p?->listing_min_service_charge ?? '') }}" class="{{ $inputClass }}" />
            </div>
            <div>
                <label class="{{ $labelClass }}">Listing max service charge</label>
                <input type="number" step="0.01" min="0" name="listing_max_service_charge" value="{{ old('listing_max_service_charge', $p?->listing_max_service_charge ?? '') }}" class="{{ $inputClass }}" />
            </div>
        </div>
        <p class="mt-1 text-xs text-slate-500">Public unit ads (photos, description, featured) stay under Listings. Landlord payout bank details are on the landlord profile.</p>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Communication exemptions</p>
        <p class="mt-1 text-xs text-slate-500">Optional suppress flags for this building. Tenant/landlord contacts still apply.</p>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach ([
                'sms_all' => 'Exempt all SMS',
                'sms_invoice' => 'Exempt invoice SMS',
                'sms_general' => 'Exempt general SMS',
                'sms_receipt' => 'Exempt receipt SMS',
                'sms_balance' => 'Exempt balance SMS',
                'email_all' => 'Exempt all email',
                'email_invoice' => 'Exempt invoice email',
                'email_general' => 'Exempt general email',
                'email_receipt' => 'Exempt receipt email',
                'email_balance' => 'Exempt balance email',
            ] as $key => $label)
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="hidden" name="exempt_{{ $key }}" value="0" />
                    <input type="checkbox" name="exempt_{{ $key }}" value="1" @checked($exempt($key)) class="rounded border-slate-300" />
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>
</div>
