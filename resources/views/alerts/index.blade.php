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
                    <th>Alert Message</th>
                    <th>Value</th>
                    <th>Triggered At</th>
                    <th>Mail Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach($alerts as $alert)
                    <tr>
                        <td>{{ optional($alert->device)->device_name ?: ($alert->reading?->device_name ?: $alert->device_id) }}</td>

                        <td>
                            @php
                                $alertType = $alert->type ?? $alert->alert_type ?? 'alert';
                                $alertLabel = str_replace('_', ' ', ucfirst($alertType));
                                $alertClass = 'bg-secondary';
                                $isOfflineAlert = str_contains(strtolower((string) $alertType), 'offline')
                                    || str_contains(strtolower((string) $alert->message), 'offline');

                                if (str_contains($alertType, 'critical') || str_contains($alertType, 'high') || $alertType === 'device_offline') {
                                    $alertClass = 'bg-danger';
                                } elseif (str_contains($alertType, 'warn') || str_contains($alertType, 'severe')) {
                                    $alertClass = 'bg-warning text-dark';
                                } else {
                                    $alertClass = 'bg-info text-dark';
                                }
                            @endphp
                            <span class="badge {{ $alertClass }} rounded-pill">{{ $alertLabel }}</span>
                        </td>

                        <td>
                            {{ $alert->message ?: (str_replace('_', ' ', ucfirst($alert->type ?? $alert->alert_type ?? 'Alert'))) }}
                        </td>

                        <td>
                            @if($isOfflineAlert)
                                N/A
                            @else
                                {{ $alert->alert_value ?? $alert->reading?->reading_value ?? '-' }}
                            @endif
                        </td>

                        <td>{{ $alert->created_at ? $alert->created_at->format('d M Y H:i:s') : '-' }}</td>

                        <td>
                            @if($alert->last_email_at)
                                <span class="badge bg-success">Sent</span>
                            @else
                                <span class="badge bg-severe text-dark">Pending</span>
                            @endif
                        </td>


                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        window.initWarehouseDataTable('#alertsTable', {
            paging: true,
            info: true,
            pageLength: 10,
            language: {
                search: 'Search alerts:'
            }
        });


    });
</script>
@endsection
