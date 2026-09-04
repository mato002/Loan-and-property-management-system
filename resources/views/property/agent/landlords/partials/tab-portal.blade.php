@php
    $portal = $portalAccess ?? [];
    $hasPortal = (bool) ($portal['has_portal_role'] ?? false);
@endphp

<div id="landlord-portal-access" class="property-compact-panel rounded-xl sm:rounded-2xl border {{ $hasPortal ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/20' }} p-4 shadow-sm w-full min-w-0">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Landlord portal access</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                @if ($hasPortal)
                    Credentials are sent by email and/or SMS when you onboard or use <strong>Reset &amp; send login</strong>.
                @else
                    This user is not set up as a landlord portal account. Edit the landlord profile to enable portal access.
                @endif
            </p>
        </div>
        @if ($hasPortal && auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            @php $resendTarget = trim((string) ($landlord->email ?? '')) ?: trim((string) ($landlord->phone ?? '')); @endphp
            <form method="post" action="{{ route('property.landlords.resend_portal_login', $landlord, false) }}" data-turbo-frame="_top" data-swal-title="Reset portal password?" data-swal-confirm="Generate a new temporary password and send it to {{ $resendTarget }}?" data-swal-confirm-text="Yes, reset &amp; send" class="w-full sm:w-auto shrink-0">
                @csrf
                <button type="submit" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-900">Reset &amp; send login</button>
            </form>
        @endif
    </div>
    @if ($hasPortal)
        @php
            $freshCreds = $portalCredentials ?? null;
            if (! is_array($freshCreds)) {
                $freshCreds = session('landlord_portal_credentials');
            }
            if (! is_array($freshCreds)) {
                $freshCreds = session('landlord_portal_credentials_pending_'.(int) $landlord->id);
            }
            $showFreshPassword = is_array($freshCreds)
                && (int) ($freshCreds['landlord_id'] ?? 0) === (int) $landlord->id
                && ! empty($freshCreds['temporary_password']);
        @endphp
        @if ($showFreshPassword)
            <div class="mt-3 rounded-lg border border-emerald-400 bg-emerald-100/80 p-3 dark:border-emerald-700 dark:bg-emerald-900/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">New temporary password — copy now</p>
                <p class="mt-1 text-xs text-emerald-900/80">{{ $freshCreds['delivery_summary'] ?? 'Sent when delivery succeeded.' }}</p>
                <input type="text" readonly value="{{ $freshCreds['temporary_password'] }}" class="mt-2 w-full rounded-lg border border-emerald-300 bg-white px-3 py-2.5 font-mono text-base font-bold dark:bg-gray-900" />
            </div>
        @endif
        <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="rounded-lg bg-white/70 dark:bg-gray-900/40 p-3"><dt class="text-xs font-medium text-slate-500 uppercase">Login email</dt><dd class="mt-1 break-all">{{ $landlord->email ?: '—' }}</dd></div>
            <div class="rounded-lg bg-white/70 dark:bg-gray-900/40 p-3"><dt class="text-xs font-medium text-slate-500 uppercase">Login phone</dt><dd class="mt-1">{{ $landlord->phone ?: '—' }}</dd></div>
            <div class="rounded-lg bg-white/70 dark:bg-gray-900/40 p-3 sm:col-span-2"><dt class="text-xs font-medium text-slate-500 uppercase">Portal sign-in URL</dt><dd class="mt-1 break-all"><a href="{{ $portal['login_url'] ?? '#' }}" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">{{ $portal['login_url'] ?? '—' }}</a></dd></div>
        </dl>
        @if (auth()->check() && auth()->user()?->hasPmPermission('users.impersonate'))
            <form method="post" action="{{ route('property.landlords.impersonate', $landlord, false) }}" class="mt-4" data-swal-title="View as landlord?" data-swal-confirm="Open the landlord portal as {{ $landlord->name }}?" data-swal-confirm-text="Yes, open portal">
                @csrf
                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-800 hover:bg-indigo-100">View portal as landlord</button>
            </form>
        @endif
    @endif
</div>

@include('property.agent.landlords.partials.portal-credentials-banner', [
    'landlord' => $landlord,
    'portalCredentials' => $portalCredentials ?? null,
])
