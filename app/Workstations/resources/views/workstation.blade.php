@extends('workstations::layout')
@section('title', $w->label ?: $w->workstation_id)
@php
  use App\Workstations\Support\Timeline;
  $badgeText = ['online' => 'Online', 'offline' => 'Offline', 'pending' => 'Pending', 'blocked' => 'Blocked'];
  $cmdInfo = [
    'restart' => ['🔄 Restart', 'GasMonitor ko restart karein? Monitoring kuch second ke liye rukegi.'],
    'sync_now' => ['⇅ Sync now', 'Abhi cloud ke saath data sync karein?'],
    'stop_polling' => ['⏸ Polling band karo', 'Sensors se reading lena band karein? Restart karne par wapas shuru hoga.'],
  ];
@endphp
@section('content')
<p style="margin:0 0 8px"><a href="{{ route('wsm.index') }}">← Saare workstations</a></p>
<div class="row" style="margin-bottom:16px">
  <div>
    <h1>{{ $w->label ?: $w->workstation_id }} <span class="badge b-{{ $w->badge }}" style="vertical-align:middle">{{ $badgeText[$w->badge] }}</span></h1>
    <p class="sub" style="margin:0"><span class="mono">{{ $w->workstation_id }}</span> · {{ $w->warehouse_code ?: 'warehouse set nahi' }} · last seen {{ Timeline::pehle($w->last_seen_at) }}</p>
  </div>
  <div class="spacer"></div>
  @if($w->status !== 'approved')
    <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="{{ $w->workstation_id }} ko approve karein? Isse updates, settings aur commands milne lagenge.">
      @csrf<input type="hidden" name="action" value="approve"><button class="btn good">✓ Approve</button></form>
  @endif
  @if($w->status !== 'blocked')
    <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="{{ $w->workstation_id }} ko block karein? Ye server se connect nahi kar payega.">
      @csrf<input type="hidden" name="action" value="block"><button class="btn danger">⛔ Block</button></form>
  @endif
</div>

@if($w->status === 'pending')
  <div class="banner">⏳ Ye workstation approval ka intezaar kar raha hai. Agar ye aapki machine hai to <b>Approve</b> dabayein.</div>
@elseif($w->status === 'blocked')
  <div class="banner" style="background:var(--grey-bg);border-color:var(--line)">⛔ Ye workstation blocked hai. Wapas chalu karne ke liye <b>Approve</b> dabayein.</div>
@endif

<div class="grid g2">
  <div class="card">
    <h2>Haal-chaal</h2>
    <dl class="kv">
      <dt>Version</dt><dd class="mono">{{ $w->current_version ? 'v'.$w->current_version : '—' }}
        @if($w->vstate === 'outdated') <span class="tag">Outdated</span>@elseif($w->vstate === 'latest') <span class="tag green">Up to date</span>@endif</dd>
      <dt>Update</dt><dd>{{ $w->update_state }}@if($w->update_message) — <span class="muted">{{ $w->update_message }}</span>@endif</dd>
      <dt>Devices</dt><dd>{{ $w->devices_online }} / {{ $w->devices_total }} online @if($w->alerts)<span class="tag red">{{ $w->alerts }} alert</span>@endif</dd>
      <dt>Polling</dt><dd>{{ $w->polling_running ? 'Chal raha hai' : 'Band' }}{{ $w->demo_mode ? ' (demo mode)' : '' }}</dd>
      <dt>Hostname</dt><dd>{{ $w->hostname ?: '—' }}</dd>
      <dt>IP</dt><dd class="mono">{{ $w->ip ?: '—' }} @if($w->last_ip && $w->last_ip !== $w->ip)<span class="muted">(public {{ $w->last_ip }})</span>@endif</dd>
      <dt>OS</dt><dd>{{ $w->os ?: '—' }}</dd>
      <dt>Uptime</dt><dd>{{ $w->uptime_s ? \Carbon\CarbonInterval::seconds($w->uptime_s)->cascade()->forHumans(['parts' => 2]) : '—' }}</dd>
      <dt>Settings</dt><dd>@if($inSync)<span class="tag green">Lag chuki hain</span>@else<span class="tag">Agle check-in par lagengi</span>@endif</dd>
    </dl>
  </div>

  <div class="card">
    <h2>Commands</h2>
    @if($w->status === 'approved')
      <p class="muted" style="margin-top:0">Command agle check-in par chalega (lagbhag {{ (int)ceil(config('workstations.check_interval')/60) }} min ke andar).</p>
      <div class="row">
        @foreach($cmds as $c)
          <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="{{ $cmdInfo[$c][1] ?? 'Command bhejein?' }}">
            @csrf<input type="hidden" name="action" value="command"><input type="hidden" name="command" value="{{ $c }}">
            <button class="btn">{{ $cmdInfo[$c][0] ?? $c }}</button>
          </form>
        @endforeach
      </div>
    @else
      <p class="muted">Commands bhejne ke liye pehle workstation approve karein.</p>
    @endif
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h2>Security</h2>
    <div class="row">
      <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="Token reset karein? Workstation ka purana token band ho jayega. Naya token tabhi milega jab wo valid install key ke saath check-in kare.">
        @csrf<input type="hidden" name="action" value="reset_token"><button class="btn">🔑 Reset token</button></form>
      <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}" data-confirm="{{ $w->workstation_id }} ko list se hata dein? Agar machine abhi bhi chal rahi hai aur enroll ho sakti hai to wo dobara pending mein aa jayegi.">
        @csrf<input type="hidden" name="action" value="delete"><button class="btn danger">🗑 Remove</button></form>
    </div>
  </div>
</div>

<div class="card">
  <h2>Details &amp; updates</h2>
  <form method="post" action="{{ route('wsm.update', $w->workstation_id) }}">
    @csrf<input type="hidden" name="action" value="save">
    <div class="grid g2">
      <label class="f"><span>Naam / label</span><input type="text" name="label" value="{{ old('label', $w->label) }}" placeholder="jaise: Gate 2 control room">
        @error('label')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f"><span>Warehouse code</span><input type="text" name="warehouse_code" value="{{ old('warehouse_code', $w->warehouse_code) }}">
        @error('warehouse_code')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f"><span>Kaunsa version chale</span>
        <select name="target_release_id">
          <option value="">Hamesha latest (recommended)</option>
          @foreach($releases as $r)<option value="{{ $r->id }}" @selected($w->target_release_id == $r->id)>📌 Pin v{{ $r->version }}{{ $r->is_latest ? ' (latest)' : '' }}</option>@endforeach
        </select></label>
      <label class="f" style="display:flex;gap:10px;align-items:center;margin-top:22px">
        <input type="checkbox" name="auto_update" value="1" @checked($w->auto_update) style="width:18px;height:18px">
        <span style="margin:0;color:var(--ink)">Auto-update on — naya version apne aap install ho</span></label>
    </div>
    <button class="btn primary">Save</button>
  </form>
</div>

<div class="card">
  <h2>History</h2>
  @if($timeline->isEmpty())
    <div class="empty"><b>Abhi koi history nahi</b>Workstation ke check-in karne par yahan updates dikhenge.</div>
  @else
    <ul class="timeline">
      @foreach($timeline as $t)
        <li class="{{ $t['tone'] }}">{{ $t['text'] }}<small title="{{ $t['at'] }}">{{ Timeline::pehle($t['at']) }} · {{ $t['at']?->format('d M, h:i A') }}</small></li>
      @endforeach
    </ul>
  @endif
</div>

@if(count($eff))
<div class="card">
  <h2>Is workstation ki settings</h2>
  <p class="muted" style="margin-top:0">Global → warehouse → workstation milakar. Badalne ke liye <a href="{{ route('wsm.env') }}">Settings</a> page dekhein.</p>
  <table><tbody>@foreach($eff as $k => $v)<tr><td class="mono">{{ $k }}</td><td class="mono">{{ $v }}</td></tr>@endforeach</tbody></table>
</div>
@endif
@endsection
