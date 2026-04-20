@extends('layouts.dashboard')

@section('title', 'Master Sheet (Accordion History)')

@section('content')
<style>
  .excel-table { font-size: 0.9rem; width: 100% !important; margin-bottom: 0 !important; }
  .excel-table th { background: #212529 !important; color: white !important; font-size: 0.75rem; vertical-align: middle !important; padding: 12px 8px !important; }
  .excel-table td { vertical-align: middle !important; padding: 10px 12px !important; }
  .excel-table tr.main-row { cursor: pointer; transition: background 0.2s; }
  .excel-table tr.main-row:hover { background-color: #f1f3f5 !important; }
  .excel-table tr.main-row[aria-expanded="true"] { background-color: #e7f3ff !important; border-bottom: none !important; }
  
  .money-column { text-align: right; font-family: 'Courier New', Courier, monospace; }
  .status-badge { font-size: 0.65rem; padding: 2px 5px; border-radius: 3px; font-weight: bold; text-transform: uppercase; }
  .badge-open { border: 1px solid #28a745; color: #28a745; }
  .badge-closed { border: 1px solid #dc3545; color: #dc3545; }

  .detail-row { background-color: #fcfcfc !important; }
  .detail-container { padding: 20px 40px; border-left: 5px solid #0056b3; box-shadow: inset 0 3px 6px rgba(0,0,0,0.08); background: #fdfdfd; }
  .nested-table { font-size: 0.85rem; background: white; border: 1px solid #dee2e6; }
  .nested-table th { background: #6c757d !important; color: white !important; text-transform: uppercase; font-size: 0.7rem; border: none !important; }
  
  @media print {
    .d-print-none { display: none !important; }
    .excel-table { font-size: 10pt; width: 100% !important; }
    .excel-table th { background: #eee !important; color: #000 !important; border: 1px solid #000 !important; }
    .excel-table td { border: 1px solid #000 !important; }
    .app-content { margin: 0 !important; padding: 10px !important; }
    @page { size: landscape; margin: 0.5cm; }
  }
</style>

@section('styles')
<style>
  .manager-received { background-color: #e8f5e9 !important; }
</style>
@endsection

<div class="app-title d-print-none">
  <div>
    <h1><i class="fa fa-list-alt"></i> Master Sheet Archive</h1>
    <p>Click any row to instantly see the reconciliation breakdown.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Accountant</li>
    <li class="breadcrumb-item active">Interactive History</li>
  </ul>
</div>

<div class="tile d-print-none mb-3 py-2">
  <form method="GET" action="{{ route('accountant.daily-master-sheet.history') }}" class="row align-items-center">
    <div class="col-md-3">
      <label class="small font-weight-bold mb-0">From Date:</label>
      <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
    </div>
    <div class="col-md-3">
      <label class="small font-weight-bold mb-0">To Date:</label>
      <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
    </div>
    <div class="col-md-2 mt-3">
      <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-search"></i> Search</button>
    </div>
    <div class="col-md-2 mt-3 text-right">
       <a href="{{ route('accountant.daily-master-sheet.history') }}" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-refresh"></i> Reset</a>
    </div>
  </form>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile p-0" style="overflow:hidden;">
      <div class="table-responsive">
        <table class="table table-bordered excel-table">
          <thead>
            <tr>
              <th rowspan="2" class="text-center">#</th>
              <th rowspan="2">DATE</th>
              <th rowspan="2" class="text-center">STATUS</th>
              <th rowspan="2" class="text-right">OPENING CASH</th>
              <th colspan="3" class="text-center">SUBMITTED COLLECTIONS</th>

              <th class="text-right text-success">PROFIT CONTENT</th>
              <th class="text-right text-info">CIRCULATION REFILL</th>
              <th class="text-right text-info">ROLLOVER CYCLE</th>
              <th rowspan="2" class="text-center d-print-none">PRINT</th>
            </tr>
            <tr>
              <th class="text-right">CASH</th>
              <th class="text-right">DIGITAL</th>
              <th class="text-right">TOTAL</th>
              <th class="text-right bg-secondary text-white">ASSETS</th>
              <th class="text-right">EXPENSES</th>

              <th class="text-right text-success">PROFIT CONTENT</th>
              <th class="text-right text-info">CIRCULATION</th>
              <th class="text-right text-info">ROLLOVER CYCLE</th>
            </tr>
          </thead>
          <tbody>
            @if($ledgers->count() > 0)
              @foreach($ledgers as $index => $ledger)
                @php
                    $isClosed        = $ledger->status === 'closed';
                    $rowClass        = $ledger->isManagerReceived ? 'manager-received' : '';

                    // [CENTRALIZED LOGIC] Use model-calculated attributes for all metrics
                    $cashCollected     = $ledger->total_cash_received ?? 0;
                    $digitalCollected  = $ledger->total_digital_received ?? 0;
                    $shortageCollected = $ledger->shortageCollected ?? 0;
                    $subTotal          = $cashCollected + $digitalCollected; 
                    $totalAssets       = $ledger->opening_cash + $subTotal;
                    
                                    $margin = $ledger->expectedRevenue > 0 ? ($ledger->grossProfit / $ledger->expectedRevenue) : 0.35;
                                    $recoveryProfitPart = 0;
                                    if ($shortageCollected > 0) {
                                        $recoveryProfitPart = $shortageCollected * $margin;
                                    }
                                    
                                    $finalProfitDisplay = $ledger->profit_generated + $recoveryProfitPart;
                                    $finalCircDisplay = $ledger->total_expenses_from_circulation + ($subTotal - $ledger->profit_generated) + ($shortageCollected - $recoveryProfitPart);
                                    $shiftProfitPart = $ledger->profit_generated - $recoveryProfitPart;

                                    $actualPayout = $ledger->profit_submitted_to_boss ?? 0;
                                    $payoutDiff   = $actualPayout - $ledger->netAvailableProfit;
                @endphp
                {{-- MAIN ROW: Click to Collapse --}}
                <tr class="main-row {{ $rowClass }}" data-toggle="collapse" data-target="#details-{{ $ledger->id }}">
                  <td class="text-center text-muted"><i class="fa fa-chevron-down"></i></td>
                  <td class="font-weight-bold text-primary">{{ \Carbon\Carbon::parse($ledger->ledger_date)->format('d M, Y') }}</td>
                  <td class="text-center">
                    <span class="status-badge" style="border: 1px solid {{ $ledger->statusColor ?? '#28a745' }}; color: {{ $ledger->statusColor ?? '#28a745' }};">
                      {{ $ledger->businessStatus ?? 'DONE' }}
                    </span>
                  </td>
                  <td class="money-column">{{ number_format($ledger->opening_cash) }}</td>
                  <td class="money-column">
                      <div class="font-weight-bold">{{ number_format($cashCollected) }}</div>
                      @if($shortageCollected > 0)
                          @if($cashCollected - $shortageCollected > 0)
                              <div style="font-size:0.6rem;" class="text-muted">Shift: {{ number_format($cashCollected - $shortageCollected) }}</div>
                          @endif
                          <div style="font-size:0.65rem;" class="text-success font-weight-bold">(+) {{ number_format($shortageCollected) }} Recovery Pay</div>
                      @endif
                  </td>
                  <td class="money-column">{{ number_format($digitalCollected) }}</td>
                  <td class="money-column font-weight-bold">
                      <div>{{ number_format($subTotal) }}</div>
                      @if($shortageCollected > 0)
                          <div class="badge mt-1 d-block text-right" style="font-size:0.6rem; font-weight:bold; border: 1px solid #28a745; color: #28a745; background: #f2fff5;">
                            (+) {{ number_format($shortageCollected) }} RECOVERY
                          </div>
                      @endif
                      @if(($ledger->totalDayShortage ?? 0) > 0)
                          <div class="badge mt-1 d-block text-right" style="font-size:0.6rem; font-weight:normal; border: 1px solid #dc3545; color: #dc3545; background: #fff5f5;">
                            MISSING: {{ number_format($ledger->totalDayShortage) }}
                          </div>
                      @endif
                  </td>
                  <td class="money-column font-weight-bold bg-light">{{ number_format($totalAssets) }}</td>
                  <td class="money-column text-danger">({{ number_format($ledger->combined_expenses ?? $ledger->total_expenses) }})</td>

                  <td class="money-column text-success font-weight-bold">
                      <div>{{ number_format($ledger->profit_generated) }}</div>
                      @if($shortageCollected > 0)
                         @if($shiftProfitPart > 0)
                            <div class="text-muted" style="font-size:0.6rem; font-weight:normal;">Shift Part: {{ number_format($shiftProfitPart) }}</div>
                         @endif
                         <div class="text-success" style="font-size:0.65rem;">(+) {{ number_format($recoveryProfitPart) }} from Recovery</div>
                      @endif
                      @if(($ledger->totalDayShortage ?? 0) > 0 && !(isset($shortageCollected) && $shortageCollected > 0))
                          <div class="text-muted" style="font-size:0.6rem; font-weight:normal;">Margin: {{ number_format($margin * 100, 1) }}%</div>
                      @endif
                      @if($ledger->total_profit_outflow > 0)
                         <div class="text-danger" style="font-size:0.65rem;">-{{ number_format($ledger->total_profit_outflow) }} paid out</div>
                         <div style="font-size:0.75rem; border-top:1px dashed #ccc; margin-top:2px; padding-top:2px;">
                             <span class="text-muted" style="font-weight:normal;">Remains:</span> <span class="text-success">{{ number_format($ledger->netAvailableProfit) }}</span>
                         </div>
                      @endif
                  </td>
                   <td class="money-column text-info font-weight-bold">
                      <div>{{ number_format($ledger->circulationRefill) }}</div>
                      @if($shortageCollected > 0)
                         @if($ledger->circulationRefill - ($shortageCollected - $recoveryProfitPart) > 0)
                            <div class="text-muted" style="font-size:0.6rem; font-weight:normal;">Shift Capital: {{ number_format($ledger->circulationRefill - ($shortageCollected - $recoveryProfitPart)) }}</div>
                         @endif
                         <div class="text-info" style="font-size:0.65rem;">(+) {{ number_format($shortageCollected - $recoveryProfitPart) }} recovered cap</div>
                      @endif
                      @if(($ledger->circulationDebt ?? 0) > 0)
                         <div class="text-danger" style="font-size:0.6rem;"><i class="fa fa-warning"></i> {{ number_format($ledger->circulationDebt) }} LOSS</div>
                      @endif
                  </td>
                  <td class="money-column font-weight-bold">
                      {{ number_format($ledger->money_in_circulation) }}
                      @if($ledger->isManagerReceived && abs($payoutDiff) > 0)
                         <br><small class="{{ $payoutDiff > 0 ? 'text-danger' : 'text-success' }}" style="font-weight:normal;">
                            <i class="fa fa-{{ $payoutDiff > 0 ? 'arrow-up' : 'arrow-down' }}"></i> 
                            Boss took {{ number_format(abs($payoutDiff)) }} {{ $payoutDiff > 0 ? 'too much' : 'too little' }}
                         </small>
                      @elseif(!$ledger->isManagerReceived)
                         <br><span class="badge badge-light text-muted border" style="font-size:0.6rem; font-weight:normal;">AVAILABLE</span>
                      @endif
                      @if($ledger->isManagerReceived)
                         <br><span class='status-badge text-success mt-1 d-inline-block' style="border-color: #28a745;"><i class='fa fa-check-circle'></i> Received</span>
                      @elseif($ledger->managerReceiptStatus === 'pending')
                         <br><span class='status-badge text-warning mt-1 d-inline-block' style="border-color: #ffc107;">Pending</span>
                      @endif
                  </td>
                  <td class="text-center d-print-none" style="white-space:nowrap; vertical-align: middle;">
                      <div class="btn-group btn-group-sm mb-1">
                          <a href="{{ route('accountant.counter.reconciliation', ['date' => \Carbon\Carbon::parse($ledger->ledger_date)->format('Y-m-d')]) }}" class="btn btn-primary shadow-sm" title="Full Shift View">
                            <i class="fa fa-eye"></i> View
                          </a>
                          <a href="{{ route('accountant.daily-master-sheet', ['date' => \Carbon\Carbon::parse($ledger->ledger_date)->format('Y-m-d')]) }}" target="_blank" class="btn btn-dark shadow-sm" title="Print Report">
                            <i class="fa fa-print"></i> Print
                          </a>
                      </div>

                      {{-- Inline Quick Submit --}}
                      @php 
                         $hasPendingDiff = ($ledger->managerReceiptStatus === 'pending' && abs($payoutDiff) > 0);
                      @endphp
                      
                      @if($ledger->status === 'closed' && !$ledger->isManagerReceived)
                          <div class="">
                              @if($ledger->managerReceiptStatus === 'none')
                                  <button data-id="{{ $ledger->id }}" data-amount="{{ round($ledger->netAvailableProfit) }}" class="btn btn-success btn-sm btn-block shadow-sm submit-to-boss-btn" style="font-size: 11px; border-radius: 4px;">
                                      <i class="fa fa-send"></i> Submit Payout
                                  </button>
                              @elseif($ledger->managerReceiptStatus === 'pending')
                                  <button data-id="{{ $ledger->id }}" data-amount="{{ round($ledger->netAvailableProfit) }}" data-mode="update" class="btn btn-warning btn-sm btn-block shadow-sm submit-to-boss-btn" style="font-size: 11px; border-radius: 4px; color: #000;">
                                      <i class="fa fa-refresh"></i> Update Payout
                                  </button>
                              @endif
                          </div>
                      @endif
                  </td>
                </tr>
                {{-- COLLAPSIBLE DETAIL ROW --}}
                <tr id="details-{{ $ledger->id }}" class="collapse detail-row">
                  <td colspan="12">
                    <div class="detail-container">
                      <div class="row">
                         {{-- EXPENSE BREAKDOWN --}}
                         <div class="col-md-6 border-right">
                           <h6 class="text-danger"><i class="fa fa-minus-circle"></i> DAILY EXPENDITURES (CASH OUT)</h6>
                           <table class="table table-sm nested-table mt-2">
                             <thead>
                               <tr>
                                 <th>Description</th>
                                 <th class="text-right">Amount</th>
                               </tr>
                             </thead>
                             <tbody>
                               @if($ledger->expenseList->count() > 0)
                                 @foreach($ledger->expenseList as $ex)
                                   <tr>
                                     <td>{{ $ex->description }} <small class="text-muted">({{ $ex->category }})</small></td>
                                     <td class="text-right font-weight-bold">
                                       TSh {{ number_format($ex->amount) }}
                                       <span class="badge {{ $ex->fund_source === 'profit' ? 'badge-info' : 'badge-secondary' }} small" style="font-size:0.6rem;">
                                          {{ strtoupper($ex->fund_source ?? 'CIRCULATION') }}
                                       </span>
                                     </td>
                                   </tr>
                                 @endforeach
                               @endif
                               
                               @foreach($ledger->pettyCashList as $pc)
                                 <tr>
                                   <td><i class="fa fa-shopping-cart text-muted"></i> Petty Cash Issue to {{ $pc->recipient->full_name }}</td>
                                   <td class="text-right font-weight-bold text-info">
                                      TSh {{ number_format($pc->amount) }}
                                      <span class="badge {{ $pc->fund_source === 'profit' ? 'badge-info' : 'badge-secondary' }} small" style="font-size:0.6rem;">
                                          {{ strtoupper($pc->fund_source ?? 'CIRCULATION') }}
                                      </span>
                                   </td>
                                 </tr>
                               @endforeach

                               @if($ledger->expenseList->count() == 0 && $ledger->pettyCashList->count() == 0)
                                 <tr><td colspan="2" class="text-center italic text-muted">No expenses recorded.</td></tr>
                               @endif

                               <tr class="bg-light">
                                 <th class="text-right">Total Outflow:</th>
                                 <th class="text-right text-danger">TSh {{ number_format($ledger->combined_expenses ?? $ledger->total_expenses) }}</th>
                               </tr>
                             </tbody>
                           </table>
                           
                           @if($ledger->shortages && $ledger->shortages->count() > 0)
                           <div class="mt-4 border-top pt-3">
                             <h6 class="text-danger font-weight-bold" style="font-size:0.8rem;"><i class="fa fa-exclamation-triangle"></i> STAFF SHORTAGE ALERT</h6>
                             <table class="table table-sm nested-table mt-2">
                               <thead><tr><th>Staff Member</th><th class="text-right">Shortage</th></tr></thead>
                               <tbody>
                                 @foreach($ledger->shortages as $short)
                                 @php $isPaid = ($short->status === 'settled'); @endphp
                                 <tr class="{{ $isPaid ? 'text-muted bg-light' : 'text-danger' }}">
                                    <td>
                                        {{ $short->waiter->full_name ?? 'Unknown' }}
                                        @if($isPaid)
                                            <span class="badge badge-success ml-2" style="font-size: 0.6rem;"><i class="fa fa-check"></i> PAID</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-weight-bold">
                                        @if($isPaid)
                                            <s style="opacity:0.6;">- TSh {{ number_format(abs($short->difference)) }}</s>
                                        @else
                                            - TSh {{ number_format(abs($short->difference)) }}
                                        @endif
                                    </td>
                                 </tr>
                                 @endforeach
                               </tbody>
                             </table>
                           </div>
                           @endif
                         </div>

                         {{-- FINAL SUMMARY --}}
                         <div class="col-md-6">
                            <h6 class="text-success"><i class="fa fa-info-circle"></i> RECONCILIATION SUMMARY</h6>
                            <div class="mt-3">
                               <p class="mb-1 d-flex justify-content-between">
                                  <span>Gross Revenue (Total Assets):</span>
                                  <span class="font-weight-bold">TSh {{ number_format($totalAssets) }}</span>
                               </p>
                               @if($shortageCollected > 0)
                               <div class="mb-2 p-2 rounded" style="background: #f2fff5; border-left: 3px solid #28a745;">
                                  <div class="d-flex justify-content-between text-success font-weight-bold" style="font-size: 0.85em;">
                                     <span><i class="fa fa-level-up fa-rotate-90"></i> Staff Debt Payments Collected:</span>
                                     <span>TSh {{ number_format($shortageCollected) }}</span>
                                  </div>
                                  <div class="mt-1">
                                     @foreach($ledger->shortageBreakdown as $sb)
                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.75em; border-top: 1px dashed rgba(40,167,69,0.2);">
                                           <span>• {{ $sb['name'] }}</span>
                                           <span>TSh {{ number_format($sb['amount']) }}</span>
                                        </div>
                                     @endforeach
                                  </div>
                               </div>
                               @endif
                               @if(($ledger->totalDayShortage ?? 0) > 0)
                               <p class="mb-1 d-flex justify-content-between" style="font-size:0.85em; color:#dc3545;">
                                  <span><i class="fa fa-exclamation-circle"></i> Unrecovered Shortage (reduces profit):</span>
                                  <span class="font-weight-bold">- TSh {{ number_format($ledger->totalDayShortage) }}</span>
                               </p>
                               <p class="mb-1 d-flex justify-content-between" style="font-size:0.85em;">
                                  <span>Gross Profit (from orders sold):</span>
                                  <span><s class="text-muted">TSh {{ number_format($ledger->grossProfit) }}</s></span>
                               </p>
                               <p class="mb-1 d-flex justify-content-between font-weight-bold border-top pt-1" style="font-size:0.9em; color: {{ ($ledger->adjustedProfit ?? 0) > 0 ? '#28a745' : '#dc3545' }};">
                                  <span>Adjusted Profit (after shortage):</span>
                                  <span>TSh {{ number_format($ledger->adjustedProfit) }}</span>
                               </p>
                               @if(($ledger->circulationDebt ?? 0) > 0)
                               <p class="mb-1 d-flex justify-content-between" style="font-size:0.8em; color:#dc3545;">
                                  <span><i class="fa fa-warning"></i> Shortage exceeds profit — eating float:</span>
                                  <span>- TSh {{ number_format($ledger->circulationDebt) }}</span>
                                </p>
                               @endif
                               @endif
                               <p class="mb-1 d-flex justify-content-between border-bottom pb-1">
                                  <span>Total Expenses Paid:</span>
                                  <span class="text-danger">(-) TSh {{ number_format($ledger->combined_expenses ?? $ledger->total_expenses) }}</span>
                               <p class="mb-3 d-flex justify-content-between h6">
                                  <span>Total Cash in Box Today:</span>
                                  <span class="font-weight-bold">{{ number_format($ledger->money_in_circulation + $ledger->profit_generated) }}</span>
                               </p>
                               <div class="alert alert-info py-2" style="font-size:0.85rem; border-left: 5px solid #17a2b8;">
                                  <strong>💼 Financial Breakdown (Uwazi):</strong>
                                  <div class="mt-2 pl-2">
                                     <div class="d-flex justify-content-between mb-1">
                                        <span>Today's Sales Performance:</span>
                                        <span class="font-weight-bold">TSh {{ number_format($ledger->profit_generated - ($ledger->shortageBreakdown->sum('amount') > 0 ? ($ledger->shortageBreakdown->sum('amount') * $margin) : 0)) }}</span>
                                     </div>
                                     @if($shortageCollected > 0)
                                     <div class="d-flex justify-content-between text-success mb-1">
                                        <span>Profit from Recovered Debts:</span>
                                        <span class="font-weight-bold">+ TSh {{ number_format($recoveryProfitPart) }}</span>
                                     </div>
                                     @endif
                                     <div class="d-flex justify-content-between border-top pt-1 font-weight-bold">
                                        <span>Total Net Profit Generated:</span>
                                        <span class="text-success">TSh {{ number_format($ledger->profit_generated) }}</span>
                                     </div>
                                  </div>
                                  
                                  <hr class="my-2">
                                  
                                   @if($ledger->netAvailableProfit > 0)
                                      <p class="mb-2">The currently available net profit to be handed over is <strong>TSh {{ number_format($ledger->netAvailableProfit) }}</strong>.</p>
                                      
                                      @if($ledger->isManagerReceived)
                                         @if(abs($payoutDiff) > 0)
                                            <span class="text-danger small font-weight-bold"><i class="fa fa-warning"></i> NOTE: Handover variance detected (TSh {{ number_format(abs($payoutDiff)) }}).</span>
                                         @else
                                            <span class="text-success small font-weight-bold"><i class="fa fa-check"></i> Handover confirmed: TSh {{ number_format($actualPayout) }}.</span>
                                         @endif
                                      @else
                                         <div class="mt-2 p-3 bg-white rounded border border-light shadow-sm">
                                            @if($ledger->managerReceiptStatus === 'pending')
                                               <div class="d-flex justify-content-between align-items-center">
                                                  <span class="text-warning font-weight-bold small"><i class="fa fa-hourglass-half"></i> PENDING TSh {{ number_format($actualPayout) }}</span>
                                                  <button data-id="{{ $ledger->id }}" data-amount="{{ $ledger->netAvailableProfit }}" data-mode="update" class="btn btn-xs btn-warning py-1 px-2 submit-to-boss-btn">Update</button>
                                               </div>
                                            @else
                                               <button data-id="{{ $ledger->id }}" data-amount="{{ $ledger->netAvailableProfit }}" class="btn btn-sm btn-block btn-primary shadow-sm submit-to-boss-btn py-1 font-weight-bold">
                                                  <i class="fa fa-handshake-o"></i> Submit Profit (TSh {{ number_format($ledger->netAvailableProfit) }})
                                               </button>
                                            @endif
                                         </div>
                                      @endif
                                   @else
                                      <p class="mb-2 text-danger font-weight-bold">No profit available today (Shortage exceeded margin).</p>
                                   @endif
                                   
                                   <div class="mt-3 pt-2 border-top border-info text-dark">
                                      <div class="d-flex justify-content-between font-weight-bold">
                                         <span class="text-primary"><i class="fa fa-clock-o"></i> Opening Cash for Tomorrow:</span>
                                         <span class="h6 mb-0 font-weight-bold text-primary">TSh {{ number_format(floatval($ledger->carried_forward)) }}</span>
                                      </div>
                                      <small class="text-muted d-block mt-1"><i>(This is your Capital/Circulation only. Profit has been isolated.)</i></small>
                                   </div>
                                </div>
                               
                               <div class="text-right">
                                  <a href="{{ route('accountant.daily-master-sheet', ['date' => \Carbon\Carbon::parse($ledger->ledger_date)->format('Y-m-d')]) }}" class="btn btn-primary btn-sm">
                                    <i class="fa fa-external-link"></i> Full Day View
                                  </a>
                               </div>
                            </div>
                         </div>
                      </div>
                    </div>
                  </td>
                </tr>
              @endforeach
            @else
              <tr><td colspan="12" class="text-center py-5 text-muted">No historical data available.</td></tr>
            @endif
          </tbody>
        </table>
      </div>
      
      <div class="mt-3 d-print-none d-flex justify-content-center pb-3">
        {{ $ledgers->links('pagination::bootstrap-4') }}
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
$(document).on('click', '.submit-to-boss-btn', function() {
    const btn = $(this);
    const ledgerId = btn.data('id');
    const amount = btn.data('amount');
    const isUpdate = btn.data('mode') === 'update';
    const title = isUpdate ? 'Update Profit Handover?' : 'Submit Profit?';
    const confirmBtnText = isUpdate ? 'Yes, Update Payout' : 'Yes, Send to Boss!';
    const successTitle = isUpdate ? 'Updated!' : 'Sent!';

    showConfirm(
        (isUpdate ? "Update existing payout to " : "Submit ") + "TSh " + Math.round(amount).toLocaleString() + " profit to the Boss now?",
        title,
        function() {
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            
            $.ajax({
                url: "{{ route('accountant.daily-master-sheet.profit-handover.submit') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    ledger_id: ledgerId,
                    amount: Math.round(amount)
                },
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message, successTitle);
                        setTimeout(() => { location.reload(); }, 1500);
                    } else {
                        showAlert('error', response.error || "Operation failed", 'Error');
                        btn.prop('disabled', false).html(isUpdate ? '<i class="fa fa-refresh"></i> Update Payout' : '<i class="fa fa-send"></i> Submit Payout');
                    }
                },
                error: function() {
                    showAlert('error', "Operation failed. Network or server error.", 'Failed');
                    btn.prop('disabled', false).html(isUpdate ? '<i class="fa fa-refresh"></i> Update Payout' : '<i class="fa fa-send"></i> Submit Payout');
                }
            });
        },
        null,
        { confirmButtonText: confirmBtnText }
    );
});
</script>
@endsection
