<?php

namespace App\Support\Property;

use Illuminate\Support\HtmlString;

final class PhoneLink
{
    public static function html(mixed $value, string $fallback = '—', string $class = ''): HtmlString
    {
        if ($value instanceof HtmlString) {
            $raw = (string) $value;
            if (str_contains($raw, 'tel:')) {
                return $value;
            }
            $value = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return new HtmlString(view('components.phone-link', [
            'value' => $value,
            'fallback' => $fallback,
            'class' => $class,
        ])->render());
    }

    public static function isPhoneColumn(mixed $label): bool
    {
        return strcasecmp(trim((string) $label), 'Phone') === 0;
    }
}
