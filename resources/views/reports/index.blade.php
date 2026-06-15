@extends('layouts.app')

@section('page-title', 'Reports')

@section('content')
<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Reports</h3>
                <p class="text-muted mb-0">Advanced filtering, analytics and export for all monitoring data.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif


        {{-- Sticky Filters --}}
        <div class="card border-0 shadow-sm p-3 mb-4" style="position: sticky; top: 84px; z-index: 10; background: rgba(255,255,255,.98);">
            <form id="filtersForm" class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="from_date" id="from_date" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="to_date" id="to_date" />
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label">Region</label>
                    <select class="form-select" name="region_code" id="region_code">
                        <option value="">All Regions</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label">Warehouse</label>
                    <select class="form-select" name="warehouse_code" id="warehouse_code" disabled>
                        <option value="">Select Region First</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label">Device</label>
                    <select class="form-select" name="device_code" id="device_code" disabled>
                        <option value="">Select Warehouse First</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-none">
                    <label class="form-label">Device Type</label>
                    <select class="form-select" name="device_type" id="device_type">
                        <option value="">All Types</option>
                        <option value="co2">CO2</option>
                        <option value="phosphorus">Phosphorus</option>
                        <option value="humidity">Humidity</option>
                        <option value="temperature">Temperature</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-none">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" id="status">
                        <option value="">All</option>
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-none">
                    <label class="form-label">Level</label>
                    <select class="form-select" name="level" id="level">
                        <option value="">All</option>
                        <option value="normal">Normal</option>
                        <option value="severe">Severe</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>


                <div class="col-12 col-md-3" style="display:none;">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="report_type" id="report_type" aria-hidden="true" tabindex="-1">
                        <option value="reading" selected>Reading Report</option>
                        <option value="alert">Alert Report</option>
                        <option value="severe_alert">Severe Alert Report</option>
                        <option value="critical_alert">Critical Alert Report</option>
                        <option value="offline_device">Offline Device Report</option>
                    </select>
                </div>


                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" id="btnGenerate">Generate</button>
                </div>
            </form>
        </div>

        {{-- Statistics Cards --}}
        <div class="row g-3 mb-4" id="statsRow">
            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Total Readings</div><div class="h4 mb-0" id="stat_total_readings">0</div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Total Devices</div><div class="h4 mb-0" id="stat_total_devices">0</div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Online Devices</div><div class="h4 mb-0" id="stat_online_devices">0</div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Offline Devices</div><div class="h4 mb-0" id="stat_offline_devices">0</div></div></div>

            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Severe Alerts</div><div class="h4 mb-0" id="stat_severe_alerts">0</div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Critical Alerts</div><div class="h4 mb-0" id="stat_critical_alerts">0</div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Regions Count</div><div class="h4 mb-0" id="stat_regions_count">0</div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="text-muted">Warehouses Count</div><div class="h4 mb-0" id="stat_warehouses_count">0</div></div></div>
        </div>

        {{-- Export Buttons --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-outline-primary btn-sm rounded-pill" id="export_pdf" href="#">Export PDF</a>
            <a class="btn btn-outline-success btn-sm rounded-pill" id="export_excel" href="#">Export Excel</a>
            <a class="btn btn-outline-secondary btn-sm rounded-pill" id="export_csv" href="#">Export CSV</a>
            <button class="btn btn-outline-dark btn-sm rounded-pill" onclick="window.print()">Print Report</button>
        </div>

        <div class="table-responsive">
            <table id="reportsTable" class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Region</th>
                    <th>Region Code</th>
                    <th>Warehouse</th>
                    <th>Warehouse Code</th>
                    <th>Device Name</th>
                    <th>Device Code / Sensor Device ID</th>
                    <th>Device Type</th>
                    <th>Device IP</th>
                    <th>Value</th>
                    <th>Unit</th>
                    <th>Level</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="mt-3 text-muted" id="tableInfo"></div>


    </div>
</div>
@endsection

@section('styles')
<style>
    .badge.bg-severe { background-color:#f59e0b !important; }

    /* DataTables button container spacing */
    #reportsTable_wrapper .dt-buttons { margin-bottom: 10px; }

    /* Sticky filter visuals */
    #filtersForm label { font-weight: 600; font-size: 12px; color: #4b5563; }

    .table th { white-space: nowrap; }
</style>
@endsection

@section('scripts')
<script>

    const API = {
        regions: '{{ url('/api/regions') }}',
        warehouses: '{{ url('/api/warehouses') }}',
        devices: '{{ url('/api/devices') }}',
    };

    const routeExportPdf = '{{ route("reports.export", ["format" => "pdf"]) }}';
    const routeExportExcel = '{{ route("reports.export", ["format" => "excel"]) }}';
    const routeExportCsv = '{{ route("reports.export", ["format" => "csv"]) }}';



    function getFilters() {
        const form = document.getElementById('filtersForm');
        const data = new FormData(form);
        const params = new URLSearchParams();
        for (const [k, v] of data.entries()) {
            if (v !== null && v !== '') params.set(k, v);
        }
        return params;
    }

    async function loadRegions() {
        const res = await fetch(API.regions + '?per_page=1000');
        const json = await res.json();
        const sel = document.getElementById('region_code');
        sel.innerHTML = '<option value="">All Regions</option>';
        (json.data?.data || json.data || []).forEach(r => {
            const code = r.region_code ? r.region_code : (r.region_name || '');

            if (!code) return;
            const opt = document.createElement('option');
            opt.value = code;
            opt.textContent = r.region_name ? ('' + r.region_name) : code;
            sel.appendChild(opt);
        });
    }


    async function loadWarehouses(region_code) {
        const sel = document.getElementById('warehouse_code');
        sel.disabled = true;
        sel.innerHTML = '<option value="">Loading...</option>';
        const res = await fetch(API.warehouses + '?per_page=1000&region_code=' + encodeURIComponent(region_code));
        const json = await res.json();
        sel.innerHTML = '<option value="">All Warehouses</option>';
        (json.data?.data || json.data || []).forEach(w => {
            const opt = document.createElement('option');
            opt.value = w.warehouse_code;
            opt.textContent = w.warehouse_name ? w.warehouse_name : w.warehouse_code;
            sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    async function loadDevices(warehouse_code) {
        const sel = document.getElementById('device_code');
        sel.disabled = true;
        sel.innerHTML = '<option value="">Loading...</option>';

        // Device API supports warehouse_code filter via whereHas warehouse.
        const res = await fetch(API.devices + '?per_page=1000&warehouse_code=' + encodeURIComponent(warehouse_code));
        const json = await res.json();
        sel.innerHTML = '<option value="">All Devices</option>';
        (json.data?.data || json.data || []).forEach(d => {
            const code = d.device_code || d.device_code === '' ? d.device_code : (d.device_code || d.ip_address || '');
            const sensorId = d.device_code ?? d.device_code;
            const opt = document.createElement('option');
            opt.value = sensorId || d.device_code || '';
            opt.textContent = d.device_name ? `${d.device_name} (${sensorId})` : sensorId;
            if (opt.value) sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    // Charts were removed from the Reports page. Keep this as a no-op.
    function renderCharts(payload) {
        // no-op
    }


    async function refreshSummary() {
        const params = getFilters();

        const res = await fetch('{{ url('/admin/reports/summary') }}?' + params.toString());
        const payload = await res.json();
        if (!payload.success) return;

        document.getElementById('stat_total_readings').textContent = payload.stats.total_readings;
        document.getElementById('stat_total_devices').textContent = payload.stats.total_devices;
        document.getElementById('stat_online_devices').textContent = payload.stats.online_devices;
        document.getElementById('stat_offline_devices').textContent = payload.stats.offline_devices;
        document.getElementById('stat_severe_alerts').textContent = payload.stats.severe_alerts;
        document.getElementById('stat_critical_alerts').textContent = payload.stats.critical_alerts;
        document.getElementById('stat_regions_count').textContent = payload.stats.regions_count;
        document.getElementById('stat_warehouses_count').textContent = payload.stats.warehouses_count;

        renderCharts(payload);
    }

    function buildExportLink(baseRoute) {
        const params = getFilters();
        return baseRoute + '?' + params.toString();
    }

    let table;

    function initTable() {
        if (table) return;

        table = new DataTable('#reportsTable', {
            dom: 'Bfrtip',
            autoWidth: false,

            buttons: [
                {
                    extend: 'colvis',
                    text: 'Choose Columns',
                    collectionLayout: 'fixed two-column',
                    columns: ':visible'
                }
            ],


            responsive: true,
            searching: true,
            pageLength: 10,
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ url('/admin/reports/data') }}',
                type: 'GET',
                data: function(d) {
                    const params = getFilters();
                    for (const [k, v] of params.entries()) {
                        d[k] = v;
                    }
                }
            },
            columns: [
                { data: 'date_time' },
                { data: 'region' },
                { data: 'region_code' },
                { data: 'warehouse' },
                { data: 'warehouse_code' },
                { data: 'device_name' },
                { data: 'device_code' },
                { data: 'device_type' },
                { data: 'device_ip' },
                { data: 'value' },
                { data: 'unit' },
                {
                    data: 'level',
                    render: function(d, type, row) {
                        if (!d) return '-';
                        let cls = 'bg-success';
                        let label = (d === 'normal') ? 'Normal' : (d === 'severe' ? 'Severe' : (d === 'critical' ? 'Critical' : d));
                        if (d === 'severe') cls = 'bg-severe text-dark';
                        if (d === 'critical') cls = 'bg-danger';
                        return '<span class="badge ' + cls + '">' + label + '</span>';
                    }
                },
                {
                    data: 'status',
                    render: function(d) {
                        if (!d) d = 'offline';
                        if (d === 'online') return '<span class="badge bg-success">Online</span>';
                        if (d === 'offline') return '<span class="badge bg-secondary">Offline</span>';
                        return '<span class="badge bg-light text-dark">' + d + '</span>';
                    }
                },
            ],
            language: {
                search: 'Search reports:',
                lengthMenu: 'Show _MENU_ entries'
            },
            order: [[0, 'desc']]
        });
    }

    document.addEventListener('DOMContentLoaded', async function() {
        await loadRegions();

        initTable();
        refreshSummary();

        document.getElementById('region_code').addEventListener('change', async (e) => {
            const v = e.target.value;
            document.getElementById('warehouse_code').value = '';
            document.getElementById('device_code').value = '';
            if (!v) {
                const selW = document.getElementById('warehouse_code');
                selW.disabled = true;
                selW.innerHTML = '<option value="">Select Region First</option>';
                return;
            }
            await loadWarehouses(v);
        });

        document.getElementById('warehouse_code').addEventListener('change', async (e) => {
            const v = e.target.value;
            document.getElementById('device_code').value = '';
            if (!v) {
                const selD = document.getElementById('device_code');
                selD.disabled = true;
                selD.innerHTML = '<option value="">Select Warehouse First</option>';
                return;
            }
            await loadDevices(v);
        });

        document.getElementById('btnGenerate').addEventListener('click', async () => {
            // Open DataTables ColVis (Choose Columns) immediately on Generate click
            try {
                const colvisButton = document.querySelector('#reportsTable_wrapper .dt-buttons button.dt-button');
                if (colvisButton) colvisButton.click();
            } catch (e) {}

            // Update export links (export will use the currently selected visible columns from colvis)
            // Persist selected columns into hidden inputs so backend export can read them.
            try {
                const visibleIdx = [];
                table.columns().every(function(idx) {
                    if (this.visible()) visibleIdx.push(idx);
                });

                // Map idx -> column key (must match backend mapping order)
                const colKeys = [
                    'date_time','region','region_code','warehouse','warehouse_code',
                    'device_name','device_code','device_type','device_ip','value','unit','level','status'
                ];
                const selected = visibleIdx.map(i => colKeys[i]).filter(Boolean);

                let hidden = document.getElementById('selected_cols');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.id = 'selected_cols';
                    hidden.name = 'selected_cols';
                    document.getElementById('filtersForm').appendChild(hidden);
                }
                hidden.value = selected.join(',');
            } catch (e) {}


            document.getElementById('export_pdf').href = buildExportLink(routeExportPdf);
            document.getElementById('export_excel').href = buildExportLink(routeExportExcel);
            document.getElementById('export_csv').href = buildExportLink(routeExportCsv);

            await refreshSummary();
            table.ajax.reload();
        });

    });
</script>
@endsection

