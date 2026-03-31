<x-property-layout>
    <x-slot name="header">Report an issue</x-slot>

    <x-property.page
        title="Report an issue"
        subtitle="Photos, urgency, and access windows help us dispatch faster."
    >
        <form
            method="post"
            action="{{ route('property.tenant.maintenance.report.store') }}"
            class="space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm w-full min-w-0"
            enctype="multipart/form-data"
        >
            @csrf
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/40 dark:border-red-800 px-3 py-2 text-sm text-red-800 dark:text-red-200">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="property_id">Property</label>
                <select
                    id="property_id"
                    name="property_id"
                    required
                    @disabled($leaseUnits->isEmpty())
                    class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3 disabled:opacity-60"
                >
                    @if ($propertyOptions->isNotEmpty())
                        <option value="">Select property</option>
                        @foreach ($propertyOptions as $property)
                            <option value="{{ $property['id'] }}" @selected(old('property_id') == $property['id'])>
                                {{ $property['name'] }}
                            </option>
                        @endforeach
                    @else
                        <option value="">No property found on your active lease.</option>
                    @endif
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="property_unit_id">Unit</label>
                <select
                    id="property_unit_id"
                    name="property_unit_id"
                    required
                    @disabled($leaseUnits->isEmpty())
                    class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3 disabled:opacity-60"
                >
                    <option value="">Select property first</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="category">Category</label>
                <select
                    id="category"
                    name="category"
                    required
                    class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                >
                    <option value="plumbing" @selected(old('category') === 'plumbing')>Plumbing</option>
                    <option value="electrical" @selected(old('category') === 'electrical')>Electrical</option>
                    <option value="security" @selected(old('category') === 'security')>Security</option>
                    <option value="other" @selected(old('category', 'other') === 'other')>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="description">Describe the problem</label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    required
                    class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                    placeholder="What broke? When did it start?"
                >{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="photos">Photos</label>
                <input
                    type="file"
                    id="photos"
                    name="photos[]"
                    multiple
                    accept="image/*"
                    class="mt-1 block w-full min-w-0 text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-teal-800 hover:file:bg-teal-100 dark:file:bg-teal-950 dark:file:text-teal-200"
                />
                <p class="mt-1 text-xs text-slate-500">Optional. Attachments are not stored until file storage is configured on the server.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="urgency">Urgency</label>
                    <select
                        id="urgency"
                        name="urgency"
                        required
                        class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-3 py-2"
                    >
                        <option value="normal" @selected(old('urgency', 'normal') === 'normal')>Normal</option>
                        <option value="urgent" @selected(old('urgency') === 'urgent')>Urgent</option>
                        <option value="emergency" @selected(old('urgency') === 'emergency')>Emergency</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="access_notes">Access</label>
                    <input
                        type="text"
                        id="access_notes"
                        name="access_notes"
                        value="{{ old('access_notes') }}"
                        class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-3 py-2"
                        placeholder="e.g. Weekday 9–5"
                    />
                </div>
            </div>
            <button
                type="submit"
                @disabled($leaseUnits->isEmpty())
                class="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50 disabled:pointer-events-none"
            >
                Submit request
            </button>
        </form>
        <script>
            (function () {
                const propertySelect = document.getElementById('property_id');
                const unitSelect = document.getElementById('property_unit_id');
                const unitsByProperty = @json($unitsByProperty);
                const selectedUnit = @json((string) old('property_unit_id', ''));

                if (!propertySelect || !unitSelect) return;

                const renderUnits = function () {
                    const propertyId = String(propertySelect.value || '');
                    const list = unitsByProperty[propertyId] || [];
                    unitSelect.innerHTML = '';

                    if (!propertyId) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = 'Select property first';
                        unitSelect.appendChild(opt);
                        return;
                    }

                    if (list.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = 'No unit found under selected property';
                        unitSelect.appendChild(opt);
                        return;
                    }

                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = 'Select unit';
                    unitSelect.appendChild(placeholder);

                    list.forEach(function (unit) {
                        const opt = document.createElement('option');
                        opt.value = String(unit.id);
                        opt.textContent = unit.label;
                        if (selectedUnit && String(selectedUnit) === String(unit.id)) {
                            opt.selected = true;
                        }
                        unitSelect.appendChild(opt);
                    });
                };

                propertySelect.addEventListener('change', function () {
                    renderUnits();
                    if (propertySelect.value !== @json((string) old('property_id', ''))) {
                        unitSelect.value = '';
                    }
                });

                renderUnits();
            })();
        </script>
        <a href="{{ route('property.tenant.maintenance.index') }}" class="inline-block mt-4 text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">← Back to maintenance</a>
    </x-property.page>
</x-property-layout>
