@extends('layouts.dashboard')

@section('title', 'Shift Summary Report')

@push('styles')
<style>
    .report-container {
        background: #fff;
        width: 100%;
        max-width: 950px;
        margin: 0 auto;
        padding: 40px;
        font-family: 'Inter', 'Roboto', sans-serif;
        color: #444;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    }
    .report-header {
        border-bottom: 2px solid #009688;
        margin-bottom: 20px;
        padding-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }
    .company-info h2 { color: #009688; font-weight: 800; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
    .company-info p { color: #777; font-size: 0.9rem; margin-bottom: 0; }
    
    .report-meta { text-align: right; }
    .report-meta h3 { font-size: 1.25rem; font-weight: 700; color: #333; margin-bottom: 5px; }
    .report-meta p { color: #888; font-size: 0.85rem; margin-bottom: 0; }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
        margin-top: 20px;
    }
    .summary-card {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #eee;
        text-align: center;
    }
    .summary-card .label { font-size: 0.65rem; color: #888; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 5px; }
    .summary-card .value { font-size: 1.1rem; font-weight: 800; color: #333; }
    .summary-card.highlight { background: #e0f2f1; border-color: #b2dfdb; }
    .summary-card.highlight .value { color: #00796b; }

    .section-title {
        font-size: 0.9rem;
        font-weight: 800;
        color: #555;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 15px;
        padding-bottom: 5px;
        border-bottom: 1px dashed #ddd;
        display: flex;
        align-items: center;
    }
    .section-title i { margin-right: 10px; color: #009688; }

    .details-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        font-size: 0.85rem;
    }
    .details-table th { background: #f1f1f1; color: #333; font-weight: 700; text-align: left; padding: 12px 10px; border: 1px solid #ddd; }
    .details-table td { padding: 12px 10px; border: 1px solid #ddd; vertical-align: middle; }
    .details-table tr:nth-child(even) { background: #fafafa; }

    .handover-breakdown {
        background: #fff8f1;
        border: 1px solid #ffe0b2;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }
    .handover-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 160, 0, 0.1);
    }
    .handover-item:last-child { border-bottom: none; font-weight: 800; font-size: 1rem; color: #e65100; margin-top: 5px; }
    .handover-item span:first-child { color: #795548; font-size: 0.85rem; font-weight: 600; }

    .status-stamp {
        position: absolute;
        top: 150px;
        right: 100px;
        transform: rotate(-15deg);
        padding: 10px 25px;
        border: 4px solid;
        border-radius: 10px;
        font-size: 1.5rem;
        font-weight: 900;
        text-transform: uppercase;
        opacity: 0.1;
        z-index: 0;
        pointer-events: none;
    }
    .stamp-verified { border-color: #28a745; color: #28a745; }
    .stamp-pending { border-color: #ffc107; color: #ffc107; }

    .notes-box {
        background: #fdfdfd;
        border-left: 4px solid #009688;
        padding: 15px;
        font-style: italic;
        color: #666;
        font-size: 0.9rem;
        margin-top: 20px;
    }

    .footer {
        margin-top: 50px;
        border-top: 1px solid #eee;
        padding-top: 20px;
        display: flex;
        justify-content: space-between;
        font-size: 0.8rem;
        color: #999;
    }

    @media print {
        body { background: white !important; }
        .app-title, .app-breadcrumb, .btn-print, .d-print-none, .main-footer, .sidebar-mini { display: none !important; }
        .app-content { margin-left: 0 !important; margin-right: 0 !important; padding: 0 !important; }
        .report-container { box-shadow: none; padding: 0; width: 100%; max-width: 100%; border: none; }
        .status-stamp { opacity: 0.3; }
    }
</style>
@endpush

@section('content')
<div class="app-title d-print-none">
    <div>
        <h1><i class="fa fa-file-text-o"></i> Shift Summary</h1>
        <p>Finalized records for Shift #{{ $shift->id }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('bar.counter.shift-history') }}">Shift History</a></li>
        <li class="breadcrumb-item active">Report</li>
    </ul>
</div>



<div class="report-container position-relative">
    @if($handover && $handover->status === 'verified')
        <div class="status-stamp stamp-verified">Verified</div>
    @else
        <div class="status-stamp stamp-pending">Pending</div>
    @endif

    <div class="report-header">
        <div class="company-info">
            <h2>{{ $shift->user->name }}</h2>
            <p>Financial Shift Summary Report</p>
            <p>{{ $shift->opened_at->format('F d, Y') }}</p>
        </div>
        <div class="report-meta">
            <h3>Shift #{{ $shift->id }}</h3>
            <p><strong>Staff:</strong> {{ $shift->staff->full_name }}</p>
            <p><strong>Duration:</strong> {{ $shift->opened_at->format('H:i') }} - {{ $shift->closed_at ? $shift->closed_at->format('H:i') : 'Active' }}</p>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card highlight">
            <span class="label">Total Sales</span>
            <span class="value">TSh {{ number_format($shift->expected_cash + $shift->digital_revenue) }}</span>
        </div>
        <div class="summary-card">
            <span class="label">Physical Cash</span>
            <span class="value">TSh {{ number_format($shift->expected_cash) }}</span>
        </div>
        <div class="summary-card">
            <span class="label">Digital Revenue</span>
            <span class="value">TSh {{ number_format($shift->digital_revenue) }}</span>
        </div>
        <div class="summary-card {{ ($handover && $handover->amount >= ($shift->expected_cash + $shift->digital_revenue)) ? 'highlight' : '' }}">
            <span class="label">Handed Over</span>
            <span class="value">TSh {{ number_format($handover ? $handover->amount : 0) }}</span>
        </div>
        @if($shift->actual_cash < $shift->expected_cash)
        <div class="summary-card" style="background: #fff5f5; border-color: #feb2b2;">
            <span class="label" style="color: #c53030;">Shortage</span>
            <span class="value" style="color: #c53030;">TSh {{ number_format($shift->expected_cash - $shift->actual_cash) }}</span>
        </div>
        @endif
    </div>

    @if($handover && $handover->payment_breakdown)
    <div class="row">
        <div class="col-md-6">
            <div class="section-title"><i class="fa fa-handshake-o"></i> Handover Breakdown</div>
            <div class="handover-breakdown shadow-sm">
                <div class="handover-item">
                    <span>Physical Cash Collected</span>
                    <span>TSh {{ number_format($handover->payment_breakdown['cash'] ?? 0) }}</span>
                </div>
                @foreach($handover->payment_breakdown as $key => $amount)
                    @if($key !== 'cash' && $amount > 0)
                        <div class="handover-item">
                            <span>{{ strtoupper(str_replace('_', ' ', $key)) }}</span>
                            <span>TSh {{ number_format($amount) }}</span>
                        </div>
                    @endif
                @endforeach
                <div class="handover-item">
                    <span>TOTAL SUBMITTED</span>
                    <span>TSh {{ number_format($handover->amount) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="section-title"><i class="fa fa-info-circle"></i> Verification Status</div>
            <div class="tile p-4 bg-light border-0 shadow-sm" style="border-radius: 12px; height: calc(100% - 40px);">
                <div class="text-center py-3">
                    @if($handover->status === 'verified')
                        <i class="fa fa-check-circle fa-4x text-success mb-3"></i>
                        <h4 class="text-success font-weight-bold">VERIFIED & BALANCED</h4>
                        <p class="text-muted small">Funds have been verified and consolidated by the accountant.</p>
                        @if($handover->confirmed_at)
                            <div class="badge badge-light border py-1 px-3 mt-2 text-dark" style="font-size: 0.7rem;">
                                Verified At: {{ $handover->confirmed_at->format('M d, Y H:i') }}
                            </div>
                        @endif
                    @else
                        <i class="fa fa-clock-o fa-4x text-warning mb-3"></i>
                        <h4 class="text-warning font-weight-bold">AWAITING VERIFICATION</h4>
                        <p class="text-muted small">This handover is currently in the accountant's queue for verification.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="section-title mt-4"><i class="fa fa-users"></i> Staff Reconciliation Recap</div>
    <div class="table-responsive">
        <table class="details-table shadow-sm">
            <thead>
                <tr>
                    <th>Waiters / Staff</th>
                    <th class="text-right">Expected</th>
                    <th class="text-right">Submitted</th>
                    <th class="text-right">Difference</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reconciliations as $rec)
                <tr>
                    <td>
                        <strong>{{ $rec->waiter->full_name }}</strong><br>
                        <small class="text-muted">{{ optional($rec->waiter->role)->name ?? 'Staff' }}</small>
                    </td>
                    <td class="text-right">TSh {{ number_format($rec->expected_amount) }}</td>
                    <td class="text-right">TSh {{ number_format($rec->submitted_amount) }}</td>
                    <td class="text-right {{ $rec->difference < 0 ? 'text-danger' : 'text-success' }}">
                        <strong>{{ $rec->difference > 0 ? '+' : '' }}{{ number_format($rec->difference) }}</strong>
                    </td>
                    <td class="text-center">
                        @if($rec->status === 'verified')
                            <span class="badge badge-success px-2 py-1">Verified</span>
                        @elseif($rec->status === 'submitted')
                            <span class="badge badge-primary px-2 py-1">Submitted</span>
                        @else
                            <span class="badge badge-warning px-2 py-1">Partial</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot style="background: #eee; font-weight: 800;">
                <tr>
                    <td>Total Recap</td>
                    <td class="text-right">TSh {{ number_format($totalExpected) }}</td>
                    <td class="text-right">TSh {{ number_format($totalSubmitted) }}</td>
                    <td class="text-right {{ $totalDifference < 0 ? 'text-danger' : 'text-success' }}">
                        TSh {{ number_format($totalDifference) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($shift->notes || ($handover && $handover->notes))
    <div class="section-title mt-4"><i class="fa fa-comment-o"></i> Audit Notes</div>
    <div class="notes-box">
        @if($shift->notes)
            <p class="mb-2"><strong>Shift Note:</strong> {{ $shift->notes }}</p>
        @endif
        @if($handover && $handover->notes)
            <p class="mb-0"><strong>Handover Note:</strong> {{ $handover->notes }}</p>
        @endif
    </div>
    @endif

    <div class="footer">
        <div>Generated on {{ now()->format('M d, Y H:i:s') }}</div>
        <div>System Record: SHIFT-{{ $shift->id }}-AUDIT</div>
    </div>
</div>
@endsection
