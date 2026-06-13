@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Add Reading</h1>

    {{-- Minimal UI to satisfy route/view existence.
         (Actual create form fields can be added later.) --}}
    <form method="POST" action="{{ route('readings.store') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">Sensor Device ID</label>
            <input type="text" name="device_id" class="form-control" />
        </div>

        <div class="mb-3">
            <label class="form-label">Reading Value</label>
            <input type="number" name="reading_value" class="form-control" />
        </div>

        <div class="mb-3">
            <label class="form-label">Level</label>
            <select name="level" class="form-control">
                <option value="normal">normal</option>
                <option value="severe">severe</option>
                <option value="critical">critical</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
@endsection
