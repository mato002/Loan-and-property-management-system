<x-property.workspace
    :title="$reportTitle"
    :subtitle="$reportGroup"
    back-route="property.reports.center"
    :stats="[
        ['label' => 'Group', 'value' => $reportGroup, 'hint' => 'Reports module'],
        ['label' => 'Status', 'value' => 'Ready', 'hint' => 'Resource page'],
    ]"
    :columns="[]"
>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-700">
            This is the dedicated resource page for <span class="font-semibold">{{ $reportTitle }}</span>.
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a
                href="{{ route($legacyRoute, [], false) }}"
                data-turbo-frame="property-main"
                class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                Open source module data
            </a>
            <a
                href="{{ route('property.reports.center', [], false) }}"
                data-turbo-frame="property-main"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Back to reports
            </a>
        </div>
    </div>
</x-property.workspace>
