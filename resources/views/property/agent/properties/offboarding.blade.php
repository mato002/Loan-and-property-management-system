<x-property.workspace
    :title="'Offboarding: '.$property->name"
    subtitle="Safe property wind-down — no financial records are deleted."
    back-route="property.properties.show"
    :back-route-params="['property' => $property->id]"
    :show-workspace-tabs="false"
    :stats="[]"
    :columns="[]"
>
    @php
        $steps = [
            1 => 'Status check',
            2 => 'Lease wind-down',
            3 => 'Financial settlement',
            4 => 'Landlord detachment',
            5 => 'Archive property',
        ];
        $isReadOnly = $property->isManagementReadOnly();
        $isOffboarding = $property->isOffboarding();
        $canStart = $property->isManagementActive() && (auth()->user()?->hasPmPermission('property.offboarding.start') || auth()->user()?->hasPmPermission('properties.manage'));
        $canComplete = auth()->user()?->hasPmPermission('property.offboarding.complete');
        $canRestore = auth()->user()?->hasPmPermission('property.archive.restore');
        $canOverride = auth()->user()?->hasPmPermission('property.archive.override');
    @endphp

    <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">Management status</p>
                <p class="text-xs text-slate-500">{{ $property->managementStatusLabel() }}
                    @if ($property->archived_at)
                        · Archived {{ $property->archived_at->format('Y-m-d') }}
                        @if ($property->archivedByUser)
                            by {{ $property->archivedByUser->name }}
                        @endif
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('property.properties.offboarding.handover_export', $property, false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export handover pack</a>
                @if ($canRestore && ($isReadOnly || $isOffboarding))
                    <form method="post" action="{{ route('property.properties.offboarding.restore', $property, false) }}" data-swal-confirm="Restore this property to active management?">
                        @csrf
                        <button type="submit" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Restore to active</button>
                    </form>
                @endif
            </div>
        </div>
        @if ($property->management_end_reason)
            <p class="mt-2 text-sm text-slate-600"><span class="font-medium">Reason:</span> {{ $property->management_end_reason }}</p>
        @endif
        @if ($property->offboarding_notes)
            <p class="mt-1 text-sm text-slate-600 whitespace-pre-line">{{ $property->offboarding_notes }}</p>
        @endif
    </div>

    @if ($canStart && $property->isManagementActive())
        <form method="post" action="{{ route('property.properties.offboarding.start', $property, false) }}" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
            @csrf
            <h2 class="text-sm font-semibold text-amber-900">Start offboarding</h2>
            <p class="mt-1 text-xs text-amber-800">Moves the property into wind-down mode. Existing invoices, payments, and history stay intact.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-700">Reason</label>
                    <input type="text" name="management_end_reason" value="{{ old('management_end_reason', $property->management_end_reason) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Landlord quit management" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-700">Notes</label>
                    <textarea name="offboarding_notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('offboarding_notes', $property->offboarding_notes) }}</textarea>
                </div>
            </div>
            <button type="submit" class="mt-3 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Start offboarding</button>
        </form>
    @endif

    <nav class="mb-6 flex flex-wrap gap-2">
        @foreach ($steps as $num => $label)
            <a href="{{ route('property.properties.offboarding', ['property' => $property->id, 'step' => $num], false) }}"
               class="rounded-full px-3 py-1 text-xs font-semibold {{ $step === $num ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                {{ $num }}. {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($step === 1)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Active leases', $check['active_leases_count'] ?? 0],
                ['Open invoices', $check['open_invoice_count'] ?? 0],
                ['Open AR (KES)', number_format((float) ($check['open_ar'] ?? 0), 2)],
                ['Pending payments', $check['pending_payments'] ?? 0],
                ['Open maintenance', $check['open_maintenance'] ?? 0],
                ['Active utility accounts', $check['active_utility_accounts'] ?? 0],
                ['Pending notices', $check['pending_notices'] ?? 0],
                ['Landlord payable (KES)', number_format((float) ($check['landlord_payable_balance'] ?? 0), 2)],
                ['Unmatched bank payments', $check['unmatched_payments'] ?? 0],
            ] as [$label, $value])
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($step === 2)
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Lease</th>
                        <th class="px-4 py-3">Tenant</th>
                        <th class="px-4 py-3">Units</th>
                        <th class="px-4 py-3">Rent</th>
                        <th class="px-4 py-3">End date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($check['active_leases'] ?? collect()) as $lease)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3">#{{ $lease->id }}</td>
                            <td class="px-4 py-3">{{ $lease->pmTenant?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $lease->units->pluck('label')->join(', ') }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $lease->monthly_rent, 2) }}</td>
                            <td class="px-4 py-3">{{ $lease->end_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if (auth()->user()?->hasPmPermission('leases.manage'))
                                    <form method="post" action="{{ route('property.leases.terminate', $lease, false) }}" class="inline" data-swal-confirm="Terminate this lease now?">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-rose-600 hover:underline">Terminate</button>
                                    </form>
                                    <form method="post" action="{{ route('property.properties.offboarding.schedule_lease', ['property' => $property->id, 'lease' => $lease->id], false) }}" class="inline-flex items-center gap-1 mt-1">
                                        @csrf
                                        <input type="date" name="end_date" class="rounded border border-slate-300 px-2 py-1 text-xs" min="{{ now()->toDateString() }}" />
                                        <button type="submit" class="text-xs text-blue-600 hover:underline">Schedule</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No active leases.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($step === 3)
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Tenant open balances</h3>
                <p class="mt-1 text-2xl font-bold text-slate-900">KES {{ number_format((float) ($check['open_ar'] ?? 0), 2) }}</p>
                <p class="text-xs text-slate-500">{{ $check['open_invoice_count'] ?? 0 }} open invoice(s)</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Landlord ledger / payable</h3>
                <p class="mt-1 text-2xl font-bold text-slate-900">KES {{ number_format((float) ($check['landlord_payable_balance'] ?? 0), 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Suspense / unmatched</h3>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $check['unmatched_payments'] ?? 0 }} unmatched bank payment(s)</p>
                <p class="text-xs text-slate-500">Pending tenant payments in queue: {{ $check['pending_payments'] ?? 0 }}</p>
            </div>
        </div>
    @endif

    @if ($step === 4)
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Linked landlords</h3>
            @if (! ($canDetach['allowed'] ?? false))
                <ul class="mt-2 list-disc pl-5 text-sm text-amber-800">
                    @foreach (($canDetach['reasons'] ?? []) as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            @endif
            <ul class="mt-3 space-y-2">
                @forelse ($property->landlords as $landlord)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2">
                        <span class="text-sm text-slate-800">{{ $landlord->name }} <span class="text-slate-500">({{ $landlord->email }})</span></span>
                        @if (auth()->user()?->hasPmPermission('properties.manage'))
                            <form method="post" action="{{ route('property.properties.landlords.detach', absolute: false) }}" data-swal-confirm="Detach this landlord?">
                                @csrf
                                <input type="hidden" name="property_id" value="{{ $property->id }}" />
                                <input type="hidden" name="user_id" value="{{ $landlord->id }}" />
                                @if ($canOverride)
                                    <input type="hidden" name="admin_override" value="1" />
                                @endif
                                <button type="submit" class="text-xs font-medium text-rose-600 hover:underline" @disabled(! ($canDetach['allowed'] ?? false))>Detach</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No landlords linked.</li>
                @endforelse
            </ul>
        </div>
        @if ($isOffboarding || $isReadOnly)
            <form method="post" action="{{ route('property.properties.offboarding.notes', $property, false) }}" class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
                @csrf
                @method('PATCH')
                <label class="block text-xs font-medium text-slate-700">Update offboarding reason / notes</label>
                <input type="text" name="management_end_reason" value="{{ $property->management_end_reason }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <textarea name="offboarding_notes" rows="2" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $property->offboarding_notes }}</textarea>
                <button type="submit" class="mt-2 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Save notes</button>
            </form>
        @endif
    @endif

    @if ($step === 5)
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm max-w-xl">
            <h3 class="text-sm font-semibold text-slate-900">Archive property</h3>
            <p class="mt-1 text-sm text-slate-600">The property and its units stay in the system for lease, invoice, and statement history. They are hidden from the active Properties and Units lists by default — tick “Include archived” on those pages to review them.</p>
            @if (! ($canArchive['allowed'] ?? false))
                <ul class="mt-3 list-disc pl-5 text-sm text-amber-800">
                    @foreach (($canArchive['reasons'] ?? []) as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($canComplete && ! $isReadOnly)
                <form method="post" action="{{ route('property.properties.offboarding.archive', $property, false) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Final status</label>
                        <select name="final_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="archived">Archived (hidden from active workspace)</option>
                            <option value="ended_management">Ended management (read-only)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Reason (optional)</label>
                        <input type="text" name="management_end_reason" value="{{ $property->management_end_reason }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    @if ($canOverride)
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" name="admin_override" value="1" class="rounded border-slate-300" />
                            Admin override (bypass settlement gates)
                        </label>
                    @endif
                    <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900" data-swal-confirm="Archive this property? Financial history will be preserved.">
                        Complete offboarding
                    </button>
                </form>
            @elseif ($isReadOnly)
                <p class="mt-3 text-sm text-emerald-700 font-medium">This property is already {{ strtolower($property->managementStatusLabel()) }}.</p>
            @endif
        </div>
    @endif
</x-property.workspace>
