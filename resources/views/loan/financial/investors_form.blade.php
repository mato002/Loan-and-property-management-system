<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.investors_list') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to investors
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-2xl">
            <form method="post" action="{{ $action }}" class="px-5 py-6 space-y-4">
                @csrf
                @if ($method === 'patch')
                    @method('patch')
                @endif
                <div x-data="{ open: false, saving: false, error: '', name: '', rate_label: '', minimum_label: '', status: 'draft' }">
                    <div class="flex items-end gap-2">
                        <div class="flex-1 min-w-0">
                    <label for="investment_package_id" class="block text-xs font-semibold text-slate-600 mb-1">Package</label>
                    <select id="investment_package_id" name="investment_package_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">— None —</option>
                        @foreach ($packages as $pkg)
                            <option value="{{ $pkg->id }}" @selected((string) old('investment_package_id', $investor->investment_package_id) === (string) $pkg->id)>{{ $pkg->name }}</option>
                        @endforeach
                    </select>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                            @click="open = true; error = ''; name = ''; rate_label = ''; minimum_label = ''; status = 'draft';"
                            title="Add package"
                        >
                            + Package
                        </button>
                    </div>
                    @error('investment_package_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

                    <!-- Modal -->
                    <div x-show="open" x-cloak class="fixed inset-0 z-50">
                        <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
                        <div class="absolute inset-0 flex items-center justify-center p-4">
                            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl border border-slate-200">
                                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-900">Create package</h3>
                                    <button type="button" class="text-slate-500 hover:text-slate-700" @click="open = false">✕</button>
                                </div>
                                <div class="p-5 space-y-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Package name</label>
                                        <input type="text" class="w-full rounded-lg border-slate-200 text-sm" x-model="name" placeholder="e.g. Fixed 12% - 6 months" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Rate label</label>
                                        <input type="text" class="w-full rounded-lg border-slate-200 text-sm" x-model="rate_label" placeholder="e.g. 12% p.a." />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Minimum label</label>
                                        <input type="text" class="w-full rounded-lg border-slate-200 text-sm" x-model="minimum_label" placeholder="e.g. KES 50,000" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                                        <select class="w-full rounded-lg border-slate-200 text-sm" x-model="status">
                                            <option value="draft">Draft</option>
                                            <option value="active">Active</option>
                                        </select>
                                    </div>

                                    <template x-if="error">
                                        <p class="text-xs text-red-600" x-text="error"></p>
                                    </template>
                                </div>
                                <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="open = false" :disabled="saving">Cancel</button>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040] disabled:opacity-60"
                                        :disabled="saving"
                                        @click="
                                            saving = true;
                                            error = '';
                                            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                                            fetch('{{ route('loan.financial.packages.store') }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': token,
                                                },
                                                body: JSON.stringify({ name, rate_label, minimum_label, status }),
                                            })
                                            .then(async (r) => {
                                                const json = await r.json().catch(() => ({}));
                                                if (!r.ok) {
                                                    const msg = json?.message || 'Failed to create package.';
                                                    const firstErr = json?.errors ? Object.values(json.errors)[0]?.[0] : '';
                                                    throw new Error(firstErr || msg);
                                                }
                                                return json;
                                            })
                                            .then((json) => {
                                                const pkg = json.package;
                                                const sel = document.getElementById('investment_package_id');
                                                const opt = document.createElement('option');
                                                opt.value = pkg.id;
                                                opt.textContent = pkg.name;
                                                sel.appendChild(opt);
                                                sel.value = String(pkg.id);
                                                open = false;
                                            })
                                            .catch((e) => { error = e.message || 'Failed to create package.'; })
                                            .finally(() => { saving = false; });
                                        "
                                    >
                                        <span x-show="!saving">Create</span>
                                        <span x-show="saving" x-cloak>Creating…</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input id="name" name="name" value="{{ old('name', $investor->name) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $investor->email) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $investor->phone) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="committed_amount" class="block text-xs font-semibold text-slate-600 mb-1">Committed amount</label>
                        <input id="committed_amount" name="committed_amount" type="number" step="0.01" min="0" value="{{ old('committed_amount', $investor->committed_amount) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('committed_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="accrued_interest" class="block text-xs font-semibold text-slate-600 mb-1">Accrued interest</label>
                        <input id="accrued_interest" name="accrued_interest" type="number" step="0.01" min="0" value="{{ old('accrued_interest', $investor->accrued_interest ?? 0) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('accrued_interest')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="maturity_date" class="block text-xs font-semibold text-slate-600 mb-1">Maturity date</label>
                    <input id="maturity_date" name="maturity_date" type="date" value="{{ old('maturity_date', optional($investor->maturity_date)->format('Y-m-d')) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('maturity_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        {{ $method === 'patch' ? 'Update investor' : 'Save investor' }}
                    </button>
                    <a href="{{ route('loan.financial.investors_list') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
