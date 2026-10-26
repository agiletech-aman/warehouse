<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>GasMonitor Fleet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-3"><div class="container-fluid">
<a class="navbar-brand" href="{{ route('fleet.index') }}">GasMonitor Fleet</a>
<div><a class="btn btn-sm btn-outline-light" href="{{ route('fleet.index') }}">Workstations</a>
<a class="btn btn-sm btn-outline-light" href="{{ route('fleet.releases') }}">Releases</a>
<a class="btn btn-sm btn-outline-light" href="{{ route('fleet.env') }}">Env</a></div></div></nav>
<div class="container-fluid">
@if(session('ok'))<div class="alert alert-success py-2">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
@yield('body')
</div></body></html>
