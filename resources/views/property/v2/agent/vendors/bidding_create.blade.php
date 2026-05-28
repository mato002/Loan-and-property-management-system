<x-property-layout>
    <x-slot name="header">Create RFQ</x-slot>

    <x-property.page
        title="Create RFQ"
        subtitle="Describe scope, access, and deadline. This draft is logged for audit until RFQs are stored as first-class records."
    >
        <div class="flex flex-wrap items-center gap-3">
            <a
                href="{{ route('property.vendors.bidding') }}"
                class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
            >
                ← Back to job bidding
            </a>
        </div>

        <form
            method="post"
            action="{{ route('property.vendors.bidding.store') }}"
            class="space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm w-full max-w-2xl min-w-0"
        >
            @csrf
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/40 dark:border-red-800 px-3 py-2 text-sm text-red-800 dark:text-red-200">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="property_unit">Property / unit</label>
                <input
                    type="text"
                    id="property_unit"
                    name="property_unit"
                    value="{{ old('property_unit') }}"
                    placeholder="e.g. Oak Court / Unit 12B"
                    class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="scope">Scope of work</label>
                <textarea
                    id="scope"
                    name="scope"
                    required
                    rows="6"
                    placeholder="What needs quoting? Materials, constraints, photos to attach later…"
                    class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                >{{ old('scope') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="deadline">Quote deadline</label>
                <input
                    type="date"
                    id="deadline"
                    name="deadline"
                    value="{{ old('deadline') }}"
                    class="mt-1 w-full min-w-0 sm:max-w-xs rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="access_notes">Access window &amp; contacts</label>
                <textarea
                    id="access_notes"
                    name="access_notes"
                    rows="3"
                    placeholder="Key pickup, gate codes, tenant availability…"
                    class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                >{{ old('access_notes') }}</textarea>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 pt-2">
                <button
                    type="submit"
                    class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Save RFQ draft
                </button>
                <a
                    href="{{ route('property.vendors.bidding') }}"
                    class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                >
                    Cancel
                </a>
            </div>
        </form>
    </x-property.page>
</x-property-layout>
