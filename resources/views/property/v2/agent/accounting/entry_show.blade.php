<x-property.workspace
    title="Journal entry details"
    subtitle="View posted lines and reversal linkage."
    back-route="property.accounting.entries"
    :stats="[
        ['label' => 'Reference', 'value' => 'JRN-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT), 'hint' => 'Batch ID'],
        ['label' => 'Source', 'value' => $sourceLabel, 'hint' => 'Manual/system'],
        ['label' => 'Total Debit', 'value' => \App\Services\Property\PropertyMoney::kes((float) $totalDebit), 'hint' => 'Batch total'],
        ['label' => 'Total Credit', 'value' => \App\Services\Property\PropertyMoney::kes((float) $totalCredit), 'hint' => 'Batch total'],
    ]"
    :columns="['Account', 'Debit', 'Credit', 'Memo', 'Reference']"
    :table-rows="$batch->lines->map(function ($line) {
        return [
            (($line->structuredAccount?->code ? $line->structuredAccount->code.' - ' : '').($line->structuredAccount?->name ?? 'Unknown account')),
            \App\Services\Property\PropertyMoney::kes((float) $line->debit),
            \App\Services\Property\PropertyMoney::kes((float) $line->credit),
            (string) ($line->memo ?? '—'),
            (string) ($line->reference ?? '—'),
        ];
    })->all()"
    empty-title="No lines"
    empty-hint="This journal has no lines."
>
    <x-slot name="above">
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
            <p><span class="font-semibold">Date:</span> {{ optional($batch->date)->format('Y-m-d') }}</p>
            <p><span class="font-semibold">Description:</span> {{ $batch->description ?: '—' }}</p>
            <p><span class="font-semibold">Status:</span> {{ ucfirst((string) $batch->status) }}</p>
            <p><span class="font-semibold">Created by:</span> {{ $batch->createdByUser?->name ?: 'System' }}</p>
            <p><span class="font-semibold">Posted by:</span> {{ $batch->postedByUser?->name ?: 'System' }}</p>
            <p><span class="font-semibold">Reversal info:</span>
                @if($linkedReverse)
                    Reversed by batch #{{ $linkedReverse->id }}
                @elseif(!is_null($batch->reversed_from_batch_id))
                    This batch is a reversal of #{{ $batch->reversed_from_batch_id }}
                @else
                    Not reversed
                @endif
            </p>
        </div>
    </x-slot>
</x-property.workspace>
