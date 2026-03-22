<x-loan-layout>
    <x-loan.page title="Edit ticket" :subtitle="$ticket->ticket_number">
        <x-slot name="actions">
            <a href="{{ route('loan.system.tickets.show', $ticket) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-2xl p-6">
            <p class="text-sm text-slate-600 mb-4">You can only edit tickets while they are <strong>open</strong>.</p>
            <form method="post" action="{{ route('loan.system.tickets.update', $ticket) }}" class="space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="subject" class="block text-xs font-semibold text-slate-600 mb-1">Subject</label>
                    <input id="subject" name="subject" value="{{ old('subject', $ticket->subject) }}" required maxlength="255" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('subject')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="category" class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                        <select id="category" name="category" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category', $ticket->category) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="priority" class="block text-xs font-semibold text-slate-600 mb-1">Priority</label>
                        <select id="priority" name="priority" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach ($priorities as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $ticket->priority) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="body" class="block text-xs font-semibold text-slate-600 mb-1">Details</label>
                    <textarea id="body" name="body" rows="8" required class="w-full rounded-lg border-slate-200 text-sm">{{ old('body', $ticket->body) }}</textarea>
                    @error('body')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Save changes</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
