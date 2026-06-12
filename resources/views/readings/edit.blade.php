@extends('layouts.app')

@section('page-title', 'Edit Reading')

@section('content')
<div class="container">
    <h3>Edit Reading</h3>

    <form action="{{ route('readings.update', $reading->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Device</label>
                <select name="device_id" class="form-control">
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ $reading->device_id == $device->id ? 'selected' : '' }}>{{ $device->device_name }}</option>
                    @endforeach
                </select>
                @error('device_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Reading Value</label>
                <input type="number" step="0.01" name="reading_value" class="form-control" value="{{ old('reading_value', $reading->reading_value) }}">
                @error('reading_value')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" class="form-control" value="{{ old('unit', $reading->unit) }}">
                @error('unit')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="normal" {{ $reading->status === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="warning" {{ $reading->status === 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="critical" {{ $reading->status === 'critical' ? 'selected' : '' }}>Critical</option>
                </select>
                @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Recorded At</label>
                <input type="datetime-local" name="recorded_at" class="form-control" value="{{ old('recorded_at', optional($reading->recorded_at)->format('Y-m-d\TH:i')) }}">
            </div>
        </div>

        <button class="btn btn-success">Update</button>
        <a href="{{ route('readings.index') }}" class="btn btn-secondary">Back</a>
    </form>
</div>
@endsection
