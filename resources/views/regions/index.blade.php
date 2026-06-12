@extends('layouts.app')

@section('page-title', 'Regions')
@section('page-subtitle', 'Track all warehouse regions and update their status quickly.')

@section('content')

<div class="content-shell">

    <div class="card border-0 shadow-sm p-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

            <div>
                <h3 class="mb-1">Regions</h3>
                <p class="text-muted mb-0">Manage warehouse regions and update their status quickly.</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <input type="text"
                       id="regionsSearch"
                       class="form-control form-control-sm"
                       style="width: 260px;"
                       placeholder="Search (code/name/status)" />

                <a href="{{ route('regions.create') }}" class="btn btn-primary btn-sm rounded-pill px-3">
                    + Add Region
                </a>
            </div>

        </div>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="table-responsive">
            <table id="regionsTable" class="table table-hover align-middle mb-0">

<thead>

<tr>
    <th>Code</th>
    <th>Name</th>
    <th>Status</th>

    <th>Action</th>
</tr>

</thead>

<tbody>

@foreach($regions as $region)

<tr>

<td>{{ $region->region_code }}</td>

<td>{{ $region->region_name }}</td>


<td>


@if($region->status=='active')
<span class="badge bg-success">Active</span>
@else
<span class="badge bg-danger">Inactive</span>
@endif

</td>

<td>

<div class="d-flex gap-2">
    <a href="{{ route('regions.edit',$region->id) }}"
       class="btn btn-warning btn-sm rounded-pill px-3">
        Edit
    </a>

    <form action="{{ route('regions.destroy',$region->id) }}"
          method="POST"
          class="d-inline">

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
            {{ $regions->links() }}
        </div>


    </div>

</div>

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('regionsSearch');
        if (!input) return;

        const table = $('#regionsTable');
        const dt = table.length && $.fn.DataTable ? table.DataTable() : null;
        if (!dt) return;

        // Hide/disable DataTables default search UI (if it exists)
        // DataTables 2.x often renders its search in the wrapper.
        try {
            $(dt.table().container()).find('div.dataTables_filter').hide();
        } catch (e) {
            // ignore
        }

        const apply = function () {
            dt.search(input.value).draw();
        };

        // Instant filter (good UX)
        input.addEventListener('input', apply);

        // If user clears the input, reset search
        apply();
    });
</script>
@endsection
