@extends('layouts.app')

@section('title','Dashboard')

@section('page-title','Dashboard')

@section('content')

<div class="content-shell">
    <div class="card border-0 shadow-sm p-4 mb-4">
        <h2 class="mb-1">Welcome {{ session('admin_name') }}</h2>
        <p class="text-muted mb-0">Here is a quick overview of your warehouse system.</p>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="text-muted small text-uppercase">Total Warehouses</div>
                <div class="display-6 fw-bold mt-2">{{ $totalWarehouses }}</div>
                <p class="text-muted mb-0">Current warehouse count in the system.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="text-muted small text-uppercase">Active Warehouses</div>
                <div class="display-6 fw-bold mt-2">{{ $activeWarehouses }}</div>
                <p class="text-muted mb-0">Currently visible in normal warehouse queries.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="text-muted small text-uppercase">Regions</div>
                <div class="display-6 fw-bold mt-2">{{ $totalRegions }}</div>
                <p class="text-muted mb-0">Registered warehouse regions in the system.</p>
            </div>
        </div>
    </div>
</div>

@endsection