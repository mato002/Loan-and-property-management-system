<x-property-layout>
    <x-slot name="header">System setup · Unit fields</x-slot>

    <x-property.page
        title="Unit fields"
        subtitle="Configure the fields used in unit creation/edit forms. Keep only what operations teams need."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup hub</a>
            <a href="{{ route('property.settings.system_setup.property_onboarding_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Property onboarding fields</a>
            <a href="{{ route('property.settings.system_setup.unit_fields') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Unit fields</a>
            <a href="{{ route('property.settings.system_setup.forms') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">General form switches</a>
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.settings.system_setup.unit_fields.store') }}" class="space-y-4" x-data="{
            rows: @js(old('fields', $fields)),
            addRow() {
                this.rows.push({ key: '', label: '', type: 'text', required: false, enabled: true, help_text: '', options: '' });
            },
            removeRow(index) {
                this.rows.splice(index, 1);
            }
        }">
            @csrf

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Field key</th>
                            <th class="px-3 py-2 text-left">Label</th>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-left">Required</th>
                            <th class="px-3 py-2 text-left">Enabled</th>
                            <th class="px-3 py-2 text-left">Help text</th>
                            <th class="px-3 py-2 text-left">Select options</th>
                            <th class="px-3 py-2 text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in rows" :key="index">
                            <tr class="border-t border-slate-100 dark:border-slate-700/80">
                                <td class="px-3 py-2 align-top">
                                    <input :name="`fields[${index}][key]`" x-model="row.key" type="text" class="w-40 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm" placeholder="e.g. rent_amount" />
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input :name="`fields[${index}][label]`" x-model="row.label" type="text" class="w-44 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm" placeholder="Rent amount" />
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <select :name="`fields[${index}][type]`" x-model="row.type" class="w-32 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm">
                                        <option value="text">Text</option>
                                        <option value="textarea">Textarea</option>
                                        <option value="number">Number</option>
                                        <option value="select">Select</option>
                                        <option value="date">Date</option>
                                        <option value="checkbox">Checkbox</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input type="hidden" :name="`fields[${index}][required]`" value="0" />
                                    <label class="inline-flex items-center gap-2 text-xs">
                                        <input :name="`fields[${index}][required]`" x-model="row.required" value="1" type="checkbox" />
                                        Yes
                                    </label>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input type="hidden" :name="`fields[${index}][enabled]`" value="0" />
                                    <label class="inline-flex items-center gap-2 text-xs">
                                        <input :name="`fields[${index}][enabled]`" x-model="row.enabled" value="1" type="checkbox" />
                                        Active
                                    </label>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input :name="`fields[${index}][help_text]`" x-model="row.help_text" type="text" class="w-56 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm" placeholder="Helper text under field" />
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input :name="`fields[${index}][options]`" x-model="row.options" type="text" class="w-56 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm" placeholder="Comma-separated options" />
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <button type="button" @click="removeRow(index)" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Remove</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            @error('fields')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="addRow()" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">+ Add field</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save unit fields</button>
            </div>
        </form>
    </x-property.page>
</x-property-layout>
