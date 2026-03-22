<x-property-layout>
    <x-slot name="header">Payment configs</x-slot>

    <x-property.page
        title="Payment configs"
        subtitle="M-Pesa till / paybill, STK short codes, settlement accounts, and bank rails — secrets stored encrypted."
    >
        <div class="grid gap-6 lg:grid-cols-2 w-full min-w-0">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4 min-w-0">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">M-Pesa (collection)</h2>
                <div class="space-y-3">
                    <label class="block text-xs font-medium text-slate-500">Paybill / till</label>
                    <input type="text" class="w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="Not configured" />
                    <label class="block text-xs font-medium text-slate-500">Consumer key / secret</label>
                    <input type="password" class="w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="••••••••" autocomplete="off" />
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-4 min-w-0">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Bank settlement</h2>
                <div class="space-y-3">
                    <label class="block text-xs font-medium text-slate-500">Trust account</label>
                    <input type="text" class="w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 text-sm px-3 py-2" placeholder="Bank · account" />
                    <label class="block text-xs font-medium text-slate-500">Reconciliation import</label>
                    <form method="post" action="{{ route('property.quick_action.store') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                        @csrf
                        <input type="hidden" name="action_key" value="bank_statement_upload" />
                        <input type="file" name="attachment" accept=".csv,.txt,text/csv" required class="text-sm text-slate-600 dark:text-slate-300 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 dark:file:bg-slate-800" />
                        <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 shrink-0">Upload CSV / text</button>
                    </form>
                </div>
            </div>
        </div>
        <p class="text-xs text-slate-500">Draft values here are for UI only; persist via your secrets vault when the payment API is wired.</p>
        <div class="mt-4">
            <a href="{{ route('property.settings.index') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">← Back to settings</a>
        </div>
    </x-property.page>
</x-property-layout>
