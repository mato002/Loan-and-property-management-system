@php
    use App\Support\Property\PropertyNavMode;

    $navMode = PropertyNavMode::current();
@endphp

@includeFirst([
    "layouts.property.sidebar.{$navMode}",
    'layouts.property.sidebar.classic',
])
