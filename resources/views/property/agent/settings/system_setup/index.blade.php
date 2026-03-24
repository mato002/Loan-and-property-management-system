<x-property-layout>
    <x-slot name="header">System setup</x-slot>

    <x-property.page
        title="System setup"
        subtitle="Manage global form behavior, workflow automation, and document templates used across the portal."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.roles') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Users & roles</a>
            <a href="{{ route('property.settings.commission') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Commission</a>
            <a href="{{ route('property.settings.payments') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payment config</a>
            <a href="{{ route('property.settings.branding') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Branding</a>
            <a href="{{ route('property.settings.rules') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System rules</a>
            <a href="{{ route('property.settings.system_setup') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">System setup</a>
        </div>

        <x-property.hub-grid :items="[
            ['route' => 'property.settings.system_setup.forms', 'title' => 'Form adjustments', 'description' => 'Enable/disable core forms and tune custom fields.'],
            ['route' => 'property.settings.system_setup.workflows', 'title' => 'Workflow adjustments', 'description' => 'Automation toggles for assignment and reminders.'],
            ['route' => 'property.settings.system_setup.templates', 'title' => 'Template adjustments', 'description' => 'Default lease and notice text used by forms.'],
        ]" />

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Forms configured</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $formsCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Workflow rules</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $workflowsCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Templates configured</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $templatesCount }}</p>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
