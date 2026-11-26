@extends('workstations::layout')
@section('title', $w->label ?: $w->workstation_id)
@php
  use App\Workstations\Support\Timeline;
  $badgeText = ['online' => 'Online', 'offline' => 'Offline', 'pending' => 'Pending', 'blocked' => 'Blocked'];
  $cmdInfo = [
    'restart' => ['🔄 Restart', 'Restart GasMonitor? Monitoring will pause for a few seconds.'],
    'sync_now' => ['⇅ Sync now', 'Sync data with the cloud now?'],
    'stop_polling' => ['⏸ Stop polling', 'Stop reading the sensors? Polling resumes after a restart.'],
  ];
@endphp
@section('content')
<p style="margin:0 0 8px"><a href="{{ route('wsm.index') }}">← All workstations</a></p>
<div class="row" style="margin-bottom:16px">
  <div>
    <h1>{{ $w->label ?: $w->workstation_id }} <span class="badge b-{{ $w->badge }}" style="vertical-align:middle">{{ $badgeText[$w->badge] }}</span></h1>
    <p class="sub" style="margin:0"><span class="mono">{{ $w->workstation_id }}</span> · {{ $w->warehouse_code ?: 'no warehouse set' }} · last seen {{ Timeline::ago($w->last_seen_at) }}</p>
  </div>
  <div class="spacer"></div>
  @if($w->status !== 'approved')
    <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="Approve {{ $w->workstation_id }}? It will start receiving updates, settings and commands.">
      @csrf<input type="hidden" name="action" value="approve"><button class="btn good">✓ Approve</button></form>
  @endif
  @if($w->status !== 'blocked')
    <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="Block {{ $w->workstation_id }}? It will no longer be able to connect to the server.">
      @csrf<input type="hidden" name="action" value="block"><button class="btn danger">⛔ Block</button></form>
  @endif
</div>

@if($w->status === 'pending')
  <div class="banner">⏳ This workstation is waiting for approval. If it is your machine, click <b>Approve</b>.</div>
@elseif($w->status === 'blocked')
  <div class="banner" style="background:var(--grey-bg);border-color:var(--line)">⛔ This workstation is blocked. Click <b>Approve</b> to re-enable it.</div>
@endif

<div class="grid g2">
  <div class="card">
    <h2>Status</h2>
    <dl class="kv">
      <dt>Version</dt><dd class="mono">{{ $w->current_version ? 'v'.$w->current_version : '—' }}
        @if($w->vstate === 'outdated') <span class="tag">Outdated</span>@elseif($w->vstate === 'latest') <span class="tag green">Up to date</span>@endif</dd>
      <dt>Update</dt><dd>{{ $w->update_state }}@if($w->update_message) — <span class="muted">{{ $w->update_message }}</span>@endif</dd>
      <dt>Devices</dt><dd>{{ $w->devices_online }} / {{ $w->devices_total }} online @if($w->alerts)<span class="tag red">{{ $w->alerts }} alert(s)</span>@endif</dd>
      <dt>Polling</dt><dd>{{ $w->polling_running ? 'Running' : 'Stopped' }}{{ $w->demo_mode ? ' (demo mode)' : '' }}</dd>
      <dt>Hostname</dt><dd>{{ $w->hostname ?: '—' }}</dd>
      <dt>IP</dt><dd class="mono">{{ $w->ip ?: '—' }} @if($w->last_ip && $w->last_ip !== $w->ip)<span class="muted">(public {{ $w->last_ip }})</span>@endif</dd>
      <dt>OS</dt><dd>{{ $w->os ?: '—' }}</dd>
      <dt>Uptime</dt><dd>{{ $w->uptime_s ? \Carbon\CarbonInterval::seconds($w->uptime_s)->cascade()->forHumans(['parts' => 2]) : '—' }}</dd>
      <dt>Settings</dt><dd>@if($inSync)<span class="tag green">Applied</span>@else<span class="tag">Will apply on next check-in</span>@endif</dd>
    </dl>
  </div>

  <div class="card">
    <h2>Commands</h2>
    @if($w->status === 'approved')
      <p class="muted" style="margin-top:0">Commands run on the next check-in (within about {{ (int)ceil(config('workstations.check_interval')/60) }} min).</p>
      <div class="row">
        @foreach($cmds as $c)
          <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="{{ $cmdInfo[$c][1] ?? 'Send this command?' }}">
            @csrf<input type="hidden" name="action" value="command"><input type="hidden" name="command" value="{{ $c }}">
            <button class="btn">{{ $cmdInfo[$c][0] ?? $c }}</button>
          </form>
        @endforeach
      </div>
    @else
      <p class="muted">Approve the workstation to send commands.</p>
    @endif
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h2>Security</h2>
    <div class="row">
      <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="Reset the token? The workstation's current token will stop working. It gets a new token only after it checks in with a valid install key.">
        @csrf<input type="hidden" name="action" value="reset_token"><button class="btn">🔑 Reset token</button></form>
      <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="Remove {{ $w->workstation_id }} from the list? If the machine is still running and can enroll, it will show up again as pending.">
        @csrf<input type="hidden" name="action" value="delete"><button class="btn danger">🗑 Remove</button></form>
    </div>
  </div>
</div>

<div class="card">
  <h2>Details &amp; updates</h2>
  <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}">
    @csrf<input type="hidden" name="action" value="save">
    <div class="grid g2">
      <label class="f"><span>Name / label</span><input type="text" name="label" value="{{ old('label', $w->label) }}" placeholder="e.g. Gate 2 control room">
        @error('label')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f"><span>Warehouse code</span><input type="text" name="warehouse_code" value="{{ old('warehouse_code', $w->warehouse_code) }}">
        @error('warehouse_code')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f"><span>Version to run</span>
        <select name="target_release_id">
          <option value="">Always latest (recommended)</option>
          @foreach($releases as $r)<option value="{{ $r->id }}" @selected($w->target_release_id == $r->id)>📌 Pin v{{ $r->version }}{{ $r->is_latest ? ' (latest)' : '' }}</option>@endforeach
        </select></label>
      <label class="f" style="display:flex;gap:10px;align-items:center;margin-top:22px">
        <input type="checkbox" name="auto_update" value="1" @checked($w->auto_update) style="width:18px;height:18px">
        <span style="margin:0;color:var(--ink)">Auto-update on — install new versions automatically</span></label>
    </div>
    <button class="btn primary">Save</button>
  </form>
</div>

<div class="card">
  <h2>History</h2>
  @if($timeline->isEmpty())
    <div class="empty"><b>No history yet</b>Updates will appear here once the workstation checks in.</div>
  @else
    <ul class="timeline">
      @foreach($timeline as $t)
        <li class="{{ $t['tone'] }}">{{ $t['text'] }}<small title="{{ $t['at'] }}">{{ Timeline::ago($t['at']) }} · {{ $t['at']?->format('d M, h:i A') }}</small></li>
      @endforeach
    </ul>
  @endif
</div>

@if(count($eff))
<div class="card">
  <h2>Settings for this workstation</h2>
  <p class="muted" style="margin-top:0">Global → warehouse → workstation, merged. To change them, go to the <a href="{{ route('wsm.env') }}">Settings</a> page.</p>
  <table><tbody>@foreach($eff as $k => $v)<tr><td class="mono">{{ $k }}</td><td class="mono">{{ $v }}</td></tr>@endforeach</tbody></table>
</div>
@endif
@endsection
