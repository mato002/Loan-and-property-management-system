@if (($propertyPortal ?? 'agent') === 'agent' && \Illuminate\Support\Facades\Route::has('property.maintenance.requests.open_count'))
    <script>
        (function () {
            var url = @json(route('property.maintenance.requests.open_count'));
            function paint(count) {
                var n = Number(count) || 0;
                var label = n > 99 ? '99+' : String(n);
                document.querySelectorAll('[data-maintenance-bell]').forEach(function (el) {
                    var countEl = el.querySelector('[data-maintenance-bell-count]');
                    if (countEl) {
                        countEl.textContent = label;
                    }
                    el.classList.toggle('is-empty', n < 1);
                    el.title = n + ' open maintenance request' + (n === 1 ? '' : 's');
                    el.setAttribute('aria-label', el.title);
                });
            }
            function refresh() {
                if (!document.querySelector('[data-maintenance-bell]')) {
                    return;
                }
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.ok ? response.json() : null;
                }).then(function (data) {
                    if (data && typeof data.count !== 'undefined') {
                        paint(data.count);
                    }
                }).catch(function () {});
            }
            document.addEventListener('turbo:load', refresh);
            document.addEventListener('turbo:frame-load', refresh);
        })();
    </script>
@endif
