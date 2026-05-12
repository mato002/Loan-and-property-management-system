<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup.loan_products') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to products</a>
        </x-slot>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Identifiers</h2>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Product name</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ $product->name }}</dd>
                </div>
                @if (filled($product->product_code ?? null))
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Product code</dt>
                        <dd class="mt-0.5 font-mono text-sm text-slate-900">{{ $product->product_code }}</dd>
                    </div>
                @endif
                @if (filled($product->status ?? null))
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status (directory)</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ $product->status }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $product->description ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created</dt>
                    <dd class="mt-0.5 text-slate-800">{{ optional($product->created_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last updated</dt>
                    <dd class="mt-0.5 text-slate-800">{{ optional($product->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        @include('loan.system.setup.partials.loan_product_list_card', [
            'product' => $product,
            'hasProductCharges' => $hasProductCharges ?? false,
            'activeLoanCounts' => [$product->name => $activeLoansCount],
            'showToolbar' => false,
        ])

        <p class="mt-4 text-xs text-slate-500">
            To change this product, return to the product list and use <span class="font-semibold text-slate-700">Edit</span> (opens the editor).
        </p>
    </x-loan.page>
</x-loan-layout>
