@extends('layouts.app')

@section('page-title', $title)

@section('content')
<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h3 class="mb-1">{{ $title }}</h3>
                <p class="text-muted mb-0">Locations that are sending FNS detections.</p>
            </div>
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary rounded-pill px-3">
                Back to Detections
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger rounded-3 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="table-responsive">
            <table id="fnsLocationsTable" class="table table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Warehouse</th>
                        <th>Godowns / Compartments</th>
                        <th>Detections</th>
                        <th>Last Detected</th>
                        <th>Action</th>
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
        const csrfToken = @json(csrf_token());

        const escapeText = function (value) {
            const displayValue = value === null || value === undefined || value === '' ? '-' : value;
            return $('<div>').text(displayValue).html();
        };

        window.initWarehouseDataTable('#fnsLocationsTable', {
            paging: true,
            info: true,
            pageLength: 25,
            // Keep the page, sort and search after a delete reloads the page.
            stateSave: true,
            order: [[3, 'desc']],
            language: {
                search: 'Search locations:',
                emptyTable: 'No detections yet.'
            },
            ajax: {
                url: @json($dataUrl),
                dataSrc: 'data'
            },
            columns: [
                { data: 'region_name', render: escapeText },
                { data: 'warehouse_name', render: escapeText },
                { data: 'location_count' },
                { data: 'total' },
                {
                    data: 'last_detected_at',
                    // Sort on the timestamp; the "d M Y H:i:s" text would sort alphabetically.
                    render: function (value, type, row) {
                        return type === 'sort' || type === 'type' ? row.last_detected_ts : escapeText(value);
                    }
                },
                {
                    data: 'delete_url',
                    orderable: false,
                    searchable: false,
                    render: function (value, type, row) {
                        if (!value) {
                            return '-';
                        }

                        const label = row.warehouse_name + ' (all godowns / compartments)';

                        return '<form action="' + escapeText(value) + '" method="POST" class="d-inline" data-confirm-delete'
                            + ' data-confirm-title="Delete location?" data-confirm-message="This will permanently delete all '
                            + escapeText(row.total) + ' detection(s) for ' + escapeText(label) + '.">'
                            + '<input type="hidden" name="_token" value="' + escapeText(csrfToken) + '">'
                            + '<input type="hidden" name="_method" value="DELETE">'
                            + '<button class="btn btn-danger btn-sm rounded-pill px-3">Delete</button>'
                            + '</form>';
                    }
                }
            ]
        });
    });
</script>
@endsection
