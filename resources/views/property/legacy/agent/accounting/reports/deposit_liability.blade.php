<x-property.workspace
    title="Deposit liability report"
    subtitle="Tenant deposit held, used, and remaining liability."
    back-route="property.accounting.index"
    :stats="[['label' => 'Deposit rows', 'value' => (string) count($rows), 'hint' => 'Tenant deposit ledger']]"
    :columns="['Tenant', 'Deposit Held', 'Used', 'Balance']"
    :table-rows="collect($rows)->map(function($r){
        $held = (float) ($r->amount ?? 0);
        $used = strtolower((string) ($r->status ?? '')) === 'used' ? $held : 0.0;
        $bal = max(0, $held - $used);
        return [
            (string) ($r->tenant_name ?? '—'),
            \App\Services\Property\PropertyMoney::kes($held),
            \App\Services\Property\PropertyMoney::kes($used),
            \App\Services\Property\PropertyMoney::kes($bal),
        ];
    })->all()"
/>

