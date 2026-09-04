<div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm max-w-2xl">
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Profile</h3>
    <div class="mt-3 text-sm text-slate-700 dark:text-slate-200 space-y-2">
        <p><span class="text-slate-500 dark:text-slate-400">Name:</span> {{ $fieldOfficer->name }}</p>
        <p><span class="text-slate-500 dark:text-slate-400">Phone:</span> {{ $fieldOfficer->phone ?: '—' }}</p>
        <p>
            <span class="text-slate-500 dark:text-slate-400">Portal access:</span>
            @if ($fieldOfficer->portal_access)
                <span class="property-status-pill property-status-pill--occupied">Enabled</span>
            @else
                <span class="property-status-pill property-status-pill--vacant">Not enabled</span>
            @endif
        </p>
        @if ($fieldOfficer->agentUser)
            <p><span class="text-slate-500 dark:text-slate-400">Agent workspace:</span> {{ $fieldOfficer->agentUser->name }}</p>
        @endif
    </div>
    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Assign properties from the Properties tab or from each property&apos;s edit screen.</p>
</div>
