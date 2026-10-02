@php
    $matrix = $permissionMatrix ?? ['hasLogin' => false, 'roles' => collect(), 'selectedRoleIds' => [], 'groups' => collect()];
    $canEdit = (bool) ($canEditPermissions ?? false);
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80 sm:p-5">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Role and permission matrix</h3>
        <p class="mt-1 max-w-3xl text-xs text-slate-500 dark:text-slate-400">
            Inherit follows the selected roles. Allow adds a permission this person does not get from a role. Deny blocks it even when a role includes it.
            HR can change this first, then the company agent, then a super admin. HR can only grant permissions they already hold.
        </p>
    </div>

    @if (! ($matrix['hasLogin'] ?? false))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Send a portal login before assigning roles or permissions. The login is created from the Access tab.
        </div>
    @else
        <form method="post" action="{{ route('property.hr.employees.permissions.update', $employee) }}" class="space-y-4" data-turbo-frame="_top">
            @csrf
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80 sm:p-5">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Roles</h4>
                <p class="mt-1 text-xs text-slate-500">A role is the starting set. The matrix below can add or remove individual permissions.</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($matrix['roles'] as $role)
                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:text-slate-100">
                            <input
                                type="checkbox"
                                name="role_ids[]"
                                value="{{ $role->id }}"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                                @checked(in_array((int) $role->id, $matrix['selectedRoleIds'] ?? [], true))
                                @disabled(! $canEdit)
                            />
                            <span>{{ $role->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">No property roles are set up yet.</p>
                    @endforelse
                </div>
            </div>

            @forelse ($matrix['groups'] as $group => $rows)
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-600 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-300">
                        {{ str_replace('_', ' ', (string) $group) }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-700">
                                    <th class="px-4 py-2 font-semibold">Permission</th>
                                    <th class="px-3 py-2 font-semibold">From role</th>
                                    <th class="px-3 py-2 font-semibold">This employee</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <div class="font-medium text-slate-900 dark:text-white">{{ $row['name'] }}</div>
                                            <div class="font-mono text-[11px] text-slate-400">{{ $row['key'] }}</div>
                                            @if ($row['description'] !== '')
                                                <div class="mt-0.5 text-xs text-slate-500">{{ $row['description'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                            {{ $row['from_role'] ? 'Included' : 'Not included' }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <select
                                                name="effects[{{ $row['id'] }}]"
                                                class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                                                @disabled(! $canEdit)
                                            >
                                                <option value="inherit" @selected($row['effect'] === 'inherit')>Inherit</option>
                                                <option value="allow" @selected($row['effect'] === 'allow') @disabled(! ($row['can_grant'] ?? false) && $row['effect'] !== 'allow')>Allow</option>
                                                <option value="deny" @selected($row['effect'] === 'deny')>Deny</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">No permissions are defined yet.</div>
            @endforelse

            @if ($canEdit)
                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save role and permissions</button>
                </div>
            @else
                <p class="text-xs text-slate-500">You can view this matrix. HR, the company agent, or a super admin can change it.</p>
            @endif
        </form>
    @endif
</div>
