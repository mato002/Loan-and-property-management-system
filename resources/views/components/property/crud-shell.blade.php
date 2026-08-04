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
            <x-swal-flash />
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
        @isset($actions)
            <x-slot name="actions">{{ $actions }}</x-slot>
        @endisset
        @isset($modals)
            <x-slot name="modals">{{ $modals }}</x-slot>
        @endisset
        @isset($pageModalsAttributes)
            <x-slot name="pageModalsAttributes" :attributes="$pageModalsAttributes->attributes">
                {{ $pageModalsAttributes }}
            </x-slot>
        @endisset
        @isset($toolbar)
            <x-slot name="toolbar">{{ $toolbar }}</x-slot>
        @endisset
        {{ $slot }}
    </x-property.workspace>
@endif
