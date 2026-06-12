@extends('layouts.app')

@section('content')

<div class="container">

<h3>Add Region</h3>

<form action="{{ route('regions.store') }}"
method="POST">

@csrf

<div class="mb-3">

<label>Region Code</label>

<input type="text"
name="region_code"
placeholder="REG001"
class="form-control">

</div>

<div class="mb-3">

<label>Region Name</label>

<input type="text"
name="region_name"
class="form-control">

</div>

<div class="mb-3">

<label>Status</label>


<select name="status"
class="form-control">

<option value="active">Active</option>
<option value="inactive">Inactive</option>

</select>

</div>

<button class="btn btn-success">
Save
</button>

<a href="{{ route('regions.index') }}"
class="btn btn-secondary">
Back
</a>

</form>

</div>

@endsection