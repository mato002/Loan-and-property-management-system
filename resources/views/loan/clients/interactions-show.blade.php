<x-loan-layout>
    <x-loan.page
        title="Interaction details"
        subtitle="Detailed conversation for this client interaction record."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.interactions') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to interactions
            </a>
            @if ($interaction->loanClient)
                <a href="{{ route('loan.clients.interactions.for_client.create', $interaction->loanClient) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                    Client conversation
                </a>
                <a href="{{ route('loan.clients.show', $interaction->loanClient) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                    View client
                </a>
            @endif
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client</p>
                    <p class="mt-1 text-slate-900 font-medium">{{ $interaction->loanClient?->full_name ?? '—' }}</p>
                    <p class="text-xs text-slate-500">{{ $interaction->loanClient?->client_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loan officer</p>
                    <p class="mt-1 text-slate-900">{{ $interaction->loanClient?->assignedEmployee?->full_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source</p>
                    <p class="mt-1 text-slate-900">{{ $interaction->user?->name ?? 'System' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date & time</p>
                    <p class="mt-1 text-slate-900">{{ optional($interaction->interacted_at)->format('d M Y, H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type</p>
                    <p class="mt-1 text-slate-900">{{ ucfirst((string) $interaction->interaction_type) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client status</p>
                    <p class="mt-1 text-slate-900">{{ ucfirst((string) ($interaction->loanClient?->client_status ?? 'n/a')) }}</p>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</p>
                <p class="mt-1 text-slate-900">{{ $interaction->subject ?: '—' }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conversation</p>
                <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="whitespace-pre-line text-sm text-slate-800">{{ $interaction->notes ?: 'No detailed notes were provided.' }}</p>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
