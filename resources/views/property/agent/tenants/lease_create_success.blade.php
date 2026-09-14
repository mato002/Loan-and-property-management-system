<turbo-frame id="lease-create-modal">
    <div
        hidden
        aria-hidden="true"
        data-property-form-modal-success
        data-swal-flash='@json([["icon" => "success", "title" => "Success", "text" => $message ?? "Lease saved.", "timer" => 2400, "showConfirmButton" => false]])'
    ></div>
    <script>
        (function () {
            const message = @json($message ?? 'Lease saved.');
            if (message && window.Swal) {
                window.Swal.fire({
                    icon: 'success',
                    title: message,
                    timer: 2500,
                    showConfirmButton: false,
                    heightAuto: false,
                    scrollbarPadding: false,
                });
            }

            if (typeof window.closeLeaseCreateModal === 'function') {
                window.closeLeaseCreateModal();
            } else {
                const panel = document.getElementById('lease-create-panel');
                panel?.classList.add('hidden');
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
        })();
    </script>
</turbo-frame>
