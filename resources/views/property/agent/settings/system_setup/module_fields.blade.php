<x-property-layout>
    <x-slot name="header">System setup · {{ $title }}</x-slot>

    <x-property.page
        :title="$title"
        :subtitle="$subtitle"
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup hub</a>
            <a href="{{ route('property.settings.system_setup.property_onboarding_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Property onboarding fields</a>
            <a href="{{ route('property.settings.system_setup.unit_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Unit fields</a>
            <a href="{{ route('property.settings.system_setup.amenity_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Amenity fields</a>
            <a href="{{ route('property.settings.system_setup.landlord_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Landlord fields</a>
            <a href="{{ route('property.settings.system_setup.lead_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Lead fields</a>
            <a href="{{ route('property.settings.system_setup.rental_application_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Rental application fields</a>
            <a href="{{ route('property.settings.system_setup.tenant_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Tenant fields</a>
            <a href="{{ route('property.settings.system_setup.lease_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Lease fields</a>
            <a href="{{ route('property.settings.system_setup.maintenance_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Maintenance fields</a>
            <a href="{{ route('property.settings.system_setup.vendor_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Vendor fields</a>
            <a href="{{ route('property.settings.system_setup.invoice_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Invoice/payment fields</a>
            <a href="{{ route('property.settings.system_setup.tenant_notice_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Tenant notice fields</a>
            <a href="{{ route('property.settings.system_setup.movement_fields') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Move-in/move-out fields</a>
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.settings.system_setup.'.$module.'_fields.store') }}" class="space-y-4" x-data="{
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
                                    <input :name="`fields[${index}][key]`" x-model="row.key" type="text" class="w-40 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm" placeholder="e.g. email" />
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input :name="`fields[${index}][label]`" x-model="row.label" type="text" class="w-44 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm" placeholder="Email" />
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
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save {{ strtolower($title) }}</button>
            </div>
        </form>
    </x-property.page>
</x-property-layout>
