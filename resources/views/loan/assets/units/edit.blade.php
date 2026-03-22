<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.assets.units.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-2xl">
            <form method="post" action="{{ route('loan.assets.units.update', $unit) }}" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input id="name" name="name" value="{{ old('name', $unit->name) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="abbreviation" class="block text-xs font-semibold text-slate-600 mb-1">Abbreviation</label>
                    <input id="abbreviation" name="abbreviation" value="{{ old('abbreviation', $unit->abbreviation) }}" required maxlength="20" class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                    @error('abbreviation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-xs font-semibold text-slate-600 mb-1">Description (optional)</label>
                    <textarea id="description" name="description" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('description', $unit->description) }}</textarea>
                    @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Update unit</button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
