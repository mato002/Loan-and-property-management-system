<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.packages.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New package
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Packages</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Rate</th>
                            <th class="px-5 py-3">Minimum</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($packages as $pkg)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $pkg->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $pkg->rate_label }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $pkg->minimum_label }}</td>
                                <td class="px-5 py-3">
                                    @if ($pkg->status === 'active')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 border border-slate-200">Draft</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('loan.financial.packages.edit', $pkg) }}" class="text-xs font-semibold text-indigo-600 hover:underline">Edit</a>
                                        <form method="post" action="{{ route('loan.financial.packages.destroy', $pkg) }}" class="inline" data-swal-confirm="Delete this package?">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                    No packages yet. <a href="{{ route('loan.financial.packages.create') }}" class="text-indigo-600 font-medium hover:underline">Create one</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($packages->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $packages->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
