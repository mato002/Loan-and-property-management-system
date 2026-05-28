<turbo-frame id="lease-create-modal">
    <script>
        (function () {
            if (typeof window.closeLeaseCreateModal === 'function') {
                window.closeLeaseCreateModal();
            } else {
                const overlay = document.getElementById('lease-create-overlay');
                if (overlay) {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                }
                const frame = document.getElementById('lease-create-modal');
                if (frame) {
                    frame.removeAttribute('src');
                    frame.innerHTML = '';
                    delete frame.dataset.loaded;
                }
            }

            const url = @json($leasesUrl ?? route('property.tenants.leases', absolute: false));
            if (window.Turbo?.visit) {
                window.Turbo.visit(url, { frame: 'property-main', action: 'replace' });
            } else {
                window.location.href = url;
            }

            const message = @json($message ?? 'Lease saved.');
            if (message && window.Swal) {
                window.Swal.fire({
                    icon: 'success',
                    title: message,
                    timer: 2500,
                    showConfirmButton: false,
                });
            }
        })();
    </script>
</turbo-frame>
