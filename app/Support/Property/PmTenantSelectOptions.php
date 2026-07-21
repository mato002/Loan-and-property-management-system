<?php

namespace App\Support\Property;

final class PmTenantSelectOptions
{
    /**
     * @return list<array{value:int|string,label:string,search:string,selected?:bool}>
     */
    public static function fromCollection(iterable $tenants, mixed $selectedId = null): array
    {
        $selected = trim((string) ($selectedId ?? ''));

        return collect($tenants)->map(function ($tenant) use ($selected) {
            $value = (string) ($tenant->id ?? '');

            return [
                'value' => $tenant->id,
                'label' => self::label($tenant),
                'search' => self::searchText($tenant),
                'selected' => $selected !== '' && $selected === $value,
            ];
        })->values()->all();
    }

    public static function label(object $tenant): string
    {
        $name = trim((string) ($tenant->name ?? ''));
        $phone = trim((string) ($tenant->phone ?? ''));
        $email = trim((string) ($tenant->email ?? ''));
        $contact = $phone !== '' ? $phone : $email;

        if ($name === '') {
            return $contact !== '' ? $contact : 'Tenant';
        }

        return $contact !== '' ? "{$name} · {$contact}" : $name;
    }

    public static function searchText(object $tenant): string
    {
        $parts = array_filter([
            (string) ($tenant->name ?? ''),
            (string) ($tenant->phone ?? ''),
            (string) ($tenant->email ?? ''),
            (string) ($tenant->account_number ?? ''),
        ], static fn (string $part): bool => trim($part) !== '');

        return mb_strtolower(trim(implode(' ', $parts)));
    }
}
