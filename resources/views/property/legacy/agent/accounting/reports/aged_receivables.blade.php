<x-property.workspace
    title="Aged receivables"
    subtitle="Receivable balances split by overdue buckets."
    back-route="property.accounting.index"
    :stats="[['label' => 'Open receivables', 'value' => (string) count($rows), 'hint' => 'Invoice-level']]"
    :columns="['Tenant', 'Property', '0-30', '31-60', '61-90', '90+', 'Balance']"
    :table-rows="collect($rows)->map(function($r){
        $b = (float) $r['balance'];
        $d = (int) $r['days'];
        return [
            optional($r['invoice']->tenant)->name ?? '—',
            optional(optional($r['invoice']->unit)->property)->name ?? '—',
            $d <= 30 ? \App\Services\Property\PropertyMoney::kes($b) : \App\Services\Property\PropertyMoney::kes(0),
            $d > 30 && $d <= 60 ? \App\Services\Property\PropertyMoney::kes($b) : \App\Services\Property\PropertyMoney::kes(0),
            $d > 60 && $d <= 90 ? \App\Services\Property\PropertyMoney::kes($b) : \App\Services\Property\PropertyMoney::kes(0),
            $d > 90 ? \App\Services\Property\PropertyMoney::kes($b) : \App\Services\Property\PropertyMoney::kes(0),
            \App\Services\Property\PropertyMoney::kes($b),
        ];
    })->all()"
/>

