<?php

namespace App\Services\Property;

final class PropertyMoney
{
    public static function kes(float|int|string|null $amount): string
    {
        return 'KES '.number_format((float) $amount, 2);
    }
}
