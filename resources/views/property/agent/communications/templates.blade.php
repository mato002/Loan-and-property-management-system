<x-property.workspace
    title="Message templates"
    subtitle="Reusable SMS / email bodies. Merge placeholders are your convention until a linter is added."
    back-route="property.communications.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No templates"
    empty-hint="Create templates below; delete only when unused."
>
    <x-slot name="above">
        <div x-data="{ showTemplateForm: @js($errors->hasAny(['name','channel','subject','body'])) }" class="space-y-3">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            @click="showTemplateForm = !showTemplateForm"
        >
            <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
            <span x-text="showTemplateForm ? 'Hide template form' : 'Add template'"></span>
        </button>
        <form method="post" action="{{ route('property.communications.templates.store') }}" x-show="showTemplateForm" x-cloak class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New template</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                    <option value="email" @selected(old('channel') === 'email')>Email</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Subject (email)</label>
                <input type="text" name="subject" value="{{ old('subject') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Body</label>
                <textarea name="body" rows="6" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('body') }}</textarea>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save template</button>
        </form>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search templates…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>

    <div class="space-y-2">
        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">Delete</p>
        <ul class="flex flex-wrap gap-2">
            @foreach ($messageTemplates as $t)
                <li>
                    <form method="post" action="{{ route('property.communications.templates.destroy', $t) }}" onsubmit="return confirm('Delete template {{ $t->name }}?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-red-200 dark:border-red-900/50 px-2 py-1 text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30">{{ $t->name }} ×</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-property.workspace>
