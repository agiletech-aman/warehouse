@extends('layouts.app')

@section('page-title', 'Devices')

@section('content')

<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Devices</h3>
                <p class="text-muted mb-0">Latest monitoring system devices received from sensor readings.</p>
            </div>

            <a href="{{ route('devices.export', request()->only(['region_code', 'warehouse_code'])) }}" class="btn btn-outline-primary rounded-pill px-3">
                Export
            </a>
        </div>

        @if(session('success'))
        <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <div class="text-muted small text-uppercase">Total Devices</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($deviceCounts['total'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Latest devices from readings.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <div class="text-muted small text-uppercase">Online Devices</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($deviceCounts['online'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Devices currently online.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <div class="text-muted small text-uppercase">Offline Devices</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($deviceCounts['offline'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Devices currently offline.</p>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('devices.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-lg-5">
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

            <div class="col-lg-5">
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

            <div class="col-lg-2 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Filter</button>
                <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table id="devicesTable" class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Region</th>
                        <th>Warehouse</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Latest Reading</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $readingDevice)
                    @php($deleteDeviceLabel = $readingDevice->device_name ?: ($readingDevice->sensor_device_id ?: 'this device'))
                    <tr>
                        <td>{{ $readingDevice->sensor_device_id ?: '-' }}</td>
                        <td>{{ $readingDevice->device_name ?: '-' }}</td>
                        <td>
                            {{ $readingDevice->region ?: ($readingDevice->region_code ?: '-') }}
                            @if($readingDevice->region_code)
                            <span class="text-muted">({{ $readingDevice->region_code }})</span>
                            @endif
                        </td>
                        <td>
                            {{ $readingDevice->warehouse ?: ($readingDevice->warehouse_code ?: '-') }}
                            @if($readingDevice->warehouse_code)
                            <span class="text-muted">({{ $readingDevice->warehouse_code }})</span>
                            @endif
                        </td>
                        <td>{{ $readingDevice->device_type ?: '-' }}</td>
                        <td>{{ $readingDevice->godown ?: '-' }}{{ $readingDevice->compartment ? ' / '.$readingDevice->compartment : '' }}</td>
                        <td>
                            {{ $readingDevice->reading_value ?? '-' }}
                            @if($readingDevice->unit)
                            {{ $readingDevice->unit }}
                            @endif
                        </td>
                        <td>
                            @php($deviceStatus = $readingDevice->status ?? 'offline')
                            @if($deviceStatus === 'online')
                            <span class="badge bg-success">Online</span>
                            @elseif($deviceStatus === 'offline')
                            <span class="badge bg-secondary">Offline</span>
                            @else
                            <span class="badge bg-light text-dark">{{ ucfirst($deviceStatus) }}</span>
                            @endif

                            @php($deviceLevel = strtolower((string) ($readingDevice->level ?? 'normal')))
                            @if($deviceLevel === 'critical')
                            <span class="badge bg-danger">Critical</span>
                            @elseif($deviceLevel === 'severe')
                            <span class="badge bg-warning text-dark">Severe</span>
                            @else
                            <span class="badge bg-success">Normal</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button type="button"
                                class="btn btn-link text-danger p-0 delete-device-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteDeviceModal"
                                data-action="{{ route('devices.reading-destroy', $readingDevice->id) }}"
                                data-device="{{ $deleteDeviceLabel }}"
                                title="Delete"
                                aria-label="Delete {{ $deleteDeviceLabel }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
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

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteDeviceModal');
        const deleteForm = document.getElementById('deleteDeviceForm');
        const deleteDeviceName = document.getElementById('deleteDeviceName');

        if (deleteModal && deleteModal.parentElement !== document.body) {
            document.body.appendChild(deleteModal);
        }

        if (deleteModal && deleteForm && deleteDeviceName) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                if (!button) return;

                deleteForm.action = button.getAttribute('data-action') || '';
                deleteDeviceName.textContent = button.getAttribute('data-device') || 'this device';
            });
        }

        window.initWarehouseDataTable('#devicesTable', {
            searching: true,
            paging: true,
            info: true,
            pageLength: 10,
            language: {
                search: 'Search devices:'
            }
        });

        window.setInterval(function() {
            if (!document.hidden && !document.body.classList.contains('modal-open')) {
                window.location.reload();
            }
        }, 30000);
    });
</script>
@endsection
