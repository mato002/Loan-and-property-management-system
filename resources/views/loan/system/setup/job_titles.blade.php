<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">System setup</a>
        </x-slot>

        @include('loan.accounting.partials.flash')

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-1 bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-slate-700">Add job title</h2>
                <form method="post" action="{{ route('loan.system.setup.job_titles.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                        <input name="name" value="{{ old('name') }}" class="w-full rounded-lg border-slate-200 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Code (optional)</label>
                        <input name="code" value="{{ old('code') }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Description (optional)</label>
                        <textarea name="description" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('description') }}</textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        Active
                    </label>
                    <div>
                        <button class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Save job title</button>
                    </div>
                </form>
            </div>

            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-700">Existing job titles</h2>
                    <form method="post" action="{{ route('loan.system.setup.job_titles.sync') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                            Sync from employees
                        </button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Name</th>
                                <th class="px-5 py-3">Code</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($jobTitles as $jobTitle)
                                <tr>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-900">{{ $jobTitle->name }}</p>
                                        @if ($jobTitle->description)
                                            <p class="text-xs text-slate-500 mt-0.5">{{ $jobTitle->description }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $jobTitle->code ?: '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $jobTitle->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $jobTitle->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <form method="post" action="{{ route('loan.system.setup.job_titles.update', $jobTitle) }}" class="inline">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="is_active" value="{{ $jobTitle->is_active ? '0' : '1' }}">
                                            <button class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">{{ $jobTitle->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('loan.system.setup.job_titles.destroy', $jobTitle) }}" class="inline" data-swal-confirm="Remove this job title? If currently used, it will only be deactivated.">
                                            @csrf
                                            @method('delete')
                                            <button class="text-red-600 hover:text-red-500 font-medium text-sm">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No job titles yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
