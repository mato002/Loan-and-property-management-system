<x-loan-layout>
    <x-loan.page
        title="{{ $loan_client->full_name }} Interactions"
        subtitle="Interaction conversation history for this client."
    >
        <x-slot name="actions">
            <button
                type="button"
                onclick="document.getElementById('create-client-interaction-modal')?.classList.remove('hidden');document.getElementById('create-client-interaction-modal')?.classList.add('flex');"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
            >
                + Create
            </button>
            <a href="{{ route('loan.clients.show', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to profile
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3">Comment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($interactions as $interaction)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 align-top text-slate-700">
                                    <p class="font-semibold">{{ $interaction->user?->name ?? 'System' }}</p>
                                    <p class="text-xs text-cyan-700 mt-1">{{ optional($interaction->interacted_at)->format('d-m-Y, H:i') }}</p>
                                </td>
                                <td class="px-5 py-3 align-top text-slate-700">
                                    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $interaction->notes ?: ($interaction->subject ?: '—') }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-5 py-12 text-center text-slate-500">No interactions logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($interactions->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $interactions->links() }}
                </div>
            @endif
        </div>

        <div id="create-client-interaction-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/55 p-4">
            <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h3 class="text-2xl font-semibold text-slate-800">Post Client Interaction</h3>
                    <button type="button" onclick="document.getElementById('create-client-interaction-modal')?.classList.add('hidden');document.getElementById('create-client-interaction-modal')?.classList.remove('flex');" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                <form method="post" action="{{ route('loan.clients.interactions.for_client.store', $loan_client) }}" class="space-y-5 p-5">
                    @csrf
                    <div>
                        <x-input-label for="notes" value="Comment" />
                        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <x-primary-button type="submit">{{ __('Post') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const modal = document.getElementById('create-client-interaction-modal');
                    modal?.classList.remove('hidden');
                    modal?.classList.add('flex');
                });
            </script>
        @endif
    </x-loan.page>
</x-loan-layout>
