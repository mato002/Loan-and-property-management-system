<x-property-layout>
    <x-slot name="header">Collections</x-slot>

    <x-property.page
        title="Collections overview"
        subtitle="Billed versus collected by charge type for {{ $periodLabel ?? now()->format('F Y') }}."
        workspace="collections"
    >
        <x-property.module-status label="Collections" class="mb-4" />

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $stat['value'] }}</p>
                    @if (! empty($stat['hint']))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $stat['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">By charge type</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Collected is paid against this month’s invoices. Outstanding is everything still open for that charge.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (($chargeSummaries ?? []) as $row)
                @php
                    $rate = $row['rate'];
                    $bar = $rate === null ? 0 : min(100, max(0, (float) $rate));
                    $received = (float) ($row['collected'] ?? 0);
                    $applied = (float) ($row['applied'] ?? 0);
                    $billed = (float) ($row['billed'] ?? 0);
                    $caughtUp = $billed <= 0.009 && $received > 0.009;
                    $tone = match (true) {
                        $caughtUp => 'bg-amber-500',
                        $rate === null => 'bg-slate-200',
                        $rate >= 95 => 'bg-emerald-500',
                        $rate >= 60 => 'bg-sky-500',
                        default => 'bg-rose-500',
                    };
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $row['label'] }}</h4>
                        <span class="text-xs font-semibold tabular-nums {{ $rate === null ? 'text-slate-400' : ($rate >= 95 ? 'text-emerald-700' : ($rate >= 60 ? 'text-sky-700' : 'text-rose-700')) }}">
                            {{ $rate === null ? '—' : number_format($rate, 1).'%' }}
                        </span>
                    </div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-full rounded-full {{ $tone }}" style="width: {{ $caughtUp ? 100 : $bar }}%"></div>
                    </div>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Billed</dt>
                            <dd class="tabular-nums font-medium text-slate-900 dark:text-slate-100">{{ \App\Services\Property\PropertyMoney::kes($billed) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Collected</dt>
                            <dd class="tabular-nums font-medium text-slate-900 dark:text-slate-100">{{ \App\Services\Property\PropertyMoney::kes($applied) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Outstanding</dt>
                            <dd class="tabular-nums font-medium text-slate-900 dark:text-slate-100">{{ \App\Services\Property\PropertyMoney::kes((float) $row['outstanding']) }}</dd>
                        </div>
                    </dl>
                    @if($received > 0.009)
                        <p class="mt-2 text-[11px] text-slate-500">Received this month {{ \App\Services\Property\PropertyMoney::kes($received) }}</p>
                    @endif
                    @if($caughtUp)
                        <p class="mt-2 text-[11px] text-amber-700">Catch-up on older invoices — nothing billed this month.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-property.page>
</x-property-layout>
