@extends('layouts.app')

@section('page-title', 'Add Alert')

@section('content')
<div class="container">
    <h3>Add Alert</h3>

    <form action="{{ route('alerts.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Device</label>
                <select name="device_id" class="form-control">
                    <option value="">Select Device</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->device_code }}" @selected(old('device_id') == $device->device_code)>{{ $device->device_name }}</option>
                    @endforeach
                </select>
                @error('device_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Reading</label>
                <select name="reading_id" class="form-control">
                    <option value="">Optional</option>
                    @foreach($readings as $reading)
                        <option value="{{ $reading->id }}" @selected(old('reading_id') == $reading->id)>Reading #{{ $reading->id }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Alert Type</label>
                <select name="alert_type" class="form-control">
                    <option value="high_co2" @selected(old('alert_type') === 'high_co2')>High CO2</option>
                    <option value="high_phosphorus" @selected(old('alert_type') === 'high_phosphorus')>High Phosphorus</option>
                    <option value="device_offline" @selected(old('alert_type') === 'device_offline')>Device Offline</option>
                </select>
                @error('alert_type')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Alert Value</label>
                <input type="number" step="0.01" name="alert_value" class="form-control" value="{{ old('alert_value') }}">
                @error('alert_value')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>

        <button class="btn btn-success">Save</button>
        <a href="{{ route('alerts.index') }}" class="btn btn-secondary">Back</a>
    </form>
</div>
@endsection
