<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.investment_packages') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to packages
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ $action }}" class="px-5 py-6 space-y-4">
                @csrf
                @if ($method === 'patch')
                    @method('patch')
                @endif
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Package name</label>
                    <input id="name" name="name" value="{{ old('name', $package->name) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="rate_label" class="block text-xs font-semibold text-slate-600 mb-1">Rate label</label>
                    <input id="rate_label" name="rate_label" value="{{ old('rate_label', $package->rate_label) }}" placeholder="e.g. 12% p.a." required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('rate_label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="minimum_label" class="block text-xs font-semibold text-slate-600 mb-1">Minimum label</label>
                    <input id="minimum_label" name="minimum_label" value="{{ old('minimum_label', $package->minimum_label) }}" placeholder="e.g. KES 50,000" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('minimum_label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="draft" @selected(old('status', $package->status) === 'draft')>Draft</option>
                        <option value="active" @selected(old('status', $package->status) === 'active')>Active</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        {{ $method === 'patch' ? 'Update package' : 'Create package' }}
                    </button>
                    <a href="{{ route('loan.financial.investment_packages') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
