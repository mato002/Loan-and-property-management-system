<x-loan-layout>
    <x-loan.page
        title="Daily workplan"
        subtitle="Tasks are saved per user. Pick a date to review or plan another day."
    >
        <x-slot name="actions">
            <form method="get" action="{{ route('loan.employees.workplan') }}" class="flex flex-wrap items-center gap-2">
                <label for="workplan-date" class="sr-only">Date</label>
                <input id="workplan-date" type="date" name="date" value="{{ $today }}" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Go</button>
            </form>
        </x-slot>

        <x-input-error class="mb-2" :messages="$errors->get('title')" />
        <x-input-error class="mb-2" :messages="$errors->get('work_date')" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-4">Priorities — {{ \Illuminate\Support\Carbon::parse($today)->format('l, M j, Y') }}</h2>

                    <form method="post" action="{{ route('loan.employees.workplan.items.store') }}" class="flex flex-col sm:flex-row gap-2 mb-6">
                        @csrf
                        <input type="hidden" name="work_date" value="{{ $today }}" />
                        <input type="text" name="title" value="{{ old('title') }}" required placeholder="Add a task…" class="flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040] shrink-0">Add task</button>
                    </form>

                    <ul class="space-y-3">
                        @forelse ($todayItems as $item)
                            <li class="flex gap-3 items-start border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                                <form method="post" action="{{ route('loan.employees.workplan.items.toggle', $item) }}" class="shrink-0 pt-0.5">
                                    @csrf
                                    <button type="submit" class="flex h-6 w-6 items-center justify-center rounded border text-xs font-bold {{ $item->is_done ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-transparent hover:border-[#2f4f4f]' }}" title="{{ $item->is_done ? 'Mark as not done' : 'Mark done' }}">
                                        ✓
                                    </button>
                                </form>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm leading-snug {{ $item->is_done ? 'text-slate-400 line-through' : 'text-slate-800' }}">{{ $item->title }}</p>
                                    <form method="post" action="{{ route('loan.employees.workplan.items.destroy', $item) }}" class="mt-1" data-swal-confirm="Remove this task?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500 py-4">No tasks for this day. Add one above.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3">Next day — {{ \Illuminate\Support\Carbon::parse($tomorrow)->format('l, M j') }}</h2>
                    <form method="post" action="{{ route('loan.employees.workplan.items.store') }}" class="flex flex-col sm:flex-row gap-2 mb-4">
                        @csrf
                        <input type="hidden" name="work_date" value="{{ $tomorrow }}" />
                        <input type="text" name="title" required placeholder="Plan tomorrow…" class="flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <button type="submit" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 shrink-0">Add</button>
                    </form>
                    <ul class="text-sm text-slate-600 space-y-2">
                        @forelse ($tomorrowItems as $item)
                            <li class="flex items-start gap-2">
                                <form method="post" action="{{ route('loan.employees.workplan.items.toggle', $item) }}" class="shrink-0">
                                    @csrf
                                    <button type="submit" class="text-xs {{ $item->is_done ? 'text-emerald-600' : 'text-slate-400' }} hover:underline">{{ $item->is_done ? '✓' : '○' }}</button>
                                </form>
                                <span class="{{ $item->is_done ? 'line-through text-slate-400' : '' }}">{{ $item->title }}</span>
                            </li>
                        @empty
                            <li class="text-slate-500">Nothing scheduled.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            <div class="space-y-4">
                <div class="bg-[#2f4f4f] text-white rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-white/90 mb-2">Today’s progress</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4 border-b border-white/10 pb-2">
                            <dt class="text-[#8db1af]">Tasks</dt>
                            <dd class="font-semibold tabular-nums">{{ $stats['total'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-white/10 pb-2">
                            <dt class="text-[#8db1af]">Completed</dt>
                            <dd class="font-semibold tabular-nums">{{ $stats['done'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-[#8db1af]">Remaining</dt>
                            <dd class="font-semibold tabular-nums">{{ max(0, $stats['total'] - $stats['done']) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
