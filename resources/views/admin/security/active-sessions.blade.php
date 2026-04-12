@extends('layouts.dashboard')
@section('title', 'Active Sessions')

@push('styles')
<style>
.session-card { border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); margin-bottom: 12px; }
.badge-staff { background: #1a237e; color:#fff; font-size:11px; padding:3px 8px; border-radius:12px; }
.badge-user  { background: #00695c; color:#fff; font-size:11px; padding:3px 8px; border-radius:12px; }
.badge-guest { background: #757575; color:#fff; font-size:11px; padding:3px 8px; border-radius:12px; }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-users"></i> Active Sessions</h1>
    <p>All users & staff currently logged in or recently active</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>{{ session('success') }}</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        @if($sessions->isEmpty())
          <div class="text-center text-muted py-4"><i class="fa fa-user-times fa-3x mb-3"></i><br>No active sessions found.</div>
        @else
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="thead-dark">
                <tr>
                  <th>Who</th>
                  <th>Type</th>
                  <th>IP Address</th>
                  <th>Location</th>
                  <th>Last Active</th>
                  <th>Browser / Device</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($sessions as $session)
                <tr>
                  <td><strong>{{ $session->user_label }}</strong></td>
                  <td>
                    @if($session->is_staff)
                      <span class="badge-staff"><i class="fa fa-id-badge"></i> Staff</span>
                    @elseif($session->user)
                      <span class="badge-user"><i class="fa fa-user"></i> User</span>
                    @else
                      <span class="badge-guest">Guest</span>
                    @endif
                  </td>
                  <td>{{ $session->ip_address ?? '—' }}</td>
                  <td>
                    <span class="text-primary"><i class="fa fa-map-marker"></i> {{ $session->location }}</span>
                  </td>
                  <td>{{ $session->last_activity->diffForHumans() }}</td>
                  <td class="text-muted" style="font-size:11px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ $session->user_agent ?? '—' }}
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-danger" 
                            onclick="confirmRevoke('{{ $session->id }}', '{{ $session->user_label }}')">
                      <i class="fa fa-sign-out"></i> Revoke
                    </button>
                    <form id="revoke-form-{{ $session->id }}" method="POST" action="{{ route('admin.security.sessions.revoke', $session->id) }}" style="display:none;">
                      @csrf
                      @method('DELETE')
                    </form>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
function confirmRevoke(id, name) {
    if (typeof showConfirm === 'function') {
        showConfirm(
            'Are you sure you want to revoke the session for ' + name + '? They will be logged out immediately.',
            'Confirm Logout',
            function() {
                document.getElementById('revoke-form-' + id).submit();
            }
        );
    } else {
        if (confirm('Revoke session for ' + name + '?')) {
            document.getElementById('revoke-form-' + id).submit();
        }
    }
}
</script>
@endpush
