@php
    $fieldOfficersUrl = route('property.hr.employees.index', absolute: false);
@endphp

<x-property.filter-toolbar
    :action="$fieldOfficersUrl"
    :reset-url="$fieldOfficersUrl"
    drawer-label="Employee filters"
    :chip-labels="[
        'q' => 'Search',
        'department' => 'Department',
        'job_title' => 'Job title',
        'employment_status' => 'Status',
        'role_type' => 'Role',
        'agent_user_id' => 'Agent',
    ]"
    :chip-ignore-values="[
        'role_type' => ['all'],
        'agent_user_id' => ['0', 0],
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Name, number, email, phone…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="department"
            label="Department"
            empty-option="Department: All"
            :options="collect($departments ?? [])->map(fn ($d) => ['value' => $d, 'label' => $d])->all()"
            :value="$filters['department'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="job_title"
            label="Job title"
            empty-option="Job title: All"
            :options="collect($jobTitles ?? [])->map(fn ($t) => ['value' => $t, 'label' => $t])->all()"
            :value="$filters['job_title'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="employment_status"
            label="Status"
            empty-option="Status: All"
            :options="[
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'on_leave', 'label' => 'On leave'],
                ['value' => 'terminated', 'label' => 'Terminated'],
            ]"
            :value="$filters['employment_status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="role_type"
            label="Role"
            empty-option="Role: All"
            :options="[
                ['value' => 'field_officer', 'label' => 'Field officers only'],
            ]"
            :value="($filters['role_type'] ?? 'all') === 'all' ? '' : ($filters['role_type'] ?? '')"
        />
        @if (($agents ?? []) !== [])
            <x-property.filter-field type="select"
                name="agent_user_id"
                label="Agent workspace"
                :options="collect([['value' => '0', 'label' => 'Agent: All']])
                    ->merge(collect($agents)->map(fn ($a) => ['value' => (string) $a['id'], 'label' => $a['name']]))
                    ->all()"
                :value="(string) ($filters['agent_user_id'] ?? '0')"
            />
        @endif
    </x-slot>
</x-property.filter-toolbar>
