@extends('layouts.dashboard')

@section('title', 'Shift History')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-history"></i> Shift History</h1>
    <p>View your past counter sessions and financial summaries</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bar.counter.dashboard') }}">Counter Dashboard</a></li>
    <li class="breadcrumb-item">Shift History</li>
  </ul>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="tile-title mb-0">Past Shifts</h3>
                <span class="badge badge-primary px-3 py-2">Total Shifts: {{ $shifts->total() }}</span>
            </div>

            @if($shifts->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-striped border">
                        <thead class="bg-light text-uppercase">
                            <tr>
                                <th>Shift #</th>
                                <th>Opened At</th>
                                <th>Closed At</th>
                                <th>Opening Cash</th>
                                <th>Expected Cash</th>
                                <th>Actual Cash</th>
                                <th>Digital Sales</th>
                                <th>Exp. Total</th>
                                <th>Act. Total</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shifts as $shift)
                                <tr>
                                    <td>
                                        <span class="font-weight-bold">{{ $shift->formatted_id }}</span>
                                    </td>
                                    <td>
                                        <small>{{ $shift->opened_at->format('M d, Y') }}</small><br>
                                        <strong>{{ $shift->opened_at->format('H:i') }}</strong>
                                    </td>
                                    <td>
                                        @if($shift->closed_at)
                                            <small>{{ $shift->closed_at->format('M d, Y') }}</small><br>
                                            <strong>{{ $shift->closed_at->format('H:i') }}</strong>
                                        @else
                                            <span class="text-success blink font-weight-bold">ACTIVE NOW</span>
                                        @endif
                                    </td>
                                    <td>TSh {{ number_format($shift->opening_cash) }}</td>
                                    <td>TSh {{ number_format($shift->expected_cash) }}</td>
                                    <td>
                                        @if($shift->status === 'closed')
                                            TSh {{ number_format($shift->actual_cash) }}
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>
                                    <td>TSh {{ number_format($shift->digital_revenue) }}</td>
                                    <td>
                                        <strong>TSh {{ number_format($shift->expected_cash + $shift->digital_revenue) }}</strong>
                                    </td>
                                    <td>
                                        @if($shift->status === 'closed')
                                            <span class="{{ ($shift->actual_cash + $shift->digital_revenue) < ($shift->expected_cash + $shift->digital_revenue) ? 'text-danger' : 'text-success' }} font-weight-bold">
                                                TSh {{ number_format($shift->actual_cash + $shift->digital_revenue) }}
                                            </span>
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($shift->status === 'open')
                                            <span class="badge badge-success px-2 py-1">OPEN</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1">CLOSED</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('bar.counter.reconciliation', ['date' => $shift->opened_at->format('Y-m-d')]) }}" class="btn btn-sm btn-info shadow-sm" title="View Reconciliation">
                                            <i class="fa fa-balance-scale mr-1"></i> VIEW MORE
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 d-flex justify-content-center">
                    {{ $shifts->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fa fa-calendar-times-o fa-5x text-muted opacity-50"></i>
                    </div>
                    <h4 class="text-muted">No shift history found.</h4>
                    <p class="text-muted">Your completed shifts will appear here once you close them.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
