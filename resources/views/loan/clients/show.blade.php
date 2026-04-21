<x-loan-layout>
    <x-loan.page
        title="{{ $loan_client->full_name }}"
        subtitle="{{ $loan_client->kind === 'lead' ? 'Lead' : 'Client' }} · {{ $loan_client->client_number }}"
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.interactions.for_client.create', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Log interaction
            </a>
            <a href="{{ route('loan.clients.edit', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Edit
            </a>
            <a href="{{ $loan_client->kind === 'lead' ? route('loan.clients.leads') : route('loan.clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back
            </a>
        </x-slot>

        @if ($loan_client->kind === 'lead')
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <span>This record is a <strong>lead</strong> (status: {{ $loan_client->lead_status ?? 'new' }}).</span>
                <form method="post" action="{{ route('loan.clients.leads.convert', $loan_client) }}" class="shrink-0" data-swal-confirm="Convert this lead to a client?">
                    @csrf
                    <x-primary-button type="submit" class="bg-emerald-700 hover:bg-emerald-800">Convert to client</x-primary-button>
                </form>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-slate-700 mb-4">Profile</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div><dt class="text-slate-500">Phone</dt><dd class="text-slate-900"><x-phone-link :value="$loan_client->phone" /></dd></div>
                        <div><dt class="text-slate-500">Email</dt><dd class="text-slate-900">{{ $loan_client->email ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">ID / registration</dt><dd class="text-slate-900">{{ $loan_client->id_number ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Branch</dt><dd class="text-slate-900">{{ $loan_client->branch ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Address</dt><dd class="text-slate-900 whitespace-pre-line">{{ $loan_client->address ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Assigned officer</dt><dd class="text-slate-900">{{ $loan_client->assignedEmployee?->full_name ?? '—' }}</dd></div>
                        @if ($loan_client->kind === 'client')
                            <div><dt class="text-slate-500">Client status</dt><dd class="text-slate-900">{{ $loan_client->client_status }}</dd></div>
                            @if ($loan_client->converted_at)
                                <div class="sm:col-span-2"><dt class="text-slate-500">Converted from lead</dt><dd class="text-slate-900">{{ $loan_client->converted_at->format('M j, Y H:i') }}</dd></div>
                            @endif
                        @endif
                    </dl>

                    @php
                        $guarantorAttrs = [
                            'guarantor_1_full_name', 'guarantor_1_phone', 'guarantor_1_id_number', 'guarantor_1_relationship', 'guarantor_1_address',
                            'guarantor_2_full_name', 'guarantor_2_phone', 'guarantor_2_id_number', 'guarantor_2_relationship', 'guarantor_2_address',
                        ];
                        $hasGuarantorInfo = collect($guarantorAttrs)->contains(fn ($a) => filled($loan_client->{$a}));
                    @endphp
                    @if ($hasGuarantorInfo)
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Guarantors</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                @if (collect(['guarantor_1_full_name', 'guarantor_1_phone', 'guarantor_1_id_number', 'guarantor_1_relationship', 'guarantor_1_address'])->contains(fn ($a) => filled($loan_client->{$a})))
                                    <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-4 space-y-2">
                                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Guarantor 1</p>
                                        @if (filled($loan_client->guarantor_1_full_name))<div><span class="text-slate-500">Name</span><p class="text-slate-900">{{ $loan_client->guarantor_1_full_name }}</p></div>@endif
                                        @if (filled($loan_client->guarantor_1_phone))<div><span class="text-slate-500">Phone</span><p class="text-slate-900"><x-phone-link :value="$loan_client->guarantor_1_phone" /></p></div>@endif
                                        @if (filled($loan_client->guarantor_1_id_number))<div><span class="text-slate-500">ID / registration</span><p class="text-slate-900">{{ $loan_client->guarantor_1_id_number }}</p></div>@endif
                                        @if (filled($loan_client->guarantor_1_relationship))<div><span class="text-slate-500">Relationship</span><p class="text-slate-900">{{ $loan_client->guarantor_1_relationship }}</p></div>@endif
                                        @if (filled($loan_client->guarantor_1_address))<div class="sm:col-span-2"><span class="text-slate-500">Address</span><p class="text-slate-900 whitespace-pre-line">{{ $loan_client->guarantor_1_address }}</p></div>@endif
                                    </div>
                                @endif
                                @if (collect(['guarantor_2_full_name', 'guarantor_2_phone', 'guarantor_2_id_number', 'guarantor_2_relationship', 'guarantor_2_address'])->contains(fn ($a) => filled($loan_client->{$a})))
                                    <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-4 space-y-2">
                                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Guarantor 2</p>
                                        @if (filled($loan_client->guarantor_2_full_name))<div><span class="text-slate-500">Name</span><p class="text-slate-900">{{ $loan_client->guarantor_2_full_name }}</p></div>@endif
                                        @if (filled($loan_client->guarantor_2_phone))<div><span class="text-slate-500">Phone</span><p class="text-slate-900"><x-phone-link :value="$loan_client->guarantor_2_phone" /></p></div>@endif
                                        @if (filled($loan_client->guarantor_2_id_number))<div><span class="text-slate-500">ID / registration</span><p class="text-slate-900">{{ $loan_client->guarantor_2_id_number }}</p></div>@endif
                                        @if (filled($loan_client->guarantor_2_relationship))<div><span class="text-slate-500">Relationship</span><p class="text-slate-900">{{ $loan_client->guarantor_2_relationship }}</p></div>@endif
                                        @if (filled($loan_client->guarantor_2_address))<div class="sm:col-span-2"><span class="text-slate-500">Address</span><p class="text-slate-900 whitespace-pre-line">{{ $loan_client->guarantor_2_address }}</p></div>@endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($loan_client->notes)
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Notes</p>
                            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $loan_client->notes }}</p>
                        </div>
                    @endif
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-700">Recent interactions</h2>
                        <a href="{{ route('loan.clients.interactions') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">View all</a>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($loan_client->interactions->take(8) as $interaction)
                            <li class="px-5 py-3 text-sm">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="font-medium text-slate-900">{{ ucfirst($interaction->interaction_type) }}</span>
                                    <span class="text-xs text-slate-500">{{ $interaction->interacted_at->format('M j, Y H:i') }}</span>
                                </div>
                                @if ($interaction->subject)
                                    <p class="text-slate-700 mt-0.5">{{ $interaction->subject }}</p>
                                @endif
                                @if ($interaction->notes)
                                    <p class="text-slate-600 text-xs mt-1 whitespace-pre-line">{{ $interaction->notes }}</p>
                                @endif
                                <p class="text-xs text-slate-400 mt-1">By {{ $interaction->user?->name ?? 'System' }}</p>
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-slate-500 text-sm">No interactions logged yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="space-y-6">
                @if ($loan_client->kind === 'client' && $loan_client->defaultGroups->isNotEmpty())
                    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                        <h2 class="text-sm font-semibold text-slate-700 mb-3">Default groups</h2>
                        <ul class="space-y-2 text-sm">
                            @foreach ($loan_client->defaultGroups as $group)
                                <li>
                                    <a href="{{ route('loan.clients.default_groups.show', $group) }}" class="text-indigo-600 hover:text-indigo-500 font-medium">{{ $group->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-700 mb-3">Transfer history</h2>
                    @php $transfers = $loan_client->transfers()->with(['fromEmployee', 'toEmployee', 'transferredByUser'])->limit(10)->get(); @endphp
                    <ul class="space-y-3 text-xs text-slate-600">
                        @forelse ($transfers as $t)
                            <li class="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                                <p>{{ $t->created_at->format('M j, Y') }} · {{ $t->transferredByUser?->name ?? 'User' }}</p>
                                <p>Branch: {{ $t->from_branch ?? '—' }} → {{ $t->to_branch ?? '—' }}</p>
                                <p>Officer: {{ $t->fromEmployee?->full_name ?? '—' }} → {{ $t->toEmployee?->full_name ?? '—' }}</p>
                                @if ($t->reason)<p class="mt-1 text-slate-500">{{ $t->reason }}</p>@endif
                            </li>
                        @empty
                            <li class="text-slate-500">No transfers recorded.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
