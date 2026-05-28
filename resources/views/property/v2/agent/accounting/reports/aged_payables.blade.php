<x-property.workspace
    title="Aged payables"
    subtitle="Supplier/landlord obligations by aging buckets."
    back-route="property.accounting.index"
    :stats="[['label' => 'Payable rows', 'value' => (string) count($rows), 'hint' => 'Supplier-side']]"
    :columns="['Supplier/Landlord', '0-30', '31-60', '61-90', '90+', 'Amount']"
    :table-rows="collect($rows)->map(function($r){
        $amount = (float) ($r->amount ?? 0);
        $days = isset($r->invoice_date) ? max(0, \Carbon\Carbon::parse($r->invoice_date)->diffInDays(now(), false) * -1) : 0;
        return [
            (string) ($r->supplier_name ?? '—'),
            $days <= 30 ? \App\Services\Property\PropertyMoney::kes($amount) : \App\Services\Property\PropertyMoney::kes(0),
            $days > 30 && $days <= 60 ? \App\Services\Property\PropertyMoney::kes($amount) : \App\Services\Property\PropertyMoney::kes(0),
            $days > 60 && $days <= 90 ? \App\Services\Property\PropertyMoney::kes($amount) : \App\Services\Property\PropertyMoney::kes(0),
            $days > 90 ? \App\Services\Property\PropertyMoney::kes($amount) : \App\Services\Property\PropertyMoney::kes(0),
            \App\Services\Property\PropertyMoney::kes($amount),
        ];
    })->all()"
/>

