@extends('layouts.app')

@section('page-title', 'Warehouses')

@section('content')

<div class="content-shell">

    <div class="card border-0 shadow-sm p-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

            <div>
                <h3 class="mb-1">Warehouses</h3>
                <p class="text-muted mb-0">Track all warehouse details.</p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('warehouses.export') }}" class="btn btn-outline-primary rounded-pill px-3">
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

                <tbody>
                @foreach($warehouses as $warehouse)
                    <tr>
                        <td>{{ $warehouse->warehouse_code }}</td>
                        <td>{{ $warehouse->warehouse_name }}</td>
                        <td>{{ optional($warehouse->region)->region_name }}</td>
                        <td>
                            <div class="fw-semibold">{{ $warehouse->manager_name }}</div>
                            <div class="text-muted" style="font-size: 0.9rem;">
                                @if($warehouse->manager_email)
                                    {{ $warehouse->manager_email }}
                                @else
                                    -
                                @endif
                                @if($warehouse->manager_phone)
                                    <span> | {{ $warehouse->manager_phone }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @php
                                $status = strtolower((string) ($warehouse->status ?? ''));
                            @endphp

                            @if($status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($status === 'inactive')
                                <span class="badge bg-danger">Inactive</span>
                            @else
                                <span class="badge bg-secondary">{{ $warehouse->status ?: 'Unknown' }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="btn btn-severe btn-sm rounded-pill px-3">
                                    Edit
                                </a>

                                <form action="{{ route('warehouses.destroy', $warehouse->id) }}" method="POST" class="d-inline"
                                      data-confirm-delete
                                      data-confirm-title="Delete warehouse?"
                                      data-confirm-message="Are you sure you want to delete {{ $warehouse->warehouse_name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm rounded-pill px-3">
                                        Delete
                                    </button>
                                </form>
                            </div>
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
        window.initWarehouseDataTable('#warehousesTable', {
            paging: true,
            info: true,
            pageLength: 10,
            order: [[0, 'asc']],
            language: {
                search: 'Search warehouses:'
            }
        });
    });
</script>
@endsection
