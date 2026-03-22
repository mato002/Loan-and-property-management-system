<x-property.workspace
    title="SMS / email"
    subtitle="Outbound log — nothing is sent externally until you connect a provider. Each row is stored when you submit the form below."
    back-route="property.communications.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    empty-title="No messages logged"
    empty-hint="Log a test row below; wire Africa’s Talking / Twilio / SMTP for real delivery."
>
    <x-slot name="above">
        <form method="post" action="{{ route('property.communications.messages.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Log a message (dry run)</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                    <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="email" @selected(old('channel') === 'email')>Email</option>
                        <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="text" name="to_address" value="{{ old('to_address') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="email or phone" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Subject (email)</label>
                <input type="text" name="subject" value="{{ old('subject') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Body</label>
                <textarea name="body" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('body') }}</textarea>
            </div>
            @error('channel')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @error('to_address')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Log entry</button>
        </form>
    </x-slot>

    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Channel: All</option>
            <option value="sms">SMS</option>
            <option value="email">Email</option>
        </select>
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search phone or email…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
