@extends('layouts.app')

@section('page-title', 'Reports')

@section('content')
<div class="content-shell">

    {{-- Main Card --}}
    <div class="card border-0 shadow-sm rounded-4">

        {{-- Header --}}
        <div class="card-body border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                <div>
                    <h3 class="fw-bold mb-1">Reports Dashboard</h3>
                    <div class="text-muted small">
                        Analytics, filters and export tools for warehouse monitoring.
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">

                    <a id="export_pdf"
                        href="#"
                        class="btn btn-outline-danger rounded-pill">
                        <i class="bi bi-file-earmark-pdf"></i>
                        PDF
                    </a>

                    <a id="export_excel"
                        href="#"
                        class="btn btn-outline-success rounded-pill">
                        <i class="bi bi-file-earmark-excel"></i>
                        Excel
                    </a>

                    <a id="export_csv"
                        href="#"
                        class="btn btn-outline-secondary rounded-pill">
                        CSV
                    </a>

                    <button
                        onclick="window.print()"
                        class="btn btn-outline-dark rounded-pill">

                        Print
                    </button>

                </div>

            </div>
        </div>


        {{-- Filters --}}
        <div class="card-body border-bottom bg-light">

            <form id="filtersForm">

                <div class="row g-3">

                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">
                            From Date
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="from_date"
                            name="from_date">
                    </div>


                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">
                            To Date
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="to_date"
                            name="to_date">
                    </div>


                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">
                            Region
                        </label>

                        <select
                            id="region_code"
                            name="region_code"
                            class="form-select">

                            <option value="">
                                All Regions
                            </option>

                        </select>
                    </div>


                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">
                            Type
                        </label>

                        <select
                            id="device_type"
                            name="device_type"
                            class="form-select">

                            <option value="">
                                All Types
                            </option>

                            <option value="CO2">
                                CO₂
                            </option>

                            <option value="PH3">
                                PH₃
                            </option>

                        </select>
                    </div>


                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">
                            Warehouse
                        </label>

                        <select
                            id="warehouse_code"
                            name="warehouse_code"
                            class="form-select">

                            <option value="">
                                All Warehouses
                            </option>

                        </select>
                    </div>


                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">
                            Level
                        </label>

                        <div class="dropdown">
                            <button
                                type="button"
                                id="levelDropdownBtn"
                                class="form-select text-start"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false">
                                All Levels
                            </button>

                            <ul class="dropdown-menu p-2 w-100" aria-labelledby="levelDropdownBtn">
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input level-option" type="checkbox" value="normal" id="level_normal">
                                        <label class="form-check-label" for="level_normal">Normal</label>
                                    </div>
                                </li>
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input level-option" type="checkbox" value="severe" id="level_severe">
                                        <label class="form-check-label" for="level_severe">Severe</label>
                                    </div>
                                </li>
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input level-option" type="checkbox" value="critical" id="level_critical">
                                        <label class="form-check-label" for="level_critical">Critical</label>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <input type="hidden" id="level" name="level" value="">
                    </div>

                </div>


                <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">

                    <div id="colvisContainer"></div>

                    <button
                        type="button"
                        id="btnReset"
                        class="btn btn-outline-secondary rounded-pill">

                        ↺ Reset Filters

                    </button>

                </div>

            </form>

        </div>


        {{-- Statistics --}}
        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="text-muted small">
                                Total Readings
                            </div>

                            <h3 id="stat_total_readings">
                                0
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="text-muted small">
                                Total Devices
                            </div>

                            <h3 id="stat_total_devices">
                                0
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="text-muted small">
                                Online Devices
                            </div>

                            <h3 id="stat_online_devices">
                                0
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="text-muted small">
                                Offline Devices
                            </div>

                            <h3 id="stat_offline_devices">
                                0
                            </h3>
                        </div>
                    </div>
                </div>

            </div>

        </div>


        {{-- Table Section --}}
        <div class="card-body pt-0">

            <div class="table-responsive">

                <table id="reportsTable"
                    class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>Date & Time</th>

                            <th>Region</th>

                            <th>Region Code</th>

                            <th>Warehouse</th>

                            <th>Warehouse Code</th>

                            <th>Device Name</th>

                            <th>Device Code</th>

                            <th>Type</th>

                            <th>IP Address</th>

                            <th>Value</th>

                            <th>Unit</th>

                            <th>Level</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>
</div>
@endsection


@section('styles')

<style>
    .table th {
        white-space: nowrap;
        font-size: 14px;
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
    }

    .card {
        border-radius: 18px;
    }

    #filtersForm label {
        font-size: 13px;
    }

    .badge.bg-severe {
        background: #f59e0b !important;
    }

    #reportsTable_wrapper .dt-buttons {
        margin-bottom: 0;
    }

    #colvisContainer .dt-button {
        border-radius: 50rem !important;
    }

    .dt-button-collection {
        min-width: 260px !important;
        left: auto !important;
        right: 0 !important;
    }

    .dt-button-collection .dt-button {
        width: 100%;
        text-align: left;
    }

    div.dt-processing {
        background: white;
    }
</style>

@endsection


@section('scripts')
<script>
    const API = {
        regions: '{{ url("/api/regions") }}',
        warehouses: '{{ url("/api/warehouses") }}',
    };

    const routeExportPdf = '{{ route("reports.export",["format"=>"pdf"]) }}';
    const routeExportExcel = '{{ route("reports.export",["format"=>"excel"]) }}';
    const routeExportCsv = '{{ route("reports.export",["format"=>"csv"]) }}';

    let table = null;
    const reportColumnKeys = [
        'date_time', 'region', 'region_code', 'warehouse', 'warehouse_code',
        'device_name', 'device_code', 'device_type', 'device_ip', 'value',
        'unit', 'level', 'status'
    ];


    /* ---------------- FILTERS ---------------- */

    function getFilters() {

        const form = document.getElementById('filtersForm');
        const data = new FormData(form);

        const params = new URLSearchParams();

        for (const [k, v] of data.entries()) {
            if (v !== '') {
                params.set(k, v);
            }
        }

        return params;
    }

    function buildExportLinks() {

        const params = getFilters();

        if (table) {
            const visibleColumns = table.columns().visible().toArray()
                .map((visible, index) => visible ? reportColumnKeys[index] : null)
                .filter(Boolean);

            if (visibleColumns.length) {
                params.set('selected_cols', visibleColumns.join(','));
            }
        }

        const query = params.toString();

        document.getElementById('export_pdf').href =
            routeExportPdf + '?' + query;

        document.getElementById('export_excel').href =
            routeExportExcel + '?' + query;

        document.getElementById('export_csv').href =
            routeExportCsv + '?' + query;
    }


    /* ---------------- REGIONS ---------------- */

    async function loadRegions() {

        const response = await fetch(
            API.regions + '?per_page=1000'
        );

        const json = await response.json();

        const select = document.getElementById('region_code');

        select.innerHTML =
            '<option value="">All Regions</option>';

        (json.data?.data || json.data || []).forEach(region => {

            const option = document.createElement('option');

            option.value = region.region_code;

            option.textContent =
                region.region_name;

            select.appendChild(option);

        });
    }


    /* ---------------- WAREHOUSE ---------------- */

    async function loadWarehouses(regionCode) {

        const select =
            document.getElementById('warehouse_code');

        select.disabled = true;

        select.innerHTML =
            '<option>Loading...</option>';

        const response = await fetch(
            API.warehouses +
            '?per_page=1000&region_code=' +
            encodeURIComponent(regionCode || '')
        );

        const json = await response.json();

        select.innerHTML =
            '<option value="">All Warehouses</option>';

        (json.data?.data || json.data || []).forEach(w => {

            select.innerHTML +=
                `<option value="${w.warehouse_code}">
                ${w.warehouse_name}
             </option>`;
        });

        select.disabled = false;
    }


    /* ---------------- SUMMARY ---------------- */

    async function refreshSummary() {

        const response = await fetch(
            '{{ url("/admin/reports/summary") }}?' +
            getFilters()
        );

        const payload = await response.json();

        if (!payload.success) return;

        const setStat = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value ?? 0;
        };

        setStat('stat_total_readings', payload.stats.total_readings);
        setStat('stat_total_devices', payload.stats.total_devices);
        setStat('stat_online_devices', payload.stats.online_devices);
        setStat('stat_offline_devices', payload.stats.offline_devices);
        setStat('stat_severe_alerts', payload.stats.severe_alerts);
        setStat('stat_critical_alerts', payload.stats.critical_alerts);
        setStat('stat_regions_count', payload.stats.regions_count);
        setStat('stat_warehouses_count', payload.stats.warehouses_count);
    }


    /* ---------------- TABLE ---------------- */

    function initTable() {

        table = window.initWarehouseDataTable('#reportsTable', {

            processing: true,
            serverSide: true,
            paging: true,
            info: true,
            lengthChange: true,
            scrollX: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],

            dom: '<"top d-flex justify-content-between align-items-center flex-wrap gap-2"lBf>rt<"bottom d-flex justify-content-between align-items-center"ip>',

            buttons: [{
                extend: 'colvis',
                text: 'Choose Columns',
                className: 'btn btn-secondary rounded-pill'
            }],

            ajax: {
                url: '{{ url("/admin/reports/data") }}',

                data: function(d) {

                    const params = getFilters();

                    for (const [k, v] of params.entries()) {
                        d[k] = v;
                    }

                }
            },

            columns: [{
                    data: 'date_time',
                    defaultContent: ''
                },
                {
                    data: 'region',
                    defaultContent: ''
                },
                {
                    data: 'region_code',
                    defaultContent: ''
                },
                {
                    data: 'warehouse',
                    defaultContent: ''
                },
                {
                    data: 'warehouse_code',
                    defaultContent: ''
                },
                {
                    data: 'device_name',
                    defaultContent: ''
                },
                {
                    data: 'device_code',
                    defaultContent: ''
                },
                {
                    data: 'device_type',
                    defaultContent: ''
                },
                {
                    data: 'device_ip',
                    defaultContent: ''
                },
                {
                    data: 'value',
                    defaultContent: 'N/A'
                },
                {
                    data: 'unit',
                    defaultContent: ''
                },

                {
    data: 'level',
    defaultContent: 'unknown',
    render: function (d) {
        const level = String(d || 'unknown').toLowerCase();

        let cls = 'bg-secondary';
        let label = 'Unknown';

        if (level === 'normal') {
            cls = 'bg-success';
            label = 'Normal';
        } else if (level === 'severe') {
            cls = 'bg-warning text-dark';
            label = 'Severe';
        } else if (level === 'critical') {
            cls = 'bg-danger';
            label = 'Critical';
        }

        return `<span class="badge ${cls}">${label}</span>`;
    }
},

                {
                    data: 'status',
                    defaultContent: '',
                    render: function(d) {

                        if (d === 'online')
                            return '<span class="badge bg-success">Online</span>';

                        if (d === 'offline')
                            return '<span class="badge bg-secondary">Offline</span>';

                        return d ?? '-';
                    }
                }
            ],

            order: [
                [0, 'desc']
            ]
        });

        table.buttons()
            .container()
            .appendTo('#colvisContainer');

        table.on('column-visibility.dt', buildExportLinks);
    }


    /* ---------------- REFRESH ---------------- */

    function reloadTable() {

        buildExportLinks();

        refreshSummary();

        table.ajax.reload();
    }


    /* ---------------- DEBOUNCE ---------------- */

    function debounce(func, delay) {

        let timer;

        return function() {

            clearTimeout(timer);

            timer = setTimeout(
                () => func(),
                delay
            );
        };
    }

    const autoRefresh =
        debounce(reloadTable, 500);


    /* ---------------- EVENTS ---------------- */

    function setupFilters() {

        document
            .querySelectorAll(
                '#filtersForm input:not(.level-option),#filtersForm select'
            )
            .forEach(el => {

                el.addEventListener(
                    'change',
                    autoRefresh
                );

            });
    }


    function setupLevelFilter() {

        const checkboxes = document.querySelectorAll('.level-option');
        const hiddenInput = document.getElementById('level');
        const btn = document.getElementById('levelDropdownBtn');

        const labels = {
            normal: 'Normal',
            severe: 'Severe',
            critical: 'Critical'
        };

        function sync() {

            const checked = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            hiddenInput.value = checked.join(',');

            btn.textContent = checked.length
                ? checked.map(v => labels[v]).join(', ')
                : 'All Levels';

            hiddenInput.dispatchEvent(new Event('change'));
        }

        checkboxes.forEach(cb => cb.addEventListener('change', sync));
    }


    function setupDropdowns() {

        document
            .getElementById('region_code')
            .addEventListener(
                'change',
                async e => {

                    let value = e.target.value;

                    await loadWarehouses(value);

                    autoRefresh();

                }
            );

    }


    function setupResetButton() {

        document
            .getElementById('btnReset')
            .addEventListener('click', async () => {

                document
                    .getElementById('filtersForm')
                    .reset();

                document.getElementById('levelDropdownBtn').textContent = 'All Levels';

                await loadWarehouses('');

                autoRefresh();

            });

    }


    /* ---------------- INIT ---------------- */

    document.addEventListener(
        'DOMContentLoaded',
        async function() {

            await loadRegions();
            await loadWarehouses('');

            initTable();

            refreshSummary();

            buildExportLinks();

            setupFilters();

            setupDropdowns();

            setupLevelFilter();

            setupResetButton();

        }
    );
</script>
@endsection
