<x-loan-layout>
    <x-loan.page
        title="SMS templates"
        subtitle="Reusable message bodies for campaigns and reminders."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.templates.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Create template
            </a>
            <a href="{{ route('loan.bulksms.compose') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Send SMS
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Templates</h2>
                <p class="text-xs text-slate-500">{{ $templates->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3">Body</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($templates as $t)
                            <tr class="hover:bg-slate-50/80 align-top">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $t->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $t->description ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-md">
                                    <span class="line-clamp-2" title="{{ $t->body }}">{{ $t->body }}</span>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.bulksms.compose', ['template' => $t->id]) }}" class="text-xs font-semibold text-indigo-600 hover:underline mr-3">Use</a>
                                    <a href="{{ route('loan.bulksms.templates.edit', $t) }}" class="text-xs font-semibold text-slate-700 hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.bulksms.templates.destroy', $t) }}" class="inline" data-swal-confirm="Delete this template?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                                    No templates. <a href="{{ route('loan.bulksms.templates.create') }}" class="text-indigo-600 font-medium hover:underline">Create the first one</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($templates->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $templates->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
