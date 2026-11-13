@extends('layouts.app')

@section('page-title', 'Devices')

@section('styles')
<style>
    .device-summary-metric + .device-summary-metric {
        border-left: 1px solid #e9ecef;
    }

    .device-summary-toggle .summary-chevron {
        transition: transform .2s ease;
    }

    .device-summary-toggle[aria-expanded="true"] .summary-chevron {
        transform: rotate(180deg);
    }

    .device-type-card {
        background: #f8fafc;
        border: 1px solid #e9ecef;
    }

    @media (max-width: 767.98px) {
        .device-summary-metric + .device-summary-metric {
            border-left: 0;
            border-top: 1px solid #e9ecef;
        }
    }
</style>
@endsection

@section('content')

<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Devices</h3>
                <p class="text-muted mb-0">Latest monitoring system devices received from sensor readings.</p>
            </div>

            <a id="devicesExport" href="{{ route('devices.export', request()->only(['region_code', 'warehouse_code', 'status', 'search'])) }}" class="btn btn-outline-primary rounded-pill px-3">
                Export
            </a>
        </div>

        @if(session('success'))
        <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif

        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-body p-0">
                <div class="px-4 pt-4">
                    <h5 class="fw-bold mb-1">Overall Summary</h5>
                    <p class="text-muted small mb-0">Latest status of devices from sensor readings.</p>
                </div>

                <div class="row g-0 mt-3">
                    <div class="col-md-3 device-summary-metric p-4">
                    <div class="text-muted small text-uppercase">Total Devices</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($deviceCounts['total'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Latest devices from readings.</p>
                    </div>

                    <div class="col-md-3 device-summary-metric p-4">
                    <div class="text-muted small text-uppercase">Online Devices</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($deviceCounts['online'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Devices currently online.</p>
                    </div>

                    <div class="col-md-3 device-summary-metric p-4">
                    <div class="text-muted small text-uppercase">Offline Devices</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($deviceCounts['offline'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Devices currently offline.</p>
                    </div>

                    <div class="col-md-3 device-summary-metric p-4">
                        <div class="text-muted small text-uppercase">Active Warehouses</div>
                        <div class="display-6 fw-bold mt-2">{{ number_format($activeWarehouseCount ?? 0) }}</div>
                        <p class="text-muted mb-0">Warehouses reporting in the last 24 hours.</p>
                    </div>
                </div>

                <button class="btn btn-primary rounded-0 w-100 py-3 device-summary-toggle"
                    type="button" data-bs-toggle="collapse" data-bs-target="#detailedDeviceSummary"
                    aria-expanded="false" aria-controls="detailedDeviceSummary">
                    <span>View Detailed Summary</span>
                    <i class="fa-solid fa-chevron-down ms-2 summary-chevron"></i>
                </button>

                <div class="collapse" id="detailedDeviceSummary">
                    <div class="p-4 border-top">
                        <h6 class="fw-bold mb-3">Device Type Summary</h6>
                        <div class="row g-3 mb-4">
                            @foreach(['CO2' => 'CO₂ Devices', 'PH3' => 'PH₃ Devices'] as $type => $label)
                            <div class="col-lg-6">
                                <div class="device-type-card rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="fw-semibold">{{ $label }}</span>
                                        <span class="badge bg-primary rounded-pill">{{ number_format($deviceTypeCounts[$type]['total'] ?? 0) }} total</span>
                                    </div>
                                    <div class="row text-center g-2">
                                        <div class="col-6">
                                            <div class="text-success fw-bold fs-4">{{ number_format($deviceTypeCounts[$type]['online'] ?? 0) }}</div>
                                            <div class="text-muted small">Online</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-secondary fw-bold fs-4">{{ number_format($deviceTypeCounts[$type]['offline'] ?? 0) }}</div>
                                            <div class="text-muted small">Offline</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h6 class="fw-bold mb-0">Warehouse-wise Device Summary</h6>
                            <a id="detailedDevicesExport"
                                href="{{ route('devices.detailed-summary.export', request()->only(['region_code', 'warehouse_code', 'status'])) }}"
                                class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                Export Detailed Summary
                            </a>
                        </div>
                        <div class="table-responsive border rounded-3">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th rowspan="2" class="align-middle">Warehouse</th>
                                        <th rowspan="2" class="align-middle">Region</th>
                                        <th colspan="3" class="text-center border-start">Overall</th>
                                        <th colspan="3" class="text-center border-start">CO₂</th>
                                        <th colspan="3" class="text-center border-start">PH₃</th>
                                    </tr>
                                    <tr>
                                        @foreach(range(1, 3) as $group)
                                        <th class="text-center border-start">Total</th>
                                        <th class="text-center">Online</th>
                                        <th class="text-center">Offline</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($warehouseDeviceCounts as $warehouseSummary)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $warehouseSummary['name'] }}</div>
                                            @if($warehouseSummary['code'] && $warehouseSummary['code'] !== $warehouseSummary['name'])
                                            <div class="text-muted small">{{ $warehouseSummary['code'] }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $warehouseSummary['region_name'] }}</div>
                                            @if($warehouseSummary['region_code'] && $warehouseSummary['region_code'] !== $warehouseSummary['region_name'])
                                            <div class="text-muted small">{{ $warehouseSummary['region_code'] }}</div>
                                            @endif
                                        </td>
                                        @foreach(['overall', 'CO2', 'PH3'] as $group)
                                        <td class="text-center border-start fw-semibold">{{ number_format($warehouseSummary[$group]['total']) }}</td>
                                        <td class="text-center text-success">{{ number_format($warehouseSummary[$group]['online']) }}</td>
                                        <td class="text-center text-secondary">{{ number_format($warehouseSummary[$group]['offline']) }}</td>
                                        @endforeach
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">No warehouse device data found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('devices.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-lg-4">
                <label for="regionFilter" class="form-label mb-1">Region</label>
                <select id="regionFilter" name="region_code" class="form-select">
                    <option value="">All Regions</option>
                    @foreach($regions as $region)
                    @php($regionValue = $region->region_code ?: $region->region)
                    <option value="{{ $regionValue }}" @selected($selectedRegion===$regionValue)>
                        {{ $region->region ?: $regionValue }}
                        @if($region->region_code)
                        ({{ $region->region_code }})
                        @endif
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-4">
                <label for="warehouseFilter" class="form-label mb-1">Warehouse</label>
                <select id="warehouseFilter" name="warehouse_code" class="form-select">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                    @php($warehouseValue = $warehouse->warehouse_code ?: $warehouse->warehouse)
                    <option value="{{ $warehouseValue }}" @selected($selectedWarehouse===$warehouseValue)>
                        {{ $warehouse->warehouse ?: $warehouseValue }}
                        @if($warehouse->warehouse_code)
                        ({{ $warehouse->warehouse_code }})
                        @endif
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-2">
                <label for="statusFilter" class="form-label mb-1">Status</label>
                <select id="statusFilter" name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="online" @selected($selectedStatus === 'online')>Online</option>
                    <option value="offline" @selected($selectedStatus === 'offline')>Offline</option>
                </select>
            </div>

            <div class="col-lg-2 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Filter</button>
                <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Reset</a>
            </div>
        </form>

        <div id="bulkDeleteBar" class="d-none align-items-center justify-content-between gap-2 bg-light border rounded-3 px-3 py-2 mb-3">
            <span class="fw-semibold"><span id="selectedDeviceCount">0</span> device(s) selected</span>
            <div class="d-flex gap-2">
                <button type="button" id="clearDeviceSelection" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Clear</button>
                <button type="button" class="btn btn-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#bulkDeleteDeviceModal">
                    <i class="fa-solid fa-trash me-1"></i> Delete Selected
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="devicesTable" class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAllDevices" aria-label="Select all devices on this page">
                        </th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Region</th>
                        <th>Warehouse</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Latest Reading</th>
                        <th>Latest Reading Time</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade" id="deleteDeviceModal" tabindex="-1" aria-labelledby="deleteDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="deleteDeviceForm" method="POST" action="">
                @csrf
                @method('DELETE')

                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold" id="deleteDeviceModalLabel">Delete Device</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body px-4 pt-3 pb-2">
                    <p class="mb-3 text-muted">
                        Are you sure you want to delete
                        <strong class="text-dark" id="deleteDeviceName">this device</strong>?
                    </p>

                    <div class="alert alert-danger d-flex align-items-start gap-2 rounded-3 mb-0 py-3">
                        <i class="fas fa-exclamation-triangle mt-1"></i>
                        <div>
                            <strong>Warning:</strong>
                            This will permanently delete the device and all associated readings.
                            This action cannot be undone.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-3">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">
                        <i class="fa-solid fa-trash me-1"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkDeleteDeviceModal" tabindex="-1" aria-labelledby="bulkDeleteDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="bulkDeleteDeviceForm" method="POST" action="{{ route('devices.bulk-destroy') }}">
                @csrf
                @method('DELETE')
                <div id="bulkDeleteDeviceInputs"></div>

                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold" id="bulkDeleteDeviceModalLabel">Delete Selected Devices</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body px-4 pt-3 pb-2">
                    <p class="mb-3 text-muted">
                        Are you sure you want to delete
                        <strong class="text-dark"><span id="bulkDeleteDeviceCount">0</span> selected device(s)</strong>?
                    </p>

                    <div class="alert alert-danger d-flex align-items-start gap-2 rounded-3 mb-0 py-3">
                        <i class="fas fa-exclamation-triangle mt-1"></i>
                        <div>
                            <strong>Warning:</strong>
                            This will permanently delete the selected devices and all associated readings.
                            This action cannot be undone.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-3">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">
                        <i class="fa-solid fa-trash me-1"></i> Delete Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const escapeText = function(value) {
            const displayValue = value === null || value === undefined || value === '' ? '-' : value;
            return $('<div>').text(displayValue).html();
        };

        const deleteModal = document.getElementById('deleteDeviceModal');
        const deleteForm = document.getElementById('deleteDeviceForm');
        const deleteDeviceName = document.getElementById('deleteDeviceName');
        const detailedSummary = document.getElementById('detailedDeviceSummary');
        const detailedSummaryButtonText = document.querySelector('.device-summary-toggle span');

        if (detailedSummary && detailedSummaryButtonText) {
            detailedSummary.addEventListener('shown.bs.collapse', function() {
                detailedSummaryButtonText.textContent = 'Hide Detailed Summary';
            });
            detailedSummary.addEventListener('hidden.bs.collapse', function() {
                detailedSummaryButtonText.textContent = 'View Detailed Summary';
            });
        }

        if (deleteModal && deleteModal.parentElement !== document.body) {
            document.body.appendChild(deleteModal);
        }

        // ---- Multi-select bulk delete ----
        const selectedDevices = new Set();
        const bulkDeleteModal = document.getElementById('bulkDeleteDeviceModal');
        const bulkDeleteBar = document.getElementById('bulkDeleteBar');
        const selectAllCheckbox = document.getElementById('selectAllDevices');

        if (bulkDeleteModal && bulkDeleteModal.parentElement !== document.body) {
            document.body.appendChild(bulkDeleteModal);
        }

        const pageCheckboxes = function() {
            return Array.from(document.querySelectorAll('#devicesTable tbody .device-select'));
        };

        const refreshSelectionUi = function() {
            const count = selectedDevices.size;
            document.getElementById('selectedDeviceCount').textContent = count;
            bulkDeleteBar.classList.toggle('d-none', count === 0);
            bulkDeleteBar.classList.toggle('d-flex', count > 0);

            const boxes = pageCheckboxes();
            const checkedOnPage = boxes.filter(function(box) { return box.checked; }).length;
            selectAllCheckbox.checked = boxes.length > 0 && checkedOnPage === boxes.length;
            selectAllCheckbox.indeterminate = checkedOnPage > 0 && checkedOnPage < boxes.length;
        };

        document.getElementById('devicesTable').addEventListener('change', function(event) {
            const box = event.target;
            if (!box.classList || !box.classList.contains('device-select')) return;

            box.checked ? selectedDevices.add(box.value) : selectedDevices.delete(box.value);
            refreshSelectionUi();
        });

        selectAllCheckbox.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        selectAllCheckbox.addEventListener('change', function() {
            const checked = selectAllCheckbox.checked;
            pageCheckboxes().forEach(function(box) {
                box.checked = checked;
                checked ? selectedDevices.add(box.value) : selectedDevices.delete(box.value);
            });
            refreshSelectionUi();
        });

        document.getElementById('clearDeviceSelection').addEventListener('click', function() {
            selectedDevices.clear();
            pageCheckboxes().forEach(function(box) { box.checked = false; });
            refreshSelectionUi();
        });

        bulkDeleteModal.addEventListener('show.bs.modal', function(event) {
            if (selectedDevices.size === 0) {
                event.preventDefault();
                return;
            }

            const container = document.getElementById('bulkDeleteDeviceInputs');
            container.innerHTML = '';
            selectedDevices.forEach(function(id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'device_ids[]';
                input.value = id;
                container.appendChild(input);
            });
            document.getElementById('bulkDeleteDeviceCount').textContent = selectedDevices.size;
        });

        if (deleteModal && deleteForm && deleteDeviceName) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                if (!button) return;

                deleteForm.action = button.getAttribute('data-action') || '';
                deleteDeviceName.textContent = button.getAttribute('data-device') || 'this device';
            });
        }

        const table = window.initWarehouseDataTable('#devicesTable', {
            processing: true,
            serverSide: true,
            searching: true,
            paging: true,
            info: true,
            pageLength: 10,
            search: { search: new URLSearchParams(window.location.search).get('search') || '' },
            order: [],
            language: {
                search: 'Search devices:'
            },
            ajax: {
                url: '{{ route('devices.data') }}',
                data: function(data) {
                    const params = new URLSearchParams(window.location.search);
                    ['region_code', 'warehouse_code', 'status'].forEach(function(key) {
                        if (params.get(key)) {
                            data[key] = params.get(key);
                        }
                    });
                }
            },
            columns: [
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    responsivePriority: 1,
                    render: function(value, type, row) {
                        if (!value) return '';
                        const checked = selectedDevices.has(String(value)) ? ' checked' : '';
                        return '<input type="checkbox" class="form-check-input device-select" value="' + escapeText(value) + '"' +
                            checked + ' aria-label="Select ' + escapeText(row.delete_label || value) + '">';
                    }
                },
                { data: 'code', defaultContent: '-', render: escapeText },
                { data: 'name', defaultContent: '-', render: escapeText },
                {
                    data: 'region',
                    defaultContent: '-',
                    render: function(value, type, row) {
                        const text = row.region_code ? value + ' (' + row.region_code + ')' : value;
                        return escapeText(text);
                    }
                },
                {
                    data: 'warehouse',
                    defaultContent: '-',
                    render: function(value, type, row) {
                        const text = row.warehouse_code ? value + ' (' + row.warehouse_code + ')' : value;
                        return escapeText(text);
                    }
                },
                { data: 'type', defaultContent: '-', render: escapeText },
                { data: 'location', defaultContent: '-', render: escapeText },
                {
                    data: 'value',
                    defaultContent: '',
                    render: function(value, type, row) {
                        if (value === null || value === undefined || value === '') {
                            return 'N/A';
                        }

                        return escapeText(row.unit ? value + ' ' + row.unit : value);
                    }
                },
                { data: 'recorded_at', defaultContent: '-', render: escapeText },
                {
                    data: 'status',
                    defaultContent: 'offline',
                    render: function(value, type, row) {
                        const status = String(value || 'offline').toLowerCase();
                        const level = String(row.level || 'unknown').toLowerCase();
                        let html = status === 'online'
                            ? '<span class="badge bg-success">Online</span>'
                            : status === 'offline'
                                ? '<span class="badge bg-secondary">Offline</span>'
                                : '<span class="badge bg-light text-dark">' + escapeText(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';

                        if (level === 'critical') {
                            html += ' <span class="badge bg-danger">Critical</span>';
                        } else if (level === 'severe') {
                            html += ' <span class="badge bg-warning text-dark">Severe</span>';
                        } else if (level === 'normal') {
                            html += ' <span class="badge bg-success">Normal</span>';
                        } else {
                            html += ' <span class="badge bg-secondary">Unknown</span>';
                        }

                        return html;
                    }
                },
                {
                    data: 'delete_url',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(value, type, row) {
                        const label = row.delete_label || 'this device';
                        return '<button type="button" class="btn btn-link text-danger p-0 delete-device-btn"' +
                            ' data-bs-toggle="modal" data-bs-target="#deleteDeviceModal"' +
                            ' data-action="' + escapeText(value) + '" data-device="' + escapeText(label) + '"' +
                            ' title="Delete" aria-label="Delete ' + escapeText(label) + '">' +
                            '<i class="fa-solid fa-trash"></i></button>';
                    }
                }
            ]
        });

        table.on('draw.dt', refreshSelectionUi);

        table.on('search.dt', function () {
            const search = table.search();

            const exportLink = document.getElementById('devicesExport');
            const url = new URL(exportLink.href);
            search ? url.searchParams.set('search', search) : url.searchParams.delete('search');
            exportLink.href = url.toString();
        });

    });
</script>
@endsection
