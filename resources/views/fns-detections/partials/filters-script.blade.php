<script>
    window.fnsDetectionFilters = {
        values: function () {
            const values = {};

            new FormData(document.getElementById('fnsDetectionFilters')).forEach(function (value, key) {
                if (value.trim() !== '') {
                    values[key] = value.trim();
                }
            });

            return values;
        },

        bind: function (table) {
            const form = document.getElementById('fnsDetectionFilters');
            const exportLink = document.getElementById('fnsDetectionsExport');
            const reload = function () {
                table.ajax.reload();
            };
            let typingTimer = null;

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                clearTimeout(typingTimer);
                reload();
            });

            form.addEventListener('change', function () {
                clearTimeout(typingTimer);
                reload();
            });

            document.getElementById('fnsFilterWarehouse').addEventListener('input', function () {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(reload, 400);
            });

            form.addEventListener('reset', function () {
                clearTimeout(typingTimer);
                // The fields are only cleared after this event finishes.
                setTimeout(reload);
            });

            // The export always matches what the table is currently showing.
            exportLink.addEventListener('click', function () {
                const url = new URL(exportLink.href);
                const values = Object.assign({ search: table.search() }, window.fnsDetectionFilters.values());

                url.search = '';
                Object.keys(values).forEach(function (key) {
                    if (values[key]) {
                        url.searchParams.set(key, values[key]);
                    }
                });

                exportLink.href = url.toString();
            });
        }
    };
</script>
