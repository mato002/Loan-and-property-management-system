<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_agents.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <form method="post" action="{{ route('loan.book.collection_agents.update', $agent) }}" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input id="name" name="name" value="{{ old('name', $agent->name) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $agent->phone) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch</label>
                    <input id="branch" name="branch" value="{{ old('branch', $agent->branch) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="employee_id" class="block text-xs font-semibold text-slate-600 mb-1">Link to employee</label>
                    <select id="employee_id" name="employee_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">—</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}" @selected(old('employee_id', $agent->employee_id) == $e->id)>{{ $e->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('is_active', $agent->is_active)) />
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>
                <div class="pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Update agent</button>
                </div>
            </form>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-800">Agent status</h3>
                <p class="mt-2 text-xs text-slate-600">Keep inactive agents for audit trails and historical assignment records.</p>
                <div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs text-slate-700">
                    Linked employee:
                    <span class="font-semibold">{{ $agent->employee?->full_name ?? 'None' }}</span>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
