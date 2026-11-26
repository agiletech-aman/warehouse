@extends('workstations::layout')
@section('title', 'Dashboard')
@php
  use App\Workstations\Support\Timeline;
  $badgeText = ['online' => 'Online', 'offline' => 'Offline', 'pending' => 'Pending', 'blocked' => 'Blocked'];
@endphp
@section('content')
<div class="row" style="margin-bottom:16px">
  <div>
    <h1>Workstations</h1>
    <p class="sub" style="margin:0">
      Latest release:
      @if($latest)<b>v{{ $latest->version }}</b>@else<span class="muted">none</span> — <a href="{{ route('wsm.releases') }}">upload a release</a>@endif
    </p>
  </div>
  <div class="spacer"></div>
  <a class="btn primary" href="{{ route('wsm.add') }}">+ Add Workstation</a>
</div>

@if($pending->count())
  <div class="banner">
    <span>⏳ <b>{{ $pending->count() }} new workstation(s)</b> waiting for approval:
      <span class="muted">{{ $pending->take(4)->pluck('workstation_id')->join(', ') }}{{ $pending->count() > 4 ? ' …' : '' }}</span></span>
    <div class="spacer"></div>
    <form method="post" action="{{ route('wsm.approve-all') }}" data-confirm="Approve all {{ $pending->count() }} pending workstations? Once approved they will start receiving updates and settings.">
      @csrf<button class="btn good">✓ Approve all</button>
    </form>
  </div>
@endif

<div class="stats">
  <div class="stat"><b>{{ $stats['total'] }}</b><span>Total</span></div>
  <div class="stat"><b style="color:var(--good)">{{ $stats['online'] }}</b><span>Online</span></div>
  <div class="stat"><b style="color:var(--bad)">{{ $stats['offline'] }}</b><span>Offline</span></div>
  <div class="stat"><b style="color:var(--warn)">{{ $stats['outdated'] }}</b><span>Outdated</span></div>
</div>

<div class="card">
  <form class="row" method="get" style="margin-bottom:14px">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search: ID, label, hostname, version…" style="flex:1;min-width:200px">
    <select name="wh" onchange="this.form.submit()">
      <option value="">All warehouses</option>
      @foreach($warehouses as $c)<option value="{{ $c }}" @selected($wh === $c)>{{ $c }}</option>@endforeach
    </select>
    <button class="btn">Search</button>
    @if($q !== '' || $wh !== '')<a class="btn" href="{{ route('wsm.index') }}">Clear</a>@endif
  </form>

  @if($rows->isEmpty())
    <div class="empty">
      @if($stats['total'] === 0)
        <b>No workstations connected yet</b>
        To get started: 1) <a href="{{ route('wsm.releases') }}">upload a signed release</a>,
        2) get an install command from <a href="{{ route('wsm.add') }}">Add Workstation</a> and run it on the machine.
      @else
        <b>No workstations match this search</b>
        Clear the filters and try again.
      @endif
    </div>
  @else
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Workstation</th><th>Warehouse</th><th>Status</th><th>Version</th><th>Devices</th><th>Last seen</th></tr></thead>
        <tbody>
        @foreach($rows as $w)
          <tr>
            <td><a href="{{ route('wsm.show', $w->workstation_id) }}"><b>{{ $w->label ?: $w->workstation_id }}</b></a>
              @if($w->label)<div class="muted mono">{{ $w->workstation_id }}</div>@endif</td>
            <td>{{ $w->warehouse_code ?: '—' }}</td>
            <td><span class="badge b-{{ $w->badge }}">{{ $badgeText[$w->badge] }}</span></td>
            <td class="mono">{{ $w->current_version ? 'v'.$w->current_version : '—' }}
              @if($w->vstate === 'outdated') <span class="tag">Outdated</span>@endif
              @if($w->vstate === 'frozen') <span class="tag grey">Auto-update off</span>@endif
              @if($w->target_version) <span class="tag grey" title="Pinned">📌 v{{ $w->target_version }}</span>@endif</td>
            <td>{{ $w->devices_online }}/{{ $w->devices_total }} @if($w->alerts)<span class="tag red">{{ $w->alerts }} alert(s)</span>@endif</td>
            <td class="muted" title="{{ $w->last_seen_at }}">{{ Timeline::ago($w->last_seen_at) }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <div class="cards">
      @foreach($rows as $w)
        <a class="wcard" href="{{ route('wsm.show', $w->workstation_id) }}">
          <div class="row"><b>{{ $w->label ?: $w->workstation_id }}</b><div class="spacer"></div><span class="badge b-{{ $w->badge }}">{{ $badgeText[$w->badge] }}</span></div>
          <div class="muted" style="margin-top:6px">{{ $w->warehouse_code ?: 'No warehouse' }} · <span class="mono">{{ $w->current_version ? 'v'.$w->current_version : '—' }}</span>
            @if($w->vstate === 'outdated') <span class="tag">Outdated</span>@endif · {{ Timeline::ago($w->last_seen_at) }}</div>
        </a>
      @endforeach
    </div>
  @endif
</div>
@endsection
