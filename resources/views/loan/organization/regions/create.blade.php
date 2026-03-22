<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.regions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">All regions</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ route('loan.regions.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="code" class="block text-xs font-semibold text-slate-600 mb-1">Code (optional)</label>
                    <input id="code" name="code" value="{{ old('code') }}" class="w-full rounded-lg border-slate-200 text-sm font-mono text-xs" maxlength="40" />
                    @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                    <textarea id="description" name="description" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('description') }}</textarea>
                    @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
