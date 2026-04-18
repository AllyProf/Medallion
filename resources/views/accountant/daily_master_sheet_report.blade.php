@extends('layouts.dashboard')

@section('content')
<style>
   .report-container {
       background: #fff;
       width: 100%;
       max-width: 950px;
       margin: 0 auto;
       padding: 50px;
       font-family: 'Inter', 'Roboto', sans-serif;
       color: #444;
   }
   .report-header {
       border-bottom: 2px solid #940000;
       margin-bottom: 20px;
       padding-bottom: 5px;
       font-weight: 600;
       color: #940000;
       font-size: 1.15rem;
       text-transform: uppercase;
       letter-spacing: 0.5px;
   }
   .info-grid {
       display: grid;
       grid-template-columns: 1fr 1fr;
       gap: 60px;
       margin-bottom: 50px;
   }
   .info-row {
       display: flex;
       justify-content: space-between;
       padding: 10px 0;
       font-size: 0.95rem;
   }
   .info-row span:first-child {
       font-weight: 600;
       color: #666;
   }
   .info-row span:last-child {
       font-weight: 500;
       color: #333;
   }
   .stat-value { color: #28a745 !important; font-weight: 600 !important; }
   .stat-value-orange { color: #940000 !important; font-weight: 700 !important; font-size: 1.05rem; }
   
   .report-table-wrapper {
       margin-top: 40px;
   }
   .report-table {
       width: 100%;
       border-collapse: collapse;
       font-size: 0.85rem;
   }
   .report-table th, .report-table td {
       border: 1px solid #ddd;
       padding: 12px 10px;
       text-align: center;
       vertical-align: middle;
   }
   .report-table th {
       background-color: #fff;
       color: #333;
       font-weight: 700;
   }
   .report-table th:nth-child(2), .report-table td:nth-child(2) {
       text-align: left;
   }
   .table-section-header {
       background-color: #fce6e6 !important;
       font-weight: 700 !important;
       text-align: left !important;
       color: #940000;
   }
   .text-blue { color: #007bff; }
   .text-green { color: #28a745; }
   .text-theme { color: #940000; }
   .text-red { color: #dc3545; }

    .company-name {
        color: #940000;
        font-weight: 800;
        font-size: 2.2rem;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    .company-details {
        color: #777;
        font-size: 0.95rem;
        line-height: 1.6;
    }
    .badge-shift {
        background-color: #940000;
        color: white;
        padding: 5px 18px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
        letter-spacing: 1px;
        display: inline-block;
        margin-bottom: 8px;
    }
    .border-thick { border-bottom: 3px solid #940000; }
    .report-title {
        font-weight: 800;
        color: #222;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        font-size: 1.25rem;
        position: relative;
        display: inline-block;
        padding-bottom: 8px;
    }
    .report-title::after {
        content: "";
        position: absolute;
        bottom: 0; left: 10%; right: 10%;
        border-bottom: 1px dotted #bbb;
    }
    
    .card-custom {
        border: 1px solid #ddd;
        border-radius: 6px;
        overflow: hidden;
    }
    .card-custom-header {
        background-color: #940000;
        color: white;
        font-weight: 700;
        padding: 10px 15px;
        font-size: 0.95rem;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .card-custom-body {
        padding: 0 15px;
        background: #fff;
    }
    .card-custom-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eee;
    }
    .card-custom-row:last-child { border-bottom: none; }
    .card-custom-row span:first-child { color: #888; font-size: 0.95rem; }
    .card-custom-row span:last-child { font-weight: 800; color: #222; font-size: 0.95rem; }
    .badge-closed {
        background-color: #f1f1f1;
        color: #555;
        font-weight: 800;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        border: 1px solid #ddd;
    }
    .badge-active {
        background-color: #d4edda;
        color: #155724;
        font-weight: 800;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        border: 1px solid #c3e6cb;
    }

   .watermark {
       position: absolute;
       top: 40%;
       left: 50%;
       transform: translate(-50%, -50%) rotate(-15deg);
       font-size: 8rem;
       font-weight: 900;
       color: rgba(148, 0, 0, 0.04);
       z-index: 0;
       pointer-events: none;
       white-space: nowrap;
       letter-spacing: 5px;
   }

   @media print {
       body * { visibility: hidden; }
       .report-container, .report-container * { visibility: visible; }
       .report-container {
           position: absolute;
           left: 0;
           top: 0;
           width: 100%;
           padding: 0;
           box-shadow: none;
       }
       .d-print-none { display: none !important; }
   }
</style>

<div class="app-title d-print-none">
    <div>
        <h1><i class="fa fa-file-text-o"></i> Daily Master Sheet Report</h1>
        <p>Comprehensive print-ready view of stock movements and financial flow.</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
        <li class="breadcrumb-item"><a href="{{ route('accountant.daily-master-sheet.history') }}">Master Sheet History</a></li>
        <li class="breadcrumb-item">Print Report</li>
    </ul>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="tile shadow-sm p-0 mb-4 pb-4" style="border-radius: 8px; overflow: hidden;">
            <div class="bg-light p-3 text-right d-print-none border-bottom mb-4">
                <a href="{{ route('accountant.daily-master-sheet.history') }}" class="btn btn-secondary mr-2"><i class="fa fa-arrow-left"></i> Back to History</a>
                <button onclick="window.print()" class="btn btn-primary shadow-sm"><i class="fa fa-print"></i> Print Document</button>
            </div>

            <div class="report-container" style="position: relative;">
                @if($ledger->status === 'closed')
                    <div class="watermark">CLOSED</div>
                @endif

                <div class="d-flex justify-content-between align-items-end pb-3 border-thick mb-4 mt-2">
                    <div>
                        <div class="company-name">{{ $ledger->user->business_name ?? 'PRIME LAND HOTEL' }}</div>
                        <div class="company-details">
                            {{ $ledger->user->location ?? 'Plot No. 123, Opposite Main Market, Tanzania' }}<br>
                            Tel: {{ $ledger->user->phone ?? '+255 677 155 156' }} &nbsp;|&nbsp; {{ $ledger->user->email ?? 'info@primelandhotel.com' }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="badge-shift">SHIFT REPORT</div>
                        <div style="color: #666; font-size: 1rem; margin-top: 5px;">Shift #{{ $ledger->id }}</div>
                        <div style="color: #666; font-size: 1rem;">{{ \Carbon\Carbon::parse($ledger->ledger_date)->format('d M Y') }}</div>
                    </div>
                </div>

                <div class="text-center mb-5 mt-4">
                    <div class="report-title">CASH & REVENUE RECONCILIATION REPORT</div>
                </div>

                <div class="info-grid mt-4" style="gap:30px; margin-bottom: 40px; position:relative; z-index:1;">
                    <div class="card-custom">
                        <div class="card-custom-header">STAFF INFORMATION</div>
                        <div class="card-custom-body">
                            <div class="card-custom-row">
                                <span>Staff Name</span>
                                <span style="text-transform: uppercase;">{{ Auth::check() && Auth::user()->staff && Auth::user()->staff->first() ? Auth::user()->staff->first()->full_name : (Auth::user()->name ?? 'Accountant') }}</span>
                            </div>
                            <div class="card-custom-row">
                                <span>Role</span>
                                <span>Accountant</span>
                            </div>
                            <div class="card-custom-row">
                                <span>Shift Status</span>
                                <span>
                                    @if($ledger->status === 'closed')
                                        <span class="badge-closed">CLOSED</span>
                                    @else
                                        <span class="badge-active">ACTIVE</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card-custom">
                        <div class="card-custom-header">SESSION TIMELINE</div>
                        <div class="card-custom-body">
                            <div class="card-custom-row">
                                <span>Opened At</span>
                                <span style="font-weight: normal;">{{ $ledger->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <div class="card-custom-row">
                                <span>Closed At</span>
                                <span style="font-weight: normal;">{{ $ledger->status === 'closed' ? $ledger->updated_at->format('d M Y, H:i') : '--' }}</span>
                            </div>
                            <div class="card-custom-row">
                                <span>Duration</span>
                                <span style="font-weight: normal;">
                                    @if($ledger->status === 'closed')
                                        @php
                                            $start = $ledger->created_at;
                                            $end = $ledger->updated_at;
                                            $diff = $start->diff($end);
                                            echo $diff->h . 'h ' . str_pad($diff->i, 2, '0', STR_PAD_LEFT) . 'm';
                                        @endphp
                                    @else
                                       --
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                    <div>
                        <div class="report-header" style="color:#940000; border-color:#940000;">1. PAYMENT BREAKDOWN</div>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>PAYMENT METHOD</th>
                                    <th>AMOUNT (TZS)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($paymentBreakdown) > 0)
                                    @foreach($paymentBreakdown as $method => $amount)
                                    <tr>
                                        <td style="font-weight:600; text-align:left;">{{ $method }}</td>
                                        <td class="font-weight-bold text-success">{{ number_format($amount) }}</td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="2" class="text-muted py-3">No payment breakdowns provided.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    
                    <div>
                        <div class="report-header" style="color:#dc3545; border-color:#dc3545;">2. SHIFT OUTFLOWS</div>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>DESCRIPTION</th>
                                    <th>AMOUNT (TZS)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($expenses) > 0 || count($pettyCashList) > 0)
                                    @foreach($expenses as $ex)
                                    <tr>
                                        <td style="text-align:left;">{{ $ex->description }} <small class="text-muted">({{ $ex->category }})</small></td>
                                        <td class="text-danger font-weight-bold">(-) {{ number_format($ex->amount) }}</td>
                                    </tr>
                                    @endforeach
                                    @foreach($pettyCashList as $pc)
                                    <tr>
                                        <td style="text-align:left;">Petty Cash: {{ $pc->recipient->full_name ?? 'Staff' }}</td>
                                        <td class="text-danger font-weight-bold">(-) {{ number_format($pc->amount) }}</td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="2" class="text-muted py-3">No expenses or petty cash logged.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-table-wrapper mt-5 pt-3" style="page-break-inside: avoid;">
                   <div class="report-header" style="color:#28a745; border-color:#28a745;">3. FINANCIAL RECONCILIATION APPENDIX (BAR ONLY)</div>
                   <div class="info-grid mt-4" style="gap:40px;">
                       <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
                           <div class="info-row"><span>Opening Cash:</span> <span>TSh {{ number_format($ledger->opening_cash) }}</span></div>
                           <div class="info-row"><span>Bar Collections (Cash):</span> <span>TSh {{ number_format($ledger->total_cash_received) }}</span></div>
                           <div class="info-row border-bottom pb-2"><span>Bar Collections (Digital):</span> <span>TSh {{ number_format($ledger->total_digital_received) }}</span></div>
                           @if(isset($shortageCollected) && $shortageCollected > 0)
                               <div class="info-row" style="color: #28a745; font-size: 0.9rem;">
                                   <span><i class="fa fa-level-up fa-rotate-90"></i> Credit / Shortages Recovered:</span> 
                                   <span class="font-weight-bold">+ TSh {{ number_format($shortageCollected) }}</span>
                               </div>
                           @endif
                           <div class="info-row pt-2 text-danger"><span>Bar Expenses Paid:</span> <span>(-) TSh {{ number_format($ledger->total_expenses) }}</span></div>
                           @php 
                             $totalBarVault = ($ledger->opening_cash + $ledger->total_cash_received) - $ledger->total_expenses;
                           @endphp
                           <div class="info-row border-top mt-2 pt-2" style="border-top:1px solid #ccc;"><span>Total Drinks Vault:</span> <span class="font-weight-bold text-success" style="font-size:1.1rem;">TSh {{ number_format($totalBarVault) }}</span></div>
                       </div>
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
                            @if(($ledger->totalDayShortage ?? 0) > 0)
                            <div class="info-row">
                                <span>Gross Drink Profit (from orders):</span>
                                <span><s class="text-muted">TSh {{ number_format($ledger->grossProfit) }}</s></span>
                            </div>
                            <div class="info-row" style="color:#dc3545; font-size:0.9rem;">
                                <span><i class="fa fa-exclamation-circle"></i> Shortage Deduction:</span>
                                <span class="font-weight-bold">(-) TSh {{ number_format($ledger->totalDayShortage) }}</span>
                            </div>
                            <div class="info-row border-bottom pb-2" style="font-weight:700; color: {{ ($ledger->adjustedProfit ?? 0) > 0 ? '#28a745' : '#dc3545' }};">
                                <span>Adjusted Profit (after shortage):</span>
                                <span>TSh {{ number_format($ledger->adjustedProfit) }}</span>
                            </div>
                            @if(($ledger->circulationDebt ?? 0) > 0)
                            <div class="info-row" style="color:#dc3545; font-size:0.85rem;">
                                <span><i class="fa fa-warning"></i> Excess shortage eating float:</span>
                                <span>(-) TSh {{ number_format($ledger->circulationDebt) }}</span>
                            </div>
                            @endif
                            @else
                            <div class="info-row"><span>Gross Drink Profit:</span> <span class="{{ ($ledger->profit_generated ?? 0) > 0 ? 'text-success' : 'text-danger' }}">TSh {{ number_format($ledger->profit_generated) }}</span></div>
                            @endif
                            <div class="info-row"><span>Profit Expenses:</span> <span class="text-danger">(-) TSh {{ number_format($ledger->total_expenses_from_profit) }}</span></div>
                            <div class="info-row"><span>Distributed Payout:</span> 
                                 <span class="{{ $ledger->isManagerReceived ? 'text-success' : 'text-info' }} font-weight-bold">
                                     TSh {{ number_format($ledger->profit_submitted_to_boss ?? $ledger->netAvailableProfit) }}
                                     @if(!$ledger->isManagerReceived)
                                         <small class="text-muted">(Available)</small>
                                     @endif
                                 </span>
                            </div>
                             <div class="info-row border-top mt-2 pt-2" style="border-top:1px solid #ccc;"><span>Bar Rollover for Tomorrow:</span> <span class="font-weight-bold text-info" style="font-size:1.1rem;">
                               TSh {{ number_format(max(0, $ledger->money_in_circulation)) }}</span></div>
                        </div>
                   </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
