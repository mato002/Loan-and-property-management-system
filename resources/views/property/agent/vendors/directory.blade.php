@php
    $showVendorFormByDefault = $errors->hasAny(['name', 'category', 'status', 'phone', 'email', 'rating', 'service_property_ids', 'service_name', 'service_monthly_amount', 'service_starts_on', 'service_billing_day']);
@endphp
<x-property.workspace
    :legacy-toolbar="false"
    title="Vendor directory"
    subtitle="Register service providers and maintenance contractors, manage recurring agreements, and track payable bills."
    :show-search="false"
    back-route="property.vendors.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No vendors"
        empty-hint="Add a service provider or maintenance contractor here. Set recurring property agreements from the provider profile."
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from(['showVendorForm' => $showVendorFormByDefault]) !!}" ></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            data-property-modal-open="showVendorForm" @click="showVendorForm = true"
        >
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
            <span>Add vendor</span>
        </button>
        <a href="{{ route('property.accounting.payables.accounts_payable') }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Bills listing</a>
        <a href="{{ route('property.accounting.payables.expense_requisitions', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50">Expense requisitions</a>
    </x-slot>

    <x-slot name="secondary">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 max-w-3xl">
            A service provider is managed as a vendor here. Register their bank details and recurring property agreements on the provider profile. Supplier bills live under
            <a href="{{ route('property.accounting.payables.accounts_payable') }}" class="font-semibold underline">Accounting → Payables → Bills listing</a>.
            Importing bills also adds those supplier names here so you can assign them to jobs.
        </div>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showVendorForm"
            close="showVendorForm = false"
            name="vendor-create"
            title="Add vendor"
            max-width="2xl"
        >
        <form method="post" action="{{ route('property.vendors.store') }}" class="space-y-3">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add vendor</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rating (0–5)</label>
                    <input type="number" name="rating" value="{{ old('rating') }}" step="0.1" min="0" max="5" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('rating')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">ID / Registration no.</label>
                    <input type="text" name="registration_no" value="{{ old('registration_no') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Default property</label>
                    <select name="default_property_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Choose later</option>
                        @foreach ($properties ?? [] as $property)
                            <option value="{{ $property->id }}" @selected((string) old('default_property_id') === (string) $property->id)>{{ $property->code ? $property->code.' — ' : '' }}{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3">
                    <h4 class="text-sm font-semibold text-slate-800">Services and properties served</h4>
                    <p class="mt-1 text-xs text-slate-600">For a recurring service such as garbage collection, select every property this vendor serves. The system creates one recurring agreement per property, which can be used to generate that property’s monthly bill.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Service provided</label>
                            <input type="text" name="service_name" value="{{ old('service_name', old('category')) }}" placeholder="e.g. Garbage collection" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('service_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Properties served</label>
                            <select name="service_property_ids[]" multiple size="4" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                @foreach ($properties ?? [] as $property)
                                    <option value="{{ $property->id }}" @selected(in_array((string) $property->id, array_map('strval', old('service_property_ids', [])), true))>{{ $property->code ? $property->code.' — ' : '' }}{{ $property->name }}</option>
                                @endforeach
                            </select>
                            <span class="mt-1 block text-xs text-slate-500">Use Ctrl (Windows) or Cmd (Mac) to select more than one.</span>
                            @error('service_property_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            @error('service_property_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly amount per property (KES)</label>
                            <input type="number" name="service_monthly_amount" min="0.01" step="0.01" value="{{ old('service_monthly_amount') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('service_monthly_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agreement starts</label>
                            <input type="date" name="service_starts_on" value="{{ old('service_starts_on', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('service_starts_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly billing day</label>
                            <input type="number" name="service_billing_day" min="1" max="28" value="{{ old('service_billing_day', 1) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('service_billing_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @error('service_property_ids.*')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs text-amber-800">The same monthly amount is applied to each selected property. Add separate agreements from the vendor profile if costs differ by property.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bank</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bank branch code</label>
                    <input type="text" name="bank_branch_code" value="{{ old('bank_branch_code') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Account name</label>
                    <input type="text" name="bank_account_name" value="{{ old('bank_account_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Account number</label>
                    <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Creditor account</label>
                    <input type="text" name="creditor_account" value="{{ old('creditor_account') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save vendor</button>
        </form>
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.vendors_directory', ['filters' => $filters])
    </x-slot>

    @if (isset($vendorPager))
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $vendorPager->firstItem() ?? 0 }}-{{ $vendorPager->lastItem() ?? 0 }} of {{ $vendorPager->total() }} vendors.
                </p>
                <div>
                    {{ $vendorPager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif

    @if (session()->has('next_steps') && ($ns = session('next_steps')) && is_array($ns))
        <div
            x-data="{ open: true }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40"
        >
            <div class="mx-4 max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/10">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                        Vendor onboarding
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $ns['title'] ?? 'Vendor saved' }}
                    </h2>
                    @php
                        $summary = $ns['vendor'] ?? null;
                    @endphp
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $ns['message'] ?? 'Continue with the onboarding steps below.' }}
                    </p>
                    @if (is_array($summary))
                        <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3">
                                <dt class="font-medium text-slate-700">Vendor</dt>
                                <dd class="text-right">{{ $summary['name'] ?? '' }}</dd>
                            </div>
                            @if (!empty($summary['category']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Category</dt>
                                    <dd class="text-right">{{ $summary['category'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($summary['phone']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Phone</dt>
                                    <dd class="text-right"><x-phone-link :value="$summary['phone']" /></dd>
                                </div>
                            @endif
                            @if (!empty($summary['email']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Email</dt>
                                    <dd class="text-right">{{ $summary['email'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif
                </div>
                <div class="px-5 py-4 space-y-3">
                    <p class="text-xs font-medium text-slate-500">
                        Choose where to go next:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($ns['actions'] ?? [] as $action)
                            @php
                                $kind = $action['kind'] ?? 'secondary';
                                $isPrimary = $kind === 'primary';
                                $frame = $action['turbo_frame'] ?? null;
                            @endphp
                            <a
                                href="{{ $action['href'] ?? '#' }}"
                                @if ($frame) data-turbo-frame="{{ $frame }}" @endif
                                class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold
                                    {{ $isPrimary ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-300 text-slate-700 bg-white hover:bg-slate-50' }}"
                                @click="open = false"
                            >
                                @if (!empty($action['icon']))
                                    <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                                @endif
                                <span>{{ $action['label'] ?? 'Continue' }}</span>
                            </a>
                        @endforeach
                    </div>
                    <button
                        type="button"
                        class="mt-1 text-xs font-medium text-slate-500 hover:text-slate-700"
                        @click="open = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-property.workspace>
