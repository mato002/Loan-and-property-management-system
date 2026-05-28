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

<div id="audit-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-gray-900 border border-slate-200 dark:border-slate-700 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Audit quick preview</h3>
            <button type="button" data-audit-preview-close class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Close</button>
        </div>
        <div class="p-4 space-y-3 text-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><span class="text-slate-500">Batch</span><div id="ap-batch" class="font-medium text-slate-900 dark:text-slate-100"></div></div>
                <div><span class="text-slate-500">Date</span><div id="ap-date" class="font-medium text-slate-900 dark:text-slate-100"></div></div>
                <div><span class="text-slate-500">Action</span><div id="ap-action" class="font-medium text-slate-900 dark:text-slate-100"></div></div>
                <div><span class="text-slate-500">Entity</span><div id="ap-entity" class="font-medium text-slate-900 dark:text-slate-100"></div></div>
                <div><span class="text-slate-500">Reference</span><div id="ap-reference" class="font-medium text-slate-900 dark:text-slate-100"></div></div>
                <div><span class="text-slate-500">Status</span><div id="ap-status" class="font-medium text-slate-900 dark:text-slate-100"></div></div>
            </div>
            <div><span class="text-slate-500">Financial impact</span><div id="ap-impact" class="font-medium text-slate-900 dark:text-slate-100 mt-1"></div></div>
            <div><span class="text-slate-500">Description</span><div id="ap-description" class="text-slate-700 dark:text-slate-300 mt-1"></div></div>
            <div id="ap-reversal" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-3 py-2 text-slate-700 dark:text-slate-300"></div>
        </div>
    </div>
</div>

<script>
    (function () {
        const payload = @json($previewPayload ?? []);
        const modal = document.getElementById('audit-preview-modal');
        if (!modal) return;
        const closeBtn = modal.querySelector('[data-audit-preview-close]');
        const el = (id) => document.getElementById(id);

        const openPreview = (id) => {
            const row = payload[String(id)] || payload[id];
            if (!row) return;
            el('ap-batch').textContent = row.batch || '—';
            el('ap-date').textContent = row.date || '—';
            el('ap-action').textContent = row.action || '—';
            el('ap-entity').textContent = row.entity || '—';
            el('ap-reference').textContent = row.reference || '—';
            el('ap-status').textContent = row.status || '—';
            el('ap-impact').textContent = row.impact || '—';
            el('ap-description').textContent = row.description || '—';
            const reversalText = row.reversal_of
                ? `This batch reverses ${row.reversal_of}.`
                : (row.reversal_count > 0 ? `This batch has ${row.reversal_count} linked reversal batch(es).` : 'No reversal linkage found for this batch.');
            el('ap-reversal').textContent = reversalText;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        document.querySelectorAll('[data-audit-preview-id]').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openPreview(btn.getAttribute('data-audit-preview-id'));
            });
        });

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };
        closeBtn?.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    })();
</script>

