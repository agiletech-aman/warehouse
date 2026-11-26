@extends('workstations::layout')
@section('title', 'Login')
@section('content')
<div style="max-width:380px;margin:60px auto">
  <div class="card">
    <h1>Workstation Manager</h1>
    <p class="sub">Login karke workstations manage karein.</p>
    @unless($configured)
      <div class="flash err">Admin password set nahi hai. Server ki <span class="mono">.env</span> mein <span class="mono">WSM_ADMIN_PASS</span> daalein.</div>
    @endunless
    @error('login')<div class="flash err">{{ $message }}</div>@enderror
    <form method="post" action="{{ route('wsm.login') }}">
      @csrf
      <label class="f"><span>Username</span><input type="text" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></label>
      <label class="f"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn primary" style="width:100%;justify-content:center" @disabled(!$configured)>Login</button>
    </form>
  </div>
</div>
@endsection
