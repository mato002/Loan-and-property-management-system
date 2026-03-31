<x-property-layout>
    <x-slot name="header">Search</x-slot>

    <x-property.page
        title="Search"
        subtitle="Find tenants, units, properties, invoices and payments."
    >
        <form method="get" action="{{ route('property.search') }}" class="mb-5">
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="flex-1">
                    <label class="sr-only" for="q">Search</label>
                    <input
                        id="q"
                        name="q"
                        value="{{ $q }}"
                        placeholder="Search by tenant name, phone, unit label, property, invoice #, txn code…"
                        class="block w-full rounded-2xl border-slate-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        autofocus
                    />
                </div>
                <button class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">
                    Search
                </button>
            </div>
        </form>

        @if ($q === '')
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
                Type something above to search. Examples: <span class="font-semibold">0717…</span>, <span class="font-semibold">INV-000123</span>, <span class="font-semibold">Greenfield</span>, <span class="font-semibold">Unit A1</span>.
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-900">Tenants</h3>
                        <span class="text-xs font-bold text-slate-500">{{ $tenants->count() }}</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse ($tenants as $t)
                            <a href="{{ route('property.tenants.show', $t) }}" data-turbo-frame="property-main" class="block rounded-xl border border-slate-100 px-4 py-3 hover:bg-slate-50">
                                <div class="font-bold text-slate-900">{{ $t->name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $t->phone ?: '—' }}@if($t->account_number) • {{ $t->account_number }}@endif@if($t->email) • {{ $t->email }}@endif
                                </div>
                            </a>
                        @empty
                            <div class="text-sm text-slate-500">No tenants matched.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-900">Properties</h3>
                        <span class="text-xs font-bold text-slate-500">{{ $properties->count() }}</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse ($properties as $p)
                            <a href="{{ route('property.properties.show', $p) }}" data-turbo-frame="property-main" class="block rounded-xl border border-slate-100 px-4 py-3 hover:bg-slate-50">
                                <div class="font-bold text-slate-900">{{ $p->name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $p->code ?: '—' }}@if($p->city) • {{ $p->city }}@endif@if($p->address_line) • {{ $p->address_line }}@endif
                                </div>
                            </a>
                        @empty
                            <div class="text-sm text-slate-500">No properties matched.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-900">Units</h3>
                        <span class="text-xs font-bold text-slate-500">{{ $units->count() }}</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse ($units as $u)
                            <a href="{{ route('property.properties.units', ['q' => $u->label]) }}" data-turbo-frame="property-main" class="block rounded-xl border border-slate-100 px-4 py-3 hover:bg-slate-50">
                                <div class="font-bold text-slate-900">{{ $u->label }} <span class="text-xs font-semibold text-slate-500">({{ $u->property?->name ?? 'Property' }})</span></div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ ucfirst((string) $u->status) }} • {{ $u->unitTypeLabel() }} • Rent {{ number_format((float) $u->rent_amount, 2) }}
                                </div>
                            </a>
                        @empty
                            <div class="text-sm text-slate-500">No units matched.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-900">Invoices</h3>
                        <span class="text-xs font-bold text-slate-500">{{ $invoices->count() }}</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse ($invoices as $i)
                            <a href="{{ route('property.revenue.invoices', ['q' => $i->invoice_no]) }}" data-turbo-frame="property-main" class="block rounded-xl border border-slate-100 px-4 py-3 hover:bg-slate-50">
                                <div class="font-bold text-slate-900">{{ $i->invoice_no }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $i->tenant?->name ?? '—' }} • {{ $i->unit?->property?->name ?? '—' }}/{{ $i->unit?->label ?? '—' }} • {{ number_format((float) $i->amount, 2) }} • {{ ucfirst((string) $i->status) }}
                                </div>
                            </a>
                        @empty
                            <div class="text-sm text-slate-500">No invoices matched.</div>
                        @endforelse
                    </div>
                    <div class="mt-3 text-xs text-slate-400">
                        Tip: open the invoices page to apply payments.
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-900">Payments</h3>
                        <span class="text-xs font-bold text-slate-500">{{ $payments->count() }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                        @forelse ($payments as $p)
                            <a href="{{ route('property.revenue.payments') }}" data-turbo-frame="property-main" class="block rounded-xl border border-slate-100 px-4 py-3 hover:bg-slate-50">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-bold text-slate-900">PAY-{{ $p->id }}</div>
                                    <div class="text-xs font-bold text-slate-500">{{ number_format((float) $p->amount, 2) }}</div>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $p->tenant?->name ?? '—' }} • {{ $p->external_ref ?? '—' }} • {{ $p->paid_at?->format('Y-m-d H:i') ?? '—' }} • {{ ucfirst((string) $p->status) }}
                                </div>
                            </a>
                        @empty
                            <div class="text-sm text-slate-500">No payments matched.</div>
                        @endforelse
                    </div>
                    <div class="mt-3 text-xs text-slate-400">
                        Note: payments currently open the Payments page (agent view has no single-payment details screen yet).
                    </div>
                </div>
            </div>
        @endif
    </x-property.page>
</x-property-layout>

