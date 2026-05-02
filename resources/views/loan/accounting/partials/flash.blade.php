@php
    $statusMessage = session('status');
    $errorMessages = $errors->any() ? $errors->all() : [];
@endphp

@if ($statusMessage || count($errorMessages) > 0)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusMessage = @json($statusMessage);
            const errorMessages = @json($errorMessages);

            const showAlert = (config) => {
                if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
                    window.Swal.fire(config);
                    return;
                }

                // Fallback when SweetAlert is not loaded.
                const plainText = config.html
                    ? config.html.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]*>/g, '')
                    : (config.text || '');
                window.alert((config.title ? config.title + '\n' : '') + plainText);
            };

            if (statusMessage) {
                showAlert({
                    icon: 'success',
                    title: 'Success',
                    text: statusMessage,
                    confirmButtonColor: '#2563eb'
                });
            }

            if (errorMessages.length > 0) {
                showAlert({
                    icon: 'error',
                    title: 'Please fix the following',
                    html: errorMessages.map((msg) => `<div style="text-align:left;">- ${msg}</div>`).join(''),
                    confirmButtonColor: '#dc2626'
                });
            }
        });
    </script>
@endif
