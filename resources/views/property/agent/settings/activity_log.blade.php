<x-property-layout>
    <x-slot name="header">Activity log</x-slot>

    @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.activity_log'])

    <x-property.workspace
        title="Activity log"
        subtitle="Who did what across settings, leases, invoices, finance, utilities, portal actions, and logins."
        back-route="property.settings.index"
        :legacy-toolbar="false"
        :show-search="false"
        :stats="$stats"
        :columns="$columns"
        :table-rows="$tableRows"
        empty-title="No activity found"
        empty-hint="Try widening the date range or clearing filters."
    >
        <x-slot name="toolbar">
            <form method="get" class="rounded-xl border border-slate-200 bg-white p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input
                        type="search"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Summary, action, user..."
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[220px]"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Source</label>
                    <select name="source" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All sources</option>
                        @foreach (($sourceOptions ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">User</label>
                    <select name="user_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[180px]">
                        <option value="">All users</option>
                        @foreach (($actorOptions ?? collect()) as $actor)
                            <option value="{{ $actor->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $actor->id)>
                                {{ $actor->name }} ({{ ucfirst((string) $actor->property_portal_role) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Per page</label>
                    <select name="per_page" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach ([30, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Apply</button>
                <a
                    href="{{ route('property.settings.activity_log', array_merge($filters ?? [], ['export' => 'csv'])) }}"
                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >Export CSV</a>
            </form>
            <p class="mt-2 text-xs text-slate-500">
                Includes portal actions, finance/accounting audits, utility billing, invoice lifecycle events, login records, and new activity entries (lease edits, settings changes).
            </p>
        </x-slot>

        @isset($paginator)
            <x-slot name="footer">
                @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
            </x-slot>
        @endisset
    </x-property.workspace>
</x-property-layout>
