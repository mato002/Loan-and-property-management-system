<x-property-layout>
    <x-slot name="header">System setup · Forms</x-slot>

    <x-property.page
        title="Form adjustments"
        subtitle="Adjust which core forms are active and store custom field mapping for future dynamic form rendering."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup hub</a>
            <a href="{{ route('property.settings.system_setup.forms') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Form adjustments</a>
            <a href="{{ route('property.settings.system_setup.workflows') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Workflow adjustments</a>
            <a href="{{ route('property.settings.system_setup.templates') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Template adjustments</a>
            <a href="{{ route('property.settings.system_setup.access') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Access control</a>
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.settings.system_setup.forms.store') }}" class="max-w-3xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            @csrf
            <input type="hidden" name="tenant_move_in_enabled" value="0" />
            <input type="hidden" name="maintenance_enabled" value="0" />

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="tenant_move_in_enabled" value="1" @checked(old('tenant_move_in_enabled', $tenantMoveInEnabled ? '1' : '0') === '1') />
                Enable tenant move-in form
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="maintenance_enabled" value="1" @checked(old('maintenance_enabled', $maintenanceEnabled ? '1' : '0') === '1') />
                Enable maintenance request form
            </label>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Custom fields mapping (JSON)</label>
                <textarea name="form_custom_fields_json" rows="8" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder='[{"form":"tenant_move_in","field":"emergency_contact","label":"Emergency Contact","required":false}]'>{{ old('form_custom_fields_json', $customFields) }}</textarea>
                @error('form_custom_fields_json')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save form adjustments</button>
        </form>
    </x-property.page>
</x-property-layout>
