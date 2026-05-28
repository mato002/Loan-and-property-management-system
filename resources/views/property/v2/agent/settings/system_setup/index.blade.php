<x-property-layout>
    <x-slot name="header">System setup</x-slot>
    @php
        $isSuperAdmin = (bool) (auth()->user()->is_super_admin ?? false);
        $canAccessControl = auth()->user()->hasPmPermission('settings.access.manage');
        $systemSetupHubItems = [
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
            ['route' => 'property.settings.deposits', 'title' => 'Deposit rules', 'description' => 'Define deposit lines, requirements, and formulas used by leases.'],
            ['route' => 'property.settings.expenses', 'title' => 'Expense charge rules', 'description' => 'Define utility/expense charge templates and default ledger mapping.'],
            ['route' => 'property.settings.system_setup.templates', 'title' => 'Template adjustments', 'description' => 'Default lease and notice text used by forms.'],
        ];
        if ($canAccessControl) {
            $systemSetupHubItems[] = ['route' => 'property.settings.system_setup.access', 'title' => 'Access control', 'description' => 'Create roles, permissions, and user role mappings.'];
        }
    @endphp

    <x-property.page
        title="System setup"
        subtitle="Manage global form behavior, workflow automation, and document templates used across the portal."
    >
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.system_setup'])

        <x-property.hub-grid :items="$systemSetupHubItems" />

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

        @php
            $setupStats = [
                ['label' => 'Forms configured', 'value' => (string) $formsCount],
                ['label' => 'Property onboarding fields', 'value' => (string) ($propertyOnboardingFieldsCount ?? 0)],
                ['label' => 'Unit fields', 'value' => (string) ($unitFieldsCount ?? 0)],
                ['label' => 'Amenity fields', 'value' => (string) ($amenityFieldsCount ?? 0)],
                ['label' => 'Landlord fields', 'value' => (string) ($landlordFieldsCount ?? 0)],
                ['label' => 'Lead fields', 'value' => (string) ($leadFieldsCount ?? 0)],
                ['label' => 'Rental application fields', 'value' => (string) ($rentalApplicationFieldsCount ?? 0)],
                ['label' => 'Tenant fields', 'value' => (string) ($tenantFieldsCount ?? 0)],
                ['label' => 'Lease fields', 'value' => (string) ($leaseFieldsCount ?? 0)],
                ['label' => 'Maintenance fields', 'value' => (string) ($maintenanceFieldsCount ?? 0)],
                ['label' => 'Vendor fields', 'value' => (string) ($vendorFieldsCount ?? 0)],
                ['label' => 'Invoice/payment fields', 'value' => (string) ($invoiceFieldsCount ?? 0)],
                ['label' => 'Tenant notice fields', 'value' => (string) ($tenantNoticeFieldsCount ?? 0)],
                ['label' => 'Move-in/move-out fields', 'value' => (string) ($movementFieldsCount ?? 0)],
                ['label' => 'Workflow rules', 'value' => (string) $workflowsCount],
                ['label' => 'Templates configured', 'value' => (string) $templatesCount],
                ['label' => 'Roles configured', 'value' => (string) ($accessCount ?? 0)],
            ];
        @endphp
        <x-property.responsive.stat-card-grid :stats="$setupStats" dense class="mt-4" />
    </x-property.page>
</x-property-layout>

