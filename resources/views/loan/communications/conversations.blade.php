<x-loan-layout>
    <x-slot name="header">Conversation Inbox</x-slot>

    <x-loan.page
        title="Conversation Inbox"
        subtitle="Inbound and outbound client communication threads."
    >
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse($rows as $conversation)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $conversation->topic ?: 'Untitled conversation' }}</p>
                            <p class="text-xs text-slate-500">{{ trim(($conversation->client?->first_name ?? '').' '.($conversation->client?->last_name ?? '')) ?: 'Unknown client' }}  -  {{ str_replace('_', ' ', (string) $conversation->category) }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold uppercase text-slate-700">{{ $conversation->status }}</span>
                    </div>
                    <div class="mt-3 max-h-48 space-y-2 overflow-auto rounded-lg border border-slate-100 bg-slate-50 p-2">
                        @forelse($conversation->messages as $message)
                            <div class="rounded-md bg-white p-2 text-xs">
                                <p class="font-semibold text-slate-700">
                                    {{ strtoupper((string) $message->direction) }}  -  {{ strtoupper((string) $message->channel) }}
                                    <span class="font-normal text-slate-500"> -  {{ optional($message->sent_at)->format('Y-m-d H:i') }}</span>
                                </p>
                                <p class="mt-1 text-slate-700">{{ \Illuminate\Support\Str::limit((string) $message->body, 180) }}</p>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500">No thread messages yet.</p>
                        @endforelse
                    </div>
                    <form class="conversation-reply-form mt-3 space-y-2" data-conversation-id="{{ $conversation->id }}">
                        @csrf
                        <div class="grid gap-2 sm:grid-cols-2">
                            <select name="channel" class="rounded-lg border border-slate-200 px-3 py-2 text-xs" required>
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                            </select>
                            <input type="text" name="to_address" placeholder="Auto if blank" class="rounded-lg border border-slate-200 px-3 py-2 text-xs" />
                        </div>
                        <input type="text" name="subject" placeholder="Subject (optional)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs" />
                        <textarea name="body" rows="2" required placeholder="Write reply..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></textarea>
                        <input type="file" name="attachments[]" multiple class="w-full text-xs" />
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                            Send reply
                        </button>
                        <p class="reply-feedback text-xs text-slate-500"></p>
                    </form>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-600 lg:col-span-2">
                    No conversations yet. Inbound SMS or message replies will appear here.
                </div>
            @endforelse
        </div>

        <script>
            (function () {
                const forms = document.querySelectorAll('.conversation-reply-form');
                forms.forEach((form) => {
                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        const conversationId = form.getAttribute('data-conversation-id');
                        const feedback = form.querySelector('.reply-feedback');
                        const data = new FormData(form);
                        feedback.textContent = 'Sending...';
                        try {
                            const response = await fetch(`{{ rtrim(route('loan.communications.conversations', absolute: false), '/') }}/${conversationId}/reply`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': data.get('_token'),
                                    'Accept': 'application/json',
                                },
                                body: data,
                            });
                            const payload = await response.json();
                            if (!response.ok || !payload.ok) {
                                throw new Error(payload.error || 'Failed to send reply.');
                            }
                            feedback.textContent = 'Reply queued successfully.';
                            form.reset();
                            setTimeout(() => window.location.reload(), 500);
                        } catch (error) {
                            feedback.textContent = error.message || 'Failed to send reply.';
                        }
                    });
                });
            })();
        </script>
    </x-loan.page>
</x-loan-layout>
