<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanSystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'label',
        'group',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function setValue(string $key, ?string $value, ?string $label = null, string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'label' => $label, 'group' => $group]
        );
    }
}
