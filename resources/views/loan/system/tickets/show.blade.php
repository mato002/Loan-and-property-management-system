<x-loan-layout>
    <x-loan.page :title="$ticket->subject" :subtitle="($ticket->ticket_number ?? 'Ticket').' · raised by '.$ticket->user->name">
        <x-slot name="actions">
            <a href="{{ route('loan.system.tickets.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">All tickets</a>
            @if ((int) $ticket->user_id === (int) auth()->id() && $ticket->status === \App\Models\LoanSupportTicket::STATUS_OPEN)
                <a href="{{ route('loan.system.tickets.edit', $ticket) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
            @endif
            @if ($ticket->canBeDeletedBy(auth()->user()))
                <form method="post" action="{{ route('loan.system.tickets.destroy', $ticket) }}" class="inline" data-swal-confirm="Delete this ticket permanently?">
                    @csrf
                    @method('delete')
                    <button type="submit" class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete</button>
                </form>
            @endif
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Description</h2>
                    <div class="text-sm text-slate-800 whitespace-pre-wrap">{{ $ticket->body }}</div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-4">Conversation</h2>
                    <div class="space-y-4">
                        @foreach ($ticket->replies as $reply)
                            @if ($reply->is_internal && (int) auth()->id() === (int) $ticket->user_id)
                                @continue
                            @endif
                            <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-4">
                                <div class="flex flex-wrap justify-between gap-2 text-xs text-slate-500 mb-2">
                                    <span class="font-semibold text-slate-700">{{ $reply->user->name }}</span>
                                    <span>{{ $reply->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                                @if ($reply->is_internal)
                                    <p class="text-[11px] font-semibold text-amber-800 mb-1">Internal note</p>
                                @endif
                                <div class="text-sm text-slate-800 whitespace-pre-wrap">{{ $reply->body }}</div>
                            </div>
                        @endforeach
                    </div>

                    <form method="post" action="{{ route('loan.system.tickets.replies.store', $ticket) }}" class="mt-6 space-y-3 border-t border-slate-100 pt-5">
                        @csrf
                        <div>
                            <label for="reply_body" class="block text-xs font-semibold text-slate-600 mb-1">Add reply</label>
                            <textarea id="reply_body" name="body" rows="4" required class="w-full rounded-lg border-slate-200 text-sm" placeholder="Update or ask a follow-up…">{{ old('body') }}</textarea>
                            @error('body')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        @if ((int) auth()->id() !== (int) $ticket->user_id)
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input type="hidden" name="is_internal" value="0" />
                                <input type="checkbox" name="is_internal" value="1" @checked(old('is_internal')) />
                                Internal (hidden from ticket submitter)
                            </label>
                        @endif
                        <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Post reply</button>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-4">Ticket details</h2>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">Number</dt><dd class="font-mono text-xs font-semibold text-indigo-600">{{ $ticket->ticket_number ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">Category</dt><dd class="capitalize">{{ str_replace('_', ' ', $ticket->category) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">Priority</dt><dd class="capitalize">{{ $ticket->priority }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">Status</dt><dd class="capitalize">{{ str_replace('_', ' ', $ticket->status) }}</dd></div>
                        @if ($ticket->assignedTo)
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Assigned</dt><dd>{{ $ticket->assignedTo->name }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-4">Update status</h2>
                    <form method="post" action="{{ route('loan.system.tickets.status', $ticket) }}" class="space-y-3">
                        @csrf
                        @method('patch')
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                            <select name="status" class="w-full rounded-lg border-slate-200 text-sm">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $ticket->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Assign to</label>
                            <select name="assigned_to_user_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">— Unassigned —</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected(old('assigned_to_user_id', $ticket->assigned_to_user_id) == $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Resolution notes</label>
                            <textarea name="resolution_notes" rows="3" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Optional closure summary">{{ old('resolution_notes', $ticket->resolution_notes) }}</textarea>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Apply</button>
                    </form>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
