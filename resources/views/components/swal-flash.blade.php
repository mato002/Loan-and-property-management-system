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

    $errorMessage = session('error');
    if (is_string($errorMessage) && trim($errorMessage) !== '') {
        $flashes[] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => $errorMessage,
            'confirmButtonColor' => '#dc2626',
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

    $statusSentinelFlashes = [
        'verification-link-sent' => [
            'icon' => 'success',
            'title' => __('Verification sent'),
            'text' => __('A new verification link has been sent to your email address.'),
            'confirmButtonColor' => '#059669',
        ],
        'device-removed' => [
            'icon' => 'success',
            'title' => __('Device removed'),
            'text' => __('Device removed successfully.'),
            'confirmButtonColor' => '#059669',
        ],
        'devices-cleared' => [
            'icon' => 'success',
            'title' => __('Devices signed out'),
            'text' => __('All other devices were signed out.'),
            'confirmButtonColor' => '#059669',
        ],
        'device-current' => [
            'icon' => 'warning',
            'title' => __('Current device'),
            'text' => __('Current device cannot be removed from this list.'),
            'confirmButtonColor' => '#d97706',
        ],
        'device-unavailable' => [
            'icon' => 'warning',
            'title' => __('Unavailable'),
            'text' => __('Device management is unavailable because sessions are not stored in database.'),
            'confirmButtonColor' => '#d97706',
        ],
    ];

    $statusMessage = session('status');
    if (is_string($statusMessage) && trim($statusMessage) !== '') {
        if (isset($statusSentinelFlashes[$statusMessage])) {
            $flashes[] = $statusSentinelFlashes[$statusMessage];
        } else {
            $flashes[] = [
                'icon' => 'success',
                'title' => session('loan_register_bulk_title') ?: 'Success',
                'text' => $statusMessage,
                'confirmButtonColor' => '#2563eb',
            ];
        }
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
