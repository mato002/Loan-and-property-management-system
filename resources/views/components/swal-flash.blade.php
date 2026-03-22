@php
    $flashes = [];
    $status = session('status');

    if ($status === 'profile-updated') {
        $flashes[] = [
            'icon' => 'success',
            'text' => __('Profile updated.'),
        ];
    } elseif ($status === 'password-updated') {
        $flashes[] = [
            'icon' => 'success',
            'text' => __('Password updated.'),
        ];
    } elseif ($status === 'verification-link-sent') {
        $flashes[] = [
            'icon' => 'success',
            'text' => __('A new verification link has been sent to your email address.'),
        ];
    } elseif (is_string($status) && $status !== '') {
        $flashes[] = [
            'icon' => 'success',
            'text' => $status,
        ];
    }

    if (session()->has('success')) {
        $flashes[] = [
            'icon' => 'success',
            'text' => session('success'),
        ];
    }

    if (session()->has('error')) {
        $flashes[] = [
            'icon' => 'error',
            'title' => __('Error'),
            'text' => session('error'),
        ];
    }

    if (isset($errors) && $errors->any()) {
        $items = collect($errors->all())->map(fn ($e) => '<li>'.e($e).'</li>')->implode('');
        $flashes[] = [
            'icon' => 'error',
            'title' => __('Please review the following'),
            'html' => '<ul class="text-left list-disc pl-5 space-y-1">'.$items.'</ul>',
        ];
    }
@endphp
@if (count($flashes))
    <script>
        window.__laravelSwalFlash = @json($flashes);
    </script>
@endif
