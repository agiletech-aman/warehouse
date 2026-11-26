@extends('workstations::layout')
@section('title', 'Releases')
@section('content')
<h1>Releases</h1>
<p class="sub">Naya GasMonitor version upload karein. Har release Ed25519 se signed honi chahiye — bina signature ke koi workstation use install nahi karega.</p>

@unless($keyReady)
  <div class="flash err">Signing key set nahi hai. Server ki <span class="mono">.env</span> mein <span class="mono">WSM_PUBLIC_KEY</span> daalein (README dekhein). Tab tak upload band hai.</div>
@endunless

<div class="card">
  <h2>Naya release upload</h2>
  <form method="post" action="{{ route('wsm.releases.store') }}" enctype="multipart/form-data" id="up">
    @csrf
    <label class="drop" id="drop" for="file">
      <div id="dropText"><b>File yahan drag &amp; drop karein</b><br>ya click karke chunein (binary ya .tar.gz)</div>
      <input type="file" name="binary" id="file" hidden required>
    </label>
    @error('binary')<div class="err-inline" style="font-size:14px;margin-top:8px">{{ $message }}</div>@enderror
    <div class="grid g2" style="margin-top:14px">
      <label class="f"><span>Version</span><input type="text" name="version" value="{{ old('version') }}" placeholder="1.2.0" required pattern="\d+\.\d+\.\d+(-[A-Za-z0-9.]+)?">
        @error('version')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f"><span>Type</span>
        <select name="type" id="type">
          <option value="binary" @selected(old('type') !== 'package')>Binary (gasmonitor)</option>
          <option value="package" @selected(old('type') === 'package')>Package (.tar.gz)</option>
        </select></label>
      <label class="f" style="grid-column:1/-1"><span>Signature (base64) — <span class="mono">gasmonitor|VERSION|SHA256</span> par sign kiya hua</span>
        <input type="text" name="signature" value="{{ old('signature') }}" class="mono" required>
        @error('signature')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f" id="postWrap"><span>Update ke baad (sirf package)</span>
        <select name="post_update">
          <option value="">Kuch nahi</option>
          @foreach($actions as $a)<option value="{{ $a }}" @selected(old('post_update') === $a)>{{ str_replace('_', ' ', $a) }}</option>@endforeach
        </select></label>
      <label class="f"><span>Notes (optional)</span><input type="text" name="notes" value="{{ old('notes') }}" placeholder="Kya badla?"></label>
    </div>
    <div class="row">
      <label class="row" style="gap:8px"><input type="checkbox" name="make_latest" value="1" checked> Upload ke baad <b>latest</b> bana do</label>
      <div class="spacer"></div>
      <button class="btn primary" @disabled(!$keyReady)>⬆ Upload</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Saare releases</h2>
  @if($releases->isEmpty())
    <div class="empty"><b>Abhi koi release nahi</b>Upar se pehla signed release upload karein. Uske baad hi naye workstation install ho payenge.</div>
  @else
  <div style="overflow-x:auto">
    <table>
      <thead><tr><th>Version</th><th>Type</th><th>Workstations</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
      <tbody>
      @foreach($releases as $r)
        <tr>
          <td><b class="mono">v{{ $r->version }}</b>
            @if($r->is_latest) <span class="tag green">Latest</span>@endif
            @if($r->yanked) <span class="tag red">Yanked</span>@endif
            @if($r->notes)<div class="muted" style="font-size:13px">{{ $r->notes }}</div>@endif
            <div class="muted mono" style="font-size:11px" title="sha256">{{ substr($r->sha256, 0, 16) }}…</div></td>
          <td>{{ $r->type === 'package' ? 'Package' : 'Binary' }}@if($r->post_update)<div class="muted" style="font-size:12px">+ {{ str_replace('_',' ',$r->post_update) }}</div>@endif</td>
          <td><b>{{ $counts[$r->version] ?? 0 }}</b> <span class="muted">is version par</span></td>
          <td>{{ number_format($r->size / 1048576, 1) }} MB</td>
          <td class="muted">{{ $r->created_at?->format('d M Y') }}</td>
          <td><div class="row" style="justify-content:flex-end">
            @if(!$r->is_latest && !$r->yanked)
              <form method="post" action="{{ route('wsm.releases.action', $r) }}" data-confirm="v{{ $r->version }} ko latest banayein? Auto-update wale saare workstations ise install karenge.">
                @csrf<input type="hidden" name="action" value="latest"><button class="btn sm">Set as latest</button></form>
            @endif
            @if($r->yanked)
              <form method="post" action="{{ route('wsm.releases.action', $r) }}">@csrf<input type="hidden" name="action" value="unyank"><button class="btn sm">Unyank</button></form>
            @else
              <form method="post" action="{{ route('wsm.releases.action', $r) }}" data-confirm="v{{ $r->version }} ko yank karein? Koi naya workstation ise install nahi karega.{{ $r->is_latest ? ' Ye latest hai — koi dusra release latest banana padega.' : '' }}">
                @csrf<input type="hidden" name="action" value="yank"><button class="btn sm danger">Yank</button></form>
            @endif
          </div></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>
@endsection
@push('scripts')
<script>
(function () {
  var drop = document.getElementById('drop'), file = document.getElementById('file'), txt = document.getElementById('dropText');
  var type = document.getElementById('type'), post = document.getElementById('postWrap');
  function show() { if (file.files.length) { var f = file.files[0]; txt.innerHTML = '<b>' + f.name.replace(/</g, '&lt;') + '</b><br>' + (f.size / 1048576).toFixed(1) + ' MB — badalne ke liye click karein';
    if (/\.(tar\.gz|tgz)$/i.test(f.name)) { type.value = 'package'; toggle(); } } }
  function toggle() { post.style.display = type.value === 'package' ? '' : 'none'; }
  ['dragenter', 'dragover'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (ev) { if (ev.dataTransfer.files.length) { file.files = ev.dataTransfer.files; show(); } });
  file.addEventListener('change', show); type.addEventListener('change', toggle); toggle();
})();
</script>
@endpush
