<x-loan-layout>
    <x-loan.page
        title="Log interaction"
        subtitle="For {{ $loan_client->full_name }} ({{ $loan_client->client_number }})"
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.show', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to profile
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-2xl">
            <form method="post" action="{{ route('loan.clients.interactions.for_client.store', $loan_client) }}" class="space-y-5">
                @csrf
                <div>
                    <x-input-label for="interaction_type" value="Type" />
                    <select id="interaction_type" name="interaction_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (['call', 'visit', 'sms', 'email', 'whatsapp', 'other'] as $t)
                            <option value="{{ $t }}" @selected(old('interaction_type', 'call') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('interaction_type')" />
                </div>
                <div>
                    <x-input-label for="subject" value="Subject (optional)" />
                    <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" :value="old('subject')" />
                    <x-input-error class="mt-2" :messages="$errors->get('subject')" />
                </div>
                <div>
                    <x-input-label for="interacted_at" value="Date & time" />
                    <x-text-input id="interacted_at" name="interacted_at" type="datetime-local" class="mt-1 block w-full" :value="old('interacted_at', now()->format('Y-m-d\TH:i'))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('interacted_at')" />
                </div>
                <div>
                    <x-input-label for="notes" value="Notes" />
                    <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>
                <x-primary-button type="submit">{{ __('Save interaction') }}</x-primary-button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
