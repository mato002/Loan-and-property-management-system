<x-property-layout>
    <x-slot name="header">System setup</x-slot>

    <x-property.page
        title="System setup"
        subtitle="Manage global form behavior, workflow automation, and document templates used across the portal."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.roles') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Property users</a>
            <a href="{{ route('property.settings.commission') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Commission</a>
            <a href="{{ route('property.settings.payments') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payment config</a>
            <a href="{{ route('property.settings.branding') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Branding</a>
            <a href="{{ route('property.settings.rules') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System rules</a>
            <a href="{{ route('property.settings.system_setup') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">System setup</a>
        </div>

        <x-property.hub-grid :items="[
            ['route' => 'property.settings.system_setup.property_onboarding_fields', 'title' => 'Property onboarding fields', 'description' => 'Define exact fields shown when creating/onboarding a property.'],
            ['route' => 'property.settings.system_setup.unit_fields', 'title' => 'Unit fields', 'description' => 'Configure fields used when adding or editing units.'],
            ['route' => 'property.settings.system_setup.amenity_fields', 'title' => 'Amenity fields', 'description' => 'Define amenity creation fields and optional metadata.'],
            ['route' => 'property.settings.system_setup.landlord_fields', 'title' => 'Landlord fields', 'description' => 'Configure landlord onboarding and profile fields.'],
            ['route' => 'property.settings.system_setup.lead_fields', 'title' => 'Leads fields', 'description' => 'Configure listing lead capture and qualification fields.'],
            ['route' => 'property.settings.system_setup.rental_application_fields', 'title' => 'Rental application fields', 'description' => 'Configure rental application screening fields.'],
            ['route' => 'property.settings.system_setup.tenant_fields', 'title' => 'Tenant fields', 'description' => 'Configure tenant onboarding and profile fields.'],
            ['route' => 'property.settings.system_setup.lease_fields', 'title' => 'Lease fields', 'description' => 'Configure lease setup fields and required inputs.'],
            ['route' => 'property.settings.system_setup.maintenance_fields', 'title' => 'Maintenance fields', 'description' => 'Configure maintenance ticket and work-order fields.'],
            ['route' => 'property.settings.system_setup.vendor_fields', 'title' => 'Vendor fields', 'description' => 'Configure vendor onboarding and profile fields.'],
            ['route' => 'property.settings.system_setup.invoice_fields', 'title' => 'Invoice & payment fields', 'description' => 'Configure invoice and payment capture fields.'],
            ['route' => 'property.settings.system_setup.tenant_notice_fields', 'title' => 'Tenant notice fields', 'description' => 'Configure vacate/notice form fields.'],
            ['route' => 'property.settings.system_setup.movement_fields', 'title' => 'Move-in / move-out fields', 'description' => 'Configure movement scheduling/tracking fields.'],
            ['route' => 'property.settings.system_setup.forms', 'title' => 'General form switches', 'description' => 'Enable/disable broad form modules and shared JSON mappings.'],
            ['route' => 'property.settings.system_setup.workflows', 'title' => 'Workflow adjustments', 'description' => 'Automation toggles for assignment and reminders.'],
            ['route' => 'property.settings.rules', 'title' => 'Automation rules', 'description' => 'Define business rules and triggers used for automated actions.'],
            ['route' => 'property.settings.system_setup.templates', 'title' => 'Template adjustments', 'description' => 'Default lease and notice text used by forms.'],
            ['route' => 'property.settings.system_setup.access', 'title' => 'Access control', 'description' => 'Create roles, permissions, and user role mappings.'],
        ]" />

        @php
            $completion = $fieldModuleCompletion ?? ['configured' => 0, 'total' => 0, 'pending' => 0, 'configured_keys' => [], 'items' => []];
            $configuredKeys = collect($completion['configured_keys'] ?? [])->map(fn ($v) => (string) $v)->all();
            $pendingItems = collect($completion['items'] ?? [])->filter(fn ($item) => ! in_array((string) ($item['key'] ?? ''), $configuredKeys, true));
            $completionPct = ((int) ($completion['total'] ?? 0)) > 0
                ? (int) round((((int) ($completion['configured'] ?? 0)) / ((int) ($completion['total'] ?? 1))) * 100)
                : 0;
        @endphp

        <div class="mt-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Field module completion</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                        {{ (int) ($completion['configured'] ?? 0) }}/{{ (int) ($completion['total'] ?? 0) }} modules configured
                    </p>
                    <p class="text-xs text-slate-500">
                        @if (($completion['pending'] ?? 0) > 0)
                            {{ (int) $completion['pending'] }} module(s) still using defaults / untouched.
                        @else
                            All field modules have saved configuration.
                        @endif
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ ($completion['pending'] ?? 0) > 0 ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-emerald-300 bg-emerald-50 text-emerald-800' }}">
                    {{ $completionPct }}% complete
                </span>
            </div>
            <div class="mt-3 h-2 w-full rounded-full bg-slate-200">
                <div class="h-2 rounded-full {{ ($completion['pending'] ?? 0) > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}" style="width: {{ max(0, min(100, $completionPct)) }}%;"></div>
            </div>
            @if ($pendingItems->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($pendingItems as $item)
                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">
                            {{ $item['label'] ?? 'Unknown module' }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Forms configured</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $formsCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Property onboarding fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $propertyOnboardingFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Unit fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $unitFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amenity fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $amenityFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Landlord fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $landlordFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Lead fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $leadFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Rental application fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $rentalApplicationFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tenant fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $tenantFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Lease fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $leaseFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Maintenance fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $maintenanceFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vendor fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $vendorFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Invoice/payment fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $invoiceFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tenant notice fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $tenantNoticeFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Move-in/move-out fields</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $movementFieldsCount ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Workflow rules</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $workflowsCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Templates configured</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $templatesCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Roles configured</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $accessCount ?? 0 }}</p>
            </div>
        </div>
    </x-property.page>
</x-property-layout>

