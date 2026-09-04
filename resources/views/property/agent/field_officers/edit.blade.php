<x-property.crud-shell
    :in-property-form-modal="$inPropertyFormModal ?? false"
    :title="'Edit field officer: '.$fieldOfficer->name"
    subtitle="Update contact details and portal access for this portfolio manager."
    back-route="property.field_officers.show"
    :back-route-params="['fieldOfficer' => $fieldOfficer->id]"
    :stats="[
        ['label' => 'Officer', 'value' => $fieldOfficer->name, 'hint' => $fieldOfficer->phone ?: 'No phone'],
        ['label' => 'Properties', 'value' => (string) $fieldOfficer->properties()->count(), 'hint' => 'Assigned'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.field_officers.update', $fieldOfficer, false) }}" class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-4 max-w-2xl w-full min-w-0">
        @csrf
        @method('PUT')

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Officer details</h3>
        </div>

        @if (($agents ?? []) !== [])
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agent workspace</label>
                <select name="agent_user_id" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach ($agents as $agent)
                        <option value="{{ $agent['id'] }}" @selected((int) old('agent_user_id', $fieldOfficer->agent_user_id) === (int) $agent['id'])>{{ $agent['name'] }}</option>
                    @endforeach
                </select>
                @error('agent_user_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Changing workspace only affects new assignments — reassign properties if needed.</p>
            </div>
        @endif

        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Full name</label>
            <input
                type="text"
                name="name"
                value="{{ old('name', $fieldOfficer->name) }}"
                required
                autofocus
                class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
            />
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
            <input
                type="text"
                name="phone"
                value="{{ old('phone', $fieldOfficer->phone) }}"
                placeholder="e.g. 0712345678"
                class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
            />
            @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" name="portal_access" value="1" @checked(old('portal_access', $fieldOfficer->portal_access)) class="rounded border-slate-300" />
            Portal access (future — officer can sign in when enabled)
        </label>

        <div class="flex flex-col sm:flex-row gap-2 pt-1">
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Save changes</button>
            <a href="{{ route('property.field_officers.show', $fieldOfficer, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</a>
        </div>
    </form>
</x-property.crud-shell>
