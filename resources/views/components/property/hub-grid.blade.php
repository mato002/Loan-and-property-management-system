@if (\App\Support\Property\PropertyUiVersion::isV2())
    @include('components.property.v2.hub-grid', get_defined_vars())
@else
    @include('components.property.legacy.hub-grid', get_defined_vars())
@endif
