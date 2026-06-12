@extends('layouts.app')

@section('page-title', 'Add Reading')

@section('content')
<div class="container">
    <h3>Add Reading</h3>

    <form action="{{ route('readings.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Device</label>
                <select name="device_id" class="form-control">
                    <option value="">Select Device</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" @selected(old('device_id') == $device->id)>{{ $device->device_name }}</option>
                    @endforeach
                </select>
                @error('device_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Reading Value</label>
                <input type="number" step="0.01" name="reading_value" class="form-control" value="{{ old('reading_value') }}">
                @error('reading_value')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" class="form-control" value="{{ old('unit') }}">
                @error('unit')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="normal" @selected(old('status') === 'normal')>Normal</option>
                    <option value="warning" @selected(old('status') === 'warning')>Warning</option>
                    <option value="critical" @selected(old('status') === 'critical')>Critical</option>
                </select>
                @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Recorded At</label>
                <input type="datetime-local" name="recorded_at" class="form-control" value="{{ old('recorded_at') }}">
            </div>
        </div>

        <button class="btn btn-success">Save</button>
        <a href="{{ route('readings.index') }}" class="btn btn-secondary">Back</a>
    </form>
</div>
@endsection
