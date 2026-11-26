@extends('workstations::layout')
@section('title', 'Login')
@section('content')
<div style="max-width:380px;margin:60px auto">
  <div class="card">
    <h1>Workstation Manager</h1>
    <p class="sub">Log in to manage your workstations.</p>
    @unless($configured)
      <div class="flash err">No admin password is set. Add <span class="mono">WSM_ADMIN_PASS</span> to the server's <span class="mono">.env</span>.</div>
    @endunless
    @error('login')<div class="flash err">{{ $message }}</div>@enderror
    <form method="post" action="{{ route('wsm.login') }}">
      @csrf
      <label class="f"><span>Username</span><input type="text" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></label>
      <label class="f"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn primary" style="width:100%;justify-content:center" @disabled(!$configured)>Log in</button>
    </form>
  </div>
</div>
@endsection
