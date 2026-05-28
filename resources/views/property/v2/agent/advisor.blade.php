<x-property-layout>
    <x-slot name="header">AI advisor</x-slot>

    <x-property.page
        title="Ask anything"
        subtitle="Rule-based answers from your live portfolio counts, with quick prompts and recent chat memory."
    >
        <div class="max-w-2xl w-full min-w-0 space-y-6">
            <form method="post" action="{{ route('property.advisor.ask') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-3">
                @csrf
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="ai-q">Your question</label>
                <textarea
                    id="ai-q"
                    name="question"
                    rows="3"
                    required
                    class="w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-4 py-3 text-slate-800 dark:text-slate-200"
                    placeholder="e.g. What is outstanding in arrears? How many unmatched payments?"
                >{{ old('question', $lastQuestion ?? '') }}</textarea>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="pm-advisor-prompt rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="How many unmatched payments do we have?">Unmatched payments</button>
                    <button type="button" class="pm-advisor-prompt rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="How many open maintenance requests do we have?">Open maintenance</button>
                    <button type="button" class="pm-advisor-prompt rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="How many failed communication logs do we have?">Failed communications</button>
                    <button type="button" class="pm-advisor-prompt rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="What should I check for rent collection report?">Rent collection report</button>
                </div>
                @error('question')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">Get answer</button>
            </form>

            @if (session('advisor_answer'))
                <div class="rounded-2xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/60 dark:bg-emerald-950/30 p-5 text-sm text-slate-800 dark:text-slate-200">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Answer</p>
                    <p class="mt-2 leading-relaxed">{{ session('advisor_answer') }}</p>
                </div>
            @endif

            @if (!empty($history))
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recent questions</p>
                    <div class="mt-3 space-y-3">
                        @foreach (array_reverse($history) as $item)
                            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 p-3">
                                <p class="text-[11px] text-slate-500">{{ $item['at'] ?? '' }}</p>
                                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $item['q'] ?? '' }}</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $item['a'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Try asking about</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                    <li class="flex gap-2"><span class="text-violet-500">→</span> arrears, balance, or what tenants owe</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> vacant units or vacancy</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> listings, publish, or Discover</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> invoices</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> unmatched payments (Equity / M-Pesa)</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> maintenance open requests</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> failed communication logs</li>
                </ul>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('ai-q');
    document.querySelectorAll('.pm-advisor-prompt').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!input) return;
            input.value = btn.getAttribute('data-prompt') || '';
            input.focus();
        });
    });
});
</script>