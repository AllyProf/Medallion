@extends('layouts.dashboard')

@section('title', 'Attendance Log')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-clock-o"></i> Staff Attendance Monitoring</h1>
    <p>Performance tracking and shift schedules.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item">
        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#settingsModal"><i class="fa fa-cog"></i> Shift Times</button>
    </li>
  </ul>
</div>

{{-- Navigation Tabs --}}
<div class="row mb-3">
    <div class="col-md-12">
        <div class="tile p-0" style="background: transparent; box-shadow: none;">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link {{ $range === 'today' ? 'active shadow-sm' : 'bg-white border' }} mr-2" href="{{ route('manager.attendance.index', ['range' => 'today', 'status' => $statusFilter]) }}">Today</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $range === 'week' ? 'active shadow-sm' : 'bg-white border' }} mr-2" href="{{ route('manager.attendance.index', ['range' => 'week', 'status' => $statusFilter]) }}">This Week</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $range === 'month' ? 'active shadow-sm' : 'bg-white border' }} mr-2" href="{{ route('manager.attendance.index', ['range' => 'month', 'status' => $statusFilter]) }}">This Month</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $range === 'custom' ? 'active shadow-sm' : 'bg-white border' }}" href="#" onclick="$('#custom-filter').toggle(); return false;">Custom Range <i class="fa fa-caret-down ml-1"></i></a>
                </li>
            </ul>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="widget-small primary coloured-icon">
            <i class="icon fa fa-calendar-check-o fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold">Total Sessions</p>
                <p><b>{{ $attendances->count() }}</b></p>
                <small class="text-muted">In selected period</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small success coloured-icon">
            <i class="icon fa fa-user-circle fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold" style="color:#000!important">Active Now</p>
                <p><b style="color:#000!important">{{ $activeNow }}</b></p>
                <small class="text-muted">Staff signed in</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        @php
            $lateCount = 0;
            $shiftStartTime = \Carbon\Carbon::createFromFormat('H:i', $shiftStart);
            foreach($attendances as $record) {
                if (\Carbon\Carbon::parse($record->check_in->format('H:i'))->gt($shiftStartTime)) $lateCount++;
            }
        @endphp
        <div class="widget-small danger coloured-icon">
            <i class="icon fa fa-clock-o fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold">Total Lates</p>
                <p><b>{{ $lateCount }}</b></p>
                <small class="text-muted">Punctuality alert</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small info coloured-icon">
            <i class="icon fa fa-hourglass-half fa-3x"></i>
            <div class="info">
                @php
                    $completed = $attendances->where('status','completed')->where('duration_minutes','>',0);
                    $avgMin = $completed->count() ? $completed->avg('duration_minutes') : 0;
                    $h = floor($avgMin / 60);
                    $m = floor($avgMin % 60);
                @endphp
                <p class="text-uppercase small font-weight-bold">Avg Shift</p>
                <p><b>{{ $h }}h {{ $m }}m</b></p>
                <small class="text-muted">Work duration avg</small>
            </div>
        </div>
    </div>
</div>

{{-- Custom Filter Form (Togglabel) --}}
<div class="row mb-3" id="custom-filter" style="{{ $range !== 'custom' ? 'display:none;' : '' }}">
    <div class="col-md-12">
        <div class="tile">
            <form method="GET" action="{{ route('manager.attendance.index') }}" class="form-row align-items-end">
                <input type="hidden" name="range" value="custom">
                <div class="form-group col-md-3">
                    <label class="small font-weight-bold">From:</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>
                <div class="form-group col-md-3">
                    <label class="small font-weight-bold">To:</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>
                <div class="form-group col-md-3">
                    <label class="small font-weight-bold">Status:</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Logins</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>On Shift Only</option>
                        <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed Only</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">Apply Custom Range</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Search Bar --}}
<div class="row mb-3">
    <div class="col-md-12 text-right">
        <div class="d-inline-block position-relative" style="width: 300px;">
            <input type="text" id="att-search" class="form-control form-control-sm border-primary" placeholder="&#xf002; Search Name or Role..." style="font-family: FontAwesome, sans-serif; border-radius: 20px; padding-left: 15px;">
        </div>
    </div>
</div>

{{-- Table --}}
<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <h3 class="tile-title" style="font-size: 1.1rem;">
                <i class="fa fa-list"></i> 
                @if($range === 'today') Today's Attendance
                @else Attendance Log: {{ \Carbon\Carbon::parse($startDate)->format('M d') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                @endif
            </h3>
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="att-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Punctuality</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Work Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $record)
                        @php
                            $checkInTime = \Carbon\Carbon::parse($record->check_in->format('H:i'));
                            $isLate = $checkInTime->gt($shiftStartTime);
                            $diffMin = $checkInTime->diffInMinutes($shiftStartTime);
                            $isFirst = in_array($record->id, $firstArrivalIds);
                            
                            // Format duration: Hours and Minutes
                            if($record->status === 'active') {
                                $totalMin = $record->check_in->diffInMinutes(now());
                            } else {
                                $totalMin = $record->duration_minutes ?? 0;
                            }
                            $h = floor($totalMin / 60);
                            $m = floor($totalMin % 60);
                            $durationStr = ($h > 0 ? $h.'h ' : '') . $m.'m';
                        @endphp
                        <tr class="att-row" data-search="{{ strtolower($record->staff->full_name . ' ' . ($record->staff->role->name ?? '')) }}">
                            <td>
                                <small class="text-muted font-weight-bold">{{ $record->check_in->format('D, M d') }}</small>
                            </td>
                            <td>
                                <strong>{{ $record->staff->full_name ?? '—' }}</strong>
                            </td>
                            <td>
                                <span class="badge badge-light border text-uppercase" style="font-size: 0.75rem;">
                                    {{ $record->staff->role->name ?? 'Staff' }}
                                </span>
                            </td>
                            <td>
                                @if($isLate)
                                    <span class="text-danger font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fa fa-clock-o"></i> LATE ({{ $diffMin }}m)
                                    </span>
                                @else
                                    <span class="text-success font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fa fa-check-circle"></i> ON TIME
                                    </span>
                                @endif
                                
                                @if($isFirst)
                                    <br><span class="badge badge-info shadow-sm mt-1" style="font-size: 0.65rem;"><i class="fa fa-star"></i> EARLIEST ARRIVAL</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-primary font-weight-bold">{{ $record->check_in->format('h:i A') }}</span>
                            </td>
                            <td>
                                @if($record->check_out)
                                    {{ $record->check_out->format('h:i A') }}
                                @elseif($record->status === 'active')
                                    <span class="badge badge-success p-1 px-2" style="font-size: 0.7rem;">ON SHIFT</span>
                                @else
                                    <span class="text-muted small">--:--</span>
                                @endif
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem;">{{ $durationStr }}</strong>
                                @if($record->status === 'active')
                                    <span class="small text-warning font-italic ml-1">(live)</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No records found for the selected timeframe.</td>
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
        <form action="{{ route('manager.attendance.settings.update') }}" method="POST" class="modal-content border-0 shadow">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa fa-cog mr-2"></i> Attendance Configuration</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-4">
                    <label class="font-weight-bold text-dark">Default Shift START Time</label>
                    <input type="time" name="shift_start" class="form-control form-control-lg border-primary" value="{{ $shiftStart }}" required>
                    <p class="small text-muted mt-2">Any staff signing in after this specific time will be flagged as <b class="text-danger">LATE</b> on the report.</p>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold text-dark">Default Shift END Time</label>
                    <input type="time" name="shift_end" class="form-control form-control-lg" value="{{ $shiftEnd }}" required>
                    <p class="small text-muted mt-2">Used as the reference point for full work-day calculations.</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 font-weight-bold shadow-sm">Save Shift Schedule</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Instant real-time search
    $('#att-search').on('keyup input', function() {
        const q = $(this).val().toLowerCase().trim();
        $('.att-row').each(function() {
            const text = $(this).data('search') || '';
            $(this).toggle(!q || text.includes(q));
        });
    });

    @if(session('success'))
        $.notify({
            title: "Settings Saved: ",
            message: "{{ session('success') }}",
            icon: 'fa fa-check' 
        },{
            type: "success",
            placement: { from: "top", align: "right" }
        });
    @endif
});
</script>
@endsection
