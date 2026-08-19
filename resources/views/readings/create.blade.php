@extends('layouts.app')

@section('page-title', 'Add Reading')

@section('content')
<div class="container">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h3 class="mb-3">Add Reading</h3>

            <form method="POST" action="{{ route('readings.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Registered Device (optional)</label>
                        <select name="device_id" class="form-select">
                            <option value="">No registered device</option>
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}" @selected(old('device_id') == $device->id)>
                                    {{ $device->device_name }} ({{ $device->device_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('device_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sensor Device ID</label>
                        <input type="text" name="sensor_device_id" class="form-control" value="{{ old('sensor_device_id') }}" required>
                        @error('sensor_device_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Reading Value</label>
                        <input type="number" step="any" name="reading_value" class="form-control" value="{{ old('reading_value') }}">
                        @error('reading_value')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control" value="{{ old('unit') }}">
                        @error('unit')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Recorded At</label>
                        <input type="datetime-local" name="recorded_at" class="form-control" value="{{ old('recorded_at') }}">
                        @error('recorded_at')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-select">
                            <option value="">Unknown</option>
                            <option value="normal" @selected(old('level') === 'normal')>Normal</option>
                            <option value="severe" @selected(old('level') === 'severe')>Severe</option>
                            <option value="critical" @selected(old('level') === 'critical')>Critical</option>
                        </select>
                        @error('level')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device Status</label>
                        <select name="status" class="form-select" required>
                            <option value="online" @selected(old('status', 'online') === 'online')>Online</option>
                            <option value="offline" @selected(old('status') === 'offline')>Offline</option>
                        </select>
                        @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('readings.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
