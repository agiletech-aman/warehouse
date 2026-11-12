@extends('layouts.app')

@section('title','Dashboard')

@section('page-title','Dashboard')

@section('content')

<div class="content-shell">
    <div class="card border-0 shadow-sm p-4 mb-4">
        <h2 class="mb-1">Welcome {{ session('admin_name') }}</h2>
        <p class="text-muted mb-0">Here is a quick overview of your warehouse system.</p>
    </div>

    <div class="row g-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="text-muted small text-uppercase">Total Warehouses</div>
                <div class="display-6 fw-bold mt-2">{{ $totalWarehouses }}</div>
                <p class="text-muted mb-0">All warehouses in system.</p>
            </div>
        </div>

        <div class="col-md-3">
            <a href="{{ route('warehouses.index', ['active' => 1]) }}"
               class="card border-0 shadow-sm p-4 h-100 text-decoration-none text-body"
               aria-label="View active warehouses">
                <div class="text-muted small text-uppercase">Active Warehouses</div>
                <div class="display-6 fw-bold mt-2">{{ $activeWarehouses }}</div>
                <p class="text-muted mb-0">Have readings in last 24 hours.</p>
            </a>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="text-muted small text-uppercase">Regions</div>
                <div class="display-6 fw-bold mt-2">{{ $totalRegions }}</div>
                <p class="text-muted mb-0">Registered regions.</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="text-muted small text-uppercase">Last 24h Alerts</div>
                <div class="display-6 fw-bold mt-2">{{ $last24hAlertsCount }}</div>
                <p class="text-muted mb-0">Alert events created recently.</p>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <div class="text-muted small text-uppercase">Device Status</div>
                        <h4 class="mb-1 mt-1">Online / Offline</h4>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-success rounded-pill px-3 py-2">Online: {{ $onlineDevices }}</div>
                        <div class="badge bg-secondary rounded-pill px-3 py-2 mt-2">Offline: {{ $offlineDevices }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <div class="text-muted small text-uppercase">Critical Active Alerts</div>
                        <h4 class="mb-1 mt-1">Currently Active</h4>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-danger rounded-pill px-3 py-2">Active: {{ $criticalActiveAlerts }}</div>
                    </div>
                </div>
                <p class="text-muted mb-0 mt-3">Active alerts of critical/severe/offline types.</p>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4">
                @php($levelThresholds = \App\Models\Reading::levelThresholds())

                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h3 class="mb-1">Current Level Ranges</h3>
                        <p class="text-muted mb-0">How a reading value is classified as Normal, Severe or Critical.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#levelThresholdsModal">
                        Edit Ranges
                    </button>
                </div>

                @if(session('level_thresholds_success'))
                    <div class="alert alert-success py-2">{{ session('level_thresholds_success') }}</div>
                @endif
                @if($errors->levelThresholds->any())
                    <div class="alert alert-danger py-2 mb-3">
                        @foreach($errors->levelThresholds->all() as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Gas</th>
                            <th><span class="badge bg-success">Normal</span></th>
                            <th><span class="badge bg-warning text-dark">Severe</span></th>
                            <th><span class="badge bg-danger">Critical</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($levelThresholds as $gas => $range)
                            <tr>
                                <td class="fw-semibold">{{ $gas }}</td>
                                <td>0 – {{ $range['normal'] }}</td>
                                <td>above {{ $range['normal'] }} – {{ $range['severe'] }}</td>
                                <td>above {{ $range['severe'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex align-items-start gap-2 mt-3">
                    <span class="badge bg-secondary">Unknown</span>
                    <p class="text-muted mb-0 small">
                        Shown when the device sends no reading value or a non-numeric value, so the level cannot be worked out.
                        For other device types (not listed above), the level sent by the device is used as-is.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="levelThresholdsModal" tabindex="-1" aria-labelledby="levelThresholdsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.level-thresholds.update') }}" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="levelThresholdsModalLabel">Edit Level Ranges</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Value up to <strong>Normal max</strong> is Normal, up to <strong>Severe max</strong> is Severe,
                        and anything above Severe max is Critical. Severe max must be greater than Normal max.
                    </p>

                    @foreach($levelThresholds as $gas => $range)
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold mb-2">{{ $gas }}</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small" for="threshold-{{ $gas }}-normal">Normal max</label>
                                    <input type="number" step="any" min="0" required
                                           class="form-control @error("thresholds.$gas.normal", 'levelThresholds') is-invalid @enderror"
                                           id="threshold-{{ $gas }}-normal"
                                           name="thresholds[{{ $gas }}][normal]"
                                           value="{{ old("thresholds.$gas.normal", $range['normal']) }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small" for="threshold-{{ $gas }}-severe">Severe max</label>
                                    <input type="number" step="any" min="0" required
                                           class="form-control @error("thresholds.$gas.severe", 'levelThresholds') is-invalid @enderror"
                                           id="threshold-{{ $gas }}-severe"
                                           name="thresholds[{{ $gas }}][severe]"
                                           value="{{ old("thresholds.$gas.severe", $range['severe']) }}">
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <p class="text-muted small mb-0">
                        New ranges apply to readings received after saving. Already stored readings keep their level.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Ranges</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->levelThresholds->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('levelThresholdsModal')).show();
            });
        </script>
    @endif

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h3 class="mb-1">Latest Readings</h3>
                        <p class="text-muted mb-0">Most recent sensor values (top 5).</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Device</th>
                            <th>Region</th>
                            <th>Warehouse</th>
                            <th>Level</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Recorded At</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($latestReadings as $reading)
                            <tr>
                                <td>{{ $reading->device_name ?? optional($reading->device)->device_name ?? $reading->sensor_device_id }}</td>
                                <td>
                                    {{ $reading->region ?: ($reading->region_code ?: '-') }}
                                    @if($reading->region_code)
                                        <span class="text-muted">({{ $reading->region_code }})</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $reading->warehouse ?: ($reading->warehouse_code ?: '-') }}
                                    @if($reading->warehouse_code)
                                        <span class="text-muted">({{ $reading->warehouse_code }})</span>
                                    @endif
                                </td>
                                <td>
                                    @if(blank($reading->reading_value))
                                        <span class="badge bg-secondary">Unknown</span>
                                    @elseif($reading->level === 'critical')
                                        <span class="badge bg-danger">Critical</span>
                                    @elseif($reading->level === 'severe')
                                        <span class="badge bg-warning text-dark">Severe</span>
                                    @else
                                        <span class="badge bg-success">Normal</span>
                                    @endif
                                </td>
                                <td>
                                    @if(blank($reading->reading_value))
                                        N/A
                                    @else
                                        {{ $reading->reading_value }}{{ $reading->unit ? ' '.$reading->unit : '' }}
                                    @endif
                                </td>
                                <td>
                                    @if($reading->status === 'offline')
                                        <span class="badge bg-secondary">Offline</span>
                                    @elseif($reading->status === 'online')
                                        <span class="badge bg-success">Online</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ ucfirst($reading->status ?? 'Unknown') }}</span>
                                    @endif
                                </td>
                                <td>{{ $reading->recorded_at ? $reading->recorded_at->format('d M Y H:i:s') : '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h3 class="mb-2">Quick Actions</h3>
                <p class="text-muted">Go to modules to manage system data.</p>

                <div class="d-grid gap-2 mt-3">
                    <a class="btn btn-outline-primary" href="{{ route('readings.index') }}">View Readings</a>
                    <a class="btn btn-outline-danger" href="{{ route('alerts.index') }}">View Alerts</a>
                    <a class="btn btn-outline-dark" href="{{ route('devices.index') }}">View Devices</a>
                    <a class="btn btn-outline-secondary" href="{{ route('warehouses.index') }}">View Warehouses</a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
