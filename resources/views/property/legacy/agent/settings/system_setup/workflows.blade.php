<x-property-layout>
    <x-slot name="header">System setup  -  Workflows</x-slot>

    <x-property.page
        title="Workflow adjustments"
        subtitle="Configure simple automation switches for operational workflow handling."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup hub</a>
            <a href="{{ route('property.settings.system_setup.forms') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Form adjustments</a>
            <a href="{{ route('property.settings.system_setup.workflows') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Workflow adjustments</a>
            <a href="{{ route('property.settings.system_setup.templates') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Template adjustments</a>
            @if (auth()->user()->hasPmPermission('settings.access.manage'))
                <a href="{{ route('property.settings.system_setup.access') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Access control</a>
            @endif
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <div class="mb-4 max-w-3xl rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200">
            <p class="font-medium text-slate-800 dark:text-slate-100">Scheduled automation (production)</p>
            <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                When enabled below (or via <code class="rounded bg-white px-1 py-0.5 text-slate-800 dark:bg-slate-800 dark:text-slate-200">PROPERTY_WORKFLOW_AUTOMATION_ENABLED</code> in <code class="rounded bg-white px-1 py-0.5">.env</code>),
                the server must run <code class="rounded bg-white px-1 py-0.5 text-slate-800 dark:bg-slate-800 dark:text-slate-200">php artisan schedule:run</code> every minute (see <code class="rounded bg-white px-1 py-0.5">deploy/laravel-scheduler.cron.example</code>).
                Use the switches below to turn <strong>rent invoices</strong>, <strong>water invoices</strong>, <strong>rent reminders</strong>, and <strong>water penalties</strong> on or off independently. Until you save this form once per environment, each switch follows the legacy “master” checkbox.
            </p>
            <p class="mt-2 text-xs font-medium {{ ($workflowAutomationEffective ?? false) ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-800 dark:text-amber-300' }}">
                Automation is currently <strong>{{ ($workflowAutomationEffective ?? false) ? 'ON' : 'OFF' }}</strong> for scheduled commands.
            </p>
            @if (!empty($workflowAutomationEnvIsSet))
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                    <code class="rounded bg-white px-1 py-0.5 dark:bg-slate-800">PROPERTY_WORKFLOW_AUTOMATION_ENABLED</code> is set in the environment and <strong>overrides</strong> the checkbox for scheduled commands.
                </p>
            @endif
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-500">CLI check: <code class="rounded bg-white px-1 py-0.5 text-slate-800 dark:bg-slate-800">php artisan property:workflow-automation-status</code></p>
        </div>

        <form method="post" action="{{ route('property.settings.system_setup.workflows.store') }}" class="max-w-3xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            @csrf
            <input type="hidden" name="workflow_auto_assign_tickets" value="0" />
            <input type="hidden" name="workflow_auto_reminders" value="0" />
            <input type="hidden" name="workflow_auto_rent_invoices" value="0" />
            <input type="hidden" name="workflow_auto_water_invoices" value="0" />
            <input type="hidden" name="workflow_auto_rent_reminders" value="0" />
            <input type="hidden" name="workflow_auto_water_penalties" value="0" />

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="workflow_auto_assign_tickets" value="1" @checked(old('workflow_auto_assign_tickets', $autoAssignTickets ? '1' : '0') === '1') />
                Auto-assign maintenance tickets to default team
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="workflow_auto_reminders" value="1" @checked(old('workflow_auto_reminders', $autoReminders ? '1' : '0') === '1') />
                Legacy default for automation (used when a granular switch below has never been saved)
            </label>

            <div class="rounded-lg border border-slate-200 dark:border-slate-600 p-3 space-y-2">
                <p class="text-xs font-medium text-slate-600 dark:text-slate-400">Scheduled jobs (granular)</p>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="workflow_auto_rent_invoices" value="1" @checked(old('workflow_auto_rent_invoices', $rentInvoicesAuto ? '1' : '0') === '1') />
                    Monthly rent invoices (<code class="text-xs">rent:generate-invoices</code>)
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="workflow_auto_water_invoices" value="1" @checked(old('workflow_auto_water_invoices', $waterInvoicesAuto ? '1' : '0') === '1') />
                    Monthly water invoices from readings (<code class="text-xs">water:generate-invoices</code>)
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="workflow_auto_rent_reminders" value="1" @checked(old('workflow_auto_rent_reminders', $rentRemindersAuto ? '1' : '0') === '1') />
                    Rent reminder emails/SMS (<code class="text-xs">rent:send-reminders</code>)
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="workflow_auto_water_penalties" value="1" @checked(old('workflow_auto_water_penalties', $waterPenaltiesAuto ? '1' : '0') === '1') />
                    Overdue water penalties (<code class="text-xs">water:apply-penalties</code>)
                </label>
            </div>

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
