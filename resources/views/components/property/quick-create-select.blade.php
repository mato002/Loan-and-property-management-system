@if (\App\Support\Property\PropertyUiVersion::isV2())
    @include('components.property.v2.quick-create-select', get_defined_vars())
@else
    @include('components.property.legacy.quick-create-select', get_defined_vars())
@endif
