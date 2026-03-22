<x-property-layout>
    <x-slot name="header">Explore</x-slot>

    <x-property.page
        title="Explore"
        subtitle="Growth lever — available units and listings; later, curated investment opportunities."
    >
        <div class="rounded-2xl border border-teal-200/70 dark:border-teal-900/50 bg-teal-50/40 dark:bg-teal-950/15 p-6 text-sm text-slate-700 dark:text-slate-300">
            <p>No listings personalized for you yet.</p>
            <a href="{{ route('public.properties') }}" class="inline-block mt-3 text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">Browse public properties →</a>
        </div>
    </x-property.page>
</x-property-layout>
