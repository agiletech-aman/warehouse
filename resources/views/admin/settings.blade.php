@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'Update your admin password securely.')

@section('content')

<div class="content-shell">
    <div class="card border-0 shadow-sm p-4" style="max-width: 640px;">
        <h3 class="mb-1">Change Password</h3>
        <p class="text-muted mb-4">Enter your current password and a new password to update your account.</p>

        @if(session('success'))
            <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger rounded-3 shadow-sm">{{ session('error') }}</div>
        @endif

        <form action="{{ route('admin.change-password') }}" method="POST" class="row g-3">
            @csrf

            <div class="col-12">
                <label class="form-label">Old Password</label>
                <input type="password" name="old_password" class="form-control" required>
            </div>

            <div class="col-12">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
                @error('new_password')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="new_password_confirmation" class="form-control" required>
            </div>

            <div class="col-12 mt-2">
                <button class="btn btn-primary rounded-pill px-4">Update Password</button>
            </div>
        </form>
    </div>
</div>

@endsection
