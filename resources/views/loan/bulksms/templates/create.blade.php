<x-loan-layout>
    <x-loan.page
        title="Create SMS template"
        subtitle="Save a message you can load on the compose screen."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.templates.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        <div class="max-w-3xl bg-white border border-slate-200 rounded-xl shadow-sm p-6">
            <form method="post" action="{{ route('loan.bulksms.templates.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="160"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Description (optional)</label>
                    <input type="text" name="description" id="description" value="{{ old('description') }}" maxlength="500"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                </div>
                <div>
                    <label for="body" class="block text-sm font-medium text-slate-700">Message body</label>
                    <textarea id="body" name="body" rows="8" maxlength="1000" required
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        Save template
                    </button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
