@php
    $flashes = [];

    $errorsBag = $errors ?? null;
    if (is_object($errorsBag) && method_exists($errorsBag, 'any') && $errorsBag->any()) {
        $flashes[] = [
            'icon' => 'error',
            'title' => 'Please fix the following',
            'html' => collect($errorsBag->all())
                ->map(fn (string $msg): string => '<div style="text-align:left;">- '.e($msg).'</div>')
                ->implode(''),
            'confirmButtonColor' => '#dc2626',
        ];
    }

    $warningMessage = session('warning');
    if (is_string($warningMessage) && trim($warningMessage) !== '') {
        $flashes[] = [
            'icon' => 'warning',
            'title' => 'Attention',
            'text' => $warningMessage,
            'confirmButtonColor' => '#d97706',
        ];
    }

    $infoMessage = session('info');
    if (is_string($infoMessage) && trim($infoMessage) !== '') {
        $flashes[] = [
            'icon' => 'info',
            'title' => 'Note',
            'text' => $infoMessage,
            'confirmButtonColor' => '#2563eb',
        ];
    }

    $successMessage = session('success');
    if (is_string($successMessage) && trim($successMessage) !== '') {
        $flashes[] = [
            'icon' => 'success',
            'title' => 'Success',
            'text' => $successMessage,
            'confirmButtonColor' => '#059669',
        ];
    }

    $statusMessage = session('status');
    if (is_string($statusMessage) && trim($statusMessage) !== '') {
        $flashes[] = [
            'icon' => 'success',
            'title' => 'Success',
            'text' => $statusMessage,
            'confirmButtonColor' => '#2563eb',
        ];
    }

    $swalFlash = session('swal_flash');
    if (is_array($swalFlash)) {
        $looksLikeSingleConfig = array_key_exists('title', $swalFlash)
            || array_key_exists('text', $swalFlash)
            || array_key_exists('html', $swalFlash)
            || array_key_exists('icon', $swalFlash);

        $extra = $looksLikeSingleConfig
            ? [$swalFlash]
            : array_values($swalFlash);
        foreach ($extra as $item) {
            if (is_array($item)) {
                $flashes[] = $item;
            }
        }
    } elseif (is_string($swalFlash) && trim($swalFlash) !== '') {
        $flashes[] = [
            'icon' => 'info',
            'text' => $swalFlash,
        ];
    }
@endphp
@if (count($flashes))
    <div data-swal-flash='@json($flashes)' hidden aria-hidden="true"></div>
    <script>
        window.__laravelSwalFlash = @json($flashes);
    </script>
@endif
