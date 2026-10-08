@php
    $matrix = $permissionMatrix ?? ['hasLogin' => false, 'roles' => collect(), 'selectedRoleIds' => [], 'groups' => collect()];
    $canEdit = (bool) ($canEditPermissions ?? false);
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80 sm:p-5">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Role and permission matrix</h3>
        <p class="mt-1 max-w-3xl text-xs text-slate-500 dark:text-slate-400">
            Tick a permission to give it to this person. Untick to take it away. A tick that already comes from the selected role stays with the role. An extra tick is only for this employee.
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
                @php
                    $groupBoxes = collect($rows)->filter(function (array $row) use ($canEdit): bool {
                        $locked = ! $canEdit || (! ($row['can_grant'] ?? false) && ! $row['from_role'] && ($row['effect'] ?? 'inherit') !== 'allow');

                        return ! $locked;
                    });
                    $groupAllTicked = $groupBoxes->isNotEmpty() && $groupBoxes->every(fn (array $row): bool => (bool) ($row['granted'] ?? false));
                @endphp
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-gray-800/80" data-perm-group>
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2 dark:border-slate-600 dark:bg-slate-900/60">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ str_replace('_', ' ', (string) $group) }}</h4>
                        @if ($canEdit && $groupBoxes->isNotEmpty())
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600" data-perm-group-all @checked($groupAllTicked) />
                                Tick all
                            </label>
                        @endif
                    </div>
                    <div class="grid gap-2 p-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($rows as $row)
                            @php
                                $locked = ! $canEdit || (! ($row['can_grant'] ?? false) && ! $row['from_role'] && ($row['effect'] ?? 'inherit') !== 'allow');
                            @endphp
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-600 {{ $locked ? 'opacity-60' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60' }}">
                                <input
                                    type="checkbox"
                                    name="granted[]"
                                    value="{{ $row['id'] }}"
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-emerald-600"
                                    @checked($row['granted'] ?? false)
                                    @disabled($locked)
                                />
                                <span class="min-w-0">
                                    <span class="block font-medium text-slate-900 dark:text-white">{{ $row['name'] }}</span>
                                    <span class="mt-0.5 block text-[11px] text-slate-500">
                                        @if ($row['from_role'])
                                            Included in role
                                        @elseif (($row['effect'] ?? 'inherit') === 'allow')
                                            Extra for this employee
                                        @else
                                            Not in the selected role
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">No permissions are defined yet.</div>
            @endforelse

            @if ($canEdit)
                <div class="sticky bottom-3 z-20 flex justify-end">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-emerald-700">Save role and permissions</button>
                </div>
            @else
                <p class="text-xs text-slate-500">You can view this matrix. HR, the company agent, or a super admin can change it.</p>
            @endif
            @if ($canEdit)
                <script>
                    document.currentScript.closest('form')?.querySelectorAll('[data-perm-group-all]').forEach((master) => {
                        master.addEventListener('change', () => {
                            master.closest('[data-perm-group]')?.querySelectorAll('input[name="granted[]"]:not(:disabled)').forEach((box) => {
                                box.checked = master.checked;
                            });
                        });
                    });
                </script>
            @endif
        </form>
    @endif
</div>
