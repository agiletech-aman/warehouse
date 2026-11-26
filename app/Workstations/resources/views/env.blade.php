@extends('workstations::layout')
@section('title', 'Settings')
@php $errRow = $errors->first('row'); @endphp
@section('content')
<h1>Settings (.env)</h1>
<p class="sub">Yahan set ki gayi values workstations ki <span class="mono">.env</span> mein apne aap pahunch jaati hain. Order: <b>Global</b> → <b>Warehouse</b> → <b>Workstation</b> (baad wali jeet-ti hai).</p>

<div class="card">
  <form class="row" method="get">
    <a class="btn {{ $scope === 'global' ? 'primary' : '' }}" href="{{ route('wsm.env') }}">🌐 Global (sab ke liye)</a>
    <span class="muted">ya warehouse:</span>
    <input type="hidden" name="scope" value="warehouse">
    <select name="ref" onchange="this.form.submit()">
      <option value="">— warehouse chunein —</option>
      @foreach($codes as $c)<option value="{{ $c }}" @selected($scope === 'warehouse' && $ref === $c)>{{ $c }}</option>@endforeach
    </select>
  </form>
</div>

<div class="card">
  @if($scope === 'warehouse' && $ref === '')
    <div class="empty"><b>Warehouse chunein</b>Upar se warehouse select karein. @if($codes->isEmpty()) Abhi kisi workstation ka warehouse set nahi hai. @endif</div>
  @else
    <h2>{{ $scope === 'global' ? 'Global settings' : "Warehouse $ref ki settings" }}</h2>
    <div style="overflow-x:auto">
    <table>
      <thead><tr><th style="width:35%">Key</th><th>Value</th><th style="width:1%"></th></tr></thead>
      <tbody>
      @forelse($entries as $e)
        <tr>
          <td class="mono">{{ $e->key }}
            @if(in_array($e->key, $ints))<span class="tag grey">number</span>@elseif(in_array($e->key, $floats))<span class="tag grey">decimal</span>@endif</td>
          <td>
            <form method="post" action="{{ route('wsm.env.save') }}" class="row" style="flex-wrap:nowrap">
              @csrf<input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="ref" value="{{ $ref }}">
              <input type="hidden" name="key" value="{{ $e->key }}"><input type="hidden" name="row" value="{{ $e->key }}">
              <input type="text" name="value" class="mono" style="flex:1"
                value="{{ $errRow === $e->key ? old('value') : $e->value }}">
              <button class="btn sm">Save</button>
            </form>
            @if($errRow === $e->key)<div class="err-inline">{{ $errors->first('value') }}</div>@endif
          </td>
          <td><form method="post" action="{{ route('wsm.env.save') }}" data-confirm="{{ $e->key }} hata dein?">
            @csrf<input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="ref" value="{{ $ref }}">
            <input type="hidden" name="key" value="{{ $e->key }}"><input type="hidden" name="action" value="delete">
            <button class="btn sm danger" title="Hatao">✕</button></form></td>
        </tr>
      @empty
        <tr><td colspan="3"><div class="empty" style="padding:20px"><b>Koi setting nahi</b>Neeche se pehli key add karein, jaise <span class="mono">POLL_INTERVAL = 30</span>.</div></td></tr>
      @endforelse
      </tbody>
    </table>
    </div>

    <h2 style="margin-top:20px">Nayi setting</h2>
    <form method="post" action="{{ route('wsm.env.save') }}" class="row">
      @csrf<input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="ref" value="{{ $ref }}"><input type="hidden" name="row" value="new">
      <input type="text" name="key" list="known" class="mono" placeholder="KEY_NAME" value="{{ $errRow === 'new' ? old('key') : '' }}" style="text-transform:uppercase;flex:1;min-width:180px" required>
      <input type="text" name="value" class="mono" placeholder="value" value="{{ $errRow === 'new' ? old('value') : '' }}" style="flex:2;min-width:180px">
      <button class="btn primary">+ Add</button>
    </form>
    @if($errRow === 'new')<div class="err-inline">{{ $errors->first('value') }}</div>@endif
    <datalist id="known">@foreach(array_merge($ints, $floats) as $k)<option value="{{ $k }}">@endforeach</datalist>

    <details style="margin-top:20px">
      <summary class="muted" style="cursor:pointer">🔒 Locked keys (cloud se nahi badal sakte)</summary>
      <div class="row" style="margin-top:10px">
        @foreach($locked as $k)<input type="text" class="mono" value="{{ $k }}" disabled style="width:auto;opacity:.6" title="Ye key sirf workstation par set hoti hai">@endforeach
      </div>
    </details>
  @endif
</div>
@endsection
