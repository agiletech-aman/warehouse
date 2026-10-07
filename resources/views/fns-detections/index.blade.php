@extends('layouts.app')

@section('page-title', 'FNS Detections')

@section('content')
<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="mb-4">
            <h3 class="mb-1">FNS Detections</h3>
            <p class="text-muted mb-0">Monitor camera detections across warehouses.</p>
        </div>

        <div class="mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h5 class="mb-0">Detection Locations</h5>
                <span id="fnsLocationsSummary" class="text-muted small">Loading locations...</span>
            </div>
            <div class="table-responsive">
                <table id="fnsLocationsTable" class="table table-sm table-hover align-middle mb-0 w-100">
                    <thead>
                        <tr>
                            <th>Warehouse</th>
                            <th>Godown / Compartment</th>
                            <th>Cameras</th>
                            <th>Detections</th>
                            <th>Last Detected</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-responsive">
            <table id="fnsDetectionsTable" class="table table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Camera IP</th>
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

<div class="modal fade" id="detectionSnapshotModal" tabindex="-1" aria-labelledby="detectionSnapshotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 bg-dark">
            <div class="modal-header border-0 text-white">
                <h5 class="modal-title" id="detectionSnapshotModalLabel">Detection Snapshot</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <img id="detectionSnapshotModalImage" src="" alt="Detection snapshot" class="img-fluid rounded" style="max-height: calc(100vh - 150px);">
            </div>
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

        const renderSnapshot = function (value, type) {
            if (!value) {
                return '-';
            }

            const snapshotUrl = String(value);

            try {
                const parsedUrl = new URL(snapshotUrl, window.location.origin);

                if (!['http:', 'https:'].includes(parsedUrl.protocol)) {
                    return '-';
                }
            } catch (error) {
                return '-';
            }

            if (type !== 'display') {
                return snapshotUrl;
            }

            const escapedUrl = escapeText(snapshotUrl);

            return '<button type="button" class="js-detection-snapshot border-0 bg-transparent p-0" '
                + 'data-image-url="' + escapedUrl + '" aria-label="View detection snapshot">'
                + '<img src="' + escapedUrl + '" alt="Detection snapshot" class="img-thumbnail" '
                + 'style="width: 100px; height: 70px; object-fit: cover;">'
                + '</button>';
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
                { data: 'name', render: escapeText },
                { data: 'camera_ip', render: escapeText },
                { data: 'warehouse_name', render: escapeText },
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
                        return (value === null || value === '-') ? '-' : escapeText(value) + '%';
                    }
                },
                {
                    data: 'snapshot_url',
                    orderable: false,
                    searchable: false,
                    render: renderSnapshot
                },
                { data: 'bounding_box', render: escapeText },
                { data: 'detected_at', render: escapeText }
            ]
        });

        const loadDetectionLocations = function () {
            const $body = $('#fnsLocationsTable tbody');

            $.getJSON('{{ route('fns-detections.locations') }}')
                .done(function (response) {
                    const locations = response.data || [];
                    const total = locations.reduce(function (sum, location) { return sum + location.total; }, 0);

                    $('#fnsLocationsSummary').text(locations.length + ' location(s), ' + total + ' detection(s)');

                    if (!locations.length) {
                        $body.html('<tr><td colspan="6" class="text-center text-muted">No detections yet.</td></tr>');
                        return;
                    }

                    $body.html(locations.map(function (location) {
                        const sources = location.sources.map(function (source) {
                            return '<span class="badge ' + (source === 'external' ? 'bg-secondary' : 'bg-success') + ' me-1">'
                                + escapeText(source === 'external' ? 'History API' : 'Live') + '</span>';
                        }).join('');

                        return '<tr>'
                            + '<td>' + escapeText(location.warehouse_name) + '</td>'
                            + '<td>' + escapeText(location.location) + '</td>'
                            + '<td class="small">' + escapeText(location.cameras.join(', ')) + '</td>'
                            + '<td><span class="badge bg-primary">' + location.total + '</span></td>'
                            + '<td>' + escapeText(location.last_detected_at) + '</td>'
                            + '<td>' + sources + '</td>'
                            + '</tr>';
                    }).join(''));
                })
                .fail(function () {
                    $('#fnsLocationsSummary').text('');
                    $body.html('<tr><td colspan="6" class="text-center text-danger">Locations could not be loaded.</td></tr>');
                });
        };

        loadDetectionLocations();

        $('#fnsDetectionsTable').on('click', '.js-detection-snapshot', function () {
            const modalImage = document.getElementById('detectionSnapshotModalImage');
            modalImage.src = this.getAttribute('data-image-url');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('detectionSnapshotModal')).show();
        });

        document.getElementById('detectionSnapshotModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('detectionSnapshotModalImage').src = '';
        });

    });
</script>
@endsection
