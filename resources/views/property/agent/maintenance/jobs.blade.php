@php
    $showJobFormByDefault = $errors->hasAny(['pm_maintenance_request_id', 'pm_vendor_id', 'quote_amount', 'status', 'notes']);
@endphp
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Job tracking"
    subtitle="Work orders linked to requests and vendors."
    back-route="property.maintenance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No jobs"
    empty-hint="Create a job from an open request; mark done to stamp completion time."
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from(['showJobForm' => $showJobFormByDefault]) !!}" ></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            data-property-modal-open="showJobForm" @click="showJobForm = true"
        >
            <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
            <span>Add maintenance job</span>
        </button>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showJobForm"
            close="showJobForm = false"
            name="maintenance-job-create"
            title="New maintenance job"
            max-width="2xl"
        >
        <form method="post" action="{{ route('property.maintenance.jobs.store') }}" class="space-y-3">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New job</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Request</label>
                <select name="pm_maintenance_request_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($requests as $r)
                        <option value="{{ $r->id }}" @selected(old('pm_maintenance_request_id') == $r->id)>#{{ $r->id }}  -  {{ $r->locationLabel() }}  -  {{ $r->category }}</option>
                    @endforeach
                </select>
                @error('pm_maintenance_request_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Vendor</label>
                    <x-property.quick-create-select
                        name="pm_vendor_id"
                        :required="false"
                        placeholder="—"
                        :options="collect($vendors)->map(fn($v) => ['value' => $v->id, 'label' => $v->name, 'selected' => (string) old('pm_vendor_id') === (string) $v->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create vendor',
                            'endpoint' => route('property.vendors.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Vendor name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Acme Plumbing'],
                                ['name' => 'category', 'label' => 'Category (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Plumbing, Electrical…'],
                                ['name' => 'phone', 'label' => 'Phone (optional)', 'required' => false, 'span' => '2', 'placeholder' => '+2547…'],
                                ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'email', 'required' => false, 'span' => '2', 'placeholder' => 'vendor@example.com'],
                            ],
                        ]"
                    />
                    @error('pm_vendor_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Quote (KES)</label>
                    <input type="number" name="quote_amount" value="{{ old('quote_amount') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('quote_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="quoted" @selected(old('status', 'quoted') === 'quoted')>Quoted</option>
                        <option value="approved" @selected(old('status') === 'approved')>Approved</option>
                        <option value="in_progress" @selected(old('status') === 'in_progress')>In progress</option>
                        <option value="done" @selected(old('status') === 'done')>Done</option>
                        <option value="cancelled" @selected(old('status') === 'cancelled')>Cancelled</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save job</button>
        </form>
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        <style>
            @media (min-width: 768px) {
                .jobs-filter-toolbar .property-filter-toolbar__form,
                .jobs-filter-toolbar [data-filter-main-row] {
                    flex-wrap: nowrap;
                    align-items: center;
                    gap: 0.35rem;
                }
                .jobs-filter-toolbar .property-filter-field {
                    min-width: 0;
                    max-width: none;
                    width: auto;
                    flex: 1 1 0;
                }
                .jobs-filter-toolbar .property-filter-field__label {
                    position: absolute;
                    width: 1px;
                    height: 1px;
                    padding: 0;
                    margin: -1px;
                    overflow: hidden;
                    clip: rect(0, 0, 0, 0);
                    white-space: nowrap;
                    border: 0;
                }
                .jobs-filter-toolbar .property-filter-field:has(input[type="date"]) {
                    display: flex;
                    flex-direction: row;
                    align-items: center;
                    gap: 0.25rem;
                }
                .jobs-filter-toolbar .property-filter-field:has(input[type="date"]) .property-filter-field__label {
                    position: static;
                    width: auto;
                    height: auto;
                    margin: 0;
                    overflow: visible;
                    clip: auto;
                    font-size: 11px;
                    line-height: 1;
                }
                .jobs-filter-toolbar .property-filter-field__control,
                .jobs-filter-toolbar select,
                .jobs-filter-toolbar input[type="date"] {
                    min-width: 0;
                    width: 100%;
                    min-height: 38px;
                }
                .jobs-filter-toolbar [data-filter-actions] {
                    flex-wrap: nowrap;
                    margin-left: 0.25rem;
                }
            }
        </style>
        <x-property.filter-toolbar
            class="jobs-filter-toolbar"
            single-row
            :action="route('property.maintenance.jobs', absolute: false)"
            :reset-url="route('property.maintenance.jobs', absolute: false)"
            drawer-label="Job filters"
            :chip-labels="[
                'q' => 'Search',
                'status' => 'Status',
                'vendor_id' => 'Vendor',
                'from' => 'From',
                'to' => 'To',
            ]"
        >
            <x-slot name="primary">
                <x-property.filter-field type="search" name="q" label="Search" placeholder="Vendor, category, notes..." :value="$filters['q'] ?? ''" wide />
                <x-property.filter-field
                    type="select"
                    name="status"
                    label="Status"
                    empty-option="Status: All"
                    :options="collect(['quoted', 'approved', 'in_progress', 'done', 'cancelled'])->map(fn ($st) => [
                        'value' => $st,
                        'label' => ucfirst(str_replace('_', ' ', $st)),
                    ])->all()"
                    :value="$filters['status'] ?? ''"
                />
                <x-property.filter-field
                    type="select"
                    name="vendor_id"
                    label="Vendor"
                    empty-option="Vendor: All"
                    :options="collect($vendors)->map(fn ($v) => ['value' => $v->id, 'label' => $v->name])->all()"
                    :value="(string) ($filters['vendor_id'] ?? '')"
                />
                <x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
                <x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />
                <x-property.filter-field
                    type="select"
                    name="per_page"
                    label="Rows"
                    :options="collect([10, 20, 50, 100])->map(fn ($n) => ['value' => (string) $n, 'label' => $n.' / page'])->all()"
                    :value="(string) ($filters['per_page'] ?? 20)"
                />
            </x-slot>
            <x-slot name="export">
                @include('property.agent.partials.table_export_dropdown', [
                    'route' => 'property.maintenance.jobs.export',
                    'query' => (array) ($filters ?? []),
                ])
            </x-slot>
        </x-property.filter-toolbar>
    </x-slot>

    @if (isset($jobsPager))
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $jobsPager->firstItem() ?? 0 }}-{{ $jobsPager->lastItem() ?? 0 }} of {{ $jobsPager->total() }} jobs.
                </p>
                <div>
                    {{ $jobsPager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif
</x-property.workspace>
