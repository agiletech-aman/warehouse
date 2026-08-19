@extends('layouts.app')

@section('page-title', 'Edit Device')

@section('content')
<div class="container">
    <h3>Edit Device</h3>

    <form action="{{ route('devices.update', $device->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Warehouse</label>
                <select name="warehouse_id" class="form-control">
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ $device->warehouse_id == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->warehouse_name }}</option>
                    @endforeach
                </select>
                @error('warehouse_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Device Code</label>
                <input type="text" name="device_code" class="form-control" value="{{ old('device_code', $device->device_code) }}">
                @error('device_code')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Device Name</label>
                <input type="text" name="device_name" class="form-control" value="{{ old('device_name', $device->device_name) }}">
                @error('device_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Device Type</label>
                <input type="text" name="device_type" class="form-control" value="{{ old('device_type', $device->device_type) }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Model No</label>
                <input type="text" name="model_no" class="form-control" value="{{ old('model_no', $device->model_no) }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Serial No</label>
                <input type="text" name="serial_no" class="form-control" value="{{ old('serial_no', $device->serial_no) }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">MAC Address</label>
                <input type="text" name="mac_address" class="form-control" value="{{ old('mac_address', $device->mac_address) }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">IP Address</label>
                <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address', $device->ip_address) }}">
                @error('ip_address')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Firmware Version</label>
                <input type="text" name="firmware_version" class="form-control" value="{{ old('firmware_version', $device->firmware_version) }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Installation Date</label>
                <input type="date" name="installation_date" class="form-control" value="{{ old('installation_date', $device->installation_date) }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Last Seen At</label>
                <input type="datetime-local" name="last_seen_at" class="form-control" value="{{ old('last_seen_at', optional($device->last_seen_at)->format('Y-m-d\TH:i')) }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="offline" {{ $device->status === 'offline' ? 'selected' : '' }}>Offline</option>
                    <option value="online" {{ $device->status === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="maintenance" {{ $device->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
                @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>

        <button class="btn btn-success">Update</button>
        <a href="{{ route('devices.index') }}" class="btn btn-secondary">Back</a>
    </form>
</div>
@endsection
