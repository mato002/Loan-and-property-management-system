@php
    /** @var \App\Models\LoanClient $loan_client */
    $walletModel = $loan_client->wallet;
@endphp
<div class="space-y-4">
    <p class="text-sm text-slate-600">Wallet balance: <strong>KSh {{ number_format((float) ($walletModel?->balance ?? 0), 2) }}</strong></p>
    <p class="text-xs text-amber-800">Refund requires approval. No cash movement until an approver posts the refund.</p>
    <form method="post" action="{{ route('loan.clients.wallet.refund_request.store', $loan_client) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="form_context" value="refund_request" />
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $walletModel?->balance ?? 0 }}" value="{{ old('amount') }}" class="w-full rounded-lg border-slate-200 text-sm @error('amount') border-red-500 @enderror" required />
            @error('amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
            <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-200 text-sm @error('notes') border-red-500 @enderror" placeholder="Optional context for approvers">{{ old('notes') }}</textarea>
            @error('notes')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full rounded-lg bg-amber-600 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Submit refund request</button>
    </form>
</div>
