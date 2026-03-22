<x-property.workspace
    title="Tenant reliability scoring"
    subtitle="Later phase — transparent, consent-based behavioral signals; no hidden black-box scores in production."
    back-route="property.performance.index"
    :stats="[
        ['label' => 'Model status', 'value' => 'Off', 'hint' => 'Not configured'],
        ['label' => 'Features planned', 'value' => '0', 'hint' => 'Registered'],
    ]"
    :columns="['Tenant', 'Score', 'Drivers', 'Last computed', 'Human review', 'Actions']"
    empty-title="Scoring not enabled"
    empty-hint="When you enable this: document data sources, retention, appeal path, and landlord visibility rules (GDPR / consent)."
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
