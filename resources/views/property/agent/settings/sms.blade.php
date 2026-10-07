<x-property-layout>
    <x-slot name="header">SMS Settings</x-slot>

    <x-property.page
        title="SMS Provider"
        subtitle="Switch between Pradytec and Africa's Talking SMS providers."
    >
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.sms'])

        <form method="post" action="{{ route('property.settings.sms.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            @csrf

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 p-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Current provider</h3>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                    @if ($usingDatabaseSetting)
                        Using database setting: <span class="font-semibold text-blue-600 dark:text-blue-400">{{ strtoupper($effectiveDriver) }}</span>
                    @else
                        Using .env file setting: <span class="font-semibold text-blue-600 dark:text-blue-400">{{ strtoupper($effectiveDriver) }}</span>
                    @endif
                </p>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="use_database_setting" value="1" @checked($usingDatabaseSetting) />
                    Override .env and use database setting
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">When checked, the provider selection below will be used instead of the BULKSMS_DRIVER value in your .env file.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">SMS Provider</label>
                <select name="bulksms_driver" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="pradytec" @selected(old('bulksms_driver', $currentDriver ?? $envDriver) === 'pradytec')>Pradytec AI CRM (default)</option>
                    <option value="africastalking" @selected(old('bulksms_driver', $currentDriver ?? $envDriver) === 'africastalking')>Africa's Talking</option>
                </select>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    <strong>Pradytec:</strong> Includes in-app M-Pesa top-up, provider balance API, and delivery history.<br>
                    <strong>Africa's Talking:</strong> Use your Africa's Talking dashboard for top-up and balance management.
                </p>
                @error('bulksms_driver')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save SMS provider</button>
        </form>

        <div class="mt-6">
            <a href="{{ route('property.settings.index') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">← Back to settings</a>
        </div>
    </x-property.page>
</x-property-layout>
