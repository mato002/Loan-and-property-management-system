@php
    $exportQuery = (array) ($filters ?? []);
    $activeFilterCount = collect($filters ?? [])
        ->except(['sort', 'dir', 'per_page', 'page', 'export'])
        ->filter(static fn ($value) => ! is_null($value) && $value !== '')
        ->count();
    $quickFilterCounts = (array) ($quickFilterCounts ?? []);
    $quickFilterLinkClass = 'inline-flex items-center justify-center gap-1.5 rounded-lg border px-2 py-2 text-xs font-medium text-center min-h-[38px] md:min-h-0 md:px-3 md:py-1.5 transition-colors';
    $quickFilterActiveRing = 'ring-2 ring-blue-500 border-blue-400 bg-blue-50 text-blue-900 dark:bg-blue-950/40 dark:text-blue-100 dark:border-blue-500';
    $isQuickFilterActive = static function (array $params) use ($filters): bool {
        foreach ($params as $key => $value) {
            if (trim((string) ($filters[$key] ?? '')) !== (string) $value) {
                return false;
            }
        }

        return true;
    };
    $quickFilterClass = static function (array $params, string $inactiveClass) use ($isQuickFilterActive, $quickFilterLinkClass, $quickFilterActiveRing): string {
        $active = $isQuickFilterActive($params);

        return $quickFilterLinkClass.' '.($active ? $quickFilterActiveRing : $inactiveClass);
    };
    $quickFilterBadge = static function (?int $count): string {
        if ($count === null || $count <= 0) {
            return '';
        }

        return '<span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-black/10 px-1.5 py-0.5 text-[10px] font-semibold leading-none dark:bg-white/15">'.$count.'</span>';
    };
@endphp

<div class="space-y-3 w-full min-w-0">
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-4">
        <div class="space-y-2">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Today</span>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:flex md:flex-wrap md:items-center md:gap-2">
                <a href="{{ route('property.communications.messages', ['period' => 'today'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'today'], 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50') }}">
                    All today {!! $quickFilterBadge($quickFilterCounts['today'] ?? null) !!}
                </a>
                <a href="{{ route('property.communications.messages', ['period' => 'today', 'status' => 'success'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'today', 'status' => 'success'], 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-200 dark:hover:bg-emerald-950/40') }}">
                    Sent today {!! $quickFilterBadge($quickFilterCounts['sent_today'] ?? null) !!}
                </a>
                <a href="{{ route('property.communications.messages', ['period' => 'today', 'channel' => 'sms'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'today', 'channel' => 'sms'], 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-200 dark:hover:bg-emerald-950/40') }}">
                    SMS today {!! $quickFilterBadge($quickFilterCounts['sms_today'] ?? null) !!}
                </a>
                <a href="{{ route('property.communications.messages', ['period' => 'today', 'channel' => 'email'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'today', 'channel' => 'email'], 'border-indigo-300 text-indigo-700 hover:bg-indigo-50 dark:text-indigo-200 dark:hover:bg-indigo-950/40') }}">
                    Email today {!! $quickFilterBadge($quickFilterCounts['email_today'] ?? null) !!}
                </a>
                <a href="{{ route('property.communications.messages', ['period' => 'today', 'status' => 'failed', 'channel' => 'sms'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'today', 'status' => 'failed', 'channel' => 'sms'], 'border-rose-400 font-semibold text-rose-800 hover:bg-rose-50 dark:text-rose-200 dark:hover:bg-rose-950/40') }}" title="Unresolved SMS failures sent today">
                    Failed today {!! $quickFilterBadge($quickFilterCounts['failed_today'] ?? null) !!}
                </a>
                <a href="{{ route('property.communications.messages', ['duplicates' => 'yes', 'status' => 'sent', 'channel' => 'sms', 'period' => 'today'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['duplicates' => 'yes', 'status' => 'sent', 'channel' => 'sms', 'period' => 'today'], 'border-orange-400 font-semibold text-orange-800 hover:bg-orange-50 dark:text-orange-200 dark:hover:bg-orange-950/40') }}">
                    Duplicates today
                </a>
            </div>
        </div>

        <div class="space-y-2 border-t border-slate-100 dark:border-slate-700 pt-3">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Time range</span>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 md:flex md:flex-wrap md:items-center md:gap-2">
                <a href="{{ route('property.communications.messages', ['period' => 'all'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'all'], 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50') }}">All time</a>
                <a href="{{ route('property.communications.messages', ['period' => 'week'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'week'], 'border-slate-300 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50') }}">This week</a>
                <a href="{{ route('property.communications.messages', ['period' => 'month'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['period' => 'month'], 'border-slate-300 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50') }}">This month</a>
            </div>
        </div>

        <div class="space-y-2 border-t border-slate-100 dark:border-slate-700 pt-3">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Channel &amp; issues</span>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:flex md:flex-wrap md:items-center md:gap-2">
                <a href="{{ route('property.communications.messages', ['channel' => 'sms'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['channel' => 'sms'], 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-200 dark:hover:bg-emerald-950/40') }}">SMS only</a>
                <a href="{{ route('property.communications.messages', ['channel' => 'email'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['channel' => 'email'], 'border-indigo-300 text-indigo-700 hover:bg-indigo-50 dark:text-indigo-200 dark:hover:bg-indigo-950/40') }}">Email only</a>
                <a href="{{ route('property.communications.messages', ['status' => 'failed'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['status' => 'failed'], 'border-rose-300 text-rose-700 hover:bg-rose-50 dark:text-rose-200 dark:hover:bg-rose-950/40') }}" title="Unresolved failures only (hides rows already sent on retry)">Failed (needs action)</a>
                <a href="{{ route('property.communications.messages', ['has_error' => 'yes'], absolute: false) }}" data-turbo-frame="property-main" class="{{ $quickFilterClass(['has_error' => 'yes'], 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-amber-950/40') }}">With errors</a>
            </div>
        </div>

        @if (($filters['duplicates'] ?? '') === 'yes')
            <p class="text-xs text-orange-800 dark:text-orange-200 rounded-lg border border-orange-200 bg-orange-50 dark:border-orange-900 dark:bg-orange-950/30 px-3 py-2">
                Showing messages where the <strong>same phone/email</strong> received the <strong>same subject</strong> more than once on the <strong>same day</strong> (likely double charges). Resend only after fixing the cause.
            </p>
        @endif

        <div class="space-y-2 border-t border-slate-100 dark:border-slate-700 pt-3">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Export</span>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 md:flex md:flex-wrap md:items-center md:gap-2">
            @if ($canExportCommunications ?? true)
                <a href="{{ route('property.communications.messages.export', array_merge($exportQuery, ['format' => 'csv']), absolute: false) }}" data-turbo="false" class="{{ $quickFilterLinkClass }} border-indigo-300 text-indigo-700 hover:bg-indigo-50">CSV</a>
                <a href="{{ route('property.communications.messages.export', array_merge($exportQuery, ['format' => 'xls']), absolute: false) }}" data-turbo="false" class="{{ $quickFilterLinkClass }} border-indigo-300 text-indigo-700 hover:bg-indigo-50">Excel</a>
                <a href="{{ route('property.communications.messages.export', array_merge($exportQuery, ['format' => 'pdf']), absolute: false) }}" data-turbo="false" class="{{ $quickFilterLinkClass }} border-indigo-300 text-indigo-700 hover:bg-indigo-50">PDF</a>
            @else
                <span class="col-span-full text-xs text-slate-500 dark:text-slate-400">Export requires communications export permission.</span>
            @endif
            <button type="button" onclick="window.print()" class="{{ $quickFilterLinkClass }} border-slate-300 text-slate-700 hover:bg-slate-50">Print list</button>
            </div>
        </div>
    </div>

    <form
        method="get"
        action="{{ route('property.communications.messages') }}"
        data-turbo-frame="property-main"
        class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3"
    >
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-2 lg:grid-cols-9 lg:gap-3">
            <div class="col-span-2 lg:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Recipient, subject, body, error…" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0">
                    <option value="">All outbound</option>
                    @foreach (['email', 'sms'] as $ch)
                        <option value="{{ $ch }}" @selected(($filters['channel'] ?? '') === $ch)>{{ strtoupper($ch) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0">
                    <option value="">All</option>
                    @foreach (['success', 'sent', 'delivered', 'failed', 'failed_all', 'superseded', 'queued', 'unknown'] as $st)
                        <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>
                            @if ($st === 'success')
                                SENT / DELIVERED
                            @elseif ($st === 'failed')
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
                <select name="sender" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0">
                    <option value="">Anyone</option>
                    @foreach (($senderOptions ?? []) as $sender)
                        <option value="{{ $sender['id'] }}" @selected((string) ($filters['sender'] ?? '') === (string) $sender['id'])>{{ $sender['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Errors</label>
                <select name="has_error" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0">
                    <option value="">Any</option>
                    <option value="yes" @selected(($filters['has_error'] ?? '') === 'yes')>With error</option>
                    <option value="no" @selected(($filters['has_error'] ?? '') === 'no')>No error</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Duplicates</label>
                <select name="duplicates" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0">
                    <option value="">Any</option>
                    <option value="yes" @selected(($filters['duplicates'] ?? '') === 'yes')>Same recipient + subject + day</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0" />
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
            <button type="submit" class="col-span-1 w-full sm:w-auto rounded-xl bg-blue-600 px-4 py-2.5 sm:py-2 text-sm font-medium text-white hover:bg-blue-700 min-h-[44px] sm:min-h-0">Apply filters</button>
            <a href="{{ route('property.communications.messages', absolute: false) }}" data-turbo-frame="property-main" class="col-span-1 w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 sm:py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 min-h-[44px] sm:min-h-0">Reset</a>
            <label class="col-span-2 sm:col-span-1 text-xs text-slate-500 dark:text-slate-400 sm:ml-auto">Sort</label>
            <select name="sort" class="col-span-1 w-full sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-2 min-h-[44px] sm:min-h-0">
                    <option value="created_at" @selected(($filters['sort'] ?? 'created_at') === 'created_at')>Date</option>
                    <option value="delivery_status" @selected(($filters['sort'] ?? '') === 'delivery_status')>Status</option>
                    <option value="channel" @selected(($filters['sort'] ?? '') === 'channel')>Channel</option>
                    <option value="id" @selected(($filters['sort'] ?? '') === 'id')>ID</option>
                </select>
                <select name="dir" class="col-span-1 w-full sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-2 min-h-[44px] sm:min-h-0">
                    <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Newest first</option>
                    <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Oldest first</option>
                </select>
                <label class="col-span-2 sm:col-span-1 text-xs text-slate-500 dark:text-slate-400">Per page</label>
                <select name="per_page" onchange="this.form.submit()" class="col-span-2 sm:col-span-1 w-full sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-2 min-h-[44px] sm:min-h-0">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
        </div>
        @if ($activeFilterCount > 0)
            <p class="text-xs text-slate-500 dark:text-slate-400 md:hidden">{{ $activeFilterCount }} active filter(s)</p>
        @endif
    </form>
</div>
