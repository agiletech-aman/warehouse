<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Workstations') · Workstation Manager</title>
<style>
:root{--bg:#f4f6f9;--card:#fff;--ink:#1d2433;--muted:#6b7385;--line:#e3e7ee;--brand:#2457d6;--brand-ink:#fff;
--good:#1f9d55;--good-bg:#e6f6ec;--bad:#d64545;--bad-bg:#fdecec;--warn:#b7791f;--warn-bg:#fdf4e3;--grey:#5b6474;--grey-bg:#eceff3;--r:10px}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
a{color:var(--brand);text-decoration:none}a:hover{text-decoration:underline}
header.top{background:#16213a;color:#fff;padding:0 20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap}
header.top .logo{font-weight:700;padding:14px 0;font-size:16px;color:#fff}
header.top nav{display:flex;gap:4px;flex-wrap:wrap;flex:1}
header.top nav a{color:#c8d1e6;padding:8px 12px;border-radius:8px}
header.top nav a.on,header.top nav a:hover{background:#26345a;color:#fff;text-decoration:none}
header.top form button{background:none;border:1px solid #3a4870;color:#c8d1e6;padding:6px 12px;border-radius:8px;cursor:pointer}
main{max-width:1180px;margin:0 auto;padding:24px 16px 60px}
h1{font-size:22px;margin:0 0 4px}h2{font-size:17px;margin:0 0 12px}.sub{color:var(--muted);margin:0 0 20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:18px;margin-bottom:16px}
.grid{display:grid;gap:16px}.g2{grid-template-columns:repeat(auto-fit,minmax(320px,1fr))}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px}
.stat{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:14px}
.stat b{display:block;font-size:24px}.stat span{color:var(--muted);font-size:13px}
.btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:#fff;color:var(--ink);padding:8px 14px;
border-radius:8px;cursor:pointer;font:inherit;font-weight:500;text-decoration:none!important;white-space:nowrap}
.btn:hover{background:#f0f3f8}.btn.primary{background:var(--brand);border-color:var(--brand);color:var(--brand-ink)}
.btn.primary:hover{filter:brightness(1.08)}.btn.danger{color:var(--bad);border-color:#f1c4c4}.btn.danger:hover{background:var(--bad-bg)}
.btn.good{background:var(--good);border-color:var(--good);color:#fff}.btn.sm{padding:5px 10px;font-size:13px}
.btn:disabled{opacity:.5;cursor:not-allowed}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.spacer{flex:1}
input[type=text],input[type=search],input[type=password],input[type=number],select,textarea{font:inherit;padding:8px 10px;border:1px solid #cfd6e2;
border-radius:8px;background:#fff;color:var(--ink);min-width:0}textarea{width:100%}
input:focus,select:focus,textarea:focus{outline:2px solid #bcd0ff;border-color:var(--brand)}
label.f{display:block;margin-bottom:12px}label.f>span{display:block;font-size:13px;color:var(--muted);margin-bottom:4px}
label.f input,label.f select,label.f textarea{width:100%}
.badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:3px 9px;border-radius:99px;white-space:nowrap}
.badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.b-online{background:var(--good-bg);color:var(--good)}.b-offline{background:var(--bad-bg);color:var(--bad)}
.b-pending{background:var(--warn-bg);color:var(--warn)}.b-blocked{background:var(--grey-bg);color:var(--grey)}
.tag{display:inline-block;font-size:11px;font-weight:600;padding:2px 7px;border-radius:5px;background:var(--warn-bg);color:var(--warn);text-transform:uppercase;letter-spacing:.03em}
.tag.grey{background:var(--grey-bg);color:var(--grey)}.tag.green{background:var(--good-bg);color:var(--good)}.tag.red{background:var(--bad-bg);color:var(--bad)}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-size:12px;text-transform:uppercase;color:var(--muted);font-weight:600;letter-spacing:.03em}
tr:last-child td{border-bottom:0}.muted{color:var(--muted)}.mono{font-family:ui-monospace,Consolas,monospace;font-size:13px}
.flash{padding:12px 16px;border-radius:var(--r);margin-bottom:16px;font-weight:500}
.flash.ok{background:var(--good-bg);color:#155f36;border:1px solid #bfe5cd}.flash.err{background:var(--bad-bg);color:#8f2424;border:1px solid #f3c6c6}
.banner{background:var(--warn-bg);border:1px solid #f1d9a6;border-radius:var(--r);padding:14px 16px;margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.empty{text-align:center;padding:40px 20px;color:var(--muted)}.empty b{display:block;color:var(--ink);font-size:17px;margin-bottom:6px}
.err-inline{color:var(--bad);font-size:13px;margin-top:4px}
.timeline{list-style:none;margin:0;padding:0}.timeline li{position:relative;padding:0 0 14px 22px;border-left:2px solid var(--line);margin-left:6px}
.timeline li::before{content:"";position:absolute;left:-7px;top:4px;width:12px;height:12px;border-radius:50%;background:#9aa6bb;border:2px solid #fff}
.timeline li.good::before{background:var(--good)}.timeline li.bad::before{background:var(--bad)}
.timeline li:last-child{border-color:transparent}.timeline small{display:block;color:var(--muted)}
.codebox{position:relative;background:#0f172a;color:#e2e8f0;border-radius:var(--r);padding:16px 110px 16px 16px;font-family:ui-monospace,Consolas,monospace;font-size:13px;word-break:break-all}
.codebox .btn{position:absolute;top:10px;right:10px}
.drop{border:2px dashed #c3cde0;border-radius:var(--r);padding:28px;text-align:center;color:var(--muted);cursor:pointer;transition:.15s}
.drop.over{border-color:var(--brand);background:#eef3ff;color:var(--brand)}.drop b{color:var(--ink)}
.kv{display:grid;grid-template-columns:140px 1fr;gap:6px 12px;font-size:14px}.kv dt{color:var(--muted)}.kv dd{margin:0;word-break:break-word}
.cards{display:none}
@media (max-width:760px){
  .tbl-wrap{display:none}.cards{display:grid;gap:10px}
  .wcard{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:14px;display:block;color:inherit}
  .wcard:hover{text-decoration:none;border-color:#b9c6de}
  .kv{grid-template-columns:110px 1fr}main{padding:16px 12px 40px}
}
</style>
@stack('head')
</head>
<body>
@if(session('wsm_admin'))
<header class="top">
  <a class="logo" href="{{ route('wsm.index') }}">🖥 Workstation Manager</a>
  <nav>
    <a href="{{ route('wsm.index') }}" class="{{ request()->routeIs('wsm.index','wsm.show') ? 'on' : '' }}">Workstations</a>
    <a href="{{ route('wsm.add') }}" class="{{ request()->routeIs('wsm.add') ? 'on' : '' }}">+ Add Workstation</a>
    <a href="{{ route('wsm.releases') }}" class="{{ request()->routeIs('wsm.releases') ? 'on' : '' }}">Releases</a>
    <a href="{{ route('wsm.env') }}" class="{{ request()->routeIs('wsm.env') ? 'on' : '' }}">Settings</a>
  </nav>
  <form method="post" action="{{ route('wsm.logout') }}">@csrf<button>Logout</button></form>
</header>
@endif
<main>
  @if(session('ok'))<div class="flash ok" role="status">✓ {{ session('ok') }}</div>@endif
  @if(session('error'))<div class="flash err" role="alert">✕ {{ session('error') }}</div>@endif
  @yield('content')
</main>
<script>
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm') || (e.submitter && e.submitter.getAttribute('data-confirm'));
  if (msg && !confirm(msg)) e.preventDefault();
});
</script>
@stack('scripts')
</body>
</html>
