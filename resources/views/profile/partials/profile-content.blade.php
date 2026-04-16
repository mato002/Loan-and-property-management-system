<div class="py-8">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-[#dbe8e4] bg-white p-5 shadow-[0_12px_28px_rgba(47,79,79,0.10)]">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $roleLabel ?? 'User' }}</p>
            </div>
            <div class="rounded-2xl border border-[#dbe8e4] bg-white p-5 shadow-[0_12px_28px_rgba(47,79,79,0.10)]">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email status</p>
                <p class="mt-2 text-lg font-semibold {{ !empty($user?->email_verified_at) ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ !empty($user?->email_verified_at) ? 'Verified' : 'Unverified' }}
                </p>
                @if (!empty($user?->email_verified_at))
                    <p class="mt-1 text-xs text-slate-500">Verified on {{ optional($user->email_verified_at)->format('M j, Y g:i a') }}</p>
                @endif
            </div>
            <div class="rounded-2xl border border-[#dbe8e4] bg-white p-5 shadow-[0_12px_28px_rgba(47,79,79,0.10)]">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active devices</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ isset($activeDevices) ? $activeDevices->count() : 0 }}</p>
            </div>
        </div>

        @php
            $assignedLoanRoleName = method_exists($user, 'activeLoanAccessRole')
                ? optional($user->activeLoanAccessRole())->name
                : null;
            $effectiveLoanRole = method_exists($user, 'effectiveLoanRole')
                ? $user->effectiveLoanRole()
                : '';
            $loanPermissionKeys = method_exists($user, 'loanPermissionKeys')
                ? $user->loanPermissionKeys()
                : [];
        @endphp
        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-5 shadow-[0_12px_28px_rgba(47,79,79,0.08)]">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-900">Effective Access Debug</h3>
                <span class="text-xs text-indigo-700">Use this to confirm HR/role access assignments</span>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-indigo-100 bg-white px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Assigned custom role</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $assignedLoanRoleName ?: 'None' }}</p>
                </div>
                <div class="rounded-xl border border-indigo-100 bg-white px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Effective base role</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $effectiveLoanRole !== '' ? ucfirst($effectiveLoanRole) : 'None' }}</p>
                </div>
                <div class="rounded-xl border border-indigo-100 bg-white px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Permission keys count</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ is_array($loanPermissionKeys) ? count($loanPermissionKeys) : 0 }}</p>
                </div>
            </div>
            <div class="mt-3 rounded-xl border border-indigo-100 bg-white p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Permission keys</p>
                @if (is_array($loanPermissionKeys) && count($loanPermissionKeys) > 0)
                    <p class="mt-1 text-xs text-slate-700 break-words">{{ implode(', ', $loanPermissionKeys) }}</p>
                @else
                    <p class="mt-1 text-xs text-slate-600">No explicit custom permissions found for this user.</p>
                @endif
            </div>
        </div>

        <div class="p-5 sm:p-8 bg-white shadow-[0_12px_28px_rgba(47,79,79,0.10)] ring-1 ring-[#dbe8e4] rounded-2xl">
            <div class="max-w-2xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="p-5 sm:p-8 bg-white shadow-[0_12px_28px_rgba(47,79,79,0.10)] ring-1 ring-[#dbe8e4] rounded-2xl">
            <div class="max-w-2xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="p-5 sm:p-8 bg-white shadow-[0_12px_28px_rgba(47,79,79,0.10)] ring-1 ring-[#dbe8e4] rounded-2xl">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Active Devices</h2>
                    <p class="mt-1 text-sm text-slate-600">Monitor your active sessions and remove any device you do not recognize.</p>
                </div>
                <form method="post" action="{{ route('profile.devices.others.destroy') }}" class="inline" data-swal-confirm="Sign out all other devices?">
                    @csrf
                    @method('delete')
                    <button type="submit" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sign out other devices</button>
                </form>
            </div>

            @if (session('status') === 'device-removed')
                <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Device removed successfully.</p>
            @elseif (session('status') === 'devices-cleared')
                <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">All other devices were signed out.</p>
            @elseif (session('status') === 'device-current')
                <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">Current device cannot be removed from this list.</p>
            @elseif (session('status') === 'device-unavailable')
                <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">Device management is unavailable because sessions are not stored in database.</p>
            @endif

            <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="md:hidden divide-y divide-slate-100">
                    @forelse (($activeDevices ?? collect()) as $device)
                        <div class="p-4">
                            <div class="font-medium text-slate-900 break-words">{{ $device->user_agent }}</div>
                            @if ($device->is_current)
                                <span class="mt-2 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Current device</span>
                            @endif
                            <div class="mt-3 space-y-1 text-sm text-slate-600">
                                <p><span class="font-semibold text-slate-700">IP:</span> {{ $device->ip }}</p>
                                <p><span class="font-semibold text-slate-700">Last active:</span> {{ $device->last_seen > 0 ? \Carbon\Carbon::createFromTimestamp($device->last_seen)->diffForHumans() : 'Unknown' }}</p>
                            </div>
                            <div class="mt-3">
                                @if (! $device->is_current)
                                    <form method="post" action="{{ route('profile.devices.destroy', $device->id) }}" class="inline" data-swal-confirm="Remove this active device session?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Remove</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-10 text-center text-slate-500">No active device sessions found.</div>
                    @endforelse
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Device</th>
                                <th class="px-4 py-3">IP address</th>
                                <th class="px-4 py-3">Last active</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse (($activeDevices ?? collect()) as $device)
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">
                                        <div class="font-medium text-slate-900">{{ $device->user_agent }}</div>
                                        @if ($device->is_current)
                                            <span class="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Current device</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $device->ip }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $device->last_seen > 0 ? \Carbon\Carbon::createFromTimestamp($device->last_seen)->diffForHumans() : 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if (! $device->is_current)
                                            <form method="post" action="{{ route('profile.devices.destroy', $device->id) }}" class="inline" data-swal-confirm="Remove this active device session?">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Remove</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-500">No active device sessions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="p-5 sm:p-8 bg-white shadow-[0_12px_28px_rgba(47,79,79,0.10)] ring-1 ring-[#f2d4d4] rounded-2xl">
            <div class="max-w-2xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
