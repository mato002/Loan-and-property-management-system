<script>
    (function () {
        document.querySelectorAll('form[data-revenue-date-filter]').forEach(function (form) {
            const rangeMonths = form.querySelector('[name="range_months"]');
            const rangeEnd = form.querySelector('[name="range_end"]');
            const from = form.querySelector('[name="from"]');
            const to = form.querySelector('[name="to"]');
            const clearCustom = () => {
                if (from) from.value = '';
                if (to) to.value = '';
            };
            rangeMonths?.addEventListener('change', clearCustom);
            rangeEnd?.addEventListener('change', clearCustom);
        });
    })();
</script>
