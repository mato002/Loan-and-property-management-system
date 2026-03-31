<x-property.workspace
    title="Bulk messaging"
    subtitle="Send bulk SMS now or schedule it. Uses the same provider + wallet as the Loan Bulk SMS module."
    back-route="property.communications.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    empty-title="No bulk jobs logged"
    empty-hint="Describe the segment and message plan below."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.communications.bulk', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All bulk logs</a>
                <a href="{{ route('property.communications.bulk', array_merge((array) ($filters ?? []), ['status' => 'queued']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Queued</a>
                <a href="{{ route('property.communications.bulk', array_merge((array) ($filters ?? []), ['status' => 'sent']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Sent</a>
                <a href="{{ route('property.communications.bulk.export', (array) ($filters ?? []), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
            </div>
        </div>

        <form method="get" action="{{ route('property.communications.bulk') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Segment, notes..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['sent', 'queued', 'failed'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ strtoupper($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.communications.bulk', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>

        <form method="post" action="{{ route('property.communications.bulk.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Send bulk SMS</h3>
                    <p class="text-xs text-slate-500 mt-1">Wallet: {{ $walletBalance ?? '0' }} {{ $currency ?? 'KES' }} · Cost/SMS: {{ number_format((float) ($costPerSms ?? 0.5), 2) }} {{ $currency ?? 'KES' }}</p>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-gray-900/40 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-slate-600 dark:text-slate-400 mr-1">Load recipients:</span>
                    <button type="button" data-segment="tenants" class="pm-load-recipients rounded border border-slate-300 dark:border-slate-600 px-2 py-1 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">Tenants</button>
                    <button type="button" data-segment="landlords" class="pm-load-recipients rounded border border-slate-300 dark:border-slate-600 px-2 py-1 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">Landlords</button>
                    <button type="button" data-segment="staff" class="pm-load-recipients rounded border border-slate-300 dark:border-slate-600 px-2 py-1 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">Staff</button>
                    <span id="pm-load-hint" class="text-[11px] text-slate-500 dark:text-slate-400 hidden">Loading…</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">This app tries to find phone numbers for the selected group and appends them below. Review before sending.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Segment label</label>
                <input type="text" name="segment_label" value="{{ old('segment_label') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. All tenants — Block A" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Recipients</label>
                <textarea id="pm-recipients" name="recipients" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="254712345678, 254723456789">{{ old('recipients') }}</textarea>
                @error('recipients')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Message</label>
                <textarea name="message" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('message') }}</textarea>
                @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Schedule at (optional)</label>
                <input type="datetime-local" name="schedule_at" value="{{ old('schedule_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('schedule_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Send / schedule</button>
        </form>
    </x-slot>
</x-property.workspace>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.pm-load-recipients');
    const recipientsEl = document.getElementById('pm-recipients');
    const hint = document.getElementById('pm-load-hint');
    async function loadPhones(type) {
        if (!recipientsEl) return;
        try {
            hint && (hint.classList.remove('hidden'), hint.textContent = 'Loading…');
            const url = "{{ route('property.communications.recipients') }}?type=" + encodeURIComponent(type);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (!json.ok) {
                alert(json.error || 'Failed to load recipients.');
                return;
            }
            if (!Array.isArray(json.phones) || json.phones.length === 0) {
                alert('No phone numbers found for ' + type + '.');
                return;
            }
            const existing = recipientsEl.value ? recipientsEl.value + '\n' : '';
            recipientsEl.value = existing + json.phones.join('\n');
        } catch (e) {
            console.error(e);
            alert('Failed to load recipients. Check your connection.');
        } finally {
            hint && (hint.textContent = 'Done', setTimeout(() => hint.classList.add('hidden'), 800));
        }
    }
    buttons.forEach(btn => {
        btn.addEventListener('click', () => loadPhones(btn.getAttribute('data-segment')));
    });
});
</script>