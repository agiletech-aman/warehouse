@extends('layouts.app')

@section('page-title', 'Warehouses')

@section('content')

<div class="content-shell">

    <div class="card border-0 shadow-sm p-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

            <div>
                <h3 class="mb-1">{{ $activeOnly ? 'Active Warehouses' : 'Warehouses' }}</h3>
                <p class="text-muted mb-0">
                    {{ $activeOnly ? 'Warehouses with readings in the last 24 hours.' : 'Track all warehouse details.' }}
                </p>
            </div>

            <div class="d-flex gap-2">
                @if($activeOnly)
                    <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                        View All
                    </a>
                @endif

                <a id="warehousesExport" href="{{ route('warehouses.export', request()->only(['search', 'active'])) }}" class="btn btn-outline-primary rounded-pill px-3">
                    Export
                </a>

                <a href="{{ route('warehouses.create') }}" class="btn btn-primary rounded-pill px-3">
                    + Add Warehouse
                </a>
            </div>

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
            <table id="warehousesTable" class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Region</th>
                    <th>Manager</th>
                    <th>Status</th>
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
        const escapeText = function (value) {
            const displayValue = value === null || value === undefined || value === '' ? '-' : value;
            return $('<div>').text(displayValue).html();
        };
        const csrfToken = @json(csrf_token());

        const table = window.initWarehouseDataTable('#warehousesTable', {
            processing: true,
            serverSide: true,
            paging: true,
            info: true,
            pageLength: 10,
            search: { search: new URLSearchParams(window.location.search).get('search') || '' },
            order: [[0, 'asc']],
            language: {
                search: 'Search warehouses:',
                processing: 'Loading warehouses...'
            },
            ajax: {
                url: '{{ route('warehouses.data') }}',
                data: function(data) {
                    const params = new URLSearchParams(window.location.search);

                    if (params.get('active')) {
                        data.active = params.get('active');
                    }
                }
            },
            columns: [
                { data: 'code', defaultContent: '-', render: escapeText },
                { data: 'name', defaultContent: '-', render: escapeText },
                { data: 'region', defaultContent: '-', render: escapeText },
                {
                    data: 'manager_name',
                    defaultContent: '-',
                    render: function(value, type, row) {
                        let contact = row.manager_email ? escapeText(row.manager_email) : '-';

                        if (row.manager_phone) {
                            contact += ' | ' + escapeText(row.manager_phone);
                        }

                        return '<div class="fw-semibold">' + escapeText(value) + '</div>' +
                            '<div class="text-muted" style="font-size: 0.9rem;">' + contact + '</div>';
                    }
                },
                {
                    data: 'status',
                    defaultContent: 'unknown',
                    render: function(value) {
                        const status = String(value || 'unknown').toLowerCase();

                        if (status === 'active') {
                            return '<span class="badge bg-success">Active</span>';
                        }

                        if (status === 'inactive') {
                            return '<span class="badge bg-danger">Inactive</span>';
                        }

                        return '<span class="badge bg-secondary">' +
                            escapeText(status.charAt(0).toUpperCase() + status.slice(1)) +
                            '</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(value, type, row) {
                        return '<div class="d-flex gap-2">' +
                            '<a href="' + escapeText(row.edit_url) + '" class="btn btn-severe btn-sm rounded-pill px-3">Edit</a>' +
                            '<form action="' + escapeText(row.delete_url) + '" method="POST" class="d-inline" data-confirm-delete' +
                            ' data-confirm-title="Delete warehouse?" data-confirm-message="Are you sure you want to delete ' +
                            escapeText(row.delete_label) + '?">' +
                            '<input type="hidden" name="_token" value="' + escapeText(csrfToken) + '">' +
                            '<input type="hidden" name="_method" value="DELETE">' +
                            '<button class="btn btn-danger btn-sm rounded-pill px-3">Delete</button>' +
                            '</form></div>';
                    }
                }
            ]
        });

        table.on('search.dt', function () {
            const url = new URL(document.getElementById('warehousesExport').href);
            const search = table.search();
            search ? url.searchParams.set('search', search) : url.searchParams.delete('search');
            document.getElementById('warehousesExport').href = url.toString();
        });
    });
</script>
@endsection
