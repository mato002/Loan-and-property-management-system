<x-property-layout>
    <x-slot name="header">Property Finance Rules - Utility Charge Rules</x-slot>

    <x-property.page title="Property Finance Rules" subtitle="Define utility charge rules for water, electricity, garbage and similar items.">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <form method="POST" action="{{ route('property.settings.expenses.store') }}" class="space-y-3">
                @csrf
                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-600">Settings -> Property Finance Rules -> Utility Charge Rules</p>
                    <button type="button" id="add-definition-row" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">+ Add rule</button>
                </div>

                @php
                    $utilityTypes = ['water', 'electricity', 'garbage', 'sewer', 'internet', 'service', 'other'];
                @endphp
                <div id="expense-definition-rows" class="space-y-2">
                    @forelse(old('definitions', $definitions->toArray()) as $idx => $row)
                        @php
                            $currentKey = (string) ($row['charge_key'] ?? '');
                            $isPreset = in_array($currentKey, $utilityTypes, true);
                        @endphp
                        <div class="grid gap-2 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 expense-rule-row rounded-lg border border-slate-200 p-2">
                            <select name="definitions[{{ $idx }}][property_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm">
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}" {{ ((int) ($row['property_id'] ?? 0) === (int) $property->id) ? 'selected' : '' }}>{{ $property->name }}</option>
                                @endforeach
                            </select>
                            <select name="definitions[{{ $idx }}][property_unit_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm">
                                <option value="">Property default</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}" {{ ((int) ($row['property_unit_id'] ?? 0) === (int) $unit->id) ? 'selected' : '' }}>{{ $unit->property?->name }} / {{ $unit->label }}</option>
                                @endforeach
                            </select>
                            <div class="space-y-1">
                                <select data-charge-key-select class="w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                                    @foreach($utilityTypes as $type)
                                        <option value="{{ $type }}" {{ ($isPreset && $currentKey === $type) ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                                    @endforeach
                                    <option value="__custom__" {{ (!$isPreset && $currentKey !== '') ? 'selected' : '' }}>Custom type...</option>
                                </select>
                                <input
                                    data-charge-key-custom
                                    type="text"
                                    value="{{ $isPreset ? '' : $currentKey }}"
                                    placeholder="Custom utility type"
                                    class="w-full rounded-lg border border-slate-200 px-2 py-2 text-sm {{ $isPreset ? 'hidden' : '' }}"
                                />
                                <input data-charge-key-hidden type="hidden" name="definitions[{{ $idx }}][charge_key]" value="{{ $currentKey }}" />
                            </div>
                            <input type="text" name="definitions[{{ $idx }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Water charge" class="rounded-lg border border-slate-200 px-2 py-2 text-sm" />
                            <label class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs"><input type="checkbox" name="definitions[{{ $idx }}][is_required]" value="1" @checked((bool) ($row['is_required'] ?? false))>Req</label>
                            <select name="definitions[{{ $idx }}][amount_mode]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm">
                                <option value="rate_per_unit" {{ (($row['amount_mode'] ?? 'fixed') === 'rate_per_unit') ? 'selected' : '' }}>Rate per unit (meter/usage)</option>
                                <option value="fixed" {{ (($row['amount_mode'] ?? 'fixed') === 'fixed') ? 'selected' : '' }}>Flat charge (monthly fixed)</option>
                            </select>
                            <input type="number" name="definitions[{{ $idx }}][amount_value]" value="{{ $row['amount_value'] ?? 0 }}" step="0.01" min="0" class="rounded-lg border border-slate-200 px-2 py-2 text-sm" placeholder="Rate or flat amount" />
                            <input type="text" name="definitions[{{ $idx }}][ledger_account]" value="{{ $row['ledger_account'] ?? '' }}" placeholder="Ledger mapping (optional)" class="rounded-lg border border-slate-200 px-2 py-2 text-sm" />
                            <button type="button" class="remove-definition-row rounded-lg border border-red-200 px-2 py-2 text-xs text-red-700 hover:bg-red-50">Remove</button>
                            <input type="hidden" name="definitions[{{ $idx }}][is_active]" value="{{ (string) (($row['is_active'] ?? true) ? '1' : '0') }}">
                            <input type="hidden" name="definitions[{{ $idx }}][sort_order]" value="{{ (int) ($row['sort_order'] ?? 0) }}">
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No utility charge rules yet. Add charge lines used in lease billing.</div>
                    @endforelse
                </div>

                @error('definitions')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="text-xs text-slate-500">Use <span class="font-medium">Rate per unit</span> for metered utilities (water/electricity). Use <span class="font-medium">Flat charge</span> for fixed utilities (e.g. garbage).</p>
                <div><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save utility charge rules</button></div>
            </form>
        </div>
    </x-property.page>

    <script>
        (function () {
            const wrap = document.getElementById('expense-definition-rows');
            const add = document.getElementById('add-definition-row');
            if (!wrap || !add) return;

            const properties = @json($properties->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values());
            const units = @json($units->map(fn($u) => ['id' => $u->id, 'label' => ($u->property?->name ?? '').' / '.$u->label])->values());

            const utilityTypes = ['water', 'electricity', 'garbage', 'sewer', 'internet', 'service', 'other'];
            const utilityOptionsHtml = utilityTypes
                .map((type) => `<option value="${type}">${type.charAt(0).toUpperCase() + type.slice(1)}</option>`)
                .join('');
            const rowHtml = (idx) => `
                <div class="grid gap-2 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 expense-rule-row rounded-lg border border-slate-200 p-2">
                    <select name="definitions[${idx}][property_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm">${properties.map((p) => `<option value="${p.id}">${p.name}</option>`).join('')}</select>
                    <select name="definitions[${idx}][property_unit_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm"><option value="">Property default</option>${units.map((u) => `<option value="${u.id}">${u.label}</option>`).join('')}</select>
                    <div class="space-y-1">
                        <select data-charge-key-select class="w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">${utilityOptionsHtml}<option value="__custom__">Custom type...</option></select>
                        <input data-charge-key-custom type="text" placeholder="Custom utility type" class="hidden w-full rounded-lg border border-slate-200 px-2 py-2 text-sm" />
                        <input data-charge-key-hidden type="hidden" name="definitions[${idx}][charge_key]" value="water" />
                    </div>
                    <input type="text" name="definitions[${idx}][label]" placeholder="Water charge" class="rounded-lg border border-slate-200 px-2 py-2 text-sm" />
                    <label class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs"><input type="checkbox" name="definitions[${idx}][is_required]" value="1">Req</label>
                    <select name="definitions[${idx}][amount_mode]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm"><option value="rate_per_unit">Rate per unit (meter/usage)</option><option value="fixed">Flat charge (monthly fixed)</option></select>
                    <input type="number" name="definitions[${idx}][amount_value]" value="0" step="0.01" min="0" class="rounded-lg border border-slate-200 px-2 py-2 text-sm" />
                    <input type="text" name="definitions[${idx}][ledger_account]" placeholder="Ledger mapping (optional)" class="rounded-lg border border-slate-200 px-2 py-2 text-sm" />
                    <button type="button" class="remove-definition-row rounded-lg border border-red-200 px-2 py-2 text-xs text-red-700 hover:bg-red-50">Remove</button>
                    <input type="hidden" name="definitions[${idx}][is_active]" value="1">
                    <input type="hidden" name="definitions[${idx}][sort_order]" value="${idx}">
                </div>`;

            const normalizeKey = (value) => String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            const wireChargeKeyRow = (row) => {
                const select = row.querySelector('[data-charge-key-select]');
                const custom = row.querySelector('[data-charge-key-custom]');
                const hidden = row.querySelector('[data-charge-key-hidden]');
                if (!(select instanceof HTMLSelectElement) || !(custom instanceof HTMLInputElement) || !(hidden instanceof HTMLInputElement)) return;

                const sync = () => {
                    const customMode = select.value === '__custom__';
                    custom.classList.toggle('hidden', !customMode);
                    hidden.value = customMode ? normalizeKey(custom.value) : select.value;
                };

                select.addEventListener('change', sync);
                custom.addEventListener('input', sync);
                sync();
            };

            wrap.querySelectorAll('.expense-rule-row').forEach((row) => wireChargeKeyRow(row));
            add.addEventListener('click', () => {
                const idx = wrap.querySelectorAll('.expense-rule-row').length;
                wrap.insertAdjacentHTML('beforeend', rowHtml(idx));
                const rows = wrap.querySelectorAll('.expense-rule-row');
                const row = rows[rows.length - 1];
                if (row) wireChargeKeyRow(row);
            });
            wrap.addEventListener('click', (e) => {
                const t = e.target;
                if (!(t instanceof HTMLElement) || !t.classList.contains('remove-definition-row')) return;
                t.closest('.expense-rule-row')?.remove();
            });
        })();
    </script>
</x-property-layout>
