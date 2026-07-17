@extends('layouts.app')

@section('page-title', 'Alert Details')

@section('content')
<div class="container">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h3 class="mb-3">Alert Details</h3>

            <div class="row g-3">
                <div class="col-md-6"><div class="fw-semibold">Device</div><div>{{ optional($alert->device)->device_name ?: ($alert->reading?->device_name ?: $alert->device_id) }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Alert Type</div><div>{{ str_replace('_', ' ', ucfirst($alert->alert_type)) }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Alert Value</div><div>
                    @if(str_contains(strtolower((string) ($alert->message ?? $alert->alert_type)), 'offline'))
                        N/A
                    @else
                        {{ $alert->alert_value ?? 'N/A' }}
                    @endif
                </div></div>
                <div class="col-md-6"><div class="fw-semibold">Reading</div><div>{{ $alert->reading_id ? 'Reading #' . $alert->reading_id : '-' }}</div></div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('alerts.edit', $alert->id) }}" class="btn btn-severe">Edit</a>
                <a href="{{ route('alerts.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
