<x-loan-layout>
    <x-loan.page title="Requisitions" subtitle="Internal purchase / payment requests with approval workflow.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.requisitions.create') }}" class="inline-flex items-center justify-center rounded-lg border border-[#2f4f4f] bg-white px-4 py-2 text-sm font-semibold text-[#2f4f4f] shadow-sm hover:bg-slate-50 transition-colors">
                <span class="mr-2 text-base leading-none">+</span> Create
            </a>
            @include('loan.accounting.partials.export_buttons')
        </x-slot>
        @include('loan.accounting.partials.flash')
        @error('status')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <select name="status" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                    @foreach(($statusOptions ?? []) as $key => $label)
                        <option value="{{ $key }}" @selected(($status ?? '') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="month" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                    <option value="">All months</option>
                    @foreach(($availableMonths ?? []) as $ym)
                        <option value="{{ $ym }}" @selected(($month ?? '') === (string) $ym)>{{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M Y') }}</option>
                    @endforeach
                </select>

                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>

                @if(($status ?? '') !== '' || ($month ?? '') !== '')
                    <a href="{{ route('loan.accounting.requisitions.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                @endif
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @forelse ($rows as $r)
                @php
                    $status = (string) $r->status;
                    $badge = match ($status) {
                        \App\Models\AccountingRequisition::STATUS_APPROVED => 'bg-emerald-600 text-white',
                        \App\Models\AccountingRequisition::STATUS_PENDING => 'bg-amber-500 text-white',
                        \App\Models\AccountingRequisition::STATUS_REJECTED => 'bg-rose-600 text-white',
                        \App\Models\AccountingRequisition::STATUS_PAID => 'bg-slate-800 text-white',
                        default => 'bg-slate-500 text-white',
                    };
                @endphp

                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-100">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs text-slate-500">
                                    From {{ $r->requestedByUser?->name ?? '—' }}
                                </div>
                                <div class="mt-1 font-semibold text-slate-800 truncate">
                                    {{ $r->title }}
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $badge }}">
                                {{ $status }}
                            </span>
                        </div>
                    </div>

                    <div class="px-4 py-3">
                        <div class="text-xs text-slate-500 mb-2">
                            <span class="font-mono">{{ $r->reference }}</span>
                            <span class="mx-2">•</span>
                            {{ optional($r->created_at)->format('d-m-Y, h:i a') }}
                        </div>

                        <div class="rounded-lg border border-slate-200 overflow-hidden">
                            <div class="grid grid-cols-12 bg-slate-50 text-[11px] font-semibold text-slate-600 uppercase tracking-wide">
                                <div class="col-span-7 px-2 py-1.5">Item</div>
                                <div class="col-span-2 px-2 py-1.5 text-right">Qty</div>
                                <div class="col-span-3 px-2 py-1.5 text-right">Cost</div>
                            </div>
                            <div class="text-sm">
                                <div class="grid grid-cols-12 border-t border-slate-100">
                                    <div class="col-span-7 px-2 py-2 text-slate-800 truncate">
                                        {{ $r->purpose ?: '—' }}
                                    </div>
                                    <div class="col-span-2 px-2 py-2 text-right text-slate-600 tabular-nums">1</div>
                                    <div class="col-span-3 px-2 py-2 text-right text-slate-800 tabular-nums font-medium">
                                        {{ number_format((float) $r->amount, 0) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-emerald-100/70 border-t border-slate-100">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-semibold text-emerald-900">
                                {{ $r->currency }} {{ number_format((float) $r->amount, 2) }}
                            </div>
                            <a href="{{ route('loan.accounting.requisitions.edit', $r) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                                Open
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-slate-200 bg-white px-6 py-10 text-center text-slate-500">
                    No requisitions found.
                </div>
            @endforelse
        </div>

        @if ($rows->hasPages())
            <div class="mt-5">{{ $rows->links() }}</div>
        @endif
    </x-loan.page>
</x-loan-layout>
