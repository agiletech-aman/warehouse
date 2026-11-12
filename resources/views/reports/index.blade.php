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

                        <div class="dropdown">
                            <button
                                type="button"
                                id="regionDropdownBtn"
                                class="form-select text-start"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false">
                                All Regions
                            </button>

                            <ul class="dropdown-menu p-2 w-100" id="regionCheckboxList" style="max-height: 260px; overflow-y: auto;"></ul>
                        </div>

                        <input type="hidden" id="region_code" name="region_code" value="">
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

                        <div class="dropdown">
                            <button
                                type="button"
                                id="warehouseDropdownBtn"
                                class="form-select text-start"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false">
                                All Warehouses
                            </button>

                            <ul class="dropdown-menu p-2 w-100" id="warehouseCheckboxList" style="max-height: 260px; overflow-y: auto;"></ul>
                        </div>

                        <input type="hidden" id="warehouse_code" name="warehouse_code" value="">
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">
                            Godown / Compartment
                        </label>

                        <div class="dropdown">
                            <button
                                type="button"
                                id="locationDropdownBtn"
                                class="form-select text-start text-truncate"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false">
                                Select a warehouse first
                            </button>

                            <ul class="dropdown-menu p-2 w-100" id="locationCheckboxList" style="max-height: 320px; overflow-y: auto;">
                                <li class="text-muted small px-2">Select a warehouse to see its godowns and compartments.</li>
                            </ul>
                        </div>

                        <input type="hidden" id="locations" name="locations" value="">
                    </div>


                    <div class="col-lg-2">
                        <label class="form-label fw-semibold" for="time_of_day">
                            Day / Night
                        </label>

                        <select id="time_of_day" name="time_of_day" class="form-select">
                            <option value="">All (Day + Night)</option>
                            <option value="day">Day (06:00 - 17:59)</option>
                            <option value="night">Night (18:00 - 05:59)</option>
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

                            <ul class="dropdown-menu p-2 w-100" id="levelCheckboxList" aria-labelledby="levelDropdownBtn">
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input dd-check" type="checkbox" value="normal" data-label="Normal" id="level_normal">
                                        <label class="form-check-label" for="level_normal">Normal</label>
                                    </div>
                                </li>
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input dd-check" type="checkbox" value="severe" data-label="Severe" id="level_severe">
                                        <label class="form-check-label" for="level_severe">Severe</label>
                                    </div>
                                </li>
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input dd-check" type="checkbox" value="critical" data-label="Critical" id="level_critical">
                                        <label class="form-check-label" for="level_critical">Critical</label>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <input type="hidden" id="level" name="level" value="">
                    </div>

                </div>


                <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">

                    <div id="colvisContainer"></div>

                    <div class="d-flex align-items-center gap-3 flex-wrap">

                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="current_only" name="current_only">
                            <label class="form-check-label fw-semibold" for="current_only">
                                Current Data Only
                            </label>
                        </div>

                        <button
                            type="button"
                            id="btnReset"
                            class="btn btn-outline-secondary rounded-pill">

                            ↺ Reset Filters

                        </button>

                    </div>

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


        {{-- View switch --}}
        <div class="card-body pt-0 pb-3">
            <div class="btn-group" role="group" aria-label="Report view">
                <input type="radio" class="btn-check" name="report_view" id="view_table" value="table" autocomplete="off" checked>
                <label class="btn btn-outline-primary rounded-start-pill" for="view_table">Table View</label>

                <input type="radio" class="btn-check" name="report_view" id="view_chart" value="chart" autocomplete="off">
                <label class="btn btn-outline-primary rounded-end-pill" for="view_chart">Chart View</label>
            </div>
        </div>


        {{-- Chart Section --}}
        <div class="card-body pt-0 d-none" id="chartSection">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="text-muted small" id="chartMeta">Loading chart...</div>
            </div>

            <div class="row g-3" id="gasCharts"></div>

            <div class="text-muted text-center py-5 d-none" id="chartEmpty">
                No readings found for the selected filters.
            </div>
        </div>


        {{-- Table Section --}}
        <div class="card-body pt-0" id="tableSection">

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

                            <th>Godown</th>

                            <th>Compartment</th>

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

    .zone-chip {
        display: inline-block;
        width: 14px;
        height: 10px;
        border: 1px solid;
        border-radius: 2px;
        margin-right: 4px;
        vertical-align: middle;
    }

    .night-chip {
        display: inline-block;
        width: 14px;
        height: 10px;
        background: rgba(100, 116, 139, 0.25);
        border-radius: 2px;
        margin-right: 4px;
        vertical-align: middle;
    }

    div.dt-processing {
        background: white;
    }
</style>

@endsection


@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    const API = {
        regions: '{{ url("/api/regions") }}',
        warehouses: '{{ url("/api/warehouses") }}',
        locations: '{{ route("reports.locations") }}',
        chart: '{{ route("reports.chart") }}',
    };

    const routeExportPdf = '{{ route("reports.export",["format"=>"pdf"]) }}';
    const routeExportExcel = '{{ route("reports.export",["format"=>"excel"]) }}';
    const routeExportCsv = '{{ route("reports.export",["format"=>"csv"]) }}';

    let table = null;
    const reportColumnKeys = [
        'date_time', 'region', 'region_code', 'warehouse', 'warehouse_code',
        'godown', 'compartment', 'device_name', 'device_code', 'device_type', 'device_ip', 'value',
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


    /* ---------------- CHECKBOX DROPDOWNS ---------------- */

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[ch]);
    }

    function renderCheckboxList(listId, items, keepChecked = []) {

        const list = document.getElementById(listId);

        if (!items.length) {
            list.innerHTML = '<li class="text-muted small px-2">No options</li>';
            return;
        }

        list.innerHTML = items.map((item, index) => `
            <li>
                <div class="form-check">
                    <input class="form-check-input dd-check" type="checkbox" value="${escapeHtml(item.value)}" data-label="${escapeHtml(item.label)}" id="${listId}_${index}"${keepChecked.includes(String(item.value)) ? ' checked' : ''}>
                    <label class="form-check-label" for="${listId}_${index}">${escapeHtml(item.label)}</label>
                </div>
            </li>
        `).join('');
    }

    function syncCheckboxDropdown(listId, hiddenInputId, buttonId, defaultLabel) {

        const list = document.getElementById(listId);
        const hiddenInput = document.getElementById(hiddenInputId);
        const btn = document.getElementById(buttonId);

        const checked = Array.from(list.querySelectorAll('input[type="checkbox"]:checked'));

        hiddenInput.value = checked.map(cb => cb.value).join(',');

        btn.textContent = checked.length
            ? checked.map(cb => cb.dataset.label).join(', ')
            : defaultLabel;

        hiddenInput.dispatchEvent(new Event('change'));
    }

    function setupCheckboxDropdown(listId, hiddenInputId, buttonId, defaultLabel, onChange) {

        document
            .getElementById(listId)
            .addEventListener('change', e => {

                if (!e.target.matches('input[type="checkbox"]')) return;

                syncCheckboxDropdown(listId, hiddenInputId, buttonId, defaultLabel);

                if (onChange) onChange();

            });
    }


    /* ---------------- REGIONS ---------------- */

    async function loadRegions() {

        const response = await fetch(
            API.regions + '?per_page=1000'
        );

        const json = await response.json();

        const items = (json.data?.data || json.data || []).map(region => ({
            value: region.region_code,
            label: region.region_name
        }));

        renderCheckboxList('regionCheckboxList', items);
    }


    /* ---------------- WAREHOUSE ---------------- */

    async function loadWarehouses(regionCode) {

        const btn = document.getElementById('warehouseDropdownBtn');
        btn.textContent = 'Loading...';

        const response = await fetch(
            API.warehouses +
            '?per_page=1000&region_code=' +
            encodeURIComponent(regionCode || '')
        );

        const json = await response.json();

        const items = (json.data?.data || json.data || []).map(w => ({
            value: w.warehouse_code,
            label: w.warehouse_name
        }));

        renderCheckboxList('warehouseCheckboxList', items);
        syncCheckboxDropdown('warehouseCheckboxList', 'warehouse_code', 'warehouseDropdownBtn', 'All Warehouses');

        await loadLocations();
    }


    /* ---------------- GODOWN / COMPARTMENT ---------------- */

    // Groups for the selected warehouse(s): [{godown: 'G1', compartments: ['C1', 'C2']}]
    let locationGroups = [];

    function currentLocations() {
        try {
            const value = document.getElementById('locations').value;
            return value ? JSON.parse(value) : [];
        } catch (e) {
            return [];
        }
    }

    function godownLabel(godown) {
        return godown === '' ? 'No Godown' : godown;
    }

    async function loadLocations() {

        const list = document.getElementById('locationCheckboxList');
        const btn = document.getElementById('locationDropdownBtn');
        const warehouseCode = document.getElementById('warehouse_code').value;
        const previous = currentLocations();

        if (!warehouseCode) {
            locationGroups = [];
            list.innerHTML = '<li class="text-muted small px-2">Select a warehouse to see its godowns and compartments.</li>';
            btn.textContent = 'Select a warehouse first';
            setLocations([]);
            return;
        }

        btn.textContent = 'Loading...';

        const response = await fetch(API.locations + '?' + new URLSearchParams({ warehouse_code: warehouseCode }));
        if (!response.ok) {
            console.error('Report locations failed with status', response.status);
            btn.textContent = 'All Godowns / Compartments';
            return;
        }

        const json = await response.json();
        locationGroups = json.groups || [];

        if (!locationGroups.length) {
            list.innerHTML = '<li class="text-muted small px-2">No godown / compartment found for this warehouse.</li>';
            setLocations([]);
            return;
        }

        list.innerHTML = locationGroups.map((group, gi) => `
            <li class="${gi ? 'border-top pt-1 mt-1' : ''}">
                <div class="form-check">
                    <input class="form-check-input dd-check loc-godown" type="checkbox" data-group="${gi}" id="loc_g_${gi}">
                    <label class="form-check-label fw-semibold" for="loc_g_${gi}">${escapeHtml(godownLabel(group.godown))}</label>
                </div>
            </li>
            ${group.compartments.map((compartment, ci) => `
                <li class="ms-4">
                    <div class="form-check">
                        <input class="form-check-input dd-check loc-compartment" type="checkbox" data-group="${gi}" data-index="${ci}" id="loc_c_${gi}_${ci}">
                        <label class="form-check-label" for="loc_c_${gi}_${ci}">${escapeHtml(compartment)}</label>
                    </div>
                </li>
            `).join('')}
        `).join('');

        // Keep earlier ticks that still exist in the new warehouse selection.
        previous.forEach(([godown, compartment]) => {
            const gi = locationGroups.findIndex(g => g.godown === godown);
            if (gi < 0) return;

            if (compartment === null) {
                list.querySelectorAll(`input[data-group="${gi}"]`).forEach(cb => { cb.checked = true; });
            } else {
                const ci = locationGroups[gi].compartments.indexOf(compartment);
                const cb = list.querySelector(`#loc_c_${gi}_${ci}`);
                if (cb) cb.checked = true;
            }
        });

        syncLocations();
    }

    function syncLocations() {

        const list = document.getElementById('locationCheckboxList');
        const selection = [];

        locationGroups.forEach((group, gi) => {
            const godownBox = list.querySelector(`#loc_g_${gi}`);
            const compartmentBoxes = Array.from(list.querySelectorAll(`.loc-compartment[data-group="${gi}"]`));
            const checked = compartmentBoxes.filter(cb => cb.checked);

            if (compartmentBoxes.length) {
                godownBox.checked = checked.length === compartmentBoxes.length;
                godownBox.indeterminate = checked.length > 0 && !godownBox.checked;
            }

            if (godownBox.checked) {
                selection.push([group.godown, null]);
            } else {
                checked.forEach(cb => selection.push([group.godown, group.compartments[cb.dataset.index]]));
            }
        });

        setLocations(selection);
    }

    function setLocations(selection) {

        const hidden = document.getElementById('locations');
        const btn = document.getElementById('locationDropdownBtn');
        const value = selection.length ? JSON.stringify(selection) : '';

        if (document.getElementById('warehouse_code').value) {
            btn.textContent = selection.length
                ? selection.map(([g, c]) => c === null ? godownLabel(g) : `${godownLabel(g)} / ${c}`).join(', ')
                : 'All Godowns / Compartments';
            btn.title = btn.textContent;
        }

        if (hidden.value !== value) {
            hidden.value = value;
            hidden.dispatchEvent(new Event('change'));
        }
    }

    function setupLocationDropdown() {

        document.getElementById('locationCheckboxList').addEventListener('change', e => {

            if (e.target.matches('.loc-godown')) {
                const gi = e.target.dataset.group;
                document
                    .querySelectorAll(`#locationCheckboxList .loc-compartment[data-group="${gi}"]`)
                    .forEach(cb => { cb.checked = e.target.checked; });
            }

            if (e.target.matches('input[type="checkbox"]')) {
                syncLocations();
            }
        });
    }


    /* ---------------- SUMMARY ---------------- */

    async function refreshSummary() {

        const response = await fetch(
            '{{ url("/admin/reports/summary") }}?' +
            getFilters()
        );

        if (!response.ok) {
            console.error('Report summary failed with status', response.status);
            return;
        }

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
                    data: 'godown',
                    defaultContent: ''
                },
                {
                    data: 'compartment',
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


    /* ---------------- CHART VIEW ---------------- */

    const chartColors = {
        normal: '#16a34a',
        severe: '#f59e0b',
        critical: '#dc2626',
        avg: '#6cb8f0',
        max: '#5b3a7e',
        min: '#94a3b8',
    };

    let gasCharts = [];

    // 674.3958 -> "674", 0.41237 -> "0.412"
    function formatValue(value) {
        if (value === null || value === undefined) return '-';
        const n = Number(value);
        return n.toLocaleString('en-IN', { maximumFractionDigits: Math.abs(n) >= 100 ? 0 : 3 });
    }

    // Level of a value against the gas's ranges (same rule as the server: <= normal, <= severe, above).
    function valueLevel(value, thresholds) {
        if (value === null || value === undefined || !thresholds) return null;
        if (value <= thresholds.normal) return 'normal';
        if (value <= thresholds.severe) return 'severe';
        return 'critical';
    }
    let chartRequestId = 0;

    function isChartView() {
        return document.getElementById('view_chart').checked;
    }

    const DAY_START_HOUR = {{ (int) \App\Exports\ReportExport::DAY_START_HOUR }};
    const NIGHT_START_HOUR = {{ (int) \App\Exports\ReportExport::NIGHT_START_HOUR }};
    const pad2 = n => String(n).padStart(2, '0');
    const DAY_RANGE = `${pad2(DAY_START_HOUR)}:00–${pad2(NIGHT_START_HOUR)}:00`;
    const NIGHT_RANGE = `${pad2(NIGHT_START_HOUR)}:00–${pad2(DAY_START_HOUR)}:00`;

    function shortDate(date, withWeekday = false) {
        return new Date(date + 'T00:00:00').toLocaleDateString('en-GB', {
            ...(withWeekday ? { weekday: 'short' } : {}), day: '2-digit', month: 'short',
        });
    }

    // Bucket keys from the server: hour "2026-11-12 14:00", shift "2026-11-12 D" / "2026-11-12 N", month "2026-11".
    function isNightBucket(key, bucket) {
        if (bucket === 'hour') {
            const hour = parseInt(key.slice(11, 13), 10);
            return hour < DAY_START_HOUR || hour >= NIGHT_START_HOUR;
        }
        return bucket === 'shift' && key.endsWith(' N');
    }

    // Two-line x-axis label: date on top, time / shift below.
    function tickLabel(key, bucket) {
        if (bucket === 'hour') {
            const time = key.slice(11);
            return [shortDate(key.slice(0, 10)), (isNightBucket(key, bucket) ? '☾ ' : '☀ ') + time];
        }
        if (bucket === 'shift') {
            return [shortDate(key.slice(0, 10)), isNightBucket(key, bucket) ? '☾ Night' : '☀ Day'];
        }
        return new Date(key + '-01T00:00:00').toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
    }

    // Full text for the tooltip title.
    function fullLabel(key, bucket) {
        if (bucket === 'hour') {
            const hour = parseInt(key.slice(11, 13), 10);
            const shift = isNightBucket(key, bucket) ? '☾ Night' : '☀ Day';
            return `${shortDate(key.slice(0, 10), true)}, ${pad2(hour)}:00–${pad2((hour + 1) % 24)}:00 · ${shift}`;
        }
        if (bucket === 'shift') {
            const date = key.slice(0, 10);
            if (!isNightBucket(key, bucket)) {
                return `${shortDate(date, true)} · ☀ Day (${DAY_RANGE})`;
            }
            const next = new Date(date + 'T00:00:00');
            next.setDate(next.getDate() + 1);
            const nextKey = `${next.getFullYear()}-${pad2(next.getMonth() + 1)}-${pad2(next.getDate())}`;
            return `☾ Night: ${shortDate(date, true)} ${pad2(NIGHT_START_HOUR)}:00 → ${shortDate(nextKey, true)} ${pad2(DAY_START_HOUR)}:00`;
        }
        return tickLabel(key, bucket);
    }

    // Light grey band behind every night point / hour.
    const nightShadePlugin = {
        id: 'nightShade',
        beforeDatasetsDraw(chart, args, options) {
            const keys = options.keys || [];
            const { ctx, chartArea, scales: { x } } = chart;
            if (!keys.length || !chartArea) return;

            const step = keys.length > 1 ? x.getPixelForValue(1) - x.getPixelForValue(0) : chartArea.width;

            ctx.save();
            ctx.fillStyle = 'rgba(100, 116, 139, 0.12)';
            keys.forEach((key, index) => {
                if (!isNightBucket(key, options.bucket)) return;
                const center = x.getPixelForValue(index);
                const left = Math.max(chartArea.left, center - step / 2);
                const right = Math.min(chartArea.right, center + step / 2);
                ctx.fillRect(left, chartArea.top, right - left, chartArea.bottom - chartArea.top);
            });
            ctx.restore();
        },
    };

    async function refreshChart() {

        if (typeof Chart === 'undefined') {
            document.getElementById('chartMeta').textContent = 'Chart library could not be loaded.';
            return;
        }

        const requestId = ++chartRequestId;
        document.getElementById('chartMeta').textContent = 'Loading chart...';

        const response = await fetch(API.chart + '?' + getFilters());
        if (requestId !== chartRequestId) return; // a newer filter change is already loading

        if (!response.ok) {
            document.getElementById('chartMeta').textContent = 'Chart could not be loaded (status ' + response.status + ').';
            return;
        }

        const payload = await response.json();
        if (requestId !== chartRequestId || !payload.success) return;

        const bucketName = { hour: 'Hourly', shift: 'Day / Night per date', month: 'Monthly' }[payload.bucket] || '';
        document.getElementById('chartMeta').textContent =
            `${bucketName} view · ${payload.from_date} to ${payload.to_date} · ${payload.time_of_day}` +
            (document.getElementById('from_date').value || document.getElementById('to_date').value ? '' : ' (no date range selected: showing last 7 days)');

        const bucketKeys = payload.labels;
        const labels = bucketKeys.map(k => tickLabel(k, payload.bucket));
        const showShifts = payload.bucket === 'hour' || payload.bucket === 'shift';
        const hasData = payload.series.length > 0;

        document.getElementById('chartEmpty').classList.toggle('d-none', hasData);

        // One value chart per gas type (CO2 and PH3 have very different scales).
        gasCharts.forEach(c => c.destroy());
        gasCharts = [];

        const levelBadge = level => {
            if (!level) return '';
            const cls = { normal: 'bg-success', severe: 'bg-warning text-dark', critical: 'bg-danger' }[level] || 'bg-secondary';
            return `<span class="badge ${cls} ms-1">${level.charAt(0).toUpperCase() + level.slice(1)}</span>`;
        };

        const statBox = (label, value, level, unit) => `
            <div class="col-4">
                <div class="border rounded-3 p-2 h-100">
                    <div class="text-muted small">${label}</div>
                    <div class="fw-bold fs-5">${value === null ? '-' : formatValue(value)}<span class="fs-6 fw-normal text-muted">${value !== null && unit ? ' ' + escapeHtml(unit) : ''}</span></div>
                    <div>${levelBadge(level)}</div>
                </div>
            </div>
        `;

        const container = document.getElementById('gasCharts');
        container.innerHTML = payload.series.map((s, i) => `
            <div class="col-lg-${payload.series.length > 1 ? 6 : 12}">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2">${escapeHtml(s.gas)}${s.unit ? ' (' + escapeHtml(s.unit) + ')' : ''}</h6>

                        <div class="row g-2 mb-2">
                            ${statBox('Average', s.summary.avg, s.summary.avg_level, s.unit)}
                            ${statBox('Highest', s.summary.max, s.summary.max_level, s.unit)}
                            ${statBox('Lowest', s.summary.min, s.summary.min_level, s.unit)}
                        </div>

                        ${s.thresholds ? `
                            <div class="d-flex flex-wrap gap-3 small text-muted mb-1">
                                <span><span class="zone-chip" style="background:${chartColors.normal};border-color:${chartColors.normal}"></span>Normal: 0 – ${formatValue(s.thresholds.normal)}</span>
                                <span><span class="zone-chip" style="background:${chartColors.severe};border-color:${chartColors.severe}"></span>Severe: ${formatValue(s.thresholds.normal)} – ${formatValue(s.thresholds.severe)}</span>
                                <span><span class="zone-chip" style="background:${chartColors.critical};border-color:${chartColors.critical}"></span>Critical: above ${formatValue(s.thresholds.severe)}</span>
                            </div>` : ''}

                        ${s.summary.error_readings ? `
                            <div class="small text-danger mb-1">
                                ${s.summary.error_readings} reading(s) had a sensor error value (${payload.sensor_error_min_value} or more, e.g. 65534) and are not shown in the chart.
                            </div>` : ''}

                        ${showShifts ? `
                            <div class="small text-muted mb-1">
                                ☀ Day ${DAY_RANGE} &nbsp;·&nbsp;
                                <span class="night-chip"></span>☾ Night ${NIGHT_RANGE} (shaded)
                            </div>` : ''}

                        <div style="position: relative; height: 300px;">
                            <canvas id="gasChart_${i}"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        payload.series.forEach((s, i) => {
            const t = s.thresholds;

            // Clean line style: smooth thin lines, no point markers (shown on hover only).
            const line = (label, data, color, extra = {}) => ({
                label, data,
                borderColor: color,
                backgroundColor: color,
                borderWidth: 2.5,
                tension: 0.35,
                cubicInterpolationMode: 'monotone',
                spanGaps: true,
                pointRadius: 0,
                pointHoverRadius: 4,
                fill: false,
                ...extra,
            });

            const datasets = [
                line('Average', s.avg, chartColors.avg),
                line('Highest', s.max, chartColors.max),
                line('Lowest', s.min, chartColors.min, { hidden: true }),
            ];

            // Limit lines only when the values come close to them, so they do not flatten the value lines.
            if (t) {
                const peak = Math.max(0, ...s.max.filter(v => v !== null));
                const flat = v => labels.map(() => v);
                if (peak >= t.normal * 0.8) {
                    datasets.push(line('Normal limit', flat(t.normal), chartColors.normal, { borderWidth: 1, borderDash: [6, 5], tension: 0, pointHoverRadius: 0 }));
                }
                if (peak >= t.severe * 0.8) {
                    datasets.push(line('Severe limit', flat(t.severe), chartColors.critical, { borderWidth: 1, borderDash: [6, 5], tension: 0, pointHoverRadius: 0 }));
                }
            }

            gasCharts.push(new Chart(document.getElementById('gasChart_' + i), {
                type: 'line',
                data: { labels, datasets },
                plugins: [nightShadePlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10, color: '#6b7280' },
                        },
                        y: {
                            // Fit the scale to the values so ups and downs are visible.
                            beginAtZero: false,
                            grace: '10%',
                            grid: { color: '#d1d5db' },
                            border: { display: false },
                            ticks: { maxTicksLimit: 7, color: '#6b7280', callback: v => formatValue(v) },
                            title: { display: true, text: s.unit ? `Value (${s.unit})` : 'Value', color: '#6b7280' },
                        },
                    },
                    plugins: {
                        nightShade: { keys: bucketKeys, bucket: payload.bucket },
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, pointStyle: 'line', boxWidth: 24 },
                        },
                        tooltip: {
                            filter: item => item.datasetIndex < 3,
                            callbacks: {
                                title: items => items.length ? fullLabel(bucketKeys[items[0].dataIndex], payload.bucket) : '',
                                label: item => {
                                    if (item.raw === null || item.raw === undefined) return `${item.dataset.label}: no data`;
                                    const level = valueLevel(item.raw, t);
                                    return `${item.dataset.label}: ${formatValue(item.raw)}${s.unit ? ' ' + s.unit : ''}` +
                                        (level ? ` (${level.charAt(0).toUpperCase() + level.slice(1)})` : '');
                                },
                            },
                        },
                    },
                },
            }));
        });
    }

    function setupViewSwitch() {
        document.querySelectorAll('input[name="report_view"]').forEach(radio => {
            radio.addEventListener('change', () => {
                const chart = isChartView();
                document.getElementById('chartSection').classList.toggle('d-none', !chart);
                document.getElementById('tableSection').classList.toggle('d-none', chart);
                document.getElementById('colvisContainer').classList.toggle('invisible', chart);

                if (chart) {
                    refreshChart();
                } else if (table) {
                    table.columns.adjust();
                }
            });
        });
    }


    /* ---------------- REFRESH ---------------- */

    function reloadTable() {

        buildExportLinks();

        refreshSummary();

        table.ajax.reload();

        if (isChartView()) refreshChart();
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
                '#filtersForm input:not(.dd-check),#filtersForm select'
            )
            .forEach(el => {

                el.addEventListener(
                    'change',
                    autoRefresh
                );

            });
    }


    function setupDropdowns() {

        setupCheckboxDropdown('regionCheckboxList', 'region_code', 'regionDropdownBtn', 'All Regions', () => {
            loadWarehouses(document.getElementById('region_code').value);
        });

        setupCheckboxDropdown('warehouseCheckboxList', 'warehouse_code', 'warehouseDropdownBtn', 'All Warehouses', () => {
            loadLocations();
        });

        setupLocationDropdown();

        setupCheckboxDropdown('levelCheckboxList', 'level', 'levelDropdownBtn', 'All Levels');

    }


    function setupResetButton() {

        document
            .getElementById('btnReset')
            .addEventListener('click', async () => {

                document
                    .getElementById('filtersForm')
                    .reset();

                document.getElementById('regionDropdownBtn').textContent = 'All Regions';
                document.querySelectorAll('#regionCheckboxList input, #levelCheckboxList input, #locationCheckboxList input')
                    .forEach(cb => { cb.checked = false; });
                ['region_code', 'level', 'locations'].forEach(id => { document.getElementById(id).value = ''; });
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

            setupResetButton();

            setupViewSwitch();

        }
    );
</script>
@endsection
