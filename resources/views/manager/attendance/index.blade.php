@extends('layouts.dashboard')

@section('title', 'Attendance Log')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-clock-o"></i> Staff Attendance Log</h1>
    <p>Monitor team shifts, late arrivals, and first-in performance.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-sh"></i> <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#settingsModal"><i class="fa fa-cog"></i> Shift Schedule</button></li>
  </ul>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="widget-small primary coloured-icon">
            <i class="icon fa fa-users fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold">Total Logins</p>
                <p><b>{{ $attendances->count() }}</b></p>
                <small class="text-muted">For {{ \Carbon\Carbon::parse($date)->format('M d') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small success coloured-icon">
            <i class="icon fa fa-user-circle fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold" style="color:#000!important">On Shift Now</p>
                <p><b style="color:#000!important">{{ $activeNow }}</b></p>
                <small class="text-muted">Active staff</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        @php
            $lateCount = 0;
            $shiftStartTime = \Carbon\Carbon::createFromFormat('H:i', $shiftStart);
            foreach($attendances as $record) {
                $checkInTime = \Carbon\Carbon::parse($record->check_in->format('H:i'));
                if ($checkInTime->gt($shiftStartTime)) $lateCount++;
            }
        @endphp
        <div class="widget-small danger coloured-icon">
            <i class="icon fa fa-exclamation-triangle fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold">Late Arrivals</p>
                <p><b>{{ $lateCount }}</b></p>
                <small class="text-muted">Past {{ $shiftStart }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small info coloured-icon">
            <i class="icon fa fa-trophy fa-3x"></i>
            <div class="info">
                @php
                    $first = $attendances->first();
                @endphp
                <p class="text-uppercase small font-weight-bold">First Arrival</p>
                <p><b>{{ $first ? $first->check_in->format('h:i A') : '--:--' }}</b></p>
                <small class="text-muted">{{ $first ? explode(' ', $first->staff->full_name)[0] : 'No data' }}</small>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="row mb-3">
    <div class="col-md-12">
        <div class="tile">
            <form method="GET" action="{{ route('manager.attendance.index') }}" class="form-inline">
                <div class="form-group mr-3">
                    <label class="mr-2 small font-weight-bold">Date:</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}" onchange="this.form.submit()">
                </div>
                <div class="form-group mr-3">
                    <label class="mr-2 small font-weight-bold">Status:</label>
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>On Shift</option>
                        <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
                <div class="form-group mr-3">
                    <input type="text" id="att-search" class="form-control form-control-sm" placeholder="&#xf002; Search staff or role..." style="font-family: FontAwesome, sans-serif; min-width:250px;">
                </div>
                <div class="ml-auto">
                    <span class="badge badge-light p-2 border">Shift Window: <b>{{ \Carbon\Carbon::createFromFormat('H:i', $shiftStart)->format('h:i A') }} - {{ \Carbon\Carbon::createFromFormat('H:i', $shiftEnd)->format('h:i A') }}</b></span>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="att-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Time Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $record)
                        @php
                            $checkInTime = \Carbon\Carbon::parse($record->check_in->format('H:i'));
                            $isLate = $checkInTime->gt($shiftStartTime);
                            $diffMin = $checkInTime->diffInMinutes($shiftStartTime);
                            $isFirst = ($record->id === $firstArrivalId);
                        @endphp
                        <tr class="att-row" data-search="{{ strtolower($record->staff->full_name . ' ' . ($record->staff->role->name ?? '')) }}">
                            <td>
                                <strong>{{ $record->staff->full_name ?? '—' }}</strong>
                            </td>
                            <td>
                                <span class="badge badge-light border text-uppercase" style="font-size: 0.75rem;">
                                    {{ $record->staff->role->name ?? 'Staff' }}
                                </span>
                            </td>
                            <td>
                                @if($isFirst)
                                    <span class="badge badge-info shadow-sm" style="font-size: 0.7rem;"><i class="fa fa-star"></i> FIRST ARRIVAL</span><br>
                                @endif
                                
                                @if($isLate)
                                    <span class="text-danger font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fa fa-clock-o"></i> LATE ({{ $diffMin }}m)
                                    </span>
                                @else
                                    <span class="text-success font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fa fa-check-circle"></i> ON TIME
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="text-primary font-weight-bold">{{ $record->check_in->format('h:i A') }}</span>
                            </td>
                            <td>
                                @if($record->check_out)
                                    {{ $record->check_out->format('h:i A') }}
                                @else
                                    <span class="text-muted small">--:--</span>
                                @endif
                            </td>
                            <td>
                                @if($record->status === 'active')
                                    @php
                                        $elapsed = $record->check_in->diffInMinutes(now());
                                        $hrs = round($elapsed/60, 1);
                                    @endphp
                                    <span class="badge badge-warning" title="Staff is currently working. Total time elapsed since check-in.">
                                        {{ $hrs }} hrs <span class="small font-italic">(live)</span>
                                    </span>
                                @else
                                    <span class="font-weight-bold">{{ $record->duration_minutes ? round($record->duration_minutes/60, 1) . ' hrs' : '—' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($record->status === 'active')
                                    <span class="badge badge-success p-1 px-2"><i class="fa fa-circle text-white small mr-1"></i> ON SHIFT</span>
                                @else
                                    <span class="badge badge-secondary p-1 px-2">FINISHED</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No attendance records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Settings Modal --}}
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('manager.attendance.settings.update') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-cog"></i> Attendance Settings</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold">Standard Shift Start Time</label>
                    <input type="time" name="shift_start" class="form-control" value="{{ $shiftStart }}" required>
                    <small class="text-muted">Staff arriving after this time will be marked as LATE.</small>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Standard Shift End Time</label>
                    <input type="time" name="shift_end" class="form-control" value="{{ $shiftEnd }}" required>
                    <small class="text-muted">Used for shift duration metrics.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Real-time search by name or role
    $('#att-search').on('keyup input', function() {
        const q = $(this).val().toLowerCase().trim();
        $('.att-row').each(function() {
            const text = $(this).data('search') || '';
            $(this).toggle(!q || text.includes(q));
        });
    });
    
    @if(session('success'))
        $.notify({
            title: "Success: ",
            message: "{{ session('success') }}",
            icon: 'fa fa-check' 
        },{
            type: "success"
        });
    @endif
});
</script>
@endsection
