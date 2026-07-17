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

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <div class="text-muted small text-uppercase">Total Readings</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($readingCounts['total'] ?? 0) }}</div>
                    <p class="text-muted mb-0">All sensor readings.</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <div class="text-muted small text-uppercase">Normal</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($readingCounts['normal'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Normal level readings.</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <div class="text-muted small text-uppercase">Severe</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($readingCounts['severe'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Severe level readings.</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <div class="text-muted small text-uppercase">Critical</div>
                    <div class="display-6 fw-bold mt-2">{{ number_format($readingCounts['critical'] ?? 0) }}</div>
                    <p class="text-muted mb-0">Critical level readings.</p>
                </div>
            </div>
        </div>

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
                <tbody></tbody>
            </table>
        </div>

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
        const escapeText = function (value) {
            const displayValue = value === null || value === undefined || value === '' ? 'N/A' : value;
            return $('<div>').text(displayValue).html();
        };

        window.initWarehouseDataTable('#readingsTable', {
            processing: true,
            serverSide: true,
            paging: true,
            info: true,
            pageLength: 10,
            search: { search: new URLSearchParams(window.location.search).get('search') || '' },
            order: [],
            language: {
                search: 'Search readings:'
            },
            ajax: {
                url: '{{ route('readings.data') }}'
            },
            columns: [
                { data: 'device', defaultContent: '-', render: escapeText },
                { data: 'type', defaultContent: '-', render: escapeText },
                { data: 'sensor', defaultContent: '-', render: escapeText },
                { data: 'value', defaultContent: '', render: escapeText },
                { data: 'unit', defaultContent: '-', render: escapeText },
                { data: 'region_warehouse', defaultContent: '-', render: escapeText },
                { data: 'godown_compartment', defaultContent: '-', render: escapeText },
                {
    data: 'level',
    defaultContent: 'unknown',
    render: function (value, type, row) {
        const readingValue = row.value;

        const isNotAvailable =
            readingValue === null ||
            readingValue === undefined ||
            readingValue === '' ||
            String(readingValue).toUpperCase() === 'N/A';

        if (isNotAvailable) {
            return '<span class="badge bg-secondary">Unknown</span>';
        }

        const level = String(value || 'unknown').toLowerCase();

        if (level === 'critical') {
            return '<span class="badge bg-danger">Critical</span>';
        }

        if (level === 'severe') {
            return '<span class="badge bg-warning text-dark">Severe</span>';
        }

        if (level === 'normal') {
            return '<span class="badge bg-success">Normal</span>';
        }

        return '<span class="badge bg-secondary">Unknown</span>';
    }
},
                {
                    data: 'status',
                    defaultContent: 'unknown',
                    render: function (value) {
                        const status = String(value || 'unknown').toLowerCase();

                        if (status === 'offline') {
                            return '<span class="badge bg-secondary">Offline</span>';
                        }

                        if (status === 'online') {
                            return '<span class="badge bg-success">Online</span>';
                        }

                        return '<span class="badge bg-light text-dark">' + escapeText(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';
                    }
                },
                { data: 'recorded_at', defaultContent: '-', render: escapeText }
            ]
        });

    });
</script>
@endsection
