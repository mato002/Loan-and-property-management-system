<?php

namespace App\Support\Property;

use Illuminate\Http\Request;

final class PropertyFormModal
{
    public const FRAME_ID = 'property-form-modal';

    public const INPUT_NAME = '_property_form_modal';

    public static function wants(?Request $request = null): bool
    {
        $request ??= request();

        return $request->header('Turbo-Frame') === self::FRAME_ID;
    }

    public static function fromModal(?Request $request = null): bool
    {
        $request ??= request();

        return $request->boolean(self::INPUT_NAME) || self::wants($request);
    }

    /**
     * @return array{inPropertyFormModal: bool}
     */
    public static function viewContext(?Request $request = null): array
    {
        return ['inPropertyFormModal' => self::wants($request)];
    }

    public static function isPropertyCrudFormPath(string $pathname): bool
    {
        if (! str_starts_with($pathname, '/property/')) {
            return false;
        }

        if (str_contains($pathname, '/workspace/forms')) {
            return false;
        }

        return (bool) preg_match('#/(edit|create)/?$#', $pathname);
    }

    public static function modalLinkAttributes(string $title): string
    {
        $escaped = e($title);

        return 'data-property-form-modal data-property-form-modal-title="'.$escaped.'"';
    }
}
