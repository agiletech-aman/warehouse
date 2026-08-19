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
