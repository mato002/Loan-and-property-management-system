<x-property.workspace
    title="Tenant reliability scoring"
    subtitle="Directory view with risk flags from tenant profiles — extend with explicit scoring rules when you define them."
    back-route="property.performance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No tenants yet"
    empty-hint="Add tenants from the Tenants workspace; use risk_level as a manual signal until a model is approved."
>
    <x-slot name="footer">
        <p class="font-medium text-slate-800 dark:text-slate-200">Ethical checklist</p>
        <ul class="mt-2 list-disc list-inside space-y-1 text-sm">
            <li>Explain every factor that can move the score.</li>
            <li>Allow tenant dispute and data correction.</li>
            <li>Never use protected characteristics as inputs.</li>
        </ul>
    </x-slot>
</x-property.workspace>
