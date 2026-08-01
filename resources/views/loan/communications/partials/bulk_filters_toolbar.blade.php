<form method="get" action="{{ route('loan.communications.bulk') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3 w-full min-w-0">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Segment, notes..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
            <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0">
                <option value="">All</option>
                <option value="sms" @selected(($filters['channel'] ?? '') === 'sms')>SMS</option>
                <option value="email" @selected(($filters['channel'] ?? '') === 'email')>EMAIL</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 min-h-[44px] md:min-h-0">
                <option value="">All</option>
                @foreach (['sent', 'queued', 'failed'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ strtoupper($st) }}</option>
                @endforeach
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
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <button type="submit" class="w-full sm:w-auto rounded-xl bg-blue-600 px-4 py-2.5 sm:py-2 text-sm font-medium text-white hover:bg-blue-700 min-h-[44px] sm:min-h-0">Apply filters</button>
        <a href="{{ route('loan.communications.bulk', absolute: false) }}" class="w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 sm:py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 min-h-[44px] sm:min-h-0">Reset</a>
    </div>
</form>
