<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to setup</a>
        </x-slot>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Add / update product</h2>
            <form method="post" action="{{ route('loan.system.setup.loan_products.store') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-5">
                @csrf
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Product name</label>
                    <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Default interest % p.a.</label>
                    <input name="default_interest_rate" type="number" step="0.0001" min="0" max="100" value="{{ old('default_interest_rate') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Default term (months)</label>
                    <input name="default_term_months" type="number" min="1" max="600" value="{{ old('default_term_months') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Active</label>
                    <select name="is_active" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="1" @selected(old('is_active', '1') === '1')>Yes</option>
                        <option value="0" @selected(old('is_active') === '0')>No</option>
                    </select>
                </div>
                <div class="sm:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Description (optional)</label>
                    <input name="description" value="{{ old('description') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Save product</button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Product pricing master</h2>
                <p class="text-xs text-slate-500">{{ $products->count() }} product(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3 text-right">Default interest %</th>
                            <th class="px-5 py-3 text-right">Default term</th>
                            <th class="px-5 py-3">Active</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $product->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $product->description ?: '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ $product->default_interest_rate !== null ? number_format((float) $product->default_interest_rate, 4) : '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ $product->default_term_months ?? '—' }}</td>
                                <td class="px-5 py-3">{{ $product->is_active ? 'Yes' : 'No' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <form
                                        method="post"
                                        action="{{ route('loan.system.setup.loan_products.update', $product) }}"
                                        class="mb-2 grid grid-cols-1 gap-1 sm:grid-cols-4 js-product-update-form"
                                        data-product-name="{{ $product->name }}"
                                        data-active-loans="{{ (int) (($activeLoanCounts[$product->name] ?? 0)) }}"
                                    >
                                        @csrf
                                        @method('patch')
                                        <input name="name" value="{{ $product->name }}" class="sm:col-span-2 rounded border-slate-200 px-2 py-1 text-xs font-medium text-slate-800" placeholder="Product name" />
                                        <input name="description" value="{{ $product->description }}" class="sm:col-span-2 rounded border-slate-200 px-2 py-1 text-xs" placeholder="Description" />
                                        <input name="default_interest_rate" type="number" step="0.0001" min="0" max="100" value="{{ $product->default_interest_rate }}" class="rounded border-slate-200 px-2 py-1 text-xs tabular-nums text-right" placeholder="Rate %" />
                                        <input name="default_term_months" type="number" min="1" max="600" value="{{ $product->default_term_months }}" class="rounded border-slate-200 px-2 py-1 text-xs tabular-nums text-right" placeholder="Months" />
                                        <label class="sm:col-span-2 inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800">
                                            <input type="checkbox" name="apply_to_existing_active_loans" value="1" class="rounded border-amber-300 text-amber-700 focus:ring-amber-500 js-apply-existing" />
                                            Apply rate to existing active loans
                                        </label>
                                        <input name="repricing_effective_date" type="date" class="rounded border-slate-200 px-2 py-1 text-xs" title="Optional effective date for audit note" />
                                        <input name="repricing_note" class="rounded border-slate-200 px-2 py-1 text-xs sm:col-span-1" placeholder="Optional repricing note" />
                                        <select name="is_active" class="rounded border-slate-200 px-2 py-1 text-xs sm:col-span-2">
                                            <option value="1" @selected($product->is_active)>Active</option>
                                            <option value="0" @selected(! $product->is_active)>Inactive</option>
                                        </select>
                                        <button type="submit" class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 sm:col-span-2">Update</button>
                                    </form>
                                    <form method="post" action="{{ route('loan.system.setup.loan_products.destroy', $product) }}" class="inline" data-swal-confirm="Remove this loan product?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No loan products defined yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
<script>
    document.querySelectorAll('.js-product-update-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            const applyCheckbox = form.querySelector('.js-apply-existing');
            if (!applyCheckbox?.checked) {
                return;
            }

            event.preventDefault();
            const productName = form.dataset.productName || 'this product';
            const activeLoans = Number(form.dataset.activeLoans || '0');
            const message = `This will reprice ${activeLoans} active/restructured loan(s) for "${productName}". Continue?`;

            if (window.Swal && typeof window.Swal.fire === 'function') {
                const result = await window.Swal.fire({
                    title: 'Apply rate update?',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, apply',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    confirmButtonColor: '#b45309',
                });

                if (result.isConfirmed) {
                    form.submit();
                }

                return;
            }

            if (window.confirm(message)) {
                form.submit();
            }
        });
    });
</script>
