@extends('layouts.app')

@section('page-title', 'FNS Detections')

@section('content')
<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="mb-4">
            <h3 class="mb-1">FNS Detections</h3>
            <p class="text-muted mb-0">Monitor camera detections across warehouses.</p>
        </div>

        <div class="table-responsive">
            <table id="fnsDetectionsTable" class="table table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Camera</th>
                        <th>Warehouse</th>
                        <th>Godown / Compartment</th>
                        <th>Detection</th>
                        <th>Confidence</th>
                        <th>Snapshot</th>
                        <th>Bounding Box</th>
                        <th>Detected At</th>
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

        window.initWarehouseDataTable('#fnsDetectionsTable', {
            processing: true,
            serverSide: true,
            paging: true,
            info: true,
            pageLength: 10,
            order: [],
            language: {
                search: 'Search detections:'
            },
            ajax: {
                url: '{{ route('fns-detections.data') }}'
            },
            columns: [
                { data: 'camera', render: escapeText },
                { data: 'warehouse_code', render: escapeText },
                { data: 'location', render: escapeText },
                {
                    data: 'detection_type',
                    render: function (value) {
                        const type = String(value || 'unknown').toLowerCase();
                        const badgeClass = ['fire', 'weapon', 'intrusion'].includes(type)
                            ? 'bg-danger'
                            : (type === 'smoke' ? 'bg-warning text-dark' : 'bg-primary');

                        return '<span class="badge ' + badgeClass + '">' + escapeText(
                            type.charAt(0).toUpperCase() + type.slice(1)
                        ) + '</span>';
                    }
                },
                {
                    data: 'confidence',
                    render: function (value) {
                        return escapeText(value) + '%';
                    }
                },
                { data: 'snapshot_path', render: escapeText },
                { data: 'bounding_box', render: escapeText },
                { data: 'detected_at', render: escapeText }
            ]
        });

    });
</script>
@endsection
