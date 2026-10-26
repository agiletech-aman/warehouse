@extends('fleet::layout')
@section('body')
<div class="row g-2 mb-3">
@foreach(['total'=>'Workstations','latest'=>'On target version','outdated'=>'Outdated','pending'=>'Awaiting approval','offline'=>'Offline'] as $k=>$l)
<div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-3">{{ $stats[$k] }}</div><div class="small text-muted">{{ $l }}</div></div></div></div>
@endforeach
<div class="col"><div class="card text-center"><div class="card-body py-2"><div class="fs-3">{{ $latest->version ?? '—' }}</div><div class="small text-muted">Latest release</div></div></div></div>
</div>
<form method="get" class="mb-2"><input name="wh" value="{{ $wh }}" class="form-control form-control-sm d-inline-block w-auto" placeholder="warehouse code"> <button class="btn btn-sm btn-secondary">Filter</button></form>
<form method="post" action="{{ route('fleet.bulk') }}">@csrf
<table class="table table-sm table-bordered bg-white align-middle">
<thead><tr><th></th><th>Warehouse</th><th>Workstation</th><th>Version</th><th>Status</th><th>Devices</th><th>Env</th><th>Update</th><th>Last seen</th></tr></thead><tbody>
@foreach($rows as $w)
<tr>
<td>@if($w->status==='pending')<input type="checkbox" name="ids[]" value="{{ $w->workstation_id }}">@endif</td>
<td>{{ $w->warehouse_code ?: '—' }}</td>
<td><a href="{{ route('fleet.ws',$w->workstation_id) }}">{{ $w->label ?: $w->hostname ?: $w->workstation_id }}</a><div class="small text-muted">{{ $w->workstation_id }} · {{ $w->ip }}</div></td>
<td>{{ $w->current_version ?: '—' }}
 @if($w->vstate==='latest')<span class="badge bg-success">ok</span>@elseif($w->vstate==='outdated')<span class="badge bg-warning text-dark">→ {{ $w->target_version ?? $latest->version ?? '' }}</span>@elseif($w->vstate==='frozen')<span class="badge bg-secondary">pinned off</span>@endif</td>
<td><span class="badge bg-{{ $w->status==='approved'?'primary':($w->status==='pending'?'info':'danger') }}">{{ $w->status }}</span>
 <span class="badge bg-{{ $w->online?'success':'secondary' }}">{{ $w->online?'online':'offline' }}</span></td>
<td>{{ $w->devices_online }}/{{ $w->devices_total }} @if($w->alerts)<span class="badge bg-danger">{{ $w->alerts }} alerts</span>@endif</td>
<td>@if($w->env_applied_hash)<span class="badge bg-{{ str_ends_with($w->env_applied_hash,'!err')?'warning text-dark':'light text-dark' }}">{{ substr($w->env_applied_hash,0,8) }}</span>@endif</td>
<td class="small">{{ $w->update_state }} @if(!$w->launcher && $w->status==='approved')<span class="badge bg-warning text-dark" title="not started via run.sh: self-update disabled">no launcher</span>@endif<div class="text-muted">{{ \Illuminate\Support\Str::limit($w->update_message,60) }}</div></td>
<td class="small">{{ $w->last_seen_at }}</td></tr>
@endforeach
</tbody></table>
<button class="btn btn-sm btn-primary">Approve selected</button>
</form>
@endsection
