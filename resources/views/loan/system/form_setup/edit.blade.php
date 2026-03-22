<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ $backLabel }}
            </a>
            @if (! empty($settingsUrl))
                <a href="{{ $settingsUrl }}" class="inline-flex items-center gap-2 rounded-lg border-2 border-indigo-500 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $settingsLabel }}
                </a>
            @endif
            @if (! empty($alternateSetupUrl))
                <a href="{{ $alternateSetupUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-800 hover:bg-indigo-100">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $alternateSetupLabel }}
                </a>
            @endif
        </x-slot>
        @include('loan.accounting.partials.flash')

        <p class="text-sm text-slate-600 max-w-4xl mb-4">{{ $introText }}</p>

        <script>
            function loanFormSetupConfig(initial, opts) {
                opts = opts || {};
                return {
                    showPrefillColumn: opts.showPrefill !== false,
                    rows: (initial || []).map((f) => ({
                        id: f.id,
                        label: f.label,
                        data_type: f.data_type,
                        select_options: f.select_options || '',
                        prefill: !!f.prefill_from_previous,
                        is_core: !!f.is_core,
                        _key: f.id ? 'id-' + f.id : 'k-' + Math.random().toString(36).slice(2),
                    })),
                    addRow() {
                        this.rows.push({
                            id: null,
                            label: '',
                            data_type: 'alphanumeric',
                            select_options: '',
                            prefill: false,
                            is_core: false,
                            _key: 'k-' + Date.now(),
                        });
                    },
                    async removeRow(index) {
                        const row = this.rows[index];
                        if (row.is_core) {
                            return;
                        }
                        if (typeof Swal !== 'undefined') {
                            const r = await Swal.fire({
                                icon: 'warning',
                                title: 'Remove field?',
                                text: 'This line will be removed when you save.',
                                showCancelButton: true,
                                confirmButtonColor: '#2f4f4f',
                                cancelButtonColor: '#64748b',
                                confirmButtonText: 'Yes, remove',
                                cancelButtonText: 'Cancel',
                            });
                            if (!r.isConfirmed) {
                                return;
                            }
                        }
                        this.rows.splice(index, 1);
                    },
                };
            }
        </script>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 max-w-5xl" x-data="loanFormSetupConfig(@js($fieldsPayload), { showPrefill: @js($showPrefillColumn) })">
            <form method="post" action="{{ $formActionUrl ?? route($saveRoute) }}" class="space-y-4">
                @csrf
                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="row._key">
                        <div class="grid grid-cols-12 gap-3 items-end border border-slate-200 rounded-lg p-4 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                            <template x-if="showPrefillColumn">
                                <div class="col-span-12 sm:col-span-1 flex justify-center pb-2">
                                    <template x-if="!row.is_core">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer text-[10px] text-slate-500 text-center leading-tight" title="Pre-fill from existing application data">
                                            <input type="checkbox" x-model="row.prefill" class="rounded border-slate-300 text-indigo-600" />
                                            <span>Prior data</span>
                                        </label>
                                    </template>
                                    <template x-if="row.is_core">
                                        <span class="text-[10px] text-slate-400 text-center leading-tight">Core</span>
                                    </template>
                                </div>
                            </template>
                            <div class="col-span-12" :class="showPrefillColumn ? 'sm:col-span-4' : 'sm:col-span-5'">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <label class="block text-xs font-semibold text-slate-600">Field label</label>
                                    <template x-if="!showPrefillColumn && row.is_core">
                                        <span class="text-[10px] font-semibold text-amber-900 bg-amber-100 px-1.5 py-0.5 rounded">Core</span>
                                    </template>
                                </div>
                                <input type="text" x-model="row.label" :name="`fields[${index}][label]`" required class="w-full rounded-lg border-slate-200 text-sm" :placeholder="row.label || 'Field name'" />
                            </div>
                            <div class="col-span-12 sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Data type</label>
                                <select x-model="row.data_type" :name="`fields[${index}][data_type]`" required class="w-full rounded-lg border-slate-200 text-sm">
                                    @foreach ($dataTypeLabels as $value => $dtLabel)
                                        <option value="{{ $value }}">{{ $dtLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-12" :class="showPrefillColumn ? 'sm:col-span-3' : 'sm:col-span-3'" x-show="row.data_type === 'select'" x-cloak>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Dropdown options</label>
                                <textarea
                                    x-model="row.select_options"
                                    :name="row.data_type === 'select' ? `fields[${index}][select_options]` : '_skip_options_' + index"
                                    :disabled="row.data_type !== 'select'"
                                    rows="2"
                                    placeholder="Comma-separated values"
                                    class="w-full rounded-lg border-slate-200 text-xs disabled:opacity-50"
                                ></textarea>
                            </div>
                            <div class="col-span-12 sm:col-span-1 flex justify-end pb-1">
                                <template x-if="!row.is_core">
                                    <button type="button" @click="removeRow(index)" class="p-2 rounded-lg text-red-600 hover:bg-red-50" title="Remove row">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </template>
                            </div>
                            <input type="hidden" :name="`fields[${index}][id]`" :value="row.id || ''" />
                            <input type="hidden" :name="`fields[${index}][prefill_from_previous]`" :value="showPrefillColumn && !row.is_core && row.prefill ? 1 : 0" />
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap gap-3 pt-4 border-t border-slate-200">
                    <button type="button" @click="addRow()" class="inline-flex items-center gap-2 rounded-lg border-2 border-indigo-500 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add field
                    </button>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 shadow-sm">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
