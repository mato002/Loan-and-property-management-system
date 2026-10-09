<x-property-layout>
    <x-slot name="header">Property Finance Rules - Deposits</x-slot>

    <x-property.page title="Property Finance Rules" subtitle="Define allowed deposit types per property with optional unit-level overrides.">
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.deposits'])

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-gray-900">
            <form id="deposit-rules-form" method="POST" action="{{ route('property.settings.deposits.store') }}" data-turbo="false" class="space-y-3">
                @csrf
                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Settings &rarr; Property Finance Rules &rarr; Deposit Types</p>
                    <button type="button" id="add-definition-row" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">+ Add rule</button>
                </div>

                <div id="deposit-definition-rows" class="space-y-2">
                    @forelse($definitions->toArray() as $idx =>$row)
                        <div class="deposit-rule-row grid grid-cols-1 gap-2 rounded-lg border border-slate-200 p-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 dark:border-slate-800">
                            <select name="definitions[{{ $idx }}][property_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white">
                                @foreach($properties as$property)
                                    <option value="{{ $property->id }}" @selected((int) ($row['property_id'] ?? 0) === (int) $property->id)>{{$property->name }}</option>
                                @endforeach
                            </select>
                            <select name="definitions[{{ $idx }}][property_unit_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white">
                                <option value="">Property default</option>
                                @foreach($units as$unit)
                                    <option value="{{ $unit->id }}" @selected((int) ($row['property_unit_id'] ?? 0) === (int)$unit->id)>{{ $unit->property?->name }} / {{$unit->label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="definitions[{{ $idx }}][deposit_key]" value="{{ $row['deposit_key'] ?? '' }}" placeholder="water_deposit" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                            <input type="text" name="definitions[{{ $idx }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Water deposit" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                            <label class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs dark:border-slate-700 dark:text-slate-300">
                                <input type="checkbox" name="definitions[{{ $idx }}][is_required]" value="1" @checked((bool) ($row['is_required'] ?? false))>Req
                            </label>
                            <select name="definitions[{{ $idx }}][amount_mode]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white">
                                <option value="fixed" @selected(($row['amount_mode'] ?? 'fixed') === 'fixed')>Fixed</option>
                                <option value="percent_rent" @selected(($row['amount_mode'] ?? '') === 'percent_rent')>% Rent</option>
                            </select>
                            <input type="number" name="definitions[{{ $idx }}][amount_value]" value="{{ $row['amount_value'] ?? 0 }}" step="0.01" min="0" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                            <input type="text" name="definitions[{{ $idx }}][ledger_account]" value="{{ $row['ledger_account'] ?? '' }}" placeholder="Ledger map" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                            <label class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs dark:border-slate-700 dark:text-slate-300">
                                <input type="checkbox" name="definitions[{{ $idx }}][is_refundable]" value="1" @checked(!array_key_exists('is_refundable', $row) \vert{}\vert{} (bool)$row['is_refundable'])>Refund
                            </label>
                            <button type="button" class="remove-definition-row rounded-lg border border-red-200 px-2 py-2 text-xs text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/30">Remove</button>
                            <input type="hidden" name="definitions[{{ $idx }}][is_active]" value="{{ (string) (($row['is_active'] ?? true) ? '1' : '0') }}">
                            <input type="hidden" name="definitions[{{ $idx }}][sort_order]" value="{{ (int) ($row['sort_order'] ?? 0) }}">
                        </div>
                    @empty
                        <div id="no-rules-hint" class="text-sm text-slate-500 dark:text-slate-400">No rules yet. Add at least rent_deposit as required.</div>
                    @endforelse
                </div>

                @error('definitions')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <div>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save deposit rules</button>
                </div>
            </form>
        </div>
    </x-property.page>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            const wrap = document.getElementById('deposit-definition-rows');
            const add = document.getElementById('add-definition-row');
            if (!wrap || !add) return;

            const properties = @json($properties->map(fn($p) => ['id' => $p->id, 'name' =>$p->name])->values());
            const units = @json($units->map(fn($u) => ['id' =>$u->id, 'label' => ($u->property?->name ?? '').' / '.$u->label])->values());

            const rowHtml = (idx) => `
                <div class="deposit-rule-row grid grid-cols-1 gap-2 rounded-lg border border-slate-200 p-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 dark:border-slate-800">
                    <select name="definitions[${idx}][property_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white">${properties.map((p) => `<option value="${p.id}">${p.name}</option>`).join('')}</select>
                    <select name="definitions[${idx}][property_unit_id]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white"><option value="">Property default</option>${units.map((u) => `<option value="${u.id}">${u.label}</option>`).join('')}</select>
                    <input type="text" name="definitions[${idx}][deposit_key]" placeholder="water_deposit" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                    <input type="text" name="definitions[${idx}][label]" placeholder="Water deposit" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                    <label class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs dark:border-slate-700 dark:text-slate-300"><input type="checkbox" name="definitions[${idx}][is_required]" value="1">Req</label>
                    <select name="definitions[${idx}][amount_mode]" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white"><option value="fixed">Fixed</option><option value="percent_rent">% Rent</option></select>
                    <input type="number" name="definitions[${idx}][amount_value]" value="0" step="0.01" min="0" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                    <input type="text" name="definitions[${idx}][ledger_account]" placeholder="Ledger map" class="rounded-lg border border-slate-200 px-2 py-2 text-sm dark:border-slate-700 dark:bg-gray-800 dark:text-white" />
                    <label class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs dark:border-slate-700 dark:text-slate-300"><input type="checkbox" name="definitions[${idx}][is_refundable]" value="1" checked>Refund</label>
                    <button type="button" class="remove-definition-row rounded-lg border border-red-200 px-2 py-2 text-xs text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/30">Remove</button>
                    <input type="hidden" name="definitions[${idx}][is_active]" value="1">
                    <input type="hidden" name="definitions[${idx}][sort_order]" value="${idx}">
                </div>`;

            add.addEventListener('click', () => {
                const hint = document.getElementById('no-rules-hint');
                if (hint) hint.remove();

                const idx = Date.now();
                wrap.insertAdjacentHTML('beforeend', rowHtml(idx));
            });

            wrap.addEventListener('click', (e) => {
                const t = e.target;
                if (!(t instanceof HTMLElement) || !t.classList.contains('remove-definition-row')) return;
                t.closest('.deposit-rule-row')?.remove();
            });

            @if(session('swal_success') || session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: "{{ session('swal_success') ?? session('success') }}",
                    timer: 3000,
                    showConfirmButton: false
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: "{{ session('error') }}",
                });
            @endif
        })();
    </script>
</x-property-layout>