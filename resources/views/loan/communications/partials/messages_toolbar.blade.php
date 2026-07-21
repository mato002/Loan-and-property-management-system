@php
    $exportQuery = (array) ($filters ?? []);
@endphp
<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mr-1">Quick filters</span>
        <a href="{{ route('loan.communications.messages', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All</a>
        <a href="{{ route('loan.communications.messages', ['period' => 'today'], absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Today</a>
        <a href="{{ route('loan.communications.messages', ['period' => 'week'], absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">This week</a>
        <a href="{{ route('loan.communications.messages', ['period' => 'month'], absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">This month</a>
        <a href="{{ route('loan.communications.messages', ['channel' => 'sms'], absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">SMS only</a>
        <a href="{{ route('loan.communications.messages', ['channel' => 'email'], absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Email only</a>
        <a href="{{ route('loan.communications.messages', ['status' => 'failed'], absolute: false) }}" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50" title="Unresolved failures only (hides rows already sent on retry)">Failed (needs action)</a>
        <a href="{{ route('loan.communications.messages', ['has_error' => 'yes'], absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">With errors</a>
        <a href="{{ route('loan.communications.messages', ['duplicates' => 'yes', 'status' => 'sent', 'channel' => 'sms', 'period' => 'today'], absolute: false) }}" class="rounded-lg border border-orange-400 px-3 py-1.5 text-xs font-semibold text-orange-800 hover:bg-orange-50 dark:text-orange-200 dark:hover:bg-orange-950/40">Duplicates today</a>
        <a href="{{ route('loan.communications.messages', ['status' => 'failed', 'channel' => 'sms', 'period' => 'today'], absolute: false) }}" class="rounded-lg border border-rose-400 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-50 dark:text-rose-200 dark:hover:bg-rose-950/40">Failed today</a>
        <a href="{{ route('loan.communications.messages', ['status' => 'failed', 'channel' => 'sms', 'from' => '2026-06-01', 'to' => '2026-06-01'], absolute: false) }}" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Failed 1 Jun</a>
    </div>

    @if (($filters['duplicates'] ?? '') === 'yes')
        <p class="text-xs text-orange-800 dark:text-orange-200 rounded-lg border border-orange-200 bg-orange-50 dark:border-orange-900 dark:bg-orange-950/30 px-3 py-2">
            Showing messages where the <strong>same phone/email</strong> received the <strong>same subject</strong> more than once on the <strong>same day</strong> (likely double charges). Resend only after fixing the cause.
        </p>
    @endif

    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 dark:border-slate-700 pt-3">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mr-1">Export</span>
        @if ($canExportCommunications ?? true)
            <a href="{{ route('loan.communications.messages.export', array_merge($exportQuery, ['format' => 'csv']), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">CSV</a>
            <a href="{{ route('loan.communications.messages.export', array_merge($exportQuery, ['format' => 'xls']), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Excel</a>
            <a href="{{ route('loan.communications.messages.export', array_merge($exportQuery, ['format' => 'pdf']), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">PDF</a>
        @else
            <span class="text-xs text-slate-500 dark:text-slate-400">Export requires communications export permission.</span>
        @endif
        <button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Print list</button>
    </div>
</div>

<form method="get" action="{{ route('loan.communications.messages') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-9">
        <div class="lg:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Recipient, subject, body, error…" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
            <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">All outbound</option>
                @foreach (['email', 'sms'] as $ch)
                    <option value="{{ $ch }}" @selected(($filters['channel'] ?? '') === $ch)>{{ strtoupper($ch) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">All</option>
                @foreach (['sent', 'delivered', 'failed', 'failed_all', 'superseded', 'queued', 'unknown'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>
                        @if ($st === 'failed')
                            FAILED (needs action)
                        @elseif ($st === 'failed_all')
                            FAILED (all rows)
                        @else
                            {{ strtoupper($st) }}
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Sent by</label>
            <select name="sender" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Anyone</option>
                @foreach (($senderOptions ?? []) as $sender)
                    <option value="{{ $sender['id'] }}" @selected((string) ($filters['sender'] ?? '') === (string) $sender['id'])>{{ $sender['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Errors</label>
            <select name="has_error" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Any</option>
                <option value="yes" @selected(($filters['has_error'] ?? '') === 'yes')>With error</option>
                <option value="no" @selected(($filters['has_error'] ?? '') === 'no')>No error</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Duplicates</label>
            <select name="duplicates" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Any</option>
                <option value="yes" @selected(($filters['duplicates'] ?? '') === 'yes')>Same recipient + subject + day</option>
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
    <div class="flex flex-wrap items-center gap-2">
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
        <a href="{{ route('loan.communications.messages', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <label class="text-xs text-slate-500 dark:text-slate-400">Sort</label>
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-1.5">
                <option value="created_at" @selected(($filters['sort'] ?? 'created_at') === 'created_at')>Date</option>
                <option value="delivery_status" @selected(($filters['sort'] ?? '') === 'delivery_status')>Status</option>
                <option value="channel" @selected(($filters['sort'] ?? '') === 'channel')>Channel</option>
                <option value="id" @selected(($filters['sort'] ?? '') === 'id')>ID</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-1.5">
                <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Newest first</option>
                <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Oldest first</option>
            </select>
            <label class="text-xs text-slate-500 dark:text-slate-400">Per page</label>
            <select name="per_page" onchange="this.form.submit()" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-1.5">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>
