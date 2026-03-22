<x-loan-layout>
    <x-loan.page
        title="Edit SMS template"
        subtitle="{{ $template->name }}"
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.templates.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Back to list
            </a>
            <a href="{{ route('loan.bulksms.compose', ['template' => $template->id]) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Use in composer
            </a>
        </x-slot>

        <div class="max-w-3xl bg-white border border-slate-200 rounded-xl shadow-sm p-6">
            <form method="post" action="{{ route('loan.bulksms.templates.update', $template) }}" class="space-y-5">
                @csrf
                @method('patch')
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $template->name) }}" required maxlength="160"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Description (optional)</label>
                    <input type="text" name="description" id="description" value="{{ old('description', $template->description) }}" maxlength="500"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                </div>
                <div>
                    <label for="body" class="block text-sm font-medium text-slate-700">Message body</label>
                    <textarea id="body" name="body" rows="8" maxlength="1000" required
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">{{ old('body', $template->body) }}</textarea>
                    @error('body')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        Update template
                    </button>
                </div>
            </form>
            <form method="post" action="{{ route('loan.bulksms.templates.destroy', $template) }}" class="mt-4 pt-4 border-t border-slate-100" data-swal-confirm="Delete this template?">
                @csrf
                @method('delete')
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 transition-colors">
                    Delete template
                </button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
