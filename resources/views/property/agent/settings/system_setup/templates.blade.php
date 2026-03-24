<x-property-layout>
    <x-slot name="header">System setup · Templates</x-slot>

    <x-property.page
        title="Template adjustments"
        subtitle="Set default text templates that can be used by lease and notice-related forms."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup hub</a>
            <a href="{{ route('property.settings.system_setup.forms') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Form adjustments</a>
            <a href="{{ route('property.settings.system_setup.workflows') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Workflow adjustments</a>
            <a href="{{ route('property.settings.system_setup.templates') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Template adjustments</a>
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.settings.system_setup.templates.store') }}" class="max-w-3xl rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Default lease template</label>
                <textarea name="template_lease_text" rows="8" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('template_lease_text', $leaseTemplate) }}</textarea>
                @error('template_lease_text')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Default notice template</label>
                <textarea name="template_notice_text" rows="8" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('template_notice_text', $noticeTemplate) }}</textarea>
                @error('template_notice_text')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save template adjustments</button>
        </form>
    </x-property.page>
</x-property-layout>
