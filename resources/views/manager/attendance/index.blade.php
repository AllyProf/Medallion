@extends('layouts.dashboard')

@section('title', 'Attendance Log')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-clock-o"></i> Staff Attendance Log</h1>
    <p>Monitor your team's daily shift sign-in and sign-out activity.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item">Attendance Log</li>
  </ul>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="widget-small primary coloured-icon">
            <i class="icon fa fa-users fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold">Total Sign-Ins Today</p>
                <p><b>{{ $attendances->count() }}</b></p>
                <small class="text-muted">Across all staff</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small success coloured-icon">
            <i class="icon fa fa-user-circle fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold" style="color:#000!important">Currently On Shift</p>
                <p><b style="color:#000!important">{{ $activeNow }}</b></p>
                <small class="text-muted">Active right now</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small info coloured-icon">
            <i class="icon fa fa-check-circle fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold">Completed Shifts</p>
                <p><b>{{ $attendances->where('status','completed')->count() }}</b></p>
                <small class="text-muted">Fully signed out</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small warning coloured-icon">
            <i class="icon fa fa-clock-o fa-3x"></i>
            <div class="info">
                <p class="text-uppercase small font-weight-bold">Avg Duration</p>
                @php
                    $completed = $attendances->where('status','completed')->where('duration_minutes','>',0);
                    $avgHours = $completed->count() ? round($completed->avg('duration_minutes') / 60, 1) : 0;
                @endphp
                <p><b>{{ $avgHours }} hrs</b></p>
                <small class="text-muted">Average shift length</small>
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
                    <input type="date" name="date" id="filter-date" class="form-control form-control-sm" value="{{ $date }}" onchange="this.form.submit()">
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
                    <input type="text" id="att-search" class="form-control form-control-sm" placeholder="&#xf002; Search name..." style="font-family: FontAwesome, sans-serif; min-width:200px;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <h3 class="tile-title"><i class="fa fa-list"></i> Attendance Records — {{ \Carbon\Carbon::parse($date)->format('D, d M Y') }}</h3>
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="att-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Staff Member</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Duration</th>
                            <th>Branch</th>
                        </tr>
                    </thead>
                    <tbody id="att-tbody">
                        @forelse($attendances as $i => $record)
                        <tr class="att-row" data-name="{{ strtolower($record->staff->full_name ?? '') }}">
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <strong>{{ $record->staff->full_name ?? '—' }}</strong>
                            </td>
                            <td>
                                @if($record->status === 'active')
                                    <span class="badge badge-success p-2 px-3">
                                        <i class="fa fa-circle"></i> ON SHIFT
                                    </span>
                                @else
                                    <span class="badge badge-secondary p-2 px-3">FINISHED</span>
                                @endif
                            </td>
                            <td><strong>{{ $record->check_in->format('h:i A') }}</strong></td>
                            <td>{{ $record->check_out ? $record->check_out->format('h:i A') : '—' }}</td>
                            <td>
                                @if($record->duration_minutes)
                                    <span class="badge badge-info p-2">{{ round($record->duration_minutes / 60, 1) }} hrs</span>
                                @elseif($record->status === 'active')
                                    @php
                                        $elapsed = $record->check_in->diffInMinutes(now());
                                    @endphp
                                    <span class="badge badge-warning p-2">{{ round($elapsed/60,1) }} hrs (live)</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><small class="text-muted">{{ $record->location_branch ?: 'Default' }}</small></td>
                        </tr>
                        @empty
                        <tr id="att-empty-row">
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fa fa-calendar-times-o fa-2x mb-2"></i><br>
                                No attendance records found for this date.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div id="att-no-results" class="text-center py-4 text-muted" style="display:none;">
                    <i class="fa fa-search fa-2x mb-2"></i><br>No staff match your search.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Real-time search
    $('#att-search').on('keyup input', function() {
        const q = $(this).val().toLowerCase().trim();
        let visible = 0;
        $('.att-row').each(function() {
            const name = $(this).data('name') || '';
            if (!q || name.includes(q)) {
                $(this).show(); visible++;
            } else {
                $(this).hide();
            }
        });
        $('#att-no-results').toggle(visible === 0 && q !== '');
        $('#att-empty-row').toggle(visible === 0 && q === '' && $('.att-row').length === 0);
    });
});
</script>
@endsection
