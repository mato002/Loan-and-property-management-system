<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup.loan_products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">+ Add product</a>
            <a href="{{ route('loan.system.setup') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to setup</a>
        </x-slot>

        @php
            $productModalPayload = $products->mapWithKeys(function ($product) use ($activeLoanCounts) {
                return [
                    (string) $product->id => [
                        'id' => (int) $product->id,
                        'name' => (string) $product->name,
                        'description' => (string) ($product->description ?? ''),
                        'default_interest_rate' => $product->default_interest_rate !== null ? (float) $product->default_interest_rate : null,
                        'default_term_months' => $product->default_term_months !== null ? (int) $product->default_term_months : null,
                        'default_interest_rate_period' => (string) ($product->default_interest_rate_period ?? 'annual'),
                        'default_term_unit' => (string) ($product->default_term_unit ?? 'monthly'),
                        'is_active' => (bool) $product->is_active,
                        'active_loans' => (int) ($activeLoanCounts[$product->name] ?? 0),
                    ],
                ];
            })->all();
            $updateActionTemplate = route('loan.system.setup.loan_products.update', ['loan_product' => '__PRODUCT_ID__']);
        @endphp

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Product pricing master</h2>
                <p class="text-xs text-slate-500">{{ $products->count() }} product(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1280px] w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Default interest</th>
                            <th class="px-4 py-3 text-right">Default term</th>
                            <th class="px-4 py-3">Charges</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Edit</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50/70 align-top">
                                <td class="px-4 py-3 font-semibold text-slate-800 whitespace-nowrap">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $product->description ?: '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700 whitespace-nowrap">
                                    @if ($product->default_interest_rate !== null)
                                        {{ number_format((float) $product->default_interest_rate, 4) }}% / {{ $product->default_interest_rate_period ?? 'annual' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700 whitespace-nowrap">
                                    @if ($product->default_term_months !== null)
                                        {{ $product->default_term_months }} {{ $product->default_term_unit ?? 'monthly' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    @if (($hasProductCharges ?? false) && $product->charges->isNotEmpty())
                                        <div class="space-y-1">
                                            @foreach ($product->charges as $charge)
                                                <div>
                                                    <span class="font-semibold text-slate-700">{{ $charge->charge_name }}</span>
                                                    <span class="text-slate-500">
                                                        —
                                                        {{ $charge->amount_type === 'percent' ? number_format((float) $charge->amount, 4).'%' : number_format((float) $charge->amount, 2) }}
                                                        · {{ str_replace('_', ' ', $charge->applies_to_stage) }}
                                                        · {{ str_replace('_', ' ', $charge->applies_to_client_scope) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="js-edit-product rounded border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                        data-product-id="{{ $product->id }}"
                                    >
                                        Edit
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <form method="post" action="{{ route('loan.system.setup.loan_products.destroy', $product) }}" class="inline" data-swal-confirm="Remove this loan product?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-xs hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">No loan products defined yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <dialog id="product-edit-modal" class="w-full max-w-3xl rounded-xl border border-slate-200 p-0 shadow-xl backdrop:bg-slate-900/40">
            <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-800">Edit loan product</h3>
                <button id="close-edit-product-modal" type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700">✕</button>
            </div>
            <form id="product-edit-form" method="post" class="p-4 grid grid-cols-1 gap-3 md:grid-cols-10 js-product-update-form">
                @csrf
                @method('patch')
                <input id="edit_name" name="name" class="md:col-span-3 rounded border-slate-200 px-2 py-1.5 text-xs font-medium text-slate-800" placeholder="Product name" />
                <input id="edit_description" name="description" class="md:col-span-3 rounded border-slate-200 px-2 py-1.5 text-xs" placeholder="Description" />
                <input id="edit_default_interest_rate" name="default_interest_rate" type="number" step="0.0001" min="0" max="100" class="rounded border-slate-200 px-2 py-1.5 text-xs tabular-nums text-right" placeholder="Rate %" />
                <input id="edit_default_term_months" name="default_term_months" type="number" min="1" max="600" class="rounded border-slate-200 px-2 py-1.5 text-xs tabular-nums text-right" placeholder="Length" />
                <select id="edit_default_interest_rate_period" name="default_interest_rate_period" class="rounded border-slate-200 px-2 py-1.5 text-xs">
                    <option value="daily">Per day</option>
                    <option value="weekly">Per week</option>
                    <option value="monthly">Per month</option>
                    <option value="annual">Per year</option>
                </select>
                <select id="edit_default_term_unit" name="default_term_unit" class="rounded border-slate-200 px-2 py-1.5 text-xs">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <select id="edit_is_active" name="is_active" class="rounded border-slate-200 px-2 py-1.5 text-xs">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
                <input id="edit_repricing_effective_date" name="repricing_effective_date" type="date" class="rounded border-slate-200 px-2 py-1.5 text-xs" title="Optional effective date for audit note" />
                <input id="edit_repricing_note" name="repricing_note" class="md:col-span-3 rounded border-slate-200 px-2 py-1.5 text-xs" placeholder="Optional repricing note" />
                <label class="md:col-span-5 inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-800">
                    <input id="edit_apply_to_existing_active_loans" type="checkbox" name="apply_to_existing_active_loans" value="1" class="rounded border-amber-300 text-amber-700 focus:ring-amber-500 js-apply-existing" />
                    Apply rate to existing active loans
                </label>
                <div class="md:col-span-5 flex justify-end gap-2">
                    <button type="button" id="cancel-edit-product-modal" class="rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Update</button>
                </div>
            </form>
        </dialog>
    </x-loan.page>
</x-loan-layout>
<script>
    const productModalPayload = @json($productModalPayload);
    const updateActionTemplate = @json($updateActionTemplate);
    const editModal = document.getElementById('product-edit-modal');
    const editForm = document.getElementById('product-edit-form');
    const closeEditBtn = document.getElementById('close-edit-product-modal');
    const cancelEditBtn = document.getElementById('cancel-edit-product-modal');

    document.querySelectorAll('.js-edit-product').forEach((btn) => {
        btn.addEventListener('click', () => {
            const productId = String(btn.dataset.productId || '');
            const product = productModalPayload[productId];
            if (!product || !editForm || !editModal) return;

            editForm.action = String(updateActionTemplate).replace('__PRODUCT_ID__', productId);
            editForm.dataset.productName = product.name || 'this product';
            editForm.dataset.activeLoans = String(product.active_loans || 0);
            editForm.querySelector('#edit_name').value = product.name ?? '';
            editForm.querySelector('#edit_description').value = product.description ?? '';
            editForm.querySelector('#edit_default_interest_rate').value = product.default_interest_rate ?? '';
            editForm.querySelector('#edit_default_term_months').value = product.default_term_months ?? '';
            editForm.querySelector('#edit_default_interest_rate_period').value = product.default_interest_rate_period ?? 'annual';
            editForm.querySelector('#edit_default_term_unit').value = product.default_term_unit ?? 'monthly';
            editForm.querySelector('#edit_is_active').value = product.is_active ? '1' : '0';
            editForm.querySelector('#edit_repricing_effective_date').value = '';
            editForm.querySelector('#edit_repricing_note').value = '';
            editForm.querySelector('#edit_apply_to_existing_active_loans').checked = false;
            editModal.showModal();
        });
    });

    [closeEditBtn, cancelEditBtn].forEach((el) => {
        el?.addEventListener('click', () => editModal?.close());
    });

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
            const wasEditModalOpen = !!(editModal && typeof editModal.open === 'boolean' && editModal.open);

            if (window.Swal && typeof window.Swal.fire === 'function') {
                // <dialog> is rendered in the browser top layer and can cover SweetAlert.
                // Temporarily close it so the confirmation dialog stays clickable.
                if (wasEditModalOpen) {
                    editModal.close();
                }

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
                    return;
                }

                if (wasEditModalOpen) {
                    editModal.showModal();
                }

                return;
            }

            if (window.confirm(message)) {
                form.submit();
                return;
            }

            if (wasEditModalOpen) {
                editModal.showModal();
            }
        });
    });
</script>
