@extends('layouts.app')

@section('page-title', 'Device Details')

@section('content')
<div class="container">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h3 class="mb-3">{{ $device->device_name }}</h3>

            <div class="row g-3">
                <div class="col-md-6"><div class="fw-semibold">Device Code</div><div>{{ $device->device_code }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Warehouse</div><div>{{ optional($device->warehouse)->warehouse_name }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Device Type</div><div>{{ $device->device_type ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Model No</div><div>{{ $device->model_no ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Serial No</div><div>{{ $device->serial_no ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">MAC Address</div><div>{{ $device->mac_address ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">IP Address</div><div>{{ $device->ip_address ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Firmware Version</div><div>{{ $device->firmware_version ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Installation Date</div><div>{{ $device->installation_date ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Last Seen At</div><div>{{ $device->last_seen_at ?: '-' }}</div></div>
                <div class="col-md-6"><div class="fw-semibold">Status</div>
                    @if($device->status === 'online')
                        <span class="badge bg-success">Online</span>
                    @elseif($device->status === 'maintenance')
                        <span class="badge bg-warning text-dark">Maintenance</span>
                    @else
                        <span class="badge bg-secondary">Offline</span>
                    @endif
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('devices.edit', $device->id) }}" class="btn btn-warning">Edit</a>
                <a href="{{ route('devices.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
