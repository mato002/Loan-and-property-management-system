<x-property-layout>
    <x-slot name="header">System setup · Workflows</x-slot>

    <x-property.page
        title="Workflow adjustments"
        subtitle="Configure simple automation switches for operational workflow handling."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup hub</a>
            <a href="{{ route('property.settings.system_setup.forms') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Form adjustments</a>
            <a href="{{ route('property.settings.system_setup.workflows') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Workflow adjustments</a>
            <a href="{{ route('property.settings.system_setup.templates') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Template adjustments</a>
            <a href="{{ route('property.settings.system_setup.access') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Access control</a>
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.settings.system_setup.workflows.store') }}" class="max-w-3xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            @csrf
            <input type="hidden" name="workflow_auto_assign_tickets" value="0" />
            <input type="hidden" name="workflow_auto_reminders" value="0" />

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="workflow_auto_assign_tickets" value="1" @checked(old('workflow_auto_assign_tickets', $autoAssignTickets ? '1' : '0') === '1') />
                Auto-assign maintenance tickets to default team
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="workflow_auto_reminders" value="1" @checked(old('workflow_auto_reminders', $autoReminders ? '1' : '0') === '1') />
                Auto-send rent reminders before due date
            </label>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reminder lead days</label>
                <input type="number" name="workflow_reminder_lead_days" min="0" max="60" value="{{ old('workflow_reminder_lead_days', $reminderLeadDays) }}" class="mt-1 w-full sm:max-w-xs rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Used when auto-reminders are enabled and no due date is provided.</p>
                @error('workflow_reminder_lead_days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Workflow notes</label>
                <textarea name="workflow_notes" rows="6" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('workflow_notes', $notes) }}</textarea>
                @error('workflow_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save workflow adjustments</button>
        </form>
    </x-property.page>
</x-property-layout>
