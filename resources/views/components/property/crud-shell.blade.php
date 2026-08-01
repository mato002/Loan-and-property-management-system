@props([
    'inPropertyFormModal' => false,
    'title' => '',
    'subtitle' => null,
    'backRoute' => null,
    'stats' => [],
    'columns' => [],
])

@php
    $inModal = (bool) $inPropertyFormModal;
    $frameId = \App\Support\Property\PropertyFormModal::FRAME_ID;
@endphp

@if ($inModal)
    <turbo-frame id="{{ $frameId }}">
        <div class="property-form-modal-content space-y-4">
            {{ $slot }}
        </div>
    </turbo-frame>
@else
    <x-property.workspace
        :title="$title"
        :subtitle="$subtitle"
        :back-route="$backRoute"
        :stats="$stats"
        :columns="$columns"
        {{ $attributes->except(['inPropertyFormModal', 'title', 'subtitle', 'backRoute', 'stats', 'columns']) }}
    >
        {{ $slot }}
    </x-property.workspace>
@endif
