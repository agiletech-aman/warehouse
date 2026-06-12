@extends('layouts.app')

@section('page-title', 'Alerts')

@section('content')
<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Alerts</h3>
                <p class="text-muted mb-0">Track alert events raised by devices and sensor readings.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table id="alertsTable" class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Device</th>
                    <th>Alert Type</th>
                    <th>Value</th>
                    <th>Reading</th>
                </tr>
                </thead>
                <tbody>
                @foreach($alerts as $alert)
                    <tr>
                        <td>{{ optional($alert->device)->device_name }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($alert->alert_type)) }}</td>
                        <td>{{ $alert->alert_value }}</td>
                        <td>{{ optional($alert->reading)->id ? 'Reading #' . $alert->reading_id : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $alerts->links() }}</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && $.fn.DataTable && $('#alertsTable').length) {
            $('#alertsTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    search: 'Search alerts:',
                    lengthMenu: 'Show _MENU_ entries'
                }
            });
        }
    });
</script>
@endsection
