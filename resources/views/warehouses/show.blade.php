@extends('layouts.app')

@section('page-title', 'Warehouse Details')

@section('content')

<div class="container">

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <h3 class="mb-3">{{ $warehouse->warehouse_name }}</h3>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="fw-semibold">Warehouse Code</div>
                    <div>{{ $warehouse->warehouse_code }}</div>
                </div>

                <div class="col-md-6">
                    <div class="fw-semibold">Region</div>
                    <div>{{ optional($warehouse->region)->region_name }}</div>
                </div>

                <div class="col-md-6">
                    <div class="fw-semibold">Manager</div>
                    <div>{{ $warehouse->manager_name }}</div>
                    <div class="text-muted">{{ $warehouse->manager_email ?: '-' }}</div>
                    <div class="text-muted">{{ $warehouse->manager_phone ?: '-' }}</div>
                </div>

                <div class="col-md-6">
                    <div class="fw-semibold">Status</div>
                    @if($warehouse->status==='active')
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-danger">Inactive</span>
                    @endif
                </div>

                <div class="col-md-12">
                    <div class="fw-semibold">Address</div>
                    <div>{{ $warehouse->address ?: '-' }}</div>
                    <div class="text-muted">
                        {{ $warehouse->city ?: '-' }}, {{ $warehouse->state ?: '-' }}, {{ $warehouse->country ?: '-' }}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="fw-semibold">Latitude</div>
                    <div>{{ $warehouse->latitude !== null ? $warehouse->latitude : '-' }}</div>
                </div>

                <div class="col-md-6">
                    <div class="fw-semibold">Longitude</div>
                    <div>{{ $warehouse->longitude !== null ? $warehouse->longitude : '-' }}</div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="btn btn-severe">Edit</a>
                <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">Back</a>
            </div>

        </div>
    </div>

</div>

@endsection

