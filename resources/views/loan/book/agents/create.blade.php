<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_agents.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ route('loan.book.collection_agents.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch</label>
                    <input id="branch" name="branch" value="{{ old('branch') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="employee_id" class="block text-xs font-semibold text-slate-600 mb-1">Link to employee (optional)</label>
                    <select id="employee_id" name="employee_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">—</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('is_active', true)) />
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
