@extends('layouts.app')

@section('content')

<div class="container">

<h3>Edit Region</h3>

<form action="{{ route('regions.update',$region->id) }}"
method="POST">

@csrf
@method('PUT')

<div class="mb-3">

<label>Region Code</label>

<input type="text"
name="region_code"
value="{{ $region->region_code }}"
class="form-control">

</div>

<div class="mb-3">

<label>Region Name</label>

<input type="text"
name="region_name"
value="{{ $region->region_name }}"
class="form-control">

</div>

<div class="mb-3">

<label>Manager Name </label>

<input type="text"
name="manager_name"
value="{{ old('manager_name', $region->manager_name) }}"
class="form-control">

</div>

<div class="mb-3">

<label>Manager Email</label>

<input type="email"
name="manager_email"
value="{{ old('manager_email', $region->manager_email) }}"
class="form-control">

</div>

<div class="mb-3">

<label>Manager Mobile </label>

<input type="text"
name="manager_phone"
value="{{ old('manager_phone', $region->manager_phone) }}"
class="form-control">

</div>

<div class="mb-3">

<label>Status</label>

<select name="status"
class="form-control">

<option value="active"
{{ $region->status=='active'?'selected':'' }}>
Active
</option>

<option value="inactive"
{{ $region->status=='inactive'?'selected':'' }}>
Inactive
</option>

</select>

</div>

<button class="btn btn-primary">
Update
</button>

<a href="{{ route('regions.index') }}"
class="btn btn-secondary">
Back
</a>

</form>

</div>

@endsection