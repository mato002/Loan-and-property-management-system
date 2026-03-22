<x-property-layout>
    <x-slot name="header">AI advisor</x-slot>

    <x-property.page
        title="Ask anything"
        subtitle="Natural-language layer on top of your portfolio — wire your LLM and tools when ready."
    >
        <div class="max-w-2xl w-full min-w-0 space-y-6">
            <form method="post" action="{{ route('property.quick_action.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-3">
                @csrf
                <input type="hidden" name="action_key" value="ai_advisor_query" />
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="ai-q">Your question</label>
                <textarea id="ai-q" name="notes" rows="3" required class="w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-4 py-3 text-slate-800 dark:text-slate-200" placeholder="e.g. Which units are 30+ days in arrears and who last contacted them?"></textarea>
                <button type="submit" class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">Save question</button>
                <p class="text-xs text-slate-500">Stored for your team; connect an LLM to answer from portfolio data.</p>
            </form>

            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Suggested queries</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                    <li class="flex gap-2"><span class="text-violet-500">→</span> Summarize this week’s collections vs rent roll.</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> List vacancies over 21 days with asking rent.</li>
                    <li class="flex gap-2"><span class="text-violet-500">→</span> What maintenance jobs exceeded quote by more than 15%?</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-violet-200/80 dark:border-violet-900/40 bg-violet-50/40 dark:bg-violet-950/20 p-5 text-sm text-slate-700 dark:text-slate-300">
                <p class="font-medium text-violet-900 dark:text-violet-200">Insights (placeholder)</p>
                <p class="mt-2 text-slate-600 dark:text-slate-400">Once connected, show proactive cards: expiring leases, SLA risk on tickets, and revenue at risk.</p>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
