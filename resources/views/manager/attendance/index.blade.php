@extends('layouts.dashboard')

@section('title', 'Attendance Pulse')

@section('content')
<div class="container-fluid">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="font-weight-bold text-dark"><i class="fa fa-clock-o text-danger"></i> Staff Attendance Log</h3>
            <p class="text-muted">Real-time monitoring of your team's shift activity.</p>
        </div>
        <div class="col-md-6 text-md-right">
            <form action="{{ route('manager.attendance.index') }}" method="GET" class="form-inline d-md-inline-flex">
                <input type="date" name="date" value="{{ $date }}" class="form-control mr-2 border-0 shadow-sm" style="border-radius: 8px;" onchange="this.form.submit()">
                <button type="button" class="btn btn-danger shadow-sm px-4" style="border-radius: 8px;" onclick="window.location.reload()">
                    <i class="fa fa-refresh mr-1"></i> Refresh
                </button>
            </form>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; border-left: 5px solid #28a745 !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1 small text-uppercase font-weight-bold">Active Right Now</h6>
                            <h2 class="font-weight-bold mb-0">{{ $activeNow }}</h2>
                        </div>
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 50px; height: 50px;">
                            <i class="fa fa-user-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; border-left: 5px solid #007bff !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1 small text-uppercase font-weight-bold">Total Sign-ins Today</h6>
                            <h2 class="font-weight-bold mb-0 text-primary">{{ $attendances->count() }}</h2>
                        </div>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 50px; height: 50px;">
                            <i class="fa fa-history fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; border-left: 5px solid #ffb822 !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1 small text-uppercase font-weight-bold">Coverage Rate</h6>
                            <h2 class="font-weight-bold mb-0 text-warning">88%</h2>
                        </div>
                        <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 50px; height: 50px;">
                            <i class="fa fa-line-chart fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="card border-0 shadow-sm" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 font-weight-bold text-dark"><i class="fa fa-list mr-2 text-danger"></i> Attendance Data</h5>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="background: #fff;">
                    <thead class="bg-light">
                        <tr>
                            <th class="border-0 px-4 py-3">Staff Member</th>
                            <th class="border-0 py-3">Status</th>
                            <th class="border-0 py-3">Checked In</th>
                            <th class="border-0 py-3">Checked Out</th>
                            <th class="border-0 py-3">Duration</th>
                            <th class="border-0 py-3 text-right pr-4">Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendances as $record)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px; border: 1px solid #eee;">
                                        <i class="fa fa-user text-danger"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold text-dark">{{ $record->staff->full_name }}</div>
                                        <small class="text-muted">{{ $record->staff->role_id ?? 'Staff' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                @if($record->status === 'active')
                                    <span class="badge badge-pill badge-success px-3 py-2 shadow-sm" style="font-size: 0.75rem;">
                                        <i class="fa fa-circle text-white mr-1" style="animation: pulse 1.5s infinite;"></i> ON SHIFT
                                    </span>
                                @else
                                    <span class="badge badge-pill badge-secondary px-3 py-2" style="font-size: 0.75rem;">FINISHED</span>
                                @endif
                            </td>
                            <td class="py-3 text-dark font-weight-bold">{{ $record->check_in->format('h:i A') }}</td>
                            <td class="py-3 text-muted">{{ $record->check_out ? $record->check_out->format('h:i A') : '--:--' }}</td>
                            <td class="py-3">
                                @if($record->duration_minutes)
                                    <span class="font-weight-bold text-dark">{{ round($record->duration_minutes / 60, 1) }} hrs</span>
                                @else
                                    <span class="text-success small">Calculating...</span>
                                @endif
                            </td>
                            <td class="py-3 text-right pr-4">
                                <span class="badge badge-light p-2">{{ $record->location_branch ?: 'Default' }}</span>
                            </td>
                        </tr>
                        @endforeach
                        @if($attendances->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fa fa-calendar-times-o fa-3x text-light mb-3"></i>
                                <p class="text-muted">No attendance activity recorded for this date.</p>
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0% { opacity: 0.4; }
    50% { opacity: 1; }
    100% { opacity: 0.4; }
}
</style>
@endsection
