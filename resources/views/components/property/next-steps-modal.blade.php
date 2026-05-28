@if (\App\Support\Property\PropertyUiVersion::isV2())
    @include('components.property.v2.next-steps-modal', get_defined_vars())
@else
    @include('components.property.legacy.next-steps-modal', get_defined_vars())
@endif
