@extends('layouts.app')

@section('page-title', 'Reading Details')

@section('content')
<div class="container">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h3 class="mb-3">Reading Details</h3>

            <div class="row g-3">
                <div class="col-md-6"><div class="fw-semibold">Device</div><div>{{ optional($reading->device)->device_name }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Reading Value</div><div>{{ $reading->reading_value }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Unit</div><div>{{ $reading->unit }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Status</div>
                    @if($reading->status === 'critical')
                        <span class="badge bg-danger">Critical</span>
                    @elseif($reading->status === 'warning')
                        <span class="badge bg-warning text-dark">Warning</span>
                    @else
                        <span class="badge bg-success">Normal</span>
                    @endif
                </div>
                <div class="col-md-6"><div class="fw-semibold">Recorded At</div><div>{{ $reading->recorded_at ? $reading->recorded_at->format('d M Y H:i') : '-' }}</div></div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('readings.edit', $reading->id) }}" class="btn btn-warning">Edit</a>
                <a href="{{ route('readings.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
