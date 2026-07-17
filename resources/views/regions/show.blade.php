@extends('layouts.app')

@section('page-title', 'Region Details')

@section('content')
<div class="container">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h3 class="mb-3">{{ $region->region_name }}</h3>
            <div class="row g-3">
                <div class="col-md-6"><div class="fw-semibold">Region Code</div><div>{{ $region->region_code }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Status</div><div>{{ ucfirst($region->status ?: 'unknown') }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Manager</div><div>{{ $region->manager_name ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Manager Email</div><div>{{ $region->manager_email ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Manager Phone</div><div>{{ $region->manager_phone ?: '-' }}</div></div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('regions.edit', $region) }}" class="btn btn-severe">Edit</a>
                <a href="{{ route('regions.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
