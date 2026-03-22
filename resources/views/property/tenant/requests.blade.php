<x-property-layout>
    <x-slot name="header">Requests &amp; notices</x-slot>

    <x-property.page
        title="Requests &amp; notices"
        subtitle="Vacate notice and lease extension requests are stored for your property manager."
    >
        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <div class="grid gap-4 lg:grid-cols-2 w-full min-w-0">
            <form method="post" action="{{ route('property.tenant.requests.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4">
                @csrf
                <input type="hidden" name="type" value="vacate_notice" />
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Vacate notice</h2>
                <p class="text-xs text-slate-500">Intended move-out or notice period start.</p>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Preferred date</label>
                    <input type="date" name="preferred_date" value="{{ old('type') === 'vacate_notice' ? old('preferred_date') : '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('preferred_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Message</label>
                    <textarea name="message" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Notice period, reasons, forwarding address…">{{ old('type') === 'vacate_notice' ? old('message') : '' }}</textarea>
                    @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @error('type')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="w-full rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Submit vacate notice</button>
            </form>

            <form method="post" action="{{ route('property.tenant.requests.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4">
                @csrf
                <input type="hidden" name="type" value="lease_extension" />
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Lease extension</h2>
                <p class="text-xs text-slate-500">Ask to renew or extend your current term.</p>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Preferred start / effective date</label>
                    <input type="date" name="preferred_date" value="{{ old('type') === 'lease_extension' ? old('preferred_date') : '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('preferred_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Message</label>
                    <textarea name="message" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Desired term length, rent expectations…">{{ old('type') === 'lease_extension' ? old('message') : '' }}</textarea>
                    @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded-xl border border-teal-600 text-teal-700 dark:text-teal-300 px-4 py-2.5 text-sm font-semibold hover:bg-teal-50 dark:hover:bg-teal-950/30">Request extension</button>
            </form>
        </div>

        @if (($requests ?? collect())->isNotEmpty())
            <div class="mt-8 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 overflow-hidden shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Your recent requests</h2>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($requests as $r)
                        <li class="px-4 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ $r->type === \App\Models\PmTenantPortalRequest::TYPE_VACATE ? 'Vacate notice' : 'Lease extension' }}
                                </span>
                                <span class="text-xs uppercase tracking-wide text-slate-500">{{ $r->status }}</span>
                            </div>
                            @if ($r->preferred_date)
                                <p class="text-xs text-slate-500 mt-1">Preferred: {{ $r->preferred_date->format('Y-m-d') }}</p>
                            @endif
                            @if ($r->message)
                                <p class="text-slate-600 dark:text-slate-300 mt-2 whitespace-pre-wrap">{{ $r->message }}</p>
                            @endif
                            <p class="text-xs text-slate-400 mt-2">{{ $r->created_at->format('Y-m-d H:i') }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-property.page>
</x-property-layout>
