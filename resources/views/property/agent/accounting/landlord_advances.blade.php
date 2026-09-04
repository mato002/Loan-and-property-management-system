@php
    $showAdvanceModal = old('landlord_form') === 'advance';
    $showScheduleModal = old('landlord_form') === 'schedule'
        || request()->query('open') === 'schedule';
@endphp
<x-property.workspace
    title="Landlord advances & pay dates"
    subtitle="Record advance payments to landlords, set agreed pay days, and review all payment schedules."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="[]"
    :table-rows="[]"
    :show-search="false"
    :compact-list="true"
>
    <x-slot name="pageModalsAttributes"
        x-data="{!! \Illuminate\Support\Js::from([
            'showAdvanceModal' => $showAdvanceModal,
            'showScheduleModal' => $showScheduleModal,
        ]) !!}"
    ></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
            data-property-modal-open="showAdvanceModal"
            @click="showAdvanceModal = true"
        >
            <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
            <span>Record advance payment</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
            data-property-modal-open="showScheduleModal"
            @click="showScheduleModal = true"
        >
            <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
            <span>Set agreed pay day</span>
        </button>
        <a href="{{ route('property.accounting.payables.landlord_payouts') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">View payouts</a>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showAdvanceModal"
            close="showAdvanceModal = false"
            name="landlord-advance-payment"
            title="Record advance payment"
            max-width="3xl"
        >
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Pay a landlord before full collections are received. Posts to landlord ledger when marked paid.</p>
            <form method="post" action="{{ route('property.accounting.payables.landlord_advances.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="landlord_form" value="advance" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                        <select name="property_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                            <option value="">Select property</option>
                            @foreach ($properties as $property)
                                <option value="{{ $property->id }}" @selected((int) old('property_id', $filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                            @endforeach
                        </select>
                        @error('property_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
                        <select name="landlord_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                            <option value="">Select landlord</option>
                            @foreach ($landlords as $landlord)
                                <option value="{{ $landlord->id }}" @selected((int) old('landlord_id', $filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                            @endforeach
                        </select>
                        @error('landlord_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                        <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                        @error('amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agreed pay date</label>
                        <input type="date" name="agreed_pay_date" value="{{ old('agreed_pay_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                        @error('agreed_pay_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payment reference</label>
                        <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="M-Pesa / bank ref" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                        @error('payment_reference')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                        @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="mark_paid" value="1" @checked(old('mark_paid', '1')) class="rounded border-slate-300" />
                    Mark paid &amp; post to ledger immediately
                </label>
                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50" @click="showAdvanceModal = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Record advance</button>
                </div>
            </form>
        </x-property.modal>

        <x-property.modal
            show="showScheduleModal"
            close="showScheduleModal = false"
            name="landlord-agreed-pay-day"
            title="Set agreed pay day"
            max-width="2xl"
        >
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Recurring day of month when landlord expects remittance (1–28).</p>
            <form method="post" action="{{ route('property.accounting.payables.landlord_advances.schedule') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="landlord_form" value="schedule" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                        <select name="property_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                            <option value="">Select property</option>
                            @foreach ($properties as $property)
                                <option value="{{ $property->id }}" @selected((int) old('property_id', $filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                            @endforeach
                        </select>
                        @error('property_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
                        <select name="landlord_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                            <option value="">Select landlord</option>
                            @foreach ($landlords as $landlord)
                                <option value="{{ $landlord->id }}" @selected((int) old('landlord_id', $filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                            @endforeach
                        </select>
                        @error('landlord_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agreed pay day</label>
                        <select name="agreed_pay_day" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                            <option value="">Not set</option>
                            @for ($day = 1; $day <= 28; $day++)
                                <option value="{{ $day }}" @selected((int) old('agreed_pay_day') === $day)>{{ $day }}{{ in_array($day % 10, [1, 2, 3], true) && ! in_array($day, [11, 12, 13], true) ? match ($day % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd' } : 'th' }} of month</option>
                            @endfor
                        </select>
                        @error('agreed_pay_day')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                        <input type="text" name="agreed_pay_notes" value="{{ old('agreed_pay_notes') }}" placeholder="e.g. Pay after 5th once rent collected" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                        @error('agreed_pay_notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50" @click="showScheduleModal = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Save schedule</button>
                </div>
            </form>
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.landlord_advances') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All properties</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
                <select name="landlord_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All landlords</option>
                    @foreach ($landlords as $landlord)
                        <option value="{{ $landlord->id }}" @selected((int) ($filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Advance status</label>
                <select name="status" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach (['open' => 'Open', 'recovered' => 'Recovered', 'written_off' => 'Written off'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Property, landlord, ref…" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm w-44" />
            </div>
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 mt-5">Filter</button>
            <a href="{{ route('property.accounting.payables.landlord_advances') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 mt-5">Reset</a>
        </form>
    </x-slot>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if ($errors->any() && ! in_array(old('landlord_form'), ['advance', 'schedule'], true))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 dark:border-slate-700 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Agreed pay schedules</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">All property × landlord links and their next agreed pay date.</p>
            </div>
            <button
                type="button"
                class="text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                data-property-modal-open="showScheduleModal"
                @click="showScheduleModal = true"
            >+ Set pay day</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Property</th>
                        <th class="px-4 py-3">Landlord</th>
                        <th class="px-4 py-3">Ownership</th>
                        <th class="px-4 py-3">Agreed pay day</th>
                        <th class="px-4 py-3">Next pay date</th>
                        <th class="px-4 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedules as $schedule)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                {{ trim(($schedule['property_code'] !== '' ? '['.$schedule['property_code'].'] ' : '').($schedule['property_name'] ?? '')) }}
                            </td>
                            <td class="px-4 py-3">{{ $schedule['landlord_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($schedule['ownership_percent'] ?? 0), 1) }}%</td>
                            <td class="px-4 py-3">
                                @if ($schedule['agreed_pay_day'])
                                    {{ $schedule['agreed_pay_day'] }}<sup>{{ in_array($schedule['agreed_pay_day'] % 10, [1, 2, 3], true) && ! in_array($schedule['agreed_pay_day'], [11, 12, 13], true) ? match ($schedule['agreed_pay_day'] % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd' } : 'th' }}</sup>
                                @else
                                    <span class="text-slate-400">Not set</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $schedule['next_agreed_pay_label'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $schedule['agreed_pay_notes'] !== '' ? $schedule['agreed_pay_notes'] : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No landlord links match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 dark:border-slate-700 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Advance payment records</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">All logged advance payments with agreed dates and recovery status.</p>
            </div>
            <button
                type="button"
                class="text-xs font-semibold text-indigo-700 hover:text-indigo-800"
                data-property-modal-open="showAdvanceModal"
                @click="showAdvanceModal = true"
            >+ Record advance</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Recorded</th>
                        <th class="px-4 py-3">Property / Landlord</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Agreed pay date</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Paid at</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($advances as $advance)
                        @php
                            $statusBadge = match ($advance['advance_status'] ?? '') {
                                'open' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                                'recovered' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                'written_off' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-3">{{ $advance['recorded_at'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900 dark:text-white">{{ $advance['property_name'] ?? '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $advance['landlord_name'] ?? '' }}</div>
                                <div class="text-xs text-slate-400">{{ $advance['description'] ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($advance['amount'] ?? 0)) }}</td>
                            <td class="px-4 py-3">{{ $advance['agreed_pay_label'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ ($advance['payment_reference'] ?? '') !== '' ? $advance['payment_reference'] : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge }}">{{ ucfirst(str_replace('_', ' ', (string) ($advance['advance_status'] ?? 'open'))) }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $advance['paid_at'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if (($advance['advance_status'] ?? '') === 'open')
                                    <div class="flex flex-wrap gap-2">
                                        <form method="post" action="{{ route('property.accounting.payables.landlord_advances.recover', $advance['id']) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-emerald-700 hover:text-emerald-800 text-xs font-medium">Mark recovered</button>
                                        </form>
                                        <form method="post" action="{{ route('property.accounting.payables.landlord_advances.write_off', $advance['id']) }}" class="inline" onsubmit="return confirm('Write off this advance?')">
                                            @csrf
                                            <button type="submit" class="text-rose-700 hover:text-rose-800 text-xs font-medium">Write off</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No advance payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-property.workspace>
