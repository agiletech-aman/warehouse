@extends('fleet::layout')
@section('body')
<h4>{{ $w->label ?: $w->hostname ?: $w->workstation_id }} <small class="text-muted">{{ $w->workstation_id }}</small></h4>
<div class="row g-3">
<div class="col-lg-5">
<div class="card mb-3"><div class="card-body">
<form method="post" action="{{ route('fleet.ws.update',$w->workstation_id) }}">@csrf<input type="hidden" name="action" value="save">
<div class="mb-2"><label class="form-label">Label</label><input name="label" value="{{ $w->label }}" class="form-control form-control-sm"></div>
<div class="mb-2"><label class="form-label">Warehouse code</label><input name="warehouse_code" value="{{ $w->warehouse_code }}" class="form-control form-control-sm">
<div class="form-text">Agent reports: {{ $w->reported_warehouse_code ?: '—' }}</div></div>
<div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="auto_update" value="1" {{ $w->auto_update?'checked':'' }}><label class="form-check-label">Auto-update</label></div>
<div class="mb-2"><label class="form-label">Pin to release (canary)</label><select name="target_release_id" class="form-select form-select-sm"><option value="">— follow latest —</option>
@foreach($releases as $r)<option value="{{ $r->id }}" {{ $w->target_release_id==$r->id?'selected':'' }}>{{ $r->version }}{{ $r->yanked?' (yanked)':'' }}</option>@endforeach</select></div>
<button class="btn btn-sm btn-primary">Save</button></form></div></div>
<div class="card mb-3"><div class="card-body">
<div>Status: <b>{{ $w->status }}</b> · Version <b>{{ $w->current_version ?: '—' }}</b> · {{ $w->os }}</div>
<div>Frozen binary: {{ $w->frozen?'yes':'no' }} · Launcher: {{ $w->launcher?'yes':'no' }} · Polling: {{ $w->polling_running?'running':'stopped' }} @if($w->demo_mode)<span class="badge bg-warning text-dark">demo</span>@endif</div>
<div>Update: {{ $w->update_state }} — {{ $w->update_message }}</div>
<div>Bad versions: {{ $w->bad_versions }}</div>
<div class="mt-2 d-flex gap-1 flex-wrap">
@foreach(['approve'=>'success','block'=>'danger','reset_token'=>'warning'] as $a=>$c)
<form method="post" action="{{ route('fleet.ws.update',$w->workstation_id) }}">@csrf<input type="hidden" name="action" value="{{ $a }}"><button class="btn btn-sm btn-{{ $c }}" @if($a==='block') onclick="return confirm('Block?')" @endif>{{ str_replace('_',' ',$a) }}</button></form>
@endforeach</div></div></div>
<div class="card"><div class="card-body"><h6>Remote command</h6>
<form method="post" action="{{ route('fleet.ws.update',$w->workstation_id) }}" class="d-flex gap-1">@csrf<input type="hidden" name="action" value="command">
<select name="command" class="form-select form-select-sm">@foreach($cmds as $c)<option>{{ $c }}</option>@endforeach</select><button class="btn btn-sm btn-dark">Queue</button></form>
<table class="table table-sm mt-2 small"><tbody>@foreach($commands as $c)<tr><td>{{ $c->command }}</td><td>{{ $c->status }}</td><td>{{ $c->result }}</td><td>{{ $c->created_at }}</td></tr>@endforeach</tbody></table></div></div>
</div>
<div class="col-lg-7">
<div class="card mb-3"><div class="card-body"><h6>.env for this workstation
 <span class="badge bg-{{ $inSync?'success':'warning text-dark' }}">{{ $inSync?'in sync':'pending/applied differs' }}</span></h6>
<form method="post" action="{{ route('fleet.env.save') }}" class="row g-1 mb-2">@csrf<input type="hidden" name="scope" value="workstation"><input type="hidden" name="ref" value="{{ $w->workstation_id }}">
<div class="col"><input name="key" class="form-control form-control-sm" placeholder="KEY" value="{{ old('key') }}"></div><div class="col"><input name="value" class="form-control form-control-sm" placeholder="value"></div><div class="col-auto"><button class="btn btn-sm btn-primary">Set override</button></div></form>
<table class="table table-sm small"><thead><tr><th>Effective (global&lt;warehouse&lt;ws)</th><th></th></tr></thead><tbody>
@foreach($eff as $k=>$v)<tr><td><code>{{ $k }}={{ $v }}</code></td><td>@if($env->contains('key',$k))<span class="badge bg-info text-dark">ws</span>
<form method="post" action="{{ route('fleet.env.save') }}" class="d-inline">@csrf<input type="hidden" name="scope" value="workstation"><input type="hidden" name="ref" value="{{ $w->workstation_id }}"><input type="hidden" name="key" value="{{ $k }}"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-link text-danger p-0">remove</button></form>@endif</td></tr>@endforeach
</tbody></table></div></div>
<div class="card"><div class="card-body"><h6>Events</h6><table class="table table-sm small"><tbody>
@foreach($events as $e)<tr><td>{{ $e->created_at }}</td><td>{{ $e->type }}</td><td>{{ $e->version }}</td><td>{{ $e->message }}</td></tr>@endforeach</tbody></table></div></div>
</div></div>
@endsection
