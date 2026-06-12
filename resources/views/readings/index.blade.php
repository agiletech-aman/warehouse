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
            <a href="{{ route('readings.create') }}" class="btn btn-primary rounded-pill px-3">+ Add Reading</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table id="readingsTable" class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Device</th>
                    <th>Value</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Recorded At</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach($readings as $reading)
                    <tr>
                        <td>{{ optional($reading->device)->device_name }}</td>
                        <td>{{ $reading->reading_value }}</td>
                        <td>{{ $reading->unit }}</td>
                        <td>
                            @if($reading->status === 'critical')
                                <span class="badge bg-danger">Critical</span>
                            @elseif($reading->status === 'warning')
                                <span class="badge bg-warning text-dark">Warning</span>
                            @else
                                <span class="badge bg-success">Normal</span>
                            @endif
                        </td>
                        <td>{{ $reading->recorded_at ? $reading->recorded_at->format('d M Y H:i') : '-' }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('readings.show', $reading->id) }}" class="btn btn-info btn-sm rounded-pill px-3">View</a>
                                <a href="{{ route('readings.edit', $reading->id) }}" class="btn btn-warning btn-sm rounded-pill px-3">Edit</a>
                                <form action="{{ route('readings.destroy', $reading->id) }}" method="POST" class="d-inline">
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

        <div class="mt-3">{{ $readings->links() }}</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && $.fn.DataTable && $('#readingsTable').length) {
            $('#readingsTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[4, 'desc']],
                language: {
                    search: 'Search readings:',
                    lengthMenu: 'Show _MENU_ entries'
                }
            });
        }
    });
</script>
@endsection
