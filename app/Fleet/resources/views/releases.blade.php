@extends('fleet::layout')
@section('body')
<div class="row g-3"><div class="col-lg-4"><div class="card"><div class="card-body"><h6>Upload release</h6>
<form method="post" action="{{ route('fleet.releases.store') }}" enctype="multipart/form-data">@csrf
<input name="version" class="form-control form-control-sm mb-2" placeholder="2.0.1" value="{{ old('version') }}">
<input type="file" name="binary" class="form-control form-control-sm mb-2">
<input name="signature" class="form-control form-control-sm mb-2" placeholder="Ed25519 signature (base64){{ $pubkey?'':' — optional, no public key set' }}">
<textarea name="notes" class="form-control form-control-sm mb-2" rows="2" placeholder="notes"></textarea>
<div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="make_latest" value="1" checked><label class="form-check-label">Make latest (fleet starts updating)</label></div>
<button class="btn btn-sm btn-primary">Upload</button></form>
<div class="form-text mt-2">Large file? <code>php artisan fleet:release 2.0.1 /path/gasmonitor --signature=... --latest</code></div></div></div></div>
<div class="col-lg-8"><table class="table table-sm table-bordered bg-white align-middle"><thead><tr><th>Version</th><th>Size</th><th>sha256</th><th>Signed</th><th>Workstations</th><th></th></tr></thead><tbody>
@foreach($releases as $r)<tr class="{{ $r->yanked?'table-danger':'' }}"><td>{{ $r->version }} @if($r->is_latest)<span class="badge bg-success">latest</span>@endif @if($r->yanked)<span class="badge bg-danger">yanked</span>@endif<div class="small text-muted">{{ $r->notes }}</div></td>
<td>{{ number_format($r->size/1048576,1) }} MB</td><td class="small"><code>{{ substr($r->sha256,0,12) }}</code></td><td>{{ $r->signature?'yes':'no' }}</td><td>{{ $counts[$r->version] ?? 0 }}</td>
<td class="d-flex gap-1">@foreach(['latest'=>'Make latest','yank'=>'Yank','unyank'=>'Unyank'] as $a=>$l)@if(!($a==='unyank'&&!$r->yanked)&&!($a==='yank'&&$r->yanked)&&!($a==='latest'&&$r->is_latest))
<form method="post" action="{{ route('fleet.releases.action',$r->id) }}">@csrf<input type="hidden" name="action" value="{{ $a }}"><button class="btn btn-sm btn-outline-secondary">{{ $l }}</button></form>@endif @endforeach</td></tr>@endforeach
</tbody></table></div></div>
@endsection
