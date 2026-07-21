<x-property.workspace
    title="Audit trace detail"
    subtitle="End-to-end financial trace for a single accounting event."
    back-route="property.accounting.audit_trail"
    :stats="[
        ['label' => 'Batch', 'value' => 'JRN-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT), 'hint' => 'Journal batch'],
        ['label' => 'Event type', 'value' => (string) $batch->event_type, 'hint' => 'Posting action'],
        ['label' => 'Source', 'value' => (string) $batch->source_type, 'hint' => 'Origin module'],
        ['label' => 'Status', 'value' => ucfirst((string) $batch->status), 'hint' => 'Lifecycle state'],
    ]"
    :show-search="false"
>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-gray-800/70">
            <h3 class="font-semibold text-slate-900 dark:text-white">Full action description</h3>
            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ $batch->description ?: 'No description captured.' }}</p>
            <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                <div><dt class="text-slate-500">Date</dt><dd class="font-medium">{{ $batch->date?->format('Y-m-d') }}</dd></div>
                <div><dt class="text-slate-500">Reference</dt><dd class="font-medium">{{ $batch->source_key ?: ($batch->source_type.'#'.$batch->source_id) }}</dd></div>
                <div><dt class="text-slate-500">Posted by</dt><dd class="font-medium">{{ $batch->postedByUser?->name ?: ($batch->createdByUser?->name ?: 'System') }}</dd></div>
                <div><dt class="text-slate-500">Created at</dt><dd class="font-medium">{{ optional($batch->created_at)->format('Y-m-d H:i') }}</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-gray-800/70">
            <h3 class="font-semibold text-slate-900 dark:text-white">Financial impact</h3>
            <ul class="mt-2 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                @forelse($lineImpact as $item)
                    <li>{{ $item }}</li>
                @empty
                    <li>No journal line impact found.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-gray-800/70">
        <h3 class="font-semibold text-slate-900 dark:text-white">Source record and linked records</h3>
        <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
            Source record: {{ $sourceRecord['type'] }} @if($sourceRecord['record']) #{{ $sourceRecord['record']->id ?? '—' }} @else not found @endif
        </p>
        <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">Linked batches for same source:</p>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach($linkedBatches as $linked)
                <li>
                    <a class="text-indigo-600 hover:text-indigo-700" href="{{ route('property.accounting.audit_trail.show', ['batch' => $linked->id]) }}">
                        JRN-{{ str_pad((string) $linked->id, 6, '0', STR_PAD_LEFT) }}  -  {{ $linked->event_type }}  -  {{ ucfirst((string) $linked->status) }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-gray-800/70">
        <h3 class="font-semibold text-slate-900 dark:text-white">Journal entries created</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="px-3 py-2">Account</th>
                        <th class="px-3 py-2">Debit</th>
                        <th class="px-3 py-2">Credit</th>
                        <th class="px-3 py-2">Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->lines as $line)
                        <tr class="border-t border-slate-100 dark:border-slate-700">
                            <td class="px-3 py-2">{{ $line->structuredAccount?->code }} - {{ $line->structuredAccount?->name }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $line->debit, 2) }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $line->credit, 2) }}</td>
                            <td class="px-3 py-2">{{ $line->reference ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-gray-800/70">
            <h3 class="font-semibold text-slate-900 dark:text-white">Landlord impact</h3>
            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                @forelse($landlordImpact as $entry)
                    <li>{{ $entry->entry_date?->format('Y-m-d') }}  -  {{ $entry->entry_type }}  -  {{ number_format((float) $entry->amount, 2) }}  -  {{ $entry->description ?: $entry->reference_type }}</li>
                @empty
                    <li>No landlord ledger impact recorded.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-gray-800/70">
            <h3 class="font-semibold text-slate-900 dark:text-white">Reversal history</h3>
            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                @foreach($reversalHistory as $item)
                    <li>
                        JRN-{{ str_pad((string) $item->id, 6, '0', STR_PAD_LEFT) }}  -  {{ $item->event_type }}  -  {{ ucfirst((string) $item->status) }}
                        @if($item->reversed_from_batch_id)
                            (reversal of #{{ $item->reversed_from_batch_id }})
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-property.workspace>
