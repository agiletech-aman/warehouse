@extends('layouts.app')

@section('page-title', 'Reading Details')

@section('content')
<div class="container">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h3 class="mb-3">Reading Details</h3>
            <div class="row g-3">
                <div class="col-md-6"><div class="fw-semibold">Sensor Device ID</div><div>{{ $reading->sensor_device_id ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Device</div><div>{{ $reading->device_name ?: optional($reading->device)->device_name ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Value</div><div>{{ $reading->reading_value !== null ? $reading->reading_value : 'N/A' }} {{ $reading->unit ?: '' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Level</div><div>{{ $reading->reading_value === null ? 'Unknown' : ucfirst($reading->level ?: 'normal') }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Status</div><div>{{ ucfirst($reading->status ?: 'unknown') }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Recorded At</div><div>{{ $reading->recorded_at?->format('d M Y H:i:s') ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Region</div><div>{{ $reading->region ?: $reading->region_code ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Warehouse</div><div>{{ $reading->warehouse ?: $reading->warehouse_code ?: '-' }}</div></div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('readings.edit', $reading) }}" class="btn btn-severe">Edit</a>
                <a href="{{ route('readings.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
