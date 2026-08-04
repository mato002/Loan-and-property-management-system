@php
    $auditTrailUrl = route('property.accounting.audit_trail', absolute: false);
    $drawerLabel = 'Audit trail filters';
    $filterFormId = 'property-filter-form-'.substr(md5($auditTrailUrl.$drawerLabel), 0, 8);

    $userOptions = collect($users ?? [])->map(fn ($user) => [
        'value' => (string) $user->id,
        'label' => (string) $user->name,
    ])->all();

    $actionOptions = collect($actionTypes ?? [])->map(fn ($type) => [
        'value' => (string) $type,
        'label' => (string) $type,
    ])->all();

    $entityOptions = collect($entityTypes ?? [])->map(fn ($type) => [
        'value' => (string) $type,
        'label' => (string) $type,
    ])->all();

    $propertyOptions = collect($properties ?? [])->map(fn ($property) => [
        'value' => (string) $property->id,
        'label' => (string) $property->name,
    ])->all();

    $tenantOptions = collect($tenants ?? [])->map(fn ($tenant) => [
        'value' => (string) $tenant->id,
        'label' => (string) $tenant->name,
    ])->all();

    $accountOptionsList = collect($accountOptions ?? [])->map(fn ($account) => [
        'value' => (string) $account->id,
        'label' => trim((string) $account->code.' - '.(string) $account->name),
    ])->all();
@endphp

<x-property.filter-toolbar
    :action="$auditTrailUrl"
    :reset-url="$auditTrailUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'from' => 'From',
        'to' => 'To',
        'user_id' => 'User',
        'action_type' => 'Action',
        'entity_type' => 'Entity',
        'reference' => 'Reference',
        'source_type' => 'Source',
        'property_id' => 'Property',
        'tenant_id' => 'Tenant',
        'account_id' => 'Account',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search description, action, entity…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />
        <x-property.filter-field
            type="select"
            name="user_id"
            label="User"
            empty-option="User: All"
            :options="$userOptions"
            :value="$filters['user_id'] ?? ''"
        />
        <x-property.filter-field
            type="select"
            name="action_type"
            label="Action"
            empty-option="Action: All"
            :options="$actionOptions"
            :value="$filters['action_type'] ?? ''"
        />
        <x-property.filter-field
            type="select"
            name="entity_type"
            label="Entity"
            empty-option="Entity: All"
            :options="$entityOptions"
            :value="$filters['entity_type'] ?? ''"
        />
    </x-slot>

    <x-slot name="secondary">
        <x-property.filter-field type="search" name="reference" label="Reference" placeholder="Reference ID / source key" :value="$filters['reference'] ?? ''" />
        <x-property.filter-field
            type="select"
            name="source_type"
            label="Source"
            empty-option="Source: All"
            :options="[
                ['value' => 'system', 'label' => 'System'],
                ['value' => 'manual', 'label' => 'Manual'],
                ['value' => 'api', 'label' => 'API'],
                ['value' => 'webhook', 'label' => 'Webhook'],
            ]"
            :value="$filters['source_type'] ?? ''"
        />
        <x-property.filter-field
            type="select"
            name="property_id"
            label="Property"
            empty-option="Property: All"
            :options="$propertyOptions"
            :value="$filters['property_id'] ?? ''"
        />
        <x-property.filter-field
            type="select"
            name="tenant_id"
            label="Tenant"
            empty-option="Tenant: All"
            :options="$tenantOptions"
            :value="$filters['tenant_id'] ?? ''"
        />
        <x-property.filter-field
            type="select"
            name="account_id"
            label="Account"
            empty-option="Account: All"
            :options="$accountOptionsList"
            :value="$filters['account_id'] ?? ''"
        />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.audit_trail.export', array_merge($filters ?? [], ['format' => 'csv']), false),
            'pdfUrl' => route('property.accounting.audit_trail.export', array_merge($filters ?? [], ['format' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
