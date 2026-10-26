@extends('fleet::layout')
@section('body')
<div class="mb-2"><a class="btn btn-sm btn-{{ $scope==='global'?'dark':'outline-dark' }}" href="{{ route('fleet.env') }}">Global (all workstations)</a>
@foreach($codes as $c)<a class="btn btn-sm btn-{{ $scope==='warehouse'&&$ref===$c?'dark':'outline-dark' }}" href="{{ route('fleet.env',['scope'=>'warehouse','ref'=>$c]) }}">{{ $c }}</a>@endforeach</div>
<div class="card"><div class="card-body"><h6>{{ $scope==='global'?'Global':'Warehouse '.$ref }} .env overrides</h6>
<form method="post" action="{{ route('fleet.env.save') }}" class="row g-1 mb-2">@csrf<input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="ref" value="{{ $ref }}">
<div class="col-md-3"><input name="key" class="form-control form-control-sm" placeholder="MODBUS_TIMEOUT" value="{{ old('key') }}"></div><div class="col-md-4"><input name="value" class="form-control form-control-sm" placeholder="value"></div><div class="col-auto"><button class="btn btn-sm btn-primary">Save</button></div></form>
<table class="table table-sm"><tbody>@foreach($entries as $e)<tr><td><code>{{ $e->key }}</code></td><td>{{ $e->value }}</td><td>
<form method="post" action="{{ route('fleet.env.save') }}">@csrf<input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="ref" value="{{ $ref }}"><input type="hidden" name="key" value="{{ $e->key }}"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-link text-danger p-0">remove</button></form></td></tr>@endforeach</tbody></table>
<div class="form-text">Precedence: global &lt; warehouse &lt; workstation. Local-only keys (DB_URL, CENTRAL_*, WAREHOUSE_CODE, …) are refused. Changes reach workstations at next check-in; the app restarts itself if values changed.</div></div></div>
@endsection
