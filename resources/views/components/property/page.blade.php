@if (\App\Support\Property\PropertyUiVersion::isV2())
    @include('components.property.v2.page', get_defined_vars())
@else
    @include('components.property.legacy.page', get_defined_vars())
@endif
