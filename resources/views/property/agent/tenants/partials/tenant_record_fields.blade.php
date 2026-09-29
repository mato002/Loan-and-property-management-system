@php
    $t = $tenant ?? null;
    $tenantCfg = $tenantCfg ?? ($tenantFields ?? []);
    $tenantRequired = $tenantRequired ?? fn (string $k, bool $d = false) => (bool) (($tenantCfg[$k]['required'] ?? $d) && ($tenantCfg[$k]['enabled'] ?? true));
    $inputClass = 'mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2';
    $labelClass = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
    $contacts = old('emergency_contacts', $t?->emergency_contacts ?? []);
    if (! is_array($contacts)) {
        $contacts = [];
    }
    $contacts = array_values(array_map(static function ($row): array {
        $row = is_array($row) ? $row : [];

        return [
            'name' => (string) ($row['name'] ?? ''),
            'relationship' => (string) ($row['relationship'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
        ];
    }, $contacts));
    $legacyEmergency = trim((string) old('emergency_contact', $t?->emergency_contact ?? ''));
    if ($contacts === [] && $legacyEmergency !== '') {
        $contacts[] = ['name' => $legacyEmergency, 'relationship' => '', 'phone' => '', 'email' => ''];
    }
    while (count($contacts) < 2) {
        $contacts[] = ['name' => '', 'relationship' => '', 'phone' => '', 'email' => ''];
    }
    $contacts = array_slice($contacts, 0, 2);
    $photoUrl = $t?->photoUrl();
@endphp

<div class="grid gap-3 sm:grid-cols-2 sm:col-span-2">
    <div>
        <label class="{{ $labelClass }}">Tenant type</label>
        <select name="tenant_type" class="{{ $inputClass }}">
            <option value="">—</option>
            @foreach (\App\Models\PmTenant::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('tenant_type', $t?->tenant_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tenant_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Other name(s)</label>
        <input type="text" name="other_names" value="{{ old('other_names', $t?->other_names) }}" maxlength="255" class="{{ $inputClass }}" placeholder="Given names" />
        @error('other_names')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Gender</label>
        <select name="gender" class="{{ $inputClass }}">
            <option value="">—</option>
            @foreach (\App\Models\PmTenant::GENDERS as $value => $label)
                <option value="{{ $value }}" @selected(old('gender', $t?->gender) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('gender')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">KRA PIN</label>
        <input type="text" name="kra_pin" value="{{ old('kra_pin', $t?->kra_pin) }}" maxlength="32" class="{{ $inputClass }}" />
        @error('kra_pin')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<details class="sm:col-span-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/70 dark:bg-slate-900/40 p-3" {{ $t ? 'open' : '' }}>
    <summary class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">Address, photo, and emergency contacts</summary>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="{{ $labelClass }}">Postal address</label>
            <input type="text" name="postal_address" value="{{ old('postal_address', $t?->postal_address) }}" maxlength="255" class="{{ $inputClass }}" />
            @error('postal_address')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $labelClass }}">Postal code</label>
            <input type="text" name="postal_code" value="{{ old('postal_code', $t?->postal_code) }}" maxlength="32" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Town</label>
            <input type="text" name="town" value="{{ old('town', $t?->town) }}" maxlength="128" class="{{ $inputClass }}" placeholder="Nairobi" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Country</label>
            <input type="text" name="country" value="{{ old('country', $t?->country ?? 'Kenya') }}" maxlength="64" class="{{ $inputClass }}" />
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $labelClass }}">Photo</label>
            @if ($photoUrl)
                <div class="mt-1 mb-2">
                    <img src="{{ $photoUrl }}" alt="" class="h-16 w-16 rounded-full object-cover border border-slate-200" />
                </div>
            @endif
            <input type="file" name="photo" accept="image/*" class="mt-1 block w-full text-sm text-slate-600" />
            @error('photo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        @foreach ($contacts as $idx => $contact)
            <div class="sm:col-span-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900/60 p-3">
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Emergency contact {{ $idx + 1 }}</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <div>
                        <label class="{{ $labelClass }}">Name</label>
                        <input type="text" name="emergency_contacts[{{ $idx }}][name]" value="{{ $contact['name'] }}" maxlength="120" class="{{ $inputClass }}" @required($idx === 0 && $tenantRequired('emergency_contact', false)) />
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Relationship</label>
                        <input type="text" name="emergency_contacts[{{ $idx }}][relationship]" value="{{ $contact['relationship'] }}" maxlength="80" class="{{ $inputClass }}" />
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Phone</label>
                        <input type="text" name="emergency_contacts[{{ $idx }}][phone]" value="{{ $contact['phone'] }}" maxlength="64" class="{{ $inputClass }}" />
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Email</label>
                        <input type="email" name="emergency_contacts[{{ $idx }}][email]" value="{{ $contact['email'] }}" maxlength="255" class="{{ $inputClass }}" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</details>
