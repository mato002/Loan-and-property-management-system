<x-loan-layout>
    <x-loan.page
        title="Add client"
        subtitle="Register a borrower or savings customer in the loan book."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        <div class="grid max-w-7xl grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" x-data="clientFormPreview()">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8">
            <form method="post" action="{{ route('loan.clients.store') }}" class="space-y-5" enctype="multipart/form-data">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label value="Client number" />
                        <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">Auto-assigned when you save</div>
                        <x-input-error class="mt-2" :messages="$errors->get('client_number')" />
                    </div>
                    <div>
                        <x-input-label for="first_name" value="{{ ($biodataLabels['full_name'] ?? 'Name') }} (First name)" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required autocomplete="given-name" />
                        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="{{ ($biodataLabels['full_name'] ?? 'Name') }} (Last name)" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required autocomplete="family-name" />
                        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="{{ $biodataLabels['phone'] ?? 'Client Contact' }}" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" autocomplete="tel" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" autocomplete="email" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    <div>
                        <x-input-label for="id_number" value="{{ $biodataLabels['id_number'] ?? 'Idno' }}" />
                        <x-text-input id="id_number" name="id_number" type="text" class="mt-1 block w-full" :value="old('id_number')" />
                        <x-input-error class="mt-2" :messages="$errors->get('id_number')" />
                    </div>
                    <div>
                        <x-input-label for="gender" value="{{ $biodataLabels['gender'] ?? 'Gender' }}" />
                        <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select —</option>
                            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('gender')" />
                    </div>
                    <div>
                        <x-input-label for="next_of_kin_contact" value="{{ $biodataLabels['next_of_kin_contact'] ?? 'Kin Contact' }}" />
                        <x-text-input id="next_of_kin_contact" name="next_of_kin_contact" type="text" class="mt-1 block w-full" :value="old('next_of_kin_contact')" />
                        <x-input-error class="mt-2" :messages="$errors->get('next_of_kin_contact')" />
                    </div>
                    <div>
                        <x-input-label for="next_of_kin_name" value="{{ $biodataLabels['next_of_kin_name'] ?? 'Next Of Kin' }}" />
                        <x-text-input id="next_of_kin_name" name="next_of_kin_name" type="text" class="mt-1 block w-full" :value="old('next_of_kin_name')" />
                        <x-input-error class="mt-2" :messages="$errors->get('next_of_kin_name')" />
                    </div>
                    <div>
                        @include('loan.clients.partials.branch-select-with-modal', [
                            'fieldId' => 'branch',
                            'selectedValue' => old('branch'),
                            'branchOptions' => ($branchOptions ?? []),
                            'storeUrl' => route('loan.clients.branches.store'),
                        ])
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Address" />
                        <textarea id="address" name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('address') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('address')" />
                    </div>
                    <div>
                        <x-input-label for="client_photo" value="{{ $biodataLabels['client_photo'] ?? 'Client Photo' }}" />
                        <input id="client_photo" name="client_photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />
                        <x-input-error class="mt-2" :messages="$errors->get('client_photo')" />
                    </div>
                    <div>
                        <x-input-label for="id_back_photo" value="{{ $biodataLabels['id_back_photo'] ?? 'Id Back Photo' }}" />
                        <input id="id_back_photo" name="id_back_photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />
                        <x-input-error class="mt-2" :messages="$errors->get('id_back_photo')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="id_front_photo" value="{{ $biodataLabels['id_front_photo'] ?? 'Id Front Photo' }}" />
                        <input id="id_front_photo" name="id_front_photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />
                        <x-input-error class="mt-2" :messages="$errors->get('id_front_photo')" />
                    </div>
                    <div>
                        <x-input-label for="assigned_employee_id" value="{{ $biodataLabels['assigned_employee_id'] ?? 'Loan Officer' }}" />
                        <select id="assigned_employee_id" name="assigned_employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— None —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('assigned_employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('assigned_employee_id')" />
                    </div>
                    <div>
                        <x-input-label for="client_status" value="Client status" />
                        <select id="client_status" name="client_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['active', 'dormant', 'watchlist'] as $st)
                                <option value="{{ $st }}" @selected(old('client_status', 'active') === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('client_status')" />
                    </div>
                    @foreach (($biodataDynamicFields ?? []) as $field)
                        @php($fieldKey = (string) ($field['key'] ?? ''))
                        @php($fieldType = (string) ($field['data_type'] ?? 'alphanumeric'))
                        @php($fieldOptions = (array) ($field['select_options'] ?? []))
                        @if ($fieldKey !== '')
                            <div class="{{ $fieldType === 'long_text' ? 'sm:col-span-2' : '' }}">
                                <x-input-label :for="'biodata_'.$fieldKey" :value="(string) ($field['label'] ?? $fieldKey)" />
                                @if ($fieldType === 'select')
                                    <select id="biodata_{{ $fieldKey }}" name="biodata_meta[{{ $fieldKey }}]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Select —</option>
                                        @foreach ($fieldOptions as $opt)
                                            <option value="{{ $opt }}" @selected(old('biodata_meta.'.$fieldKey) === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($fieldType === 'long_text')
                                    <textarea id="biodata_{{ $fieldKey }}" name="biodata_meta[{{ $fieldKey }}]" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('biodata_meta.'.$fieldKey) }}</textarea>
                                @elseif ($fieldType === 'image')
                                    <input id="biodata_{{ $fieldKey }}" name="biodata_files[{{ $fieldKey }}]" type="file" accept="image/*" class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />
                                @else
                                    <x-text-input id="biodata_{{ $fieldKey }}" :name="'biodata_meta['.$fieldKey.']'" :type="$fieldType === 'number' ? 'number' : 'text'" class="mt-1 block w-full" :value="old('biodata_meta.'.$fieldKey)" />
                                @endif
                                <x-input-error class="mt-2" :messages="$errors->get('biodata_meta.'.$fieldKey)" />
                                <x-input-error class="mt-2" :messages="$errors->get('biodata_files.'.$fieldKey)" />
                            </div>
                        @endif
                    @endforeach
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>{{ __('Save client') }}</x-primary-button>
                </div>
            </form>
            </div>
            <aside class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm xl:sticky xl:top-4" x-data="{ mobilePreviewOpen: false }">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-md text-left xl:cursor-default"
                    @click="if (window.innerWidth < 1280) mobilePreviewOpen = !mobilePreviewOpen"
                >
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Client form preview</h4>
                    <span class="text-xs font-semibold text-slate-500 xl:hidden" x-text="mobilePreviewOpen ? 'Hide' : 'Show'"></span>
                </button>
                <div class="mt-3 space-y-1 text-xs text-slate-700" x-show="mobilePreviewOpen || window.innerWidth >= 1280" x-cloak>
                    <template x-if="previewFields.length === 0 && previewImages.length === 0">
                        <p class="text-slate-500">Fill the client form to preview entered details here.</p>
                    </template>
                    <template x-for="item in previewFields" :key="item.key">
                        <p>
                            <span class="font-semibold text-slate-600" x-text="item.label + ':'"></span>
                            <span x-text="item.value"></span>
                        </p>
                    </template>
                </div>
                <div class="mt-3 space-y-2" x-show="(mobilePreviewOpen || window.innerWidth >= 1280) && previewImages.length > 0" x-cloak>
                    <h5 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Uploaded images</h5>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="image in previewImages" :key="image.key">
                            <figure class="overflow-hidden rounded-md border border-slate-200 bg-slate-50 p-1">
                                <img :src="image.url" :alt="image.label" class="h-20 w-full rounded object-cover" />
                                <figcaption class="mt-1 truncate text-[10px] text-slate-600" x-text="image.label"></figcaption>
                            </figure>
                        </template>
                    </div>
                </div>
            </aside>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    function clientFormPreview() {
        return {
            previewFields: [],
            previewImages: [],
            previewImageUrls: [],
            init() {
                this.$nextTick(() => {
                    const form = this.$el.querySelector('form');
                    if (!form) return;
                    const refresh = () => this.refreshPreview();
                    form.addEventListener('input', refresh);
                    form.addEventListener('change', refresh);
                    this.refreshPreview();
                });
            },
            refreshPreview() {
                const form = this.$el.querySelector('form');
                if (!form) return;

                const fields = [];
                const controls = Array.from(form.querySelectorAll('input, select, textarea'));
                for (const control of controls) {
                    const tagName = (control.tagName ?? '').toLowerCase();
                    const type = (control.type ?? '').toLowerCase();
                    const name = (control.name ?? '').trim();
                    const id = (control.id ?? '').trim();
                    if (!name || name === '_token' || name === '_method') continue;
                    if (type === 'hidden' || type === 'file' || type === 'checkbox' || type === 'radio') continue;

                    let value = '';
                    if (tagName === 'select') {
                        value = (control.options?.[control.selectedIndex]?.textContent ?? '').trim();
                    } else {
                        value = String(control.value ?? '').trim();
                    }
                    if (!value || value.startsWith('— Select') || value.startsWith('— None')) continue;
                    if (id === 'client_number' && value.toLowerCase().includes('auto-assigned')) continue;

                    fields.push({
                        key: `${name}-${id || 'field'}`,
                        label: this.fieldLabel(control, name, id),
                        value,
                    });
                }
                this.previewFields = fields;
                this.refreshImagePreview(form);
            },
            refreshImagePreview(form) {
                this.previewImageUrls.forEach((url) => URL.revokeObjectURL(url));
                this.previewImageUrls = [];

                const images = [];
                const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
                for (const input of fileInputs) {
                    const files = Array.from(input.files ?? []);
                    if (files.length === 0) continue;
                    const label = this.fieldLabel(input, input.name ?? '', input.id ?? '');
                    files.forEach((file, index) => {
                        if (!String(file.type ?? '').toLowerCase().startsWith('image/')) return;
                        const url = URL.createObjectURL(file);
                        this.previewImageUrls.push(url);
                        images.push({
                            key: `${input.name || input.id || 'file'}-${index}-${file.name}`,
                            label: files.length > 1 ? `${label} (${index + 1})` : label,
                            url,
                        });
                    });
                }
                this.previewImages = images;
            },
            fieldLabel(control, fallbackName, id) {
                if (id) {
                    const labelEl = this.$el.querySelector(`label[for="${id}"]`);
                    if (labelEl) {
                        const txt = (labelEl.textContent ?? '').replace(/\s+/g, ' ').trim();
                        if (txt !== '') return txt;
                    }
                }
                const normalized = String(fallbackName ?? '')
                    .replace(/\[\]/g, '')
                    .replace(/\[/g, ' ')
                    .replace(/\]/g, '')
                    .replace(/_/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
                if (normalized === '') return 'Field';
                return normalized.charAt(0).toUpperCase() + normalized.slice(1);
            },
        };
    }
</script>
