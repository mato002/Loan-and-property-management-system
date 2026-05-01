@php
    $flashes = [];
    $swalFlash = session('swal_flash');
    if (is_array($swalFlash)) {
        $looksLikeSingleConfig = array_key_exists('title', $swalFlash)
            || array_key_exists('text', $swalFlash)
            || array_key_exists('html', $swalFlash)
            || array_key_exists('icon', $swalFlash);

        $flashes = $looksLikeSingleConfig
            ? [$swalFlash]
            : array_values($swalFlash);
    } elseif (is_string($swalFlash) && trim($swalFlash) !== '') {
        $flashes[] = [
            'icon' => 'info',
            'text' => $swalFlash,
        ];
    }
@endphp
@if (count($flashes))
    <script>
        window.__laravelSwalFlash = @json($flashes);
    </script>
@endif
