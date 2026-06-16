@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'Update your admin password securely.')

@section('content')

<div class="content-shell">

    @if(session('success'))
    <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger rounded-3 shadow-sm">{{ session('error') }}</div>
    @endif

    <div class="row g-4">

        {{-- Change Password --}}
        <div class="col-md-6" style="width:30%;">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h3 class="mb-1">Change Password</h3>
                <p class="text-muted mb-4">Enter your current password and a new password to update your account.</p>

                <form action="{{ route('admin.change-password') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Old Password</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                        @error('new_password')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
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

        {{-- Alert Routing Card --}}
        <div class="col-md-6" style="width:70%;">
            <div class="card border-0 shadow-sm p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="mb-1">Alert Routing</h3>
                        <p class="text-muted">Email & WhatsApp notification settings</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary rounded-pill px-3"
                        data-bs-toggle="modal" data-bs-target="#emailRoutingModal">
                        <i class="bi bi-gear"></i> Configure
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle text-center mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Level</th>
                                <th>Warehouse Mail</th>
                                <th>Warehouse WA</th>
                                <th>Regional Mail</th>
                                <th>Regional WA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['co2' => 'CO2', 'ph3' => 'PH3'] as $type => $label)
                            @foreach(['normal' => 'Normal', 'severe' => 'Severe', 'critical' => 'Critical'] as $level => $levelLabel)
                            @php
                            $key = $type . '_' . $level;
                            $row = $routing[$key] ?? null;
                            @endphp
                            <tr>
                                <td>
                                    <span class="badge {{ $type === 'co2' ? 'bg-info' : 'bg-warning text-dark' }}">
                                        {{ $label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $level === 'normal' ? 'bg-success' : ($level === 'severe' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ $levelLabel }}
                                    </span>
                                </td>
                                <td>
                                    @if($row?->warehouse_mail) <span class="text-success fw-bold">✓</span>
                                    @else <span class="text-muted">—</span> @endif
                                </td>
                                <td>
                                    @if($row?->warehouse_whatsapp) <span class="text-success fw-bold">✓</span>
                                    @else <span class="text-muted">—</span> @endif
                                </td>
                                <td>
                                    @if($row?->regional_mail) <span class="text-success fw-bold">✓</span>
                                    @else <span class="text-muted">—</span> @endif
                                </td>
                                <td>
                                    @if($row?->regional_whatsapp) <span class="text-success fw-bold">✓</span>
                                    @else <span class="text-muted">—</span> @endif
                                </td>
                            </tr>
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div> <!-- End Row -->

    {{-- Alert CC Emails --}}
    <div class="card border-0 shadow-sm p-4 mt-4">
        <h3 class="mb-1">Alert CC Emails</h3>
        <p class="text-muted mb-3">These email addresses will receive a copy (CC) of every alert email when enabled.</p>

        {{-- Add new CC email --}}
        <form action="{{ route('settings.cc-emails.store') }}" method="POST" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-sm-6">
                <label class="form-label mb-1">New CC Email</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
            </div>
            <div class="col-sm-3">
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" name="status" value="1" id="newCcStatus" checked>
                    <label class="form-check-label" for="newCcStatus">Active</label>
                </div>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary rounded-pill px-4 w-100">Add</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle text-center mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($ccEmails ?? []) as $cc)
                    <tr>
                        <td class="text-start">{{ $cc->email }}</td>
                        <td>
                            <form action="{{ route('settings.cc-emails.toggle', $cc->id) }}" method="POST" class="d-inline">
                                @csrf
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input" type="checkbox" onchange="this.form.submit()"
                                        {{ $cc->status ? 'checked' : '' }}>
                                </div>
                            </form>
                            <span class="badge {{ $cc->status ? 'bg-success' : 'bg-secondary' }}">
                                {{ $cc->status ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3"
                                    onclick="openEditCcModal('{{ $cc->id }}', '{{ $cc->email }}')">
                                    Edit
                                </button>

                                <form action="{{ route('settings.cc-emails.destroy', $cc->id) }}" method="POST"
                                    onsubmit="return confirm('Delete this CC email?');" class="mb-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-muted">No CC emails added yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Email Routing Modal --}}
{{-- Email Routing Modal --}}
<div class="modal fade" id="emailRoutingModal" tabindex="-1" aria-labelledby="emailRoutingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div id="validationError" class="alert alert-danger d-none"></div>
            <form method="POST" action="{{ route('settings.email-routing.update') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h4 class="modal-title fw-semibold" id="emailRoutingModalLabel">
                        Alert Routing Settings
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-4">

                        <!-- CO2 Section -->
                        <div class="col-md-6">
                            <div class="text-center mb-3">
                                <span class="badge bg-info fs-5 px-4 py-2">CO2</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Level</th>
                                            <th>Warehouse Mail</th>
                                            <th>Warehouse WA</th>
                                            <th>Regional Mail</th>
                                            <th>Regional WA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(['normal' => 'Normal', 'severe' => 'Severe', 'critical' => 'Critical'] as $level => $levelLabel)
                                        @php
                                        $key = 'co2_' . $level;
                                        $row = $routing[$key] ?? null;
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="badge fs-6 {{ 
                                                        $level === 'normal' ? 'bg-success' : 
                                                        ($level === 'severe' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                                    {{ $levelLabel }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="warehouse_mail_{{ $key }}"
                                                        {{ $row?->warehouse_mail ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="warehouse_whatsapp_{{ $key }}"
                                                        {{ $row?->warehouse_whatsapp ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="regional_mail_{{ $key }}"
                                                        {{ $row?->regional_mail ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="regional_whatsapp_{{ $key }}"
                                                        {{ $row?->regional_whatsapp ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- PH3 Section -->
                        <div class="col-md-6">
                            <div class="text-center mb-3">
                                <span class="badge bg-warning text-dark fs-5 px-4 py-2">PH3</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Level</th>
                                            <th>Warehouse Mail</th>
                                            <th>Warehouse WA</th>
                                            <th>Regional Mail</th>
                                            <th>Regional WA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(['normal' => 'Normal', 'severe' => 'Severe', 'critical' => 'Critical'] as $level => $levelLabel)
                                        @php
                                        $key = 'ph3_' . $level;
                                        $row = $routing[$key] ?? null;
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="badge fs-6 {{ 
                                                        $level === 'normal' ? 'bg-success' : 
                                                        ($level === 'severe' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                                    {{ $levelLabel }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="warehouse_mail_{{ $key }}"
                                                        {{ $row?->warehouse_mail ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="warehouse_whatsapp_{{ $key }}"
                                                        {{ $row?->warehouse_whatsapp ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="regional_mail_{{ $key }}"
                                                        {{ $row?->regional_mail ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="regional_whatsapp_{{ $key }}"
                                                        {{ $row?->regional_whatsapp ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit CC Email Modal --}}
<div class="modal fade" id="editCcEmailModal" tabindex="-1" aria-labelledby="editCcEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="editCcEmailForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editCcEmailModalLabel">Edit CC Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="editCcEmailInput" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelector('#emailRoutingModal form')
        .addEventListener('submit', function(e) {

            let invalid = false;
            let message = '';

            ['co2', 'ph3'].forEach(type => {

                ['normal', 'severe', 'critical'].forEach(level => {

                    let key = `${type}_${level}`;

                    let warehouseMail =
                        document.querySelector(`[name="warehouse_mail_${key}"]`).checked;

                    let regionalMail =
                        document.querySelector(`[name="regional_mail_${key}"]`).checked;

                    if (regionalMail && !warehouseMail) {

                        invalid = true;

                        message =
                            `${type.toUpperCase()} ${level.toUpperCase()}: Regional Mail requires Warehouse Mail to be enabled.`;
                    }
                });

            });

            if (invalid) {
                e.preventDefault();

                const errorBox = document.getElementById('validationError');
                errorBox.innerText = message;
                errorBox.classList.remove('d-none');
            }

        });
</script>

<script>
    function openEditCcModal(id, email) {
        const form = document.getElementById('editCcEmailForm');
        form.action = "{{ route('settings.cc-emails.update', ['id' => '__ID__']) }}".replace('__ID__', id);
        document.getElementById('editCcEmailInput').value = email;

        const modal = new bootstrap.Modal(document.getElementById('editCcEmailModal'));
        modal.show();
    }
</script>

@endsection