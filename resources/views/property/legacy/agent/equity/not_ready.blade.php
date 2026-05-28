<x-property-layout>
    <x-slot name="header">Equity Integration</x-slot>

    <x-property.page
        title="Equity integration not ready"
        subtitle="This page is available, but required database structures are not yet in place."
    >
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
            <p class="text-sm font-semibold">Action needed</p>
            <p class="mt-1 text-sm">{{ $reason }}</p>
            <p class="mt-3 text-sm">
                Run:
                <code class="rounded bg-white px-2 py-1 text-xs">php artisan migrate</code>
            </p>
        </div>
    </x-property.page>
</x-property-layout>

