@extends('layouts.app')

@section('page-title', 'Devices')

@section('content')

<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Devices</h3>
                <p class="text-muted mb-0">Manage all IoT devices linked to warehouses.</p>
            </div>

            <a href="{{ route('devices.create') }}" class="btn btn-primary rounded-pill px-3">+ Add Device</a>
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
                    <th>Warehouse</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach($devices as $device)
                    <tr>
                        <td>{{ $device->device_code }}</td>
                        <td>{{ $device->device_name }}</td>
                        <td>{{ optional($device->warehouse)->warehouse_name }}</td>
                        <td>{{ $device->device_type ?: '-' }}</td>
                        <td>
                            @if($device->status === 'online')
                                <span class="badge bg-success">Online</span>
                            @elseif($device->status === 'maintenance')
                                <span class="badge bg-warning text-dark">Maintenance</span>
                            @else
                                <span class="badge bg-secondary">Offline</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('devices.show', $device->id) }}" class="btn btn-info btn-sm rounded-pill px-3">View</a>
                                <a href="{{ route('devices.edit', $device->id) }}" class="btn btn-warning btn-sm rounded-pill px-3">Edit</a>
                                <form action="{{ route('devices.destroy', $device->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm rounded-pill px-3">Delete</button>
                                </form>
                            </div>
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
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    search: 'Search devices:',
                    lengthMenu: 'Show _MENU_ entries'
                }
            });
        }
    });
</script>
@endsection
