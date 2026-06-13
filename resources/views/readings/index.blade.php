@extends('layouts.app')

@section('page-title', 'Readings')

@section('content')
<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Readings</h3>
                <p class="text-muted mb-0">Monitor all sensor readings from devices.</p>
            </div>

        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table id="readingsTable" class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Device</th>
                    <th>Type</th>
                    <th>Sensor</th>
                    <th>Value</th>
                    <th>Unit</th>
                    <th>Region/Warehouse</th>
                    <th>Godown/Compartment</th>
                    <th>Level</th>
                    <th>Status</th>

                    <th>Recorded At</th>
                    
                </tr>
                </thead>
                <tbody>
                @foreach($readings as $reading)
                    <tr>
                        <td>{{ $reading->device_name ?? optional($reading->device)->device_name }}</td>
                        <td>{{ $reading->device_type }}</td>
                        <td>{{ $reading->sensor_device_id }}</td>
                        <td>{{ $reading->reading_value }}</td>
                        <td>{{ $reading->unit }}</td>
                        <td>{{ $reading->region_code ?? $reading->region }}{{ ($reading->warehouse_code ?? $reading->warehouse) ? ' / '.($reading->warehouse_code ?? $reading->warehouse) : '' }}</td>
                        <td>{{ $reading->godown }}{{ ($reading->compartment) ? ' / '.($reading->compartment) : '' }}</td>
                        <td>
                            @if($reading->level === 'critical')
                                <span class="badge bg-danger">Critical</span>
                            @elseif($reading->level === 'severe')
                                <span class="badge bg-severe text-dark">Severe</span>
                            @else
                                <span class="badge bg-success">Normal</span>
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
                        <td>{{ $reading->recorded_at ? $reading->recorded_at->format('d M Y H:i') : '-' }}</td>
                      
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $readings->links() }}</div>
    </div>
</div>
@endsection

@section('styles')
<style>
    #readingsTable_wrapper .dataTables_filter {
        margin-right: 0;
    }

    #readingsTable_wrapper .dataTables_filter input {
        margin-left: 0.35rem;
    }

    #readingsTable_wrapper .dataTables_length,
    #readingsTable_wrapper .dataTables_filter {
        margin-bottom: 0;
    }
</style>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && $.fn.DataTable && $('#readingsTable').length) {
            $('#readingsTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[7, 'desc']],

                language: {
                    search: 'Search readings:',
                    lengthMenu: 'Show _MENU_ entries'
                }
            });
        }
    });
</script>
@endsection
