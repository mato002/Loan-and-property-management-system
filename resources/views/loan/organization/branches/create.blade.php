<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.branches.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        @if ($regions->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                You need at least one region. <a href="{{ route('loan.regions.create') }}" class="font-semibold underline">Create a region</a> first.
            </div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ route('loan.branches.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="loan_region_id" class="block text-xs font-semibold text-slate-600 mb-1">Region</label>
                    <select id="loan_region_id" name="loan_region_id" required class="w-full rounded-lg border-slate-200 text-sm" @disabled($regions->isEmpty())>
                        <option value="">Select…</option>
                        @foreach ($regions as $r)
                            <option value="{{ $r->id }}" @selected(old('loan_region_id') == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                    @error('loan_region_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Branch name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="code" class="block text-xs font-semibold text-slate-600 mb-1">Code (optional)</label>
                    <input id="code" name="code" value="{{ old('code') }}" class="w-full rounded-lg border-slate-200 text-sm font-mono text-xs" maxlength="40" />
                    @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="address" class="block text-xs font-semibold text-slate-600 mb-1">Address</label>
                    <textarea id="address" name="address" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('address') }}</textarea>
                    @error('address')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="manager_name" class="block text-xs font-semibold text-slate-600 mb-1">Manager</label>
                        <input id="manager_name" name="manager_name" value="{{ old('manager_name') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('manager_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('is_active', true)) />
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors" @disabled($regions->isEmpty())>Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
