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
        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif

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
                </tr>
                </thead>
                <tbody>
                @foreach($devices as $device)
                    <tr>
                        <td>{{ $device->sensor_device_id ?: '-' }}</td>
                        <td>{{ $device->device_name ?: '-' }}</td>
                        <td>
                            {{ $device->region ?: ($device->region_code ?: '-') }}
                            @if($device->region_code)
                                <span class="text-muted">({{ $device->region_code }})</span>
                            @endif
                        </td>
                        <td>
                            {{ $device->warehouse ?: ($device->warehouse_code ?: '-') }}
                            @if($device->warehouse_code)
                                <span class="text-muted">({{ $device->warehouse_code }})</span>
                            @endif
                        </td>
                        <td>{{ $device->device_type ?: '-' }}</td>
                        <td>{{ $device->godown ?: '-' }}{{ $device->compartment ? ' / '.$device->compartment : '' }}</td>
                        <td>
                            {{ $device->reading_value ?? '-' }}
                            @if($device->unit)
                                {{ $device->unit }}
                            @endif
                        </td>
                        <td>
                            @php($deviceStatus = $device->status ?? 'offline')
                            @if($deviceStatus === 'online')
                                <span class="badge bg-success">Online</span>
                            @elseif($deviceStatus === 'offline')
                                <span class="badge bg-secondary">Offline</span>
                            @else
                                <span class="badge bg-light text-dark">{{ ucfirst($deviceStatus) }}</span>
                            @endif

                            @php($deviceLevel = strtolower((string) ($device->level ?? 'normal')))
                            @if($deviceLevel === 'critical')
                                <span class="badge bg-danger">Critical</span>
                            @elseif($deviceLevel === 'severe')
                                <span class="badge bg-warning text-dark">Severe</span>
                            @else
                                <span class="badge bg-success">Normal</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $devices->links() }}</div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && $.fn.DataTable && $('#devicesTable').length) {
            $('#devicesTable').DataTable({
                responsive: true,
                paging: false,
                info: false,
                order: [],
                language: {
                    search: 'Search devices:'
                }
            });
        }

        window.setInterval(function () {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 3000);
    });
</script>
@endsection
