@props([
    'href',
    'title' => 'Edit',
])

<a
    href="{{ $href }}"
    data-property-form-modal
    data-property-form-modal-title="{{ $title }}"
    onclick="if (window.PropertyFormModal && window.PropertyFormModal.open) { event.preventDefault(); event.stopImmediatePropagation(); window.PropertyFormModal.open({ url: this.href, title: this.dataset.propertyFormModalTitle || 'Edit' }); return false; }"
    {{ $attributes }}
>{{ $slot }}</a>
