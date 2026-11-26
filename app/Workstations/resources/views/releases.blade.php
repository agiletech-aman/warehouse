@extends('workstations::layout')
@section('title', 'Releases')
@section('content')
<h1>Releases</h1>
<p class="sub">Upload a new GasMonitor version. Every release must be signed with Ed25519 — workstations will never install an unsigned one.</p>

@unless($keyReady)
  <div class="flash err">No signing key is set. Add <span class="mono">WSM_PUBLIC_KEY</span> to the server's <span class="mono">.env</span> (see the README). Uploads are disabled until then.</div>
@endunless

<div class="card">
  <h2>Upload a new release</h2>
  <form method="post" action="{{ route('wsm.releases.store') }}" enctype="multipart/form-data" id="up">
    @csrf
    <label class="drop" id="drop" for="file">
      <div id="dropText"><b>Drag &amp; drop the file here</b><br>or click to choose (binary or .tar.gz)</div>
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
      <label class="f" style="grid-column:1/-1"><span>Signature (base64) — signed over <span class="mono">gasmonitor|VERSION|SHA256</span></span>
        <input type="text" name="signature" value="{{ old('signature') }}" class="mono" required>
        @error('signature')<div class="err-inline">{{ $message }}</div>@enderror</label>
      <label class="f" id="postWrap"><span>After update (package only)</span>
        <select name="post_update">
          <option value="">Nothing</option>
          @foreach($actions as $a)<option value="{{ $a }}" @selected(old('post_update') === $a)>{{ str_replace('_', ' ', $a) }}</option>@endforeach
        </select></label>
      <label class="f"><span>Notes (optional)</span><input type="text" name="notes" value="{{ old('notes') }}" placeholder="What changed?"></label>
    </div>
    <div class="row">
      <label class="row" style="gap:8px"><input type="checkbox" name="make_latest" value="1" checked> Set as <b>latest</b> after upload</label>
      <div class="spacer"></div>
      <button class="btn primary" @disabled(!$keyReady)>⬆ Upload</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>All releases</h2>
  @if($releases->isEmpty())
    <div class="empty"><b>No releases yet</b>Upload the first signed release above. New workstations can only be installed after that.</div>
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
          <td><b>{{ $counts[$r->version] ?? 0 }}</b> <span class="muted">on this version</span></td>
          <td>{{ number_format($r->size / 1048576, 1) }} MB</td>
          <td class="muted">{{ $r->created_at?->format('d M Y') }}</td>
          <td><div class="row" style="justify-content:flex-end">
            @if(!$r->is_latest && !$r->yanked)
              <form method="post" action="{{ route('wsm.releases.action', $r) }}" data-confirm="Set v{{ $r->version }} as latest? Every workstation with auto-update on will install it.">
                @csrf<input type="hidden" name="action" value="latest"><button class="btn sm">Set as latest</button></form>
            @endif
            @if($r->yanked)
              <form method="post" action="{{ route('wsm.releases.action', $r) }}">@csrf<input type="hidden" name="action" value="unyank"><button class="btn sm">Unyank</button></form>
            @else
              <form method="post" action="{{ route('wsm.releases.action', $r) }}" data-confirm="Yank v{{ $r->version }}? No workstation will install it.{{ $r->is_latest ? ' It is the latest — you will need to set another release as latest.' : '' }}">
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
  function show() { if (file.files.length) { var f = file.files[0]; txt.innerHTML = '<b>' + f.name.replace(/</g, '&lt;') + '</b><br>' + (f.size / 1048576).toFixed(1) + ' MB — click to change';
    if (/\.(tar\.gz|tgz)$/i.test(f.name)) { type.value = 'package'; toggle(); } } }
  function toggle() { post.style.display = type.value === 'package' ? '' : 'none'; }
  ['dragenter', 'dragover'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (ev) { if (ev.dataTransfer.files.length) { file.files = ev.dataTransfer.files; show(); } });
  file.addEventListener('change', show); type.addEventListener('change', toggle); toggle();
})();
</script>
@endpush
