    <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Billing readiness</h3>
            <form method="get" action="{{ route('property.revenue.utilities', absolute: false) }}" class="flex flex-wrap items-end gap-2">
                <label class="text-xs text-slate-600">Month</label>
                <input type="month" name="rr_month" value="{{ $billingReadiness['month'] ?? now()->format('Y-m') }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}" />
                <input type="hidden" name="charge_type" value="{{ $filters['charge_type'] ?? '' }}" />
                <input type="hidden" name="month" value="{{ $filters['month'] ?? '' }}" />
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'id' }}" />
                <input type="hidden" name="dir" value="{{ $filters['dir'] ?? 'desc' }}" />
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 30 }}" />
                <input type="hidden" name="wr_q" value="{{ $filters['wr_q'] ?? '' }}" />
                <input type="hidden" name="wr_month" value="{{ $filters['wr_month'] ?? '' }}" />
                <input type="hidden" name="wr_status" value="{{ $filters['wr_status'] ?? '' }}" />
                <input type="hidden" name="wr_property_id" value="{{ $filters['wr_property_id'] ?? 0 }}" />
                <input type="hidden" name="wr_per_page" value="{{ $filters['wr_per_page'] ?? 20 }}" />
                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">Check</button>
            </form>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <p class="text-xs text-slate-500">Water-enabled units</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ (int) ($billingReadiness['water_enabled_units'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <p class="text-xs text-slate-500">Recorded this month</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ (int) ($billingReadiness['recorded_units'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <p class="text-xs text-slate-500">Missing readings</p>
                <p class="mt-1 text-lg font-semibold text-rose-700">{{ collect($billingReadiness['missing'] ?? [])->count() }}</p>
            </div>
        </div>
        <div class="grid gap-3 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
                <h4 class="text-sm font-semibold text-slate-900">Missing units</h4>
                @if (collect($billingReadiness['missing'] ?? [])->isEmpty())
                    <p class="text-sm text-emerald-700">All water-enabled units have readings for this month.</p>
                @else
                    <ul class="space-y-1 text-sm text-slate-700">
                        @foreach (($billingReadiness['missing'] ?? []) as $row)
                            <li>{{ $row['property_name'] ?? '—' }} / {{ $row['unit_label'] ?? '—' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
                <h4 class="text-sm font-semibold text-slate-900">Usage anomalies</h4>
                @if (collect($billingReadiness['anomalies'] ?? [])->isEmpty())
                    <p class="text-sm text-emerald-700">No unusual usage patterns detected for this month.</p>
                @else
                    <ul class="space-y-1 text-sm text-slate-700">
                        @foreach (($billingReadiness['anomalies'] ?? []) as $row)
                            <li>
                                {{ $row['property_name'] ?? '—' }} / {{ $row['unit_label'] ?? '—' }}:
                                {{ number_format((float) ($row['units_used'] ?? 0), 3) }} units
                                @if ((float) ($row['avg_units_used'] ?? 0) > 0)
                                    (avg {{ number_format((float) $row['avg_units_used'], 3) }})
                                @endif
                                — {{ $row['reason'] ?? 'Check reading' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>