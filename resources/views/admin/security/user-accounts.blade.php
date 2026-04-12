@extends('layouts.dashboard')
@section('title', 'Account Management')

@push('styles')
<style>
.avatar-sm { width:36px; height:36px; border-radius:50%; object-fit:cover; }
.avatar-placeholder { width:36px; height:36px; border-radius:50%; background:#e0e0e0; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:#555; }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-key"></i> Account Management</h1>
    <p>Reset passwords &amp; manage login access for users and staff</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>{!! session('success') !!}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>{{ session('error') }}</div>
@endif

{{-- Staff Accounts --}}
<div class="row mb-4">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-id-badge"></i> Staff Accounts ({{ $staff->count() }})</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="thead-dark">
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Business</th>
                <th>Status</th>
                <th>Reset Password</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($staff as $s)
              <tr>
                <td>
                  @if($s->profile_image)
                    <img src="{{ asset('storage/' . $s->profile_image) }}?v={{ time() }}" alt="" class="avatar-sm mr-2">
                  @else
                    <span class="avatar-placeholder mr-2">{{ strtoupper(substr($s->full_name, 0, 1)) }}</span>
                  @endif
                  <strong>{{ $s->full_name }}</strong>
                </td>
                <td>{{ $s->email }}</td>
                <td><span class="badge badge-secondary">{{ $s->role->name ?? '—' }}</span></td>
                <td>{{ $s->owner->business_name ?? '—' }}</td>
                <td>
                  @if($s->is_active)
                    <span class="badge badge-success">Active</span>
                  @else
                    <span class="badge badge-danger">Inactive</span>
                  @endif
                </td>
                <td>
                  <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#resetStaffModal{{ $s->id }}">
                    <i class="fa fa-key"></i> Reset
                  </button>
                  {{-- Reset Password Modal --}}
                  <div class="modal fade" id="resetStaffModal{{ $s->id }}" tabindex="-1">
                    <div class="modal-dialog">
                      <form method="POST" action="{{ route('admin.security.accounts.staff.reset-password', $s->id) }}">
                        @csrf
                        <div class="modal-content">
                          <div class="modal-header"><h5 class="modal-title">Reset Password — {{ $s->full_name }}</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button></div>
                          <div class="modal-body">
                            <div class="text-center py-3">
                              <i class="fa fa-refresh fa-3x text-warning mb-3"></i>
                              <p>Generating a new random password and sending it via SMS to:<br>
                                <strong>{{ $s->phone_number ?? 'No Phone' }}</strong></p>
                              <p class="text-muted small">The new password will also be shown on your screen after you click reset.</p>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning"><i class="fa fa-key"></i> Reset Password</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                </td>
                <td>
                  {{-- Future: toggle active --}}
                  <a href="#" class="btn btn-sm btn-outline-secondary disabled"><i class="fa fa-lock"></i></a>
                </td>
              </tr>
              @empty
              <tr><td colspan="7" class="text-center text-muted py-3">No staff accounts found.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- User Accounts --}}
<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-users"></i> User Accounts ({{ $users->count() }})</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="thead-dark">
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Business</th>
                <th>Registered</th>
                <th>Reset Password</th>
                <th>Force Logout</th>
              </tr>
            </thead>
            <tbody>
              @forelse($users as $u)
              <tr>
                <td><strong>{{ $u->name }}</strong></td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->business_name ?? '—' }}</td>
                <td>{{ $u->created_at->format('d M Y') }}</td>
                <td>
                  <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#resetUserModal{{ $u->id }}">
                    <i class="fa fa-key"></i> Reset
                  </button>
                  <div class="modal fade" id="resetUserModal{{ $u->id }}" tabindex="-1">
                    <div class="modal-dialog">
                      <form method="POST" action="{{ route('admin.security.accounts.users.reset-password', $u->id) }}">
                        @csrf
                        <div class="modal-content">
                          <div class="modal-header"><h5 class="modal-title">Reset Password — {{ $u->name }}</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button></div>
                          <div class="modal-body">
                            <div class="text-center py-3">
                              <i class="fa fa-refresh fa-3x text-warning mb-3"></i>
                              <p>Generating a new random password and sending it via SMS to:<br>
                                <strong>{{ $u->phone ?? 'No Phone' }}</strong></p>
                              <p class="text-muted small">The new password will also be shown on your screen after you click reset.</p>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning"><i class="fa fa-key"></i> Reset Password</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                </td>
                <td>
                  <form method="POST" action="{{ route('admin.security.accounts.users.force-logout', $u->id) }}"
                        onsubmit="return confirm('Force logout {{ $u->name }}?')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-sign-out"></i> Force Out</button>
                  </form>
                </td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center text-muted py-3">No user accounts found.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
