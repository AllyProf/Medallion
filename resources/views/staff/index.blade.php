@extends('layouts.dashboard')

@section('title', 'Staff Management')

@push('styles')
<style>
  .badge-pill { border-radius: 50px; }
  .table-hover tbody tr:hover { 
    background-color: rgba(148, 0, 0, 0.05); 
    transition: background 0.3s ease;
  }
  .align-middle td { vertical-align: middle !important; }
  .search-box .form-control:focus {
    border-color: #940000;
    box-shadow: 0 0 0 0.2rem rgba(148, 0, 0, 0.25);
  }
  .staff-table-wrap { overflow: visible; }
  .staff-actions .dropdown-menu { min-width: 190px; }
  .staff-actions .dropdown-item { font-size: 0.9rem; }
  .staff-actions .dropdown-item i { width: 18px; }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-users"></i> Staff Management</h1>
    <!-- DEBUG INFO -->
    @if(auth()->user()?->role === 'admin')
      <div class="alert alert-info py-1 px-3" style="font-size: 0.85rem;">
        <i class="fa fa-info-circle"></i> [Admin Debug] Total Staff in DB: <strong>{{ \App\Models\Staff::count() }}</strong> | Variable Count: <strong>{{ count($staff) }}</strong>
      </div>
    @endif
    <p>
      @if(session('active_location'))
        Viewing staff for branch: <strong>{{ session('active_location') }}</strong>
      @else
        Manage all staff members
      @endif
    </p>
  </div>
  <div>
    @if(session('active_location'))
      <a href="javascript:void(0)" onclick="switchLocation('all')" class="btn btn-secondary mr-2">
        <i class="fa fa-globe"></i> Show All Branches
      </a>
    @endif
    <a href="{{ route('staff.create') }}" class="btn btn-primary shadow-sm">
      <i class="fa fa-plus"></i> Register New Staff
    </a>
  </div>
</div>
<!-- Statistics Cards -->
<div class="row">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-users fa-3x"></i>
      <div class="info">
        <h4>Total Staff</h4>
        <p><b>{{ $stats['total'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-check-circle fa-3x"></i>
      <div class="info">
        <h4>Active Staff</h4>
        <p><b>{{ $stats['active'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Payroll (MTD)</h4>
        <p><b>{{ number_format($stats['total_salary'], 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-map-marker fa-3x"></i>
      <div class="info">
        <h4>Branches</h4>
        <p><b>{{ $stats['branches'] }}</b></p>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-md-12">
    <div class="tile">
      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="fa fa-check-circle"></i> {{ session('success') }}
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="fa fa-exclamation-triangle"></i> {{ session('error') }}
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      @endif
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="tile-title mb-0">Staff List</h3>
        <div class="search-box">
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text bg-white border-right-0"><i class="fa fa-search text-muted"></i></span>
            </div>
            <input type="text" id="staffSearch" class="form-control border-left-0" placeholder="Search staff or PIN..." style="width: 250px;">
          </div>
        </div>
      </div>
      @php
        $managerSlugs = ['manager', 'super-admin', 'superadmin', 'super_admin'];
        $managerNames = ['manager', 'super admin', 'super administrator', 'super_admin', 'superadmin'];
        $canImpersonateStaff = (bool) (auth()->user()?->isAdmin());
        if (!$canImpersonateStaff && session('is_staff') && session('staff_id')) {
            $actingStaff = \App\Models\Staff::with('role')->find(session('staff_id'));
            $actingName = strtolower(trim($actingStaff?->role?->name ?? ''));
            $actingSlug = strtolower(trim($actingStaff?->role?->slug ?? session('staff_role_slug')));
            $canImpersonateStaff = in_array($actingName, $managerNames, true) || in_array($actingSlug, $managerSlugs, true);
        }
      @endphp
      @if($staff->count() > 0)
        <div class="table-responsive staff-table-wrap">
          <table class="table table-hover table-bordered bg-white" id="staffTable">
            <thead class="bg-light">
              <tr>
                <th width="100">Staff ID</th>
                <th>Full Name</th>
                <th>Staff Role</th>
                <th width="110" class="text-center">Kiosk PIN</th>
                <th>Location</th>
                <th width="100">Status</th>
                <th width="70" class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($staff as $member)
                <tr class="align-middle staff-row">
                  <td><strong>{{ $member->staff_id }}</strong></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="https://ui-avatars.com/api/?name={{ urlencode($member->full_name) }}&background=E9ECEF&color=940000&size=32" class="rounded-circle mr-2" alt="Avatar">
                      <div>
                        <strong>{{ $member->full_name }}</strong><br>
                        <small class="text-muted">{{ $member->phone_number }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    @if($member->role)
                      @php
                        $rName = strtolower($member->role->name);
                        $badgeClass = 'badge-info';
                        if (str_contains($rName, 'admin')) $badgeClass = 'badge-danger';
                        elseif (str_contains($rName, 'manager')) $badgeClass = 'badge-warning';
                        elseif (str_contains($rName, 'waiter')) $badgeClass = 'badge-primary';
                        elseif (str_contains($rName, 'counter') || str_contains($rName, 'cashier')) $badgeClass = 'badge-success';
                      @endphp
                      <span class="badge {{ $badgeClass }} badge-pill px-3 py-1">{{ $member->role->name }}</span>
                    @else
                      <span class="badge badge-secondary badge-pill px-3 py-1">No Role</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($member->pin)
                      <span class="badge badge-light border px-3 py-1 font-weight-bold" style="letter-spacing: 2px; font-size: 0.9rem;">{{ $member->pin }}</span>
                    @else
                      <span class="badge badge-danger badge-pill px-2 py-1" style="font-size: 0.7rem;"><i class="fa fa-warning"></i> MISSING</span>
                    @endif
                  </td>
                  <td>
                    <span class="text-secondary">
                      <i class="fa fa-map-marker text-danger mr-1"></i> {{ $member->location_branch ?? 'Main' }}
                    </span>
                  </td>
                  <td>
                    @if($member->is_active)
                      <span class="badge badge-success badge-pill px-3 py-1">Active</span>
                    @else
                      <span class="badge badge-danger badge-pill px-3 py-1">Inactive</span>
                    @endif
                  </td>
                  <td class="text-center staff-actions">
                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false" title="Actions">
                        <i class="fa fa-ellipsis-v"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('staff.show', $member->id) }}">
                          <i class="fa fa-eye"></i> View
                        </a>
                        <a class="dropdown-item" href="{{ route('staff.edit', $member->id) }}">
                          <i class="fa fa-edit"></i> Edit
                        </a>
                        <button type="button" class="dropdown-item" onclick="generateStaffPassword({{ $member->id }}, '{{ addslashes($member->full_name) }}', '{{ addslashes($member->phone_number) }}')">
                          <i class="fa fa-key"></i> Send password
                        </button>
                        <form action="{{ route('staff.toggle-status', $member->id) }}" method="POST" id="toggle-status-{{ $member->id }}" class="m-0">
                          @csrf
                          <button type="button" class="dropdown-item" onclick="toggleStaffStatus({{ $member->id }}, '{{ addslashes($member->full_name) }}', {{ $member->is_active ? 'true' : 'false' }})">
                            <i class="fa {{ $member->is_active ? 'fa-ban' : 'fa-check' }}"></i> {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                          </button>
                        </form>
                        @if($canImpersonateStaff && (int) $member->id !== (int) session('staff_id'))
                          <div class="dropdown-divider"></div>
                          @if($member->is_active)
                            <form method="POST" action="{{ route('staff.impersonate', $member->id) }}" class="m-0 impersonate-form" data-name="{{ $member->full_name }}">
                              @csrf
                              <button type="submit" class="dropdown-item">
                                <i class="fa fa-user-secret"></i> Impersonate
                              </button>
                            </form>
                          @else
                            <button type="button" class="dropdown-item disabled" title="Inactive accounts cannot be impersonated">
                              <i class="fa fa-user-secret"></i> Impersonate
                            </button>
                          @endif
                        @endif
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-danger" onclick="deleteStaff({{ $member->id }}, '{{ addslashes($member->full_name) }}')">
                          <i class="fa fa-trash"></i> Delete
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-center py-5">
          <i class="fa fa-users fa-3x text-muted mb-3"></i>
          <p class="text-muted">No staff members registered yet.</p>
          <a href="{{ route('staff.create') }}" class="btn btn-primary">
            <i class="fa fa-plus"></i> Register First Staff Member
          </a>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Staff search
    $("#staffSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#staffTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    $(document).on('submit', '.impersonate-form', function (e) {
        e.preventDefault();
        const form = this;
        const name = $('<div>').text($(form).data('name')).html();
        Swal.fire({
            icon: 'question',
            title: 'Sign in as this account?',
            html: 'You will view the system as <strong>' + name + '</strong>.<br>'
                + '<span class="text-muted small">Use the banner at the top of the page to switch back.</span>',
            showCancelButton: true,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

function deleteStaff(staffId, staffName) {
  Swal.fire({
    title: 'Delete Staff Member?',
    html: `Are you sure you want to delete <strong>${staffName}</strong>?<br><br>This action cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      // Create a form and submit it
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `/staff/${staffId}`;
      
      // Add CSRF token
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_token';
      csrfInput.value = '{{ csrf_token() }}';
      form.appendChild(csrfInput);
      
      // Add method spoofing for DELETE
      const methodInput = document.createElement('input');
      methodInput.type = 'hidden';
      methodInput.name = '_method';
      methodInput.value = 'DELETE';
      form.appendChild(methodInput);
      
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function toggleStaffStatus(staffId, staffName, isActive) {
  const action = isActive ? 'Deactivate' : 'Activate';
  const actionText = isActive ? 'deactivating' : 'activating';
  const color = isActive ? '#ffc107' : '#28a745';

  Swal.fire({
    title: `${action} Staff Account?`,
    html: `Are you sure you want to ${actionText} <strong>${staffName}</strong>'s account?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: color,
    cancelButtonColor: '#3085d6',
    confirmButtonText: `Yes, ${action} it!`,
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById(`toggle-status-${staffId}`).submit();
    }
  });
}

function generateStaffPassword(staffId, staffName, phoneNumber) {
  Swal.fire({
    title: 'Generate new password?',
    html: `A new password will be created for <strong>${staffName}</strong> and sent with their username to <strong>${phoneNumber || 'their phone'}</strong>.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#940000',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, generate &amp; send',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `/staff/${staffId}/generate-password`;

      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_token';
      csrfInput.value = '{{ csrf_token() }}';
      form.appendChild(csrfInput);

      document.body.appendChild(form);
      form.submit();
    }
  });
}
</script>
@endpush
