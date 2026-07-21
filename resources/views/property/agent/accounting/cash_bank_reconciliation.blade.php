<x-property.workspace
    title="Bank reconciliation"
    subtitle="Match cash-book movements against imported bank-side transactions."
    back-route="property.accounting.index"
    :stats="[
        ['label' => 'System side', 'value' => (string) (count($cashSide ?? [])), 'hint' => 'Cash book rows'],
        ['label' => 'Bank side', 'value' => (string) (count($bankSide ?? [])), 'hint' => 'Statement rows'],
    ]"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <span class="inline-flex rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700">Match transactions</span>
        <span class="inline-flex rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700">Mark reconciled</span>
        <span class="inline-flex rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700">Add missing entry</span>
        <span class="inline-flex rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700">Flag discrepancy</span>
    </x-slot>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">System transactions</h3>
            <div class="mt-3 space-y-2 max-h-[420px] overflow-auto">
                @foreach (($cashSide ?? collect()) as $r)
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="font-medium">{{ $r->entry_date?->format('Y-m-d') ?? '—' }}  -  {{ $r->account_name }}</div>
                        <div class="text-slate-600">{{ $r->reference ?: '—' }}  -  {{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Bank statement side</h3>
            <div class="mt-3 space-y-2 max-h-[420px] overflow-auto">
                @forelse (($bankSide ?? collect()) as $r)
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="font-medium">{{ $r->transaction_date ?? $r->created_at ?? '—' }}</div>
                        <div class="text-slate-600">{{ $r->reference ?? '—' }}  -  {{ \App\Services\Property\PropertyMoney::kes((float) ($r->amount ?? 0)) }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No imported/unmatched bank rows yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-property.workspace>

