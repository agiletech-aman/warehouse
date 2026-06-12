@extends('layouts.app')

@section('page-title', 'Edit Alert')

@section('content')
<div class="container">
    <h3>Edit Alert</h3>

    <form action="{{ route('alerts.update', $alert->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Device</label>
                <select name="device_id" class="form-control">
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ $alert->device_id == $device->id ? 'selected' : '' }}>{{ $device->device_name }}</option>
                    @endforeach
                </select>
                @error('device_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Reading</label>
                <select name="reading_id" class="form-control">
                    <option value="">Optional</option>
                    @foreach($readings as $reading)
                        <option value="{{ $reading->id }}" {{ $alert->reading_id == $reading->id ? 'selected' : '' }}>Reading #{{ $reading->id }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Alert Type</label>
                <select name="alert_type" class="form-control">
                    <option value="high_co2" {{ $alert->alert_type === 'high_co2' ? 'selected' : '' }}>High CO2</option>
                    <option value="high_phosphorus" {{ $alert->alert_type === 'high_phosphorus' ? 'selected' : '' }}>High Phosphorus</option>
                    <option value="device_offline" {{ $alert->alert_type === 'device_offline' ? 'selected' : '' }}>Device Offline</option>
                </select>
                @error('alert_type')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Alert Value</label>
                <input type="number" step="0.01" name="alert_value" class="form-control" value="{{ old('alert_value', $alert->alert_value) }}">
                @error('alert_value')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>

        <button class="btn btn-success">Update</button>
        <a href="{{ route('alerts.index') }}" class="btn btn-secondary">Back</a>
    </form>
</div>
@endsection
