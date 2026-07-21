<div class="py-6">
    <div class="mx-auto max-w-6xl space-y-5 sm:px-6 lg:px-8">
        {{-- Account summary --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Role</p>
                <p class="mt-1 text-base font-semibold text-[#0f2744]">{{ $roleLabel ?? 'User' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Email status</p>
                <p class="mt-1 text-base font-semibold {{ !empty($user?->email_verified_at) ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ !empty($user?->email_verified_at) ? 'Verified' : 'Unverified' }}
                </p>
                @if (!empty($user?->email_verified_at))
                    <p class="mt-0.5 text-xs text-slate-500">{{ optional($user->email_verified_at)->format('M j, Y') }}</p>
                @endif
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Active devices</p>
                <p class="mt-1 text-base font-semibold text-[#0f2744]">{{ isset($activeDevices) ? $activeDevices->count() : 0 }}</p>
            </div>
        </div>

        @if (($activeSystem ?? 'loan') === 'loan')
        @php
            $assignedLoanRoleName = method_exists($user, 'activeLoanAccessRole')
                ? optional($user->activeLoanAccessRole())->name
                : null;
            $effectiveLoanRole = method_exists($user, 'effectiveLoanRole')
                ? $user->effectiveLoanRole()
                : '';
            $loanPermissionKeys = method_exists($user, 'loanPermissionKeys')
                ? collect($user->loanPermissionKeys())->filter()->values()->all()
                : [];
            $permissionCount = count($loanPermissionKeys);
            $groupedPermissions = collect($loanPermissionKeys)
                ->groupBy(function (string $key): string {
                    $segment = explode('.', $key)[0] ?? 'other';

                    return ucwords(str_replace(['_', '-'], ' ', $segment));
                })
                ->sortKeys();
            $accountStatus = ! empty($user?->email_verified_at) ? 'Active' : 'Pending verification';
        @endphp

        <div
            class="rounded-lg border border-slate-200 bg-white shadow-sm"
            x-data="{ showPermissions: false, permissionQuery: '' }"
        >
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h3 class="text-sm font-bold text-[#0f2744]">Access &amp; permissions</h3>
                <p class="mt-0.5 text-xs text-slate-500">Loan module role and permission summary.</p>
            </div>

            <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4 sm:p-5">
                <div class="rounded-md border border-slate-100 bg-slate-50/80 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Assigned role</p>
                    <p class="mt-1 text-sm font-semibold text-[#0f2744]">{{ $assignedLoanRoleName ?: 'None' }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50/80 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Base role</p>
                    <p class="mt-1 text-sm font-semibold text-[#0f2744]">{{ $effectiveLoanRole !== '' ? ucfirst($effectiveLoanRole) : 'None' }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50/80 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Permissions</p>
                    <p class="mt-1 text-sm font-semibold text-[#0f2744]">{{ $permissionCount }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50/80 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Account status</p>
                    <p class="mt-1 text-sm font-semibold {{ $accountStatus === 'Active' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $accountStatus }}</p>
                </div>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
                <button
                    type="button"
                    @click="showPermissions = ! showPermissions"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-[#1e4d6b] hover:text-[#163a52]"
                >
                    <svg class="h-4 w-4 transition" :class="showPermissions ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <span x-text="showPermissions ? 'Hide permissions' : 'View permissions'"></span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $permissionCount }}</span>
                </button>

                <div
                    x-show="showPermissions"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-cloak
                    class="mt-4 space-y-3"
                >
                    @if ($permissionCount > 0)
                        <div>
                            <label for="permission-search" class="sr-only">Search permissions</label>
                            <input
                                id="permission-search"
                                type="search"
                                x-model="permissionQuery"
                                placeholder="Search permissions…"
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-[#1e4d6b] focus:outline-none focus:ring-1 focus:ring-[#1e4d6b]"
                            />
                        </div>

                        <div class="max-h-80 overflow-y-auto rounded-md border border-slate-200">
                            <table class="min-w-full text-left text-xs">
                                <thead class="sticky top-0 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Module</th>
                                        <th class="px-3 py-2">Permission</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($groupedPermissions as $moduleName => $keys)
                                        @foreach ($keys as $permissionKey)
                                            <tr
                                                x-show="'{{ $permissionKey }}'.toLowerCase().includes(permissionQuery.toLowerCase()) || '{{ strtolower($moduleName) }}'.includes(permissionQuery.toLowerCase()) || permissionQuery === ''"
                                                class="hover:bg-slate-50/80"
                                            >
                                                <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-600">{{ $moduleName }}</td>
                                                <td class="px-3 py-2 font-mono text-[11px] text-slate-700 break-all">{{ $permissionKey }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">No explicit custom permissions assigned to this account.</p>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="max-w-2xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="max-w-2xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-[#0f2744]">Active devices</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Review signed-in sessions and revoke unknown devices.</p>
                </div>
                <form method="post" action="{{ route('profile.devices.others.destroy') }}" class="inline" data-swal-confirm="Sign out all other devices?">
                    @csrf
                    @method('delete')
                    <button type="submit" class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Sign out other devices</button>
                </form>
            </div>

            @if (session('status') === 'device-removed')
                <p class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Device removed successfully.</p>
            @elseif (session('status') === 'devices-cleared')
                <p class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">All other devices were signed out.</p>
            @elseif (session('status') === 'device-current')
                <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Current device cannot be removed from this list.</p>
            @elseif (session('status') === 'device-unavailable')
                <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Device management is unavailable because sessions are not stored in database.</p>
            @endif

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                <div class="md:hidden divide-y divide-slate-100">
                    @forelse (($activeDevices ?? collect()) as $device)
                        <div class="p-4">
                            <div class="text-sm font-medium text-slate-900 break-words">{{ $device->user_agent }}</div>
                            @if ($device->is_current)
                                <span class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-200">Current</span>
                            @endif
                            <div class="mt-2 space-y-0.5 text-xs text-slate-600">
                                <p><span class="font-medium text-slate-700">IP:</span> {{ $device->ip }}</p>
                                <p><span class="font-medium text-slate-700">Last active:</span> {{ $device->last_seen > 0 ? \Carbon\Carbon::createFromTimestamp($device->last_seen)->diffForHumans() : 'Unknown' }}</p>
                            </div>
                            <div class="mt-2">
                                @if (! $device->is_current)
                                    <form method="post" action="{{ route('profile.devices.destroy', $device->id) }}" class="inline" data-swal-confirm="Remove this active device session?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-slate-500">No active device sessions found.</div>
                    @endforelse
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Device</th>
                                <th class="px-4 py-2.5">IP address</th>
                                <th class="px-4 py-2.5">Last active</th>
                                <th class="px-4 py-2.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse (($activeDevices ?? collect()) as $device)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-4 py-2.5 text-slate-700">
                                        <div class="font-medium text-slate-900">{{ $device->user_agent }}</div>
                                        @if ($device->is_current)
                                            <span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-200">Current</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $device->ip }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">
                                        {{ $device->last_seen > 0 ? \Carbon\Carbon::createFromTimestamp($device->last_seen)->diffForHumans() : 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        @if (! $device->is_current)
                                            <form method="post" action="{{ route('profile.devices.destroy', $device->id) }}" class="inline" data-swal-confirm="Remove this active device session?">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No active device sessions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-red-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="max-w-2xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
