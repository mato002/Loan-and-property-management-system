@php
    use App\Support\Property\PropertyPortalTheme;

    $propertyPortalThemeClass = PropertyPortalTheme::htmlClass();
    $propertyPortalColorScheme = PropertyPortalTheme::colorScheme();
@endphp
<meta name="color-scheme" content="{{ $propertyPortalColorScheme }}">
