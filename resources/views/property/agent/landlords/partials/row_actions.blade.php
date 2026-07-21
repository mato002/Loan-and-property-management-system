@php
    /** @var \App\Models\User $u */
    $props = $u->landlordProperties ?? collect();
@endphp
<div class="relative inline-block text-left w-full sm:w-auto" data-row-ignore-click>
    <details class="group">
        <summary class="list-none cursor-pointer inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50">
            Actions <span class="text-slate-400 ml-1">▼</span>
        </summary>
        <div class="absolute right-0 z-30 mt-1 w-48 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-gray-800">
            <a href="{{ route('property.landlords.show', ['landlord' => $u->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2.5 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/40">View</a>
            @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
                <a href="{{ route('property.landlords.edit', ['landlord' => $u->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit</a>
            @endif
            <a href="{{ route('property.landlords.statement', ['landlord' => $u->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2.5 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40">Statement</a>
            <a href="{{ route('property.financials.owner_balances', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Owner balances</a>
            <a href="{{ route('property.financials.commission', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2.5 text-xs text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/40">Commission</a>
            @if (auth()->check() && auth()->user()?->hasPmPermission('users.impersonate'))
                <form
                    method="post"
                    action="{{ route('property.landlords.impersonate', ['landlord' => $u->id], false) }}"
                    data-turbo="false"
                    data-swal-title="Login as landlord?"
                    data-swal-confirm="You will temporarily view the portal as this landlord. Use “Stop impersonating” to return."
                    data-swal-confirm-text="Yes, continue"
                >
                    @csrf
                    <button type="submit" class="block w-full px-3 py-2.5 text-left text-xs text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-amber-950/40">Login as</button>
                </form>
            @endif
            @if ($props->isNotEmpty())
                <a href="{{ route('property.properties.show', ['property' => $props->first()->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Open property</a>
            @endif
            <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="block px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Adjust links</a>
            @if (trim((string) ($u->email ?? '')) !== '')
                <a href="mailto:{{ $u->email }}" class="block px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Email</a>
            @endif
        </div>
    </details>
</div>
