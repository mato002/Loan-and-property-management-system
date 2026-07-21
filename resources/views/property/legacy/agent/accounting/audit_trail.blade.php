<x-property.workspace
    title="Accounting audit trail"
    subtitle="Forensic financial trace across invoices, payments, journals, payroll, maintenance, and payouts."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No financial audit activities"
    empty-hint="No events match your filters. Clear filters to expand the financial trace."
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.audit_trail.export', array_merge($filters, ['format' => 'csv'])),
            'pdfUrl' => route('property.accounting.audit_trail.export', array_merge($filters, ['format' => 'pdf'])),
        ])
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.audit_trail') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-2 w-full">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <select name="user_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full">
                <option value="">User: All</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="action_type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full">
                <option value="">Action: All</option>
                @foreach ($actionTypes as $type)
                    <option value="{{ $type }}" @selected(($filters['action_type'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <select name="entity_type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full">
                <option value="">Entity: All</option>
                @foreach ($entityTypes as $type)
                    <option value="{{ $type }}" @selected(($filters['entity_type'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <input type="search" name="reference" value="{{ $filters['reference'] ?? '' }}" placeholder="Reference ID / source key" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full" />
            <select name="source_type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full">
                <option value="">Source type: All</option>
                <option value="system" @selected(($filters['source_type'] ?? '') === 'system')>System</option>
                <option value="manual" @selected(($filters['source_type'] ?? '') === 'manual')>Manual</option>
                <option value="api" @selected(($filters['source_type'] ?? '') === 'api')>API</option>
                <option value="webhook" @selected(($filters['source_type'] ?? '') === 'webhook')>Webhook</option>
            </select>
            <select name="property_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full">
                <option value="">Property: All</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
            <select name="tenant_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full">
                <option value="">Tenant: All</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected((string) ($filters['tenant_id'] ?? '') === (string) $tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
            <select name="account_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full">
                <option value="">Account: All</option>
                @foreach ($accountOptions as $account)
                    <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search description/action/entity" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full" />
            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.accounting.audit_trail') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-center">Clear filters</a>
            </div>
        </form>
    </x-slot>
    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>

@include('property.agent.partials.audit_preview_modal')

