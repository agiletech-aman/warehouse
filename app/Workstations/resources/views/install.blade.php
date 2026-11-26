@extends('workstations::layout')
@section('title', 'Add Workstation')
@section('content')
<h1>Add a new workstation</h1>
<p class="sub">3 simple steps: choose a warehouse → copy the command → run it on the workstation.</p>

@if(!$keyReady || !$latest)
  <div class="flash err">
    Do this first:
    @unless($keyReady) Set <span class="mono">WSM_PUBLIC_KEY</span> in <span class="mono">.env</span>. @endunless
    @unless($latest) <a href="{{ route('wsm.releases') }}">Upload a signed release and set it as latest</a>. @endunless
  </div>
@endif

@if($command)
  <div class="card" style="border-color:#bfe5cd">
    <h2>✅ Step 2: Copy this command</h2>
    <p class="muted" style="margin-top:0">Warehouse <b>{{ $forCode }}</b> · for {{ $uses }} workstation(s) · valid until <b>{{ $expires }}</b>. This command is shown only once.</p>
    <div class="codebox"><span id="cmd">{{ $command }}</span><button class="btn sm" type="button" id="copy">📋 Copy</button></div>
    <h2 style="margin-top:20px">Step 3: Run it on the workstation</h2>
    <ol style="margin:0;padding-left:20px;line-height:1.9">
      <li>Open a terminal on the workstation (Linux) or connect over SSH.</li>
      <li>Paste the command above and press Enter. Enter the sudo password if asked.</li>
      <li>When you see "GasMonitor is running", go to the <a href="{{ route('wsm.index') }}">Workstations</a> page.</li>
      <li>The new workstation appears as <span class="badge b-pending">Pending</span> — click <b>Approve</b>. Done!</li>
    </ol>
  </div>
@endif

<div class="card">
  <h2>{{ $command ? 'Create another command' : 'Step 1: Choose a warehouse' }}</h2>
  <form method="post" action="{{ route('wsm.add.create') }}">
    @csrf
    <div class="grid g2">
      <label class="f"><span>Warehouse</span>
        @if($codes->count())
          <select name="warehouse_code" required>
            <option value="">— choose —</option>
            @foreach($codes as $code => $name)<option value="{{ $code }}" @selected(old('warehouse_code') === $code)>{{ $code }}{{ $name !== $code ? " — $name" : '' }}</option>@endforeach
          </select>
        @else
          <input type="text" name="warehouse_code" value="{{ old('warehouse_code') }}" placeholder="e.g. WH-DEL-01" required>
        @endif
        @error('warehouse_code')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f"><span>How many workstations will use this command?</span>
        <input type="number" name="uses" value="{{ old('uses', 1) }}" min="1" max="50">
        @error('uses')<div class="err-inline">{{ $message }}</div>@enderror</label>
    </div>
    <button class="btn primary" @disabled(!$keyReady || !$latest)>Create install command →</button>
    <p class="muted" style="font-size:13px;margin-bottom:0">🔒 The command contains a one-time token that expires in {{ (int)round(config('workstations.install_token_ttl')/60) }} hours. Do not share it.</p>
  </form>
</div>
@endsection
@push('scripts')
<script>
(function () {
  var b = document.getElementById('copy'); if (!b) return;
  b.addEventListener('click', function () {
    var t = document.getElementById('cmd').textContent;
    var done = function () { b.textContent = '✓ Copied'; setTimeout(function () { b.textContent = '📋 Copy'; }, 2000); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(done); }
    else { var r = document.createRange(); r.selectNodeContents(document.getElementById('cmd'));
      var s = getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy'); done(); }
  });
})();
</script>
@endpush
