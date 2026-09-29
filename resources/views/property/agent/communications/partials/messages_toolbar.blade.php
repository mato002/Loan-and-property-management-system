@php
    $messagesUrl = route('property.communications.messages', absolute: false);
    $exportQuery = (array) ($filters ?? []);
    $quickFilterCounts = (array) ($quickFilterCounts ?? []);
    $periodValue = trim((string) ($filters['period'] ?? ''));
    if ($periodValue === '' && trim((string) ($filters['from'] ?? '')) === '' && trim((string) ($filters['to'] ?? '')) === '') {
        $periodValue = 'month';
    }
    $presetOptions = [
        ['url' => route('property.communications.messages', ['period' => 'today'], absolute: false), 'label' => 'All today', 'count' => $quickFilterCounts['today'] ?? null],
        ['url' => route('property.communications.messages', ['period' => 'today', 'status' => 'success'], absolute: false), 'label' => 'Sent today', 'count' => $quickFilterCounts['sent_today'] ?? null],
        ['url' => route('property.communications.messages', ['period' => 'today', 'channel' => 'sms'], absolute: false), 'label' => 'SMS today', 'count' => $quickFilterCounts['sms_today'] ?? null],
        ['url' => route('property.communications.messages', ['period' => 'today', 'channel' => 'email'], absolute: false), 'label' => 'Email today', 'count' => $quickFilterCounts['email_today'] ?? null],
        ['url' => route('property.communications.messages', ['period' => 'today', 'status' => 'failed', 'channel' => 'sms'], absolute: false), 'label' => 'Failed today', 'count' => $quickFilterCounts['failed_today'] ?? null],
        ['url' => route('property.communications.messages', ['duplicates' => 'yes', 'status' => 'sent', 'channel' => 'sms', 'period' => 'today'], absolute: false), 'label' => 'Duplicates today', 'count' => null],
    ];
    $statusOptions = [
        ['value' => 'success', 'label' => 'Sent / delivered'],
        ['value' => 'sent', 'label' => 'Sent'],
        ['value' => 'delivered', 'label' => 'Delivered'],
        ['value' => 'failed', 'label' => 'Failed (needs action)'],
        ['value' => 'failed_all', 'label' => 'Failed (all rows)'],
        ['value' => 'superseded', 'label' => 'Superseded'],
        ['value' => 'queued', 'label' => 'Queued'],
        ['value' => 'unknown', 'label' => 'Unknown'],
    ];
    $senderSelectOptions = collect($senderOptions ?? [])
        ->map(fn ($sender) => ['value' => (string) $sender['id'], 'label' => (string) $sender['name']])
        ->all();
@endphp

<div class="w-full min-w-0 space-y-2">
    <x-property.filter-toolbar
        :action="$messagesUrl"
        :reset-url="$messagesUrl"
        drawer-label="Message filters"
        :chip-labels="[
            'q' => 'Search',
            'period' => 'Period',
            'channel' => 'Channel',
            'status' => 'Status',
            'sender' => 'Sent by',
            'has_error' => 'Errors',
            'duplicates' => 'Duplicates',
            'from' => 'From',
            'to' => 'To',
        ]"
        :chip-ignore-values="['period' => ['month']]"
    >
        <x-slot name="primary">
            <x-property.filter-field type="search" name="q" placeholder="Recipient, subject, body, error…" :value="$filters['q'] ?? ''" wide />

            <x-property.filter-field type="custom" label="Quick view">
                <select
                    class="property-filter-field__control w-full min-h-[44px] md:min-h-[38px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 text-slate-900 dark:text-slate-100 md:min-w-[8.5rem] md:max-w-[11rem]"
                    onchange="if(this.value){ window.location.href=this.value; this.selectedIndex=0; }"
                >
                    <option value="">Quick view</option>
                    @foreach ($presetOptions as $preset)
                        <option value="{{ $preset['url'] }}">
                            {{ $preset['label'] }}@if (($preset['count'] ?? 0) > 0) ({{ number_format((int) $preset['count']) }})@endif
                        </option>
                    @endforeach
                </select>
            </x-property.filter-field>

            <x-property.filter-field
                type="select"
                name="period"
                label="Period"
                empty-option="Period: All time"
                :options="[
                    ['value' => 'today', 'label' => 'Today'],
                    ['value' => 'week', 'label' => 'This week'],
                    ['value' => 'month', 'label' => 'This month'],
                ]"
                :value="$periodValue === 'month' || $periodValue === 'week' || $periodValue === 'today' ? $periodValue : ''"
            />

            <x-property.filter-field
                type="select"
                name="channel"
                label="Channel"
                empty-option="Channel: All"
                :options="[
                    ['value' => 'sms', 'label' => 'SMS only'],
                    ['value' => 'email', 'label' => 'Email only'],
                ]"
                :value="$filters['channel'] ?? ''"
            />

            <x-property.filter-field
                type="select"
                name="status"
                label="Status"
                empty-option="Status: All"
                :options="$statusOptions"
                :value="$filters['status'] ?? ''"
            />

            <x-property.filter-field
                type="select"
                name="sender"
                label="Sent by"
                empty-option="Sent by: Anyone"
                :options="$senderSelectOptions"
                :value="(string) ($filters['sender'] ?? '')"
            />

            <x-property.filter-field
                type="select"
                name="has_error"
                label="Errors"
                empty-option="Errors: Any"
                :options="[
                    ['value' => 'yes', 'label' => 'With error'],
                    ['value' => 'no', 'label' => 'No error'],
                ]"
                :value="$filters['has_error'] ?? ''"
            />

            <x-property.filter-field
                type="select"
                name="duplicates"
                label="Duplicates"
                empty-option="Duplicates: Any"
                :options="[
                    ['value' => 'yes', 'label' => 'Same recipient + subject + day'],
                ]"
                :value="$filters['duplicates'] ?? ''"
            />

            <x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
            <x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />

            <x-property.filter-field
                type="select"
                name="sort"
                label="Sort"
                :options="[
                    ['value' => 'created_at', 'label' => 'Date'],
                    ['value' => 'delivery_status', 'label' => 'Status'],
                    ['value' => 'channel', 'label' => 'Channel'],
                    ['value' => 'id', 'label' => 'ID'],
                ]"
                :value="$filters['sort'] ?? 'created_at'"
            />

            <x-property.filter-field
                type="select"
                name="dir"
                label="Order"
                :options="[
                    ['value' => 'desc', 'label' => 'Newest first'],
                    ['value' => 'asc', 'label' => 'Oldest first'],
                ]"
                :value="$filters['dir'] ?? 'desc'"
            />

            <x-property.filter-field
                type="select"
                name="per_page"
                label="Per page"
                :options="collect([10, 25, 50, 100])->map(fn ($n) => ['value' => (string) $n, 'label' => (string) $n])->all()"
                :value="(string) ($perPage ?? 25)"
            />
        </x-slot>

        <x-slot name="export">
            @if ($canExportCommunications ?? true)
                @include('property.agent.partials.export_dropdown', [
                    'csvUrl' => route('property.communications.messages.export', array_merge($exportQuery, ['format' => 'csv']), absolute: false),
                    'xlsUrl' => route('property.communications.messages.export', array_merge($exportQuery, ['format' => 'xls']), absolute: false),
                    'pdfUrl' => route('property.communications.messages.export', array_merge($exportQuery, ['format' => 'pdf']), absolute: false),
                ])
            @endif
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex min-h-[38px] items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 shrink-0"
            >Print</button>
        </x-slot>
    </x-property.filter-toolbar>

    @if (($filters['duplicates'] ?? '') === 'yes')
        <p class="text-xs text-orange-800 dark:text-orange-200 rounded-lg border border-orange-200 bg-orange-50 dark:border-orange-900 dark:bg-orange-950/30 px-3 py-2">
            Showing messages where the <strong>same phone/email</strong> received the <strong>same subject</strong> more than once on the <strong>same day</strong> (likely double charges). Resend only after fixing the cause.
        </p>
    @endif
</div>
