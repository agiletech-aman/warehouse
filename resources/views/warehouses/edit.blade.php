@extends('layouts.app')

@section('page-title', 'Edit Warehouse')

@section('content')

<div class="container">

    <h3>Edit Warehouse</h3>

    <form action="{{ route('warehouses.update', $warehouse->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">

            <div class="col-md-6 mb-3">
                <label class="form-label">Region</label>
                <select name="region_id" class="form-control">
                    @foreach($regions as $region)
                        <option value="{{ $region->id }}" {{ $warehouse->region_id == $region->id ? 'selected' : '' }}>
                            {{ $region->region_name }}
                        </option>
                    @endforeach
                </select>
                @error('region_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Warehouse Code</label>
                <input type="text" name="warehouse_code" placeholder="WH001" class="form-control" value="{{ old('warehouse_code', $warehouse->warehouse_code) }}">
                @error('warehouse_code')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-12 mb-3">
                <label class="form-label">Warehouse Name</label>
                <input type="text" name="warehouse_name" class="form-control" value="{{ old('warehouse_name', $warehouse->warehouse_name) }}">
                @error('warehouse_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Manager Name</label>
                <input type="text" name="manager_name" class="form-control" value="{{ old('manager_name', $warehouse->manager_name) }}">
                @error('manager_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
<label class="form-label">Manager Email</label>
                <input type="email" name="manager_email" class="form-control" value="{{ old('manager_email', $warehouse->manager_email) }}">
                @error('manager_email')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Manager Phone (optional)</label>
                <input type="text" name="manager_phone" class="form-control" value="{{ old('manager_phone', $warehouse->manager_phone) }}">
                @error('manager_phone')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active" {{ $warehouse->status==='active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $warehouse->status==='inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-12 mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3">{{ old('address', $warehouse->address) }}</textarea>
                @error('address')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

           


        </div>

        <button class="btn btn-success">Update</button>
        <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">Back</a>

    </form>

</div>

@endsection

