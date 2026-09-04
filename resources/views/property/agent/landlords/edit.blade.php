@php
    $landlordFieldCfg = $landlordFields ?? [];
    $landlordRequired = function (string $field, bool $default = false) use ($landlordFieldCfg): bool {
        $cfg = $landlordFieldCfg[$field] ?? null;
        if (! is_array($cfg) || ! ($cfg['enabled'] ?? true)) {
            return false;
        }

        return (bool) ($cfg['required'] ?? $default);
    };
@endphp

<x-property.crud-shell :in-property-form-modal="$inPropertyFormModal ?? false"
    :title="'Edit landlord: '.$landlord->name"
    subtitle="Update landlord contact details used for portal login and communications."
    back-route="property.landlords.show"
    :back-route-params="['landlord' => $landlord->id]"
    :stats="[
        ['label' => 'Landlord', 'value' => $landlord->name, 'hint' => $landlord->email ?: ($landlord->phone ?: 'No contact')],
        ['label' => 'Portal', 'value' => 'Active', 'hint' => 'Landlord account'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.landlords.update', $landlord) }}" class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-3 max-w-2xl w-full min-w-0">
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Landlord details</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">Provide at least one of email or phone so the landlord can sign in to the portal.</p>

        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Full name</label>
            <input type="text" name="name" value="{{ old('name', $landlord->name) }}" @required($landlordRequired('name', true)) class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email <span class="font-normal text-slate-400">(optional if phone provided)</span></label>
                <input type="email" name="email" value="{{ old('email', $landlord->email) }}" @required($landlordRequired('email', false)) class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone <span class="font-normal text-slate-400">(optional if email provided)</span></label>
                <input type="text" name="phone" value="{{ old('phone', $landlord->phone) }}" @required($landlordRequired('phone', false)) placeholder="e.g. 0712345678" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        @include('property.agent.landlords.partials.profile_fields', ['landlordProfile' => $landlordProfile ?? null])

        <div class="flex flex-col sm:flex-row gap-2 pt-1">
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.landlords.show', $landlord, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</a>
        </div>
    </form>
</x-property.crud-shell>
