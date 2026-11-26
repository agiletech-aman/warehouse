@extends('workstations::layout')
@section('title', 'Add Workstation')
@section('content')
<h1>Naya workstation jodein</h1>
<p class="sub">3 aasaan steps: warehouse chunein → command copy karein → workstation par chalayein.</p>

@if(!$keyReady || !$latest)
  <div class="flash err">
    Pehle ye kaam karein:
    @unless($keyReady) <span class="mono">.env</span> mein <span class="mono">WSM_PUBLIC_KEY</span> set karein. @endunless
    @unless($latest) <a href="{{ route('wsm.releases') }}">Ek signed release upload karke latest banayein</a>. @endunless
  </div>
@endif

@if($command)
  <div class="card" style="border-color:#bfe5cd">
    <h2>✅ Step 2: Ye command copy karein</h2>
    <p class="muted" style="margin-top:0">Warehouse <b>{{ $forCode }}</b> · {{ $uses }} workstation ke liye · <b>{{ $expires }}</b> tak valid. Ye command sirf ek baar dikhega.</p>
    <div class="codebox"><span id="cmd">{{ $command }}</span><button class="btn sm" type="button" id="copy">📋 Copy</button></div>
    <h2 style="margin-top:20px">Step 3: Workstation par chalayein</h2>
    <ol style="margin:0;padding-left:20px;line-height:1.9">
      <li>Workstation (Linux) par terminal kholen ya SSH karein.</li>
      <li>Upar wala command paste karke Enter dabayein. Password maange to sudo password daalein.</li>
      <li>"GasMonitor chal raha hai" dikhe to yahan <a href="{{ route('wsm.index') }}">Workstations</a> page par jaayein.</li>
      <li>Naya workstation <span class="badge b-pending">Pending</span> mein dikhega — <b>Approve</b> dabayein. Bas!</li>
    </ol>
  </div>
@endif

<div class="card">
  <h2>{{ $command ? 'Ek aur command banayein' : 'Step 1: Warehouse chunein' }}</h2>
  <form method="post" action="{{ route('wsm.add.create') }}">
    @csrf
    <div class="grid g2">
      <label class="f"><span>Warehouse</span>
        @if($codes->count())
          <select name="warehouse_code" required>
            <option value="">— chunein —</option>
            @foreach($codes as $code => $name)<option value="{{ $code }}" @selected(old('warehouse_code') === $code)>{{ $code }}{{ $name !== $code ? " — $name" : '' }}</option>@endforeach
          </select>
        @else
          <input type="text" name="warehouse_code" value="{{ old('warehouse_code') }}" placeholder="jaise WH-DEL-01" required>
        @endif
        @error('warehouse_code')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f"><span>Kitne workstations is command se judenge?</span>
        <input type="number" name="uses" value="{{ old('uses', 1) }}" min="1" max="50">
        @error('uses')<div class="err-inline">{{ $message }}</div>@enderror</label>
    </div>
    <button class="btn primary" @disabled(!$keyReady || !$latest)>Install command banao →</button>
    <p class="muted" style="font-size:13px;margin-bottom:0">🔒 Command mein ek-baar ka token hota hai jo {{ (int)round(config('workstations.install_token_ttl')/60) }} ghante mein expire ho jaata hai. Ise kisi ke saath share na karein.</p>
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
