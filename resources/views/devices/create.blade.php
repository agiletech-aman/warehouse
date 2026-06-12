@extends('layouts.app')

@section('page-title', 'Add Device')

@section('content')
<div class="container">
    <h3>Add Device</h3>

    <form action="{{ route('devices.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Warehouse</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">Select Warehouse</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                    @endforeach
                </select>
                @error('warehouse_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Device Code</label>
                <input type="text" name="device_code" class="form-control" value="{{ old('device_code') }}">
                @error('device_code')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Device Name</label>
                <input type="text" name="device_name" class="form-control" value="{{ old('device_name') }}">
                @error('device_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Device Type</label>
                <input type="text" name="device_type" class="form-control" value="{{ old('device_type') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Model No</label>
                <input type="text" name="model_no" class="form-control" value="{{ old('model_no') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Serial No</label>
                <input type="text" name="serial_no" class="form-control" value="{{ old('serial_no') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">MAC Address</label>
                <input type="text" name="mac_address" class="form-control" value="{{ old('mac_address') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">IP Address</label>
                <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address') }}">
                @error('ip_address')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Firmware Version</label>
                <input type="text" name="firmware_version" class="form-control" value="{{ old('firmware_version') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Installation Date</label>
                <input type="date" name="installation_date" class="form-control" value="{{ old('installation_date') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Last Seen At</label>
                <input type="datetime-local" name="last_seen_at" class="form-control" value="{{ old('last_seen_at') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="offline" @selected(old('status') === 'offline')>Offline</option>
                    <option value="online" @selected(old('status') === 'online')>Online</option>
                    <option value="maintenance" @selected(old('status') === 'maintenance')>Maintenance</option>
                </select>
                @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>

        <button class="btn btn-success">Save</button>
        <a href="{{ route('devices.index') }}" class="btn btn-secondary">Back</a>
    </form>
</div>
@endsection
