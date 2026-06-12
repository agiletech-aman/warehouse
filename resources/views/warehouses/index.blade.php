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

            <a href="{{ route('warehouses.create') }}" class="btn btn-primary rounded-pill px-3">
                + Add Warehouse
            </a>

        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">
                {{ session('success') }}
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
                            @if($warehouse->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="btn btn-warning btn-sm rounded-pill px-3">
                                    Edit
                                </a>

                                <form action="{{ route('warehouses.destroy', $warehouse->id) }}" method="POST" class="d-inline">
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

        <div class="mt-3">
            {{ $warehouses->links() }}
        </div>

    </div>

</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && $.fn.DataTable && $('#warehousesTable').length) {
            $('#warehousesTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    search: 'Search warehouses:',
                    lengthMenu: 'Show _MENU_ entries'
                }
            });
        }
    });
</script>
@endsection

