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
  .detail-container { padding: 16px 20px; border-left: 5px solid #940000; background: #fff; }
  .nested-table { font-size: 0.85rem; background: white; border: 1px solid #dee2e6; }
  .summary-table td { padding: 8px 12px !important; vertical-align: middle; }
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

@if(!empty($canManageShifts) && isset($openCounterShifts) && $openCounterShifts->count() > 0)
<div class="tile d-print-none mb-3">
  <h5 class="mb-3"><i class="fa fa-exchange text-primary"></i> Open counter shifts</h5>
  <p class="small text-muted mb-3">
    Transfer this live session to another counter. The shift number stays the same and all orders remain attached.
  </p>
  <div class="table-responsive">
    <table class="table table-sm table-bordered mb-0">
      <thead class="thead-light">
        <tr>
          <th>Shift</th>
          <th>Opened by</th>
          <th>Opened at</th>
          <th class="text-right">Orders</th>
          <th class="text-right">Bar sales</th>
          <th class="text-center">Transfer to</th>
        </tr>
      </thead>
      <tbody>
        @foreach($openCounterShifts as $openShift)
        <tr>
          <td><strong>{{ $openShift->formatted_id }}</strong></td>
          <td>{{ $openShift->staff->full_name ?? 'Staff' }}</td>
          <td>{{ optional($openShift->opened_at)->format('d M Y, H:i') }}</td>
          <td class="text-right">{{ $openShift->orders_count }}</td>
          <td class="text-right">TSh {{ number_format($openShift->orders_total, 0) }}</td>
          <td>
            <form method="POST" action="{{ route('accountant.daily-master-sheet.shift.transfer', $openShift) }}" class="form-inline justify-content-center transfer-shift-form" data-shift="{{ $openShift->formatted_id }}" data-from="{{ $openShift->staff->full_name ?? 'Counter' }}">
              @csrf
              <select name="to_staff_id" class="form-control form-control-sm mr-2 mb-1 transfer-to-staff" required>
                <option value="">Select counter…</option>
                @foreach($counterStaffOptions as $counter)
                  @if((int) $counter->id !== (int) $openShift->staff_id)
                    <option value="{{ $counter->id }}">{{ $counter->full_name }}</option>
                  @endif
                @endforeach
              </select>
              <input type="text" name="reason" class="form-control form-control-sm mr-2 mb-1" placeholder="Reason (optional)" maxlength="500">
              <button type="submit" class="btn btn-sm btn-primary mb-1">
                <i class="fa fa-exchange"></i> Transfer
              </button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

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
              <th rowspan="2" class="text-right">ASSETS</th>
              <th rowspan="2" class="text-right">EXPENSES</th>
              <th rowspan="2" class="text-right">PROFIT</th>
              <th rowspan="2" class="text-right">CIRCULATION</th>
              <th rowspan="2" class="text-right">ROLLOVER</th>
              <th rowspan="2" class="text-center d-print-none">ACTIONS</th>
            </tr>
            <tr>
              <th class="text-right">CASH</th>
              <th class="text-right">DIGITAL</th>
              <th class="text-right">TOTAL</th>
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
                                  <span class="text-warning font-weight-bold small text-center d-block"><i class="fa fa-hourglass-half"></i> PENDING PAYOUT</span>
                              @endif
                          </div>
                      @endif
                  </td>
                </tr>
                {{-- COLLAPSIBLE DETAIL ROW --}}
                <tr id="details-{{ $ledger->id }}" class="collapse detail-row">
                  <td colspan="13">
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

                         <div class="col-md-6">
                            <h6 class="text-success mb-2"><i class="fa fa-info-circle"></i> Reconciliation</h6>
                            <table class="table table-sm nested-table summary-table mb-3">
                              <tbody>
                                <tr>
                                  <td>Gross revenue</td>
                                  <td class="text-right font-weight-bold">TSh {{ number_format($totalAssets) }}</td>
                                </tr>
                                @if($shortageCollected > 0)
                                <tr>
                                  <td>Staff debt collected</td>
                                  <td class="text-right text-success font-weight-bold">TSh {{ number_format($shortageCollected) }}</td>
                                </tr>
                                @foreach($ledger->shortageBreakdown as $sb)
                                <tr>
                                  <td class="pl-4 text-muted">{{ $sb['name'] }}</td>
                                  <td class="text-right text-muted">TSh {{ number_format($sb['amount']) }}</td>
                                </tr>
                                @endforeach
                                @endif
                                @if(($ledger->totalDayShortage ?? 0) > 0)
                                <tr>
                                  <td>Unrecovered shortage</td>
                                  <td class="text-right text-danger">- TSh {{ number_format($ledger->totalDayShortage) }}</td>
                                </tr>
                                <tr>
                                  <td>Profit after shortage</td>
                                  <td class="text-right font-weight-bold">TSh {{ number_format($ledger->adjustedProfit) }}</td>
                                </tr>
                                @endif
                                <tr>
                                  <td>Expenses paid</td>
                                  <td class="text-right text-danger">- TSh {{ number_format($ledger->combined_expenses ?? $ledger->total_expenses) }}</td>
                                </tr>
                                <tr>
                                  <td>Cash in box</td>
                                  <td class="text-right font-weight-bold">TSh {{ number_format($ledger->money_in_circulation + $ledger->profit_generated) }}</td>
                                </tr>
                                <tr class="bg-light">
                                  <td class="font-weight-bold">Net profit</td>
                                  <td class="text-right font-weight-bold text-success">TSh {{ number_format($ledger->netAvailableProfit) }}</td>
                                </tr>
                                <tr>
                                  <td>Opening cash for tomorrow</td>
                                  <td class="text-right font-weight-bold">TSh {{ number_format(floatval($ledger->carried_forward)) }}</td>
                                </tr>
                              </tbody>
                            </table>

                            @if($ledger->isManagerReceived)
                              @if(abs($payoutDiff) > 0)
                                <p class="small text-danger font-weight-bold mb-2"><i class="fa fa-warning"></i> Handover difference: TSh {{ number_format(abs($payoutDiff)) }}</p>
                              @else
                                <p class="small text-success font-weight-bold mb-2"><i class="fa fa-check"></i> Handover confirmed: TSh {{ number_format($actualPayout) }}</p>
                              @endif
                            @elseif($ledger->netAvailableProfit > 0)
                              @if($ledger->managerReceiptStatus === 'pending')
                                <p class="small text-warning font-weight-bold mb-2"><i class="fa fa-hourglass-half"></i> Payout pending: TSh {{ number_format($actualPayout) }}</p>
                              @else
                                <button type="button" data-id="{{ $ledger->id }}" data-amount="{{ $ledger->netAvailableProfit }}" class="btn btn-sm btn-primary shadow-sm submit-to-boss-btn mb-2">
                                  <i class="fa fa-handshake-o"></i> Submit profit (TSh {{ number_format($ledger->netAvailableProfit) }})
                                </button>
                              @endif
                            @else
                              <p class="small text-danger font-weight-bold mb-2">No profit available. Shortage exceeded the margin.</p>
                            @endif

                            <a href="{{ route('accountant.daily-master-sheet', ['date' => \Carbon\Carbon::parse($ledger->ledger_date)->format('Y-m-d')]) }}" class="btn btn-outline-primary btn-sm">
                              <i class="fa fa-external-link"></i> Full day report
                            </a>
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
$(document).on('submit', '.transfer-shift-form', function(e) {
    e.preventDefault();
    const form = this;
    const toSelect = $(form).find('.transfer-to-staff');
    const toName = toSelect.find('option:selected').text().trim();

    if (!toSelect.val()) {
        Swal.fire({
            icon: 'warning',
            title: 'Select a counter',
            text: 'Please choose the staff member who will take over this shift.',
            confirmButtonColor: '#940000'
        });
        return;
    }

    const shiftId = $(form).data('shift');
    const fromName = $(form).data('from');

    Swal.fire({
        icon: 'question',
        title: 'Transfer this shift?',
        html: '<p class="mb-2">You are transferring <strong>' + shiftId + '</strong>.</p>'
            + '<p class="mb-2">From <strong>' + fromName + '</strong> to <strong>' + toName + '</strong>.</p>'
            + '<p class="text-muted mb-0 small">The shift number remains the same. All existing orders stay on this shift. The selected counter will continue the live session.</p>',
        showCancelButton: true,
        confirmButtonColor: '#940000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, transfer',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            form.submit();
        }
    });
});

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
