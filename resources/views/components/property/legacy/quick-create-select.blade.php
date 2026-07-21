@props([
    'name',
    'label' => null,
    'required' => false,
    /** @var array<int,array{value:string|int,label:string,selected?:bool,disabled?:bool,search?:string}> */
    'options' => [],
    'placeholder' => 'Select…',
    'error' => null,
    'create' => ['mode' => 'none'],
    'selectId' => null,
    'searchable' => false,
    'searchMinOptions' => 8,
])

@include('components.property.v2.quick-create-select', get_defined_vars())
