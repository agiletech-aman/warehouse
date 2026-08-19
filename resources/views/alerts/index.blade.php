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
                <tbody></tbody>
            </table>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const escapeText = function (value) {
            const displayValue = value === null || value === undefined || value === '' ? '-' : value;
            return $('<div>').text(displayValue).html();
        };

        window.initWarehouseDataTable('#alertsTable', {
            processing: true,
            serverSide: true,
            paging: true,
            info: true,
            pageLength: 10,
            order: [[4, 'desc']],
            language: {
                search: 'Search alerts:',
                processing: 'Loading alerts...'
            },
            ajax: {
                url: '{{ route('alerts.data') }}'
            },
            columns: [
                { data: 'device', defaultContent: '-', render: escapeText },
                {
                    data: 'type',
                    defaultContent: 'unknown',
                    render: function (value) {
                        const type = String(value || 'unknown').toLowerCase();
                        const label = type.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());

                        if (type.includes('critical') || type.includes('high')) {
                            return '<span class="badge bg-danger rounded-pill">' + escapeText(label) + '</span>';
                        }

                        if (type.includes('warn') || type.includes('severe')) {
                            return '<span class="badge bg-warning text-dark rounded-pill">' + escapeText(label) + '</span>';
                        }

                        return '<span class="badge bg-secondary rounded-pill">' + escapeText(label) + '</span>';
                    }
                },
                { data: 'message', defaultContent: '-', render: escapeText },
                { data: 'value', defaultContent: '-', render: escapeText },
                { data: 'triggered_at', defaultContent: '-', render: escapeText },
                {
                    data: 'mail_status',
                    defaultContent: 'pending',
                    render: function (value) {
                        const status = String(value || 'pending').toLowerCase();

                        if (status === 'sent') {
                            return '<span class="badge bg-success">Sent</span>';
                        }

                        return '<span class="badge bg-warning text-dark">Pending</span>';
                    }
                }
            ]
        });
    });
</script>
@endsection
