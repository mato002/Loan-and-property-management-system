@php
    $agreementPropertyIds = ($serviceAgreements ?? collect())->pluck('property_id')->map(fn ($id) => (string) $id)->all();
    $agreementAmounts = ($serviceAgreements ?? collect())->mapWithKeys(fn ($agreement) => [(string) $agreement->property_id => (string) $agreement->monthly_amount])->all();
    $currentAmounts = array_values($agreementAmounts);
    $defaultPricingMode = count(array_unique($currentAmounts)) > 1 ? 'per_property' : 'shared';
    $defaultSharedAmount = $currentAmounts[0] ?? '';
@endphp
<x-property.crud-shell :in-property-form-modal="$inPropertyFormModal ?? false"
    title="Edit vendor"
    subtitle="Update the vendor profile and the service agreements attached to its properties."
    back-route="property.vendors.directory"
    :stats="[
        ['label' => 'Vendor', 'value' => $vendor->name, 'hint' => $vendor->category ?: 'No category'],
        ['label' => 'Status', 'value' => ucfirst($vendor->status), 'hint' => 'Current state'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.vendors.update', $vendor) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4 max-w-4xl">
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Vendor details</h3>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name', $vendor->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                <input type="text" name="category" value="{{ old('category', $vendor->category) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="active" @selected(old('status', $vendor->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $vendor->status) === 'inactive')>Inactive</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $vendor->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                <input type="email" name="email" value="{{ old('email', $vendor->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rating (0–5)</label>
                <input type="number" name="rating" value="{{ old('rating', $vendor->rating) }}" min="0" max="5" step="0.1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('rating')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">ID / Registration no.</label>
                <input type="text" name="registration_no" value="{{ old('registration_no', $vendor->registration_no) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
        </div>

        <section class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-4" x-data="{ pricingMode: @js(old('service_amount_mode', $defaultPricingMode)), selectedPropertyIds: @js(array_map('strval', old('service_property_ids', $agreementPropertyIds))) }">
            <h4 class="text-sm font-semibold text-slate-800">Service and assigned properties</h4>
            <p class="mt-1 text-xs text-slate-600">Update the properties and monthly price for this service. If generated bills already use an agreement, its history is retained and a new agreement is created for the changed terms.</p>
            <input type="hidden" name="service_group_name" value="{{ old('service_group_name', $serviceGroupName ?? '') }}" />
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Service provided</label>
                    <input type="text" name="service_name" value="{{ old('service_name', $serviceGroupName ?? $vendor->category) }}" placeholder="e.g. Garbage collection" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('service_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <span class="block text-xs font-medium text-slate-600 dark:text-slate-400">Properties served</span>
                    <div class="mt-1 max-h-48 space-y-1 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2">
                        @forelse ($properties ?? [] as $property)
                            <div class="rounded-md px-2 py-1.5 hover:bg-indigo-50">
                                <label class="flex items-start gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="service_property_ids[]" value="{{ $property->id }}" x-model="selectedPropertyIds" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span>{{ $property->code ? $property->code.' — ' : '' }}{{ $property->name }}</span>
                                </label>
                                <div x-cloak x-show="pricingMode === 'per_property' && selectedPropertyIds.includes('{{ (string) $property->id }}')" class="ml-6 mt-2">
                                    <label class="block text-[11px] font-medium text-slate-500">Monthly amount for this property (KES)</label>
                                    <input type="number" name="service_monthly_amounts[{{ $property->id }}]" min="0.01" step="0.01" value="{{ old('service_monthly_amounts.'.$property->id, $agreementAmounts[(string) $property->id] ?? '') }}" :required="pricingMode === 'per_property' && selectedPropertyIds.includes('{{ (string) $property->id }}')" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                    @error('service_monthly_amounts.'.$property->id)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @empty
                            <p class="px-2 py-1 text-xs text-slate-500">No properties are available to assign.</p>
                        @endforelse
                    </div>
                    <span class="mt-1 block text-xs text-slate-500">Tick every property this vendor serves.</span>
                    @error('service_property_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('service_property_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agreed pricing</label>
                    <select name="service_amount_mode" x-model="pricingMode" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="shared">Same amount for every selected property</option>
                        <option value="per_property">Different amount per property</option>
                    </select>
                    @error('service_amount_mode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div x-cloak x-show="pricingMode === 'shared'">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly amount (KES)</label>
                    <input type="number" name="service_monthly_amount" min="0.01" step="0.01" value="{{ old('service_monthly_amount', $defaultSharedAmount) }}" :required="pricingMode === 'shared' && selectedPropertyIds.length > 0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('service_monthly_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agreement starts</label>
                    <input type="date" name="service_starts_on" value="{{ old('service_starts_on', ($serviceAgreements ?? collect())->first()?->starts_on?->toDateString() ?? now()->toDateString()) }}" :required="selectedPropertyIds.length > 0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('service_starts_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly billing day</label>
                    <input type="number" name="service_billing_day" min="1" max="28" value="{{ old('service_billing_day', ($serviceAgreements ?? collect())->first()?->billing_day ?? 1) }}" :required="selectedPropertyIds.length > 0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('service_billing_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ([
                'bank_name' => 'Bank',
                'bank_branch_code' => 'Bank branch code',
                'bank_account_name' => 'Account name',
                'bank_account_number' => 'Account number',
                'creditor_account' => 'Creditor account',
            ] as $field => $label)
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $label }}</label>
                    <input type="text" name="{{ $field }}" value="{{ old($field, $vendor->{$field}) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error($field)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.vendors.directory') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
</x-property.crud-shell>
