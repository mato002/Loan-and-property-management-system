<turbo-frame id="{{ \App\Support\Property\PropertyFormModal::FRAME_ID }}">
    @php
        $successFlash = [[
            'icon' => 'success',
            'title' => 'Success',
            'text' => $message,
            'timer' => 2400,
            'showConfirmButton' => false,
        ]];
    @endphp
    <div
        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"
        data-property-form-modal-success="1"
        data-swal-flash='@json($successFlash)'
    >
        {{ $message }}
    </div>
</turbo-frame>
