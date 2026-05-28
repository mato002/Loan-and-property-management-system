@if (\App\Support\Property\PropertyUiVersion::isV2())
    @include('components.property.v2.workspace', get_defined_vars())
@else
    @include('components.property.legacy.workspace', get_defined_vars())
@endif
