@extends('layouts.dashboard')

@section('title', 'Daily Reconciliation')

@push('styles')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
<style>
  #waiters-table { border-collapse: collapse !important; border-radius: 8px; overflow: hidden; }
  #waiters-table th, #waiters-table td { vertical-align: middle; white-space: nowrap; border: 1px solid #dee2e6 !important; }
  #waiters-table thead th { background-color: #f8f9fa; color: #333; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 2px solid #009688 !important; }
  .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #dee2e6; border-radius: 5px; }
  
  /* Audit Columns Highlight */
  .audit-col-bg { background-color: rgba(0, 150, 136, 0.03); }
  .diff-col-bg { background-color: rgba(0, 0, 0, 0.02); }
  
  #waiters-table_wrapper .row { margin-bottom: 15px; }
  .badge { font-weight: 600; padding: 5px 8px; }
  @media (max-width: 768px) {
    .widget-small { margin-bottom: 10px; }
    .tile-title { font-size: 1.2rem; }
  }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-balance-scale"></i> Daily Reconciliation</h1>
    <p>View and verify waiter reconciliations. <span class="text-info small"><i class="fa fa-info-circle"></i> Note: Served orders are automatically marked as 'Paid' once reconciled.</span></p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item">{{ (Route::currentRouteName() === 'accountant.counter.reconciliation') ? 'Accountant' : 'Counter' }}</li>
    <li class="breadcrumb-item">Reconciliation</li>
  </ul>
  
  @if(Route::currentRouteName() === 'accountant.counter.reconciliation')
  <div class="ml-auto">
    <a href="{{ route('accountant.daily-master-sheet', ['date' => $date]) }}" class="btn btn-info shadow-sm rounded-pill px-4">
      <i class="fa fa-backward mr-2"></i> Back to Master Sheet
    </a>
  </div>
  @endif
</div>

<!-- Date Selector and Search -->
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <form method="GET" action="{{ Route::currentRouteName() === 'accountant.counter.reconciliation' ? route('accountant.counter.reconciliation') : route('bar.counter.reconciliation') }}" class="form-inline">
        <div class="form-group mr-3">
          <label for="date" class="mr-2">Select Date:</label>
          <input type="date" name="date" id="date" class="form-control" value="{{ $date }}" required>
        </div>
        <div class="form-group mr-3">
          <label for="status-filter" class="mr-2">Status:</label>
          <select id="status-filter" class="form-control">
            <option value="">All Statuses</option>
            <option value="verified">Verified</option>
            <option value="submitted">Submitted</option>
            <option value="paid">Paid</option>
            <option value="partial">Partial</option>
            <option value="pending">Pending</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-search"></i> View Reconciliation
        </button>
      </form>
    </div>
  </div>
</div>

@php
  $totalSalesToday = $waiters->sum('bar_sales') + $waiters->sum('food_sales');
  $totalCollections = $waiters->sum('cash_collected') + $waiters->sum('mobile_money_collected');
@endphp

@if($isManagementRole && $todayHandover)
<!-- Consolidated Financial Hub -->
<div class="row mb-4">
  {{-- 1. Sales & Collections --}}
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon shadow-sm" style="border-radius: 12px; height: 100%;">
      <i class="icon fa fa-money fa-3x" style="min-width: 70px;"></i>
      <div class="info">
        <h4 class="text-uppercase text-muted" style="font-size: 0.65rem; letter-spacing: 1px;">Shift Collections</h4>
        <p><b class="h5">TSh {{ number_format($totalCollections) }}</b></p>
        <span class="badge badge-light border text-dark py-1" style="font-size: 0.65rem;">
          <i class="fa fa-shopping-cart"></i> Sales: TSh {{ number_format($totalSalesToday) }}
        </span>
      </div>
    </div>
  </div>

  {{-- 2. Circulation (Working Capital) --}}
  <div class="col-md-3">
    <div class="widget-small info coloured-icon shadow-sm" style="border-radius: 12px; height: 100%;">
      <i class="icon fa fa-refresh fa-3x" style="min-width: 70px;"></i>
      <div class="info">
        <h4 class="text-uppercase text-muted" style="font-size: 0.65rem; letter-spacing: 1px;">In Circulation</h4>
        <p><b class="h5 text-info">TSh {{ number_format($moneyInCirculation) }}</b></p>
        @if($isAccountant)
          <span class="text-muted" style="font-size: 0.65rem;">Opening: TSh {{ number_format($ledger->opening_cash) }}</span>
        @endif
      </div>
    </div>
  </div>

  {{-- 3. Net Profit (Boss Share) --}}
  <div class="col-md-3">
    <div class="widget-small success coloured-icon shadow-sm" style="border-radius: 12px; height: 100%;">
      <i class="icon fa fa-line-chart fa-3x" style="min-width: 70px;"></i>
      <div class="info">
        <h4 class="text-uppercase text-muted" style="font-size: 0.65rem; letter-spacing: 1px;">Today's Profit</h4>
        <p><b class="h5 text-success">TSh {{ number_format($finalProfit) }}</b></p>
        @if($stockProfit > $finalProfit)
           <div class="text-muted small mb-1" style="font-size: 0.65rem; margin-top: -10px; font-weight: 600;">
              {{ number_format($stockProfit) }} <span class="text-danger">- {{ number_format($stockProfit - $finalProfit) }} exp</span>
           </div>
        @endif
        <span class="badge badge-success-light text-success py-1" style="font-size: 0.65rem; background: rgba(40,167,69,0.1);">
          <i class="fa fa-check-circle"></i> Pullout Ready
        </span>
      </div>
    </div>
  </div>

  {{-- 4. Vault Balance (Physical Cash) --}}
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon shadow-sm" style="border-radius: 12px; height: 100%;">
      <i class="icon fa fa-briefcase fa-3x" style="min-width: 70px;"></i>
      <div class="info">
        <h4 class="text-uppercase text-muted" style="font-size: 0.65rem; letter-spacing: 1px;">Total Vault Value</h4>
        <p><b class="h5">TSh {{ number_format($totalBusinessValue) }}</b></p>
        <span class="text-muted small" style="font-size: 0.65rem;">
           <i class="fa fa-users"></i> {{ $waiters->count() }} Waiters Active
        </span>
      </div>
    </div>
  </div>
</div>
@endif

@if($isManagementRole && !$todayHandover)
  <div class="row">
    <div class="col-md-12">
      <div class="tile text-center border-warning py-5 mb-4 shadow-sm" style="border-radius: 12px; border-top: 5px solid #ffc107;">
        <div class="mb-4">
            <i class="fa fa-hourglass-3 fa-4x text-warning opacity-50"></i>
        </div>
        <h3 class="text-dark font-weight-bold">Awaiting Counter Handover</h3>
        <p class="text-muted lead px-md-5 mx-auto" style="max-width: 700px;">
            The counter staff has not yet submitted their shift totals and physical cash collections for <strong>{{ \Carbon\Carbon::parse($date)->format('d M, Y') }}</strong>. 
            <br><span class="small">Waiters reconciliation tables and shift summaries will be unlocked once the handover is received.</span>
        </p>
        <div class="mt-4">
            <button class="btn btn-warning btn-lg px-5 shadow-sm font-weight-bold" onclick="location.reload();">
              <i class="fa fa-refresh mr-2"></i> Refresh Status
            </button>
        </div>
      </div>
    </div>
  </div>
@endif


@if(!$isManagementRole || $todayHandover)
<!-- Waiters List -->

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Waiters Reconciliation - {{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</h3>
      <div class="tile-body">
        @if($waiters->count() > 0)
          <div class="table-responsive shadow-sm">
            <table class="table table-hover table-bordered table-striped" id="waiters-table">
              <thead>
                <tr>
                  <th class="all">#</th>
                  <th class="all">Staff</th>
                  <th>Bar Sales</th>
                  <th>Orders</th>
                  <th class="none">Food</th>
                  <th>Cash</th>
                  <th>Digital</th>
                  <th class="all audit-col-bg">Expected</th>
                  <th class="all audit-col-bg">Recorded</th>
                  <th class="all audit-col-bg">Submitted</th>
                  <th class="all diff-col-bg">Diff</th>
                  <th class="all text-center">Status</th>
                  <th class="all">Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($waiters as $index => $data)
                @php 
                  $isCounter = in_array(strtolower($data['waiter']->role->slug ?? ''), ['counter', 'counter-staff', 'bar-manager']); 
                @endphp
                <tr data-waiter-id="{{ $data['waiter']->id }}" class="waiter-row">
                  <td>{{ $index + 1 }}</td>
                   <td>
                    <strong>{{ $data['waiter']->full_name }}</strong>
                    <span class="badge badge-secondary ml-1">{{ $data['waiter']->role->name ?? 'Staff' }}</span><br>
                    <small class="text-muted">{{ $data['waiter']->email }}</small>
                    @if($data['reconciliation'] && $data['reconciliation']->notes)
                      @php
                        $noteText = '';
                        try {
                            $decoded = json_decode($data['reconciliation']->notes, true);
                            if (is_array($decoded) && !empty($decoded['waiter_note'])) {
                                $noteText = $decoded['waiter_note'];
                            }
                        } catch(\Exception $e) {}
                      @endphp
                      @if($noteText)
                        <div class="mt-1">
                          <small class="text-danger"><i class="fa fa-info-circle"></i> <strong>Note:</strong> {{ $noteText }}</small>
                        </div>
                      @endif
                    @endif
                  </td>
                  <td>
                    <strong>TSh {{ number_format($data['bar_sales'], 0) }}</strong>
                    @if($data['food_sales'] > 0)
                      @if(Route::currentRouteName() !== 'bar.counter.reconciliation')
                        <br><small class="text-muted">Food: TSh {{ number_format($data['food_sales'], 0) }}</small>
                      @endif
                    @endif
                  </td>
                  <td><span class="badge badge-info">{{ $data['bar_orders_count'] }}</span></td>
                  <td><span class="badge badge-secondary">{{ $data['food_orders_count'] }}</span></td>
                  <td>TSh {{ number_format($data['cash_collected'], 0) }}</td>
                  <td>TSh {{ number_format($data['mobile_money_collected'], 0) }}</td>
                  <td class="audit-col-bg"><strong>TSh {{ number_format($data['expected_amount'], 0) }}</strong></td>
                   <td class="audit-col-bg">
                    @if(isset($data['recorded_amount']) && $data['recorded_amount'] > 0)
                      <strong class="text-info">TSh {{ number_format($data['recorded_amount'], 0) }}</strong>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="audit-col-bg">
                    @if($isCounter)
                      <span class="badge badge-light border text-muted">At Counter</span>
                    @elseif($data['submitted_amount'] > 0)
                      <strong class="text-success">TSh {{ number_format($data['submitted_amount'], 0) }}</strong>
                    @else
                      <span class="text-muted">Waiting</span>
                    @endif
                  </td>
                  <td class="diff-col-bg text-center">
                    @if($isCounter)
                      <span class="text-muted">-</span>
                    @elseif($data['submitted_amount'] > 0 || $data['reconciliation'])
                      <strong class="{{ $data['difference'] >= 0 ? 'text-success' : 'text-danger' }}">
                        @if($data['difference'] > 0)
                          +{{ number_format($data['difference'], 0) }}
                        @elseif($data['difference'] < 0)
                          {{ number_format($data['difference'], 0) }}
                        @else
                          0
                        @endif
                      </strong>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($isCounter)
                      <span class="badge badge-dark">Self-Managed</span>
                    @elseif($data['status'] === 'reconciled')
                      <span class="badge badge-success"><i class="fa fa-check-circle"></i> Reconciled</span>
                    @elseif($data['status'] === 'verified')
                      <span class="badge badge-success">Verified</span>
                    @elseif($data['status'] === 'submitted')
                      <span class="badge badge-info">Submitted</span>
                    @elseif($data['status'] === 'paid')
                      <span class="badge badge-success">Paid</span>
                    @elseif($data['status'] === 'partial')
                      <span class="badge badge-warning">Partial</span>
                    @elseif($data['status'] === 'disputed')
                      <span class="badge badge-danger">Disputed</span>
                    @else
                      <span class="badge badge-warning">Pending</span>
                    @endif
                  </td>
                  <td class="text-nowrap">
                    <!-- Always show View Orders -->
                    <button class="btn btn-sm btn-info view-orders-btn mr-1 mb-1" 
                            data-waiter-id="{{ $data['waiter']->id }}"
                            data-waiter-name="{{ $data['waiter']->full_name }}" title="View Orders">
                      <i class="fa fa-eye"></i> View
                    </button>
                    
                    @if(!$isCounter)
                      @if(Route::currentRouteName() === 'accountant.counter.reconciliation' && $data['reconciliation'] && $data['status'] === 'submitted')
                        <button class="btn btn-sm btn-success verify-btn mr-1 mb-1" 
                                data-reconciliation-id="{{ $data['reconciliation']->id }}" title="Verify">
                          <i class="fa fa-check"></i> Verify
                        </button>
                      @endif

                      {{-- Accountant Settle Shortage Button --}}
                      @if(Route::currentRouteName() === 'accountant.counter.reconciliation' && $data['reconciliation'] && $data['difference'] < 0)
                        <button class="btn btn-sm btn-warning settle-shortage-btn mr-1 mb-1 font-weight-bold" 
                                data-id="{{ $data['reconciliation']->id }}" 
                                data-name="{{ $data['waiter']->full_name }}"
                                data-shortage="{{ abs($data['difference']) }}" title="Settle Shortage">
                          <i class="fa fa-money text-dark"></i> <span class="text-dark">Settle</span>
                        </button>
                      @endif

                      {{-- Show Reconcile button ONLY IF no formal reconciliation has been submitted yet AND NOT accountant view --}}
                      @if(!$data['reconciliation'] && Route::currentRouteName() !== 'accountant.counter.reconciliation')
                        <button class="btn btn-sm btn-success mark-all-paid-btn mr-1 mb-1 font-weight-bold" 
                                data-waiter-id="{{ $data['waiter']->id }}"
                                data-date="{{ $date }}"
                                data-total-amount="{{ $data['expected_amount'] }}"
                                data-recorded-amount="{{ $data['recorded_amount'] ?? 0 }}"
                                data-submitted-amount="{{ $data['submitted_amount'] ?? 0 }}"
                                data-difference="{{ $data['difference'] ?? 0 }}"
                                data-breakdown="{{ json_encode($data['platform_totals'] ?? []) }}"
                                data-waiter-name="{{ $data['waiter']->full_name }}" title="Reconcile Staff">
                          <i class="fa fa-hand-holding-usd"></i> Reconcile
                        </button>
                      @endif

                      {{-- Show Undo button if a reconciliation record exists and it's not verified AND NOT accountant view --}}
                      @if($data['reconciliation'] && $data['status'] !== 'verified' && Route::currentRouteName() !== 'accountant.counter.reconciliation')
                        <button class="btn btn-sm btn-sm btn-outline-danger reset-btn mb-1" 
                                data-reconciliation-id="{{ $data['reconciliation']->id }}" title="Reset/Undo Reconciliation">
                          <i class="fa fa-undo"></i> Undo
                        </button>
                      @endif
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No waiters with orders found for this date.
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endif


@if(!$isManagementRole && !$todayHandover && $accountant && Route::currentRouteName() !== 'accountant.counter.reconciliation')
  {{-- The handover submission form section --}}
  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-handshake-o"></i> My Handover to Accountant</h3>
        <div class="tile-body">
          @php
            $totalCashHandover = 0;
            $totalDigitalHandover = 0;
            $platformTotals = [];
            
            $totalCashRecordedArr = 0;
            $totalDigitalRecordedArr = 0;
            
            foreach($waiters as $data) {
                $totalCashHandover += $data['cash_collected'];
                $totalDigitalHandover += $data['mobile_money_collected'];
                $totalCashRecordedArr += $data['recorded_cash'];
                $totalDigitalRecordedArr += $data['recorded_digital'];
                
                // For platform breakdown, we prioritize the saved breakdown in 'notes' if it exists.
                $savedBreakdown = null;
                if ($data['reconciliation'] && $data['reconciliation']->notes) {
                    try {
                        $notesData = json_decode($data['reconciliation']->notes, true);
                        if (is_array($notesData) && isset($notesData['submitted_breakdown'])) {
                            $savedBreakdown = $notesData['submitted_breakdown'];
                        }
                    } catch (\Exception $e) {}
                }

                if ($savedBreakdown) {
                    // Update the totals to match the saved submitted breakdown
                    // Note: cash_collected and mobile_money_collected are already reconciled in Controller
                    foreach($savedBreakdown as $label => $amt) {
                        if ($label === 'cash') continue;
                        $platformTotals[strtoupper(str_replace('_', ' ', $label))] = ($platformTotals[strtoupper(str_replace('_', ' ', $label))] ?? 0) + $amt;
                    }
                } else {
                    foreach($data['orders'] as $order) {
                        if ($order->orderPayments->count() > 0) {
                            foreach($order->orderPayments as $payment) {
                                if ($payment->payment_method === 'cash') continue;
                                
                                $provider = strtolower(trim($payment->mobile_money_number ?? 'mobile'));
                                $label = 'MOBILE MONEY';
                                if (str_contains($provider, 'm-pesa') || str_contains($provider, 'mpesa')) { $label = 'M-PESA'; }
                                elseif (str_contains($provider, 'mixx')) { $label = 'MIXX BY YAS'; }
                                elseif (str_contains($provider, 'halo')) { $label = 'HALOPESA'; }
                                elseif (str_contains($provider, 'tigo')) { $label = 'TIGO PESA'; }
                                elseif (str_contains($provider, 'airtel')) { $label = 'AIRTEL MONEY'; }
                                elseif (str_contains($provider, 'nmb')) { $label = 'NMB BANK'; }
                                elseif (str_contains($provider, 'crdb')) { $label = 'CRDB BANK'; }
                                elseif (str_contains($provider, 'kcb')) { $label = 'KCB BANK'; }
                                
                                $platformTotals[$label] = ($platformTotals[$label] ?? 0) + $payment->amount;
                            }
                        }
                    }
                }
            }
            $overallTotalHandover = $totalCashHandover + $totalDigitalHandover;
            
            $keyMap = [
                'M-PESA' => 'mpesa_amount',
                'MIXX BY YAS' => 'mixx_amount',
                'HALOPESA' => 'halopesa_amount',
                'TIGO PESA' => 'tigo_pesa_amount',
                'AIRTEL MONEY' => 'airtel_money_amount',
                'NMB BANK' => 'nmb_amount',
                'CRDB BANK' => 'crdb_amount',
                'KCB BANK' => 'kcb_amount',
                'MOBILE MONEY' => 'mobile_money_amount'
            ];
          @endphp

          <div class="alert alert-info border-primary mb-4 p-3 shadow-sm rounded">
            <div class="d-flex justify-content-between align-items-center">
                <h5><i class="fa fa-calculator"></i> Handover Summary</h5>
            </div>
            <div class="row text-center mt-3">
              <div class="col-md-4 mb-2 mb-md-0">
                <small class="text-uppercase font-weight-bold text-muted">Total Cash</small>
                <h4 class="text-warning mb-0">TSh {{ number_format($totalCashHandover, 0) }}</h4>
              </div>
              <div class="col-md-4 mb-2 mb-md-0" style="border-left: 1px solid #dee2e6; border-right: 1px solid #dee2e6;">
                <small class="text-uppercase font-weight-bold text-muted">Total Digital</small>
                <h4 class="text-success mb-0">TSh {{ number_format($totalDigitalHandover, 0) }}</h4>
              </div>
              <div class="col-md-4">
                <small class="text-uppercase font-weight-bold text-muted">Overall Handover</small>
                <h4 class="text-primary mb-0">TSh {{ number_format($overallTotalHandover, 0) }}</h4>
              </div>
            </div>
          </div>

          <form action="{{ route('bar.counter.handover') }}" method="POST">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            
            <div class="alert alert-warning">
              <h5><i class="fa fa-warning"></i> Ready to Close Your Day?</h5>
              <p>Please confirm the totals gathered from waiter reconciliations today.</p>
            </div>

            <div class="row">
              @if($totalCashHandover > 0)
              <div class="col-md-3 form-group">
                <label>Physical Cash</label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text">TSh</span></div>
                  <input type="number" name="cash_amount" class="form-control handover-input bg-light" value="{{ round($totalCashHandover) }}" readonly>
                </div>
              </div>
              @endif

              @foreach($platformTotals as $label => $amount)
                @if($amount > 0)
                <div class="col-md-3 form-group" title="{{ $label }} breakdown">
                  <label>{{ $label }}</label>
                  <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">TSh</span></div>
                    <input type="number" name="{{ $keyMap[$label] ?? 'mobile_money_amount' }}" class="form-control handover-input bg-light" value="{{ round($amount) }}" readonly>
                  </div>
                </div>
                @endif
              @endforeach
              
              @if($overallTotalHandover == 0)
              <div class="col-md-12">
                <div class="alert alert-info border-info">
                  <i class="fa fa-info-circle"></i> No collections recorded today. Waiters must reconcile their orders before you can handover.
                </div>
              </div>
              @endif
            </div>

            <div class="row mt-3">
              <div class="col-md-12">
                <div class="p-3 bg-light rounded text-right mb-3">
                  <h4 class="mb-0">Total Declaration: <span id="handover-total" class="text-primary font-weight-bold">TSh {{ number_format($overallTotalHandover, 0) }}</span></h4>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12 form-group">
                <label class="font-weight-bold">Notes / Comments <span id="notes-required" class="text-danger" style="display:none;">(Required for shortages)</span></label>
                <textarea name="notes" id="handover-notes" class="form-control" rows="3" placeholder="Any explanations for shortages or extra cash..."></textarea>
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
              <i class="fa fa-paper-plane"></i> Submit Detailed Handover to Accountant
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif

@if($todayHandover)
  @if($isAccountant)
    <div class="row">
      {{-- COLUMN 1: HANDOVER VERIFICATION --}}
      <div class="col-md-6 mb-4">
        <div class="tile shadow-sm h-100" style="border-radius: 12px; border-left: 5px solid #007bff;">
          <div class="d-flex justify-content-between align-items-center mb-3">
             <h3 class="tile-title mb-0"><i class="fa fa-handshake-o text-primary"></i> Handover</h3>
             <span class="badge badge-{{ $todayHandover->status === 'verified' ? 'success' : 'warning' }} px-3 py-2 shadow-sm" style="font-size: 0.8rem;">
               {{ strtoupper($todayHandover->status) }}
             </span>
          </div>
          
          <div class="p-3 bg-light rounded text-center border mb-3">
            <small class="text-uppercase font-weight-bold text-muted d-block mb-1">Received collections</small>
            <h3 class="text-primary font-weight-bold mb-0">TSh {{ number_format($todayHandover->amount, 0) }}</h3>
          </div>

          <div class="p-2 border rounded mb-3 bg-white" style="font-size: 0.85rem;">
            <small class="text-uppercase font-weight-bold text-muted d-block mb-2">Payment Breakdown:</small>
            @if($todayHandover->payment_breakdown)
              @foreach($todayHandover->payment_breakdown as $method => $amount)
                @if($amount > 0)
                  <div class="d-flex justify-content-between mb-1 border-bottom pb-1">
                    <span>{{ strtoupper(str_replace('_', ' ', $method)) }}</span>
                    <strong>TSh {{ number_format($amount, 0) }}</strong>
                  </div>
                @endif
              @endforeach
            @endif
          </div>

          @if($todayHandover->notes)
            <div class="alert alert-warning py-1 small">
              <i class="fa fa-comment-o"></i> <strong>Note:</strong> {{ $todayHandover->notes }}
            </div>
          @endif

          @if($todayHandover->status === 'pending')
            <div class="text-center mt-3 pt-2 border-top">
              <button class="btn btn-success btn-block shadow verify-handover-btn" data-id="{{ $todayHandover->id }}">
                <i class="fa fa-check-circle"></i> Confirm Receipt
              </button>
            </div>
          @else
            <div class="text-center py-2">
               <span class="text-success font-weight-bold"><i class="fa fa-check-square"></i> Funds Consolidated</span>
               <div class="mt-2 text-center">
                 @if($ledger && $ledger->status === 'closed')
                   <p class="small text-danger font-italic mb-0"><i class="fa fa-info-circle"></i> You must "Reopen" the shift before undoing verification.</p>
                 @else
                   <button class="btn btn-outline-danger btn-sm undo-verify-btn" data-id="{{ $todayHandover->id }}" style="border-radius: 50px; font-size: 0.7rem;">
                      <i class="fa fa-undo"></i> Undo Verify
                   </button>
                 @endif
               </div>
            </div>
          @endif

          {{-- SHORTAGES (Inside Column 1) --}}
          @php
            $hasShortages = false;
            foreach($waiters as $data) {
                if ($data['difference'] < 0) { $hasShortages = true; break; }
            }
          @endphp

          @if($hasShortages)
            <div class="mt-4 pt-3 border-top">
              <h6 class="text-danger font-weight-bold small mb-2"><i class="fa fa-exclamation-triangle"></i> STAFF SHORTAGE ALERT</h6>
              <div class="table-responsive">
                <table class="table table-sm table-bordered bg-light mb-0" style="font-size: 0.7rem;">
                  <thead class="bg-danger text-white">
                    <tr>
                      <th>Staff</th>
                      <th class="text-right">Shortage</th>
                      <th class="text-center" style="width: 80px;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($waiters as $data)
                      @if($data['difference'] < 0)
                      <tr class="text-danger font-weight-bold" style="vertical-align: middle;">
                        <td>{{ $data['waiter']->full_name }}</td>
                        <td class="text-right">- TSh {{ number_format(abs($data['difference']), 0) }}</td>
                        <td class="text-center">
                           <button class="btn btn-warning btn-sm font-weight-bold py-0 shadow-sm settle-shortage-btn" 
                                   data-id="{{ $data['reconciliation']->id ?? '' }}" 
                                   data-name="{{ $data['waiter']->full_name }}" 
                                   data-shortage="{{ abs($data['difference']) }}" 
                                   style="font-size: 0.65rem;">
                             Settle
                           </button>
                        </td>
                      </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          @endif
        </div>
      </div>

      {{-- COLUMN 2: SETTLEMENT & EXPENSES --}}
      <div class="col-md-6 mb-4">
        @if($todayHandover->status === 'verified')
          @php
             // Use controller calculated values for consistency and logic capping
             $vaultVal = $totalBusinessValue;
             $finPrf = $finalProfit;
             $circVal = $rolloverFloat; // Practical cash rollover
          @endphp

          <div class="tile shadow shadow-sm h-100" style="border-radius: 12px; border-top: 5px solid #28a745;">
              <h4 class="tile-title text-success"><i class="fa fa-lock"></i> Day-End Settlement</h4>
              <p class="text-muted small">Consolidate the shift and determine the rollover float for tomorrow.</p>
              
              @if($ledger->status === 'open')
                <div class="bg-light p-3 rounded border mb-3">
                   <div class="row text-center">
                     <div class="col-6 border-right border-bottom pb-2 mb-2 px-1">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 0.6rem;">VAULT CASH (ACTUAL)</small>
                        <span class="font-weight-bold small" style="color: #940000;">TSh {{ number_format($vaultVal) }}</span>
                        <br><small class="text-muted" style="font-size:0.55rem;">({{ number_format($ledger->opening_cash) }} bf + {{ number_format($totalRevenueToday) }} today)</small>
                     </div>
                     <div class="col-6 border-bottom pb-2 mb-2 px-1">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 0.6rem;">ROLLOVER FLOAT</small>
                        <span class="font-weight-bold small" style="color: #940000;">TSh {{ number_format($circVal) }}</span>
                        <br><small class="text-muted" style="font-size:0.55rem;">({{ number_format($ledger->opening_cash) }} bf + {{ number_format($moneyInCirculation) }} circ)</small>
                     </div>
                     <div class="col-6 border-right px-1">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 0.6rem;">GENERATED PROFIT</small>
                        <span class="font-weight-bold small" style="color: #940000;" title="Actual pullable sum after expenses">TSh {{ number_format($finPrf) }}</span>
                        @if($stockProfit > $finPrf)
                           <br><small class="text-muted" style="font-size:0.55rem;">({{ number_format($stockProfit) }} - {{ number_format($stockProfit - $finPrf) }} exp)</small>
                        @endif
                     </div>
                     <div class="col-6 px-1">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 0.6rem;">MONEY IN CIRCULATION</small>
                        <span class="font-weight-bold small" style="color: #940000;">TSh {{ number_format($moneyInCirculation) }}</span>
                     </div>
                   </div>
                </div>

                <div class="p-2 mb-3 rounded border bg-white small">
                   <p class="text-dark mb-0 font-italic" style="font-size: 0.75rem;">
                     <i class="fa fa-info-circle text-info"></i> Today you generated <strong>TSh {{ number_format($finPrf) }}</strong> in pullable profit (Boss share)
                     @if($stockProfit > $finPrf)
                        (after deducting TSh {{ number_format($stockProfit - $finPrf) }} for expenses).
                     @else
                        .
                     @endif
                     The remaining <strong>TSh {{ number_format($circVal) }}</strong> will roll over as <strong>Tomorrow's Opening Float</strong>.
                   </p>
                </div>

                <form id="closingForm" action="{{ route('accountant.daily-master-sheet.close') }}" method="POST" onsubmit="event.preventDefault(); Swal.fire({title: 'Are you sure?', text: 'Once the ledger is finalized and locked, financial balances cannot be changed. Proceed?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, lock it!', confirmButtonColor: '#28a745', cancelButtonColor: '#d33'}).then((result) => { if (result.isConfirmed) { this.submit(); } });">
                  @csrf
                  <input type="hidden" name="ledger_id" value="{{ $ledger->id }}">
                  <input type="hidden" name="actual_closing_cash" id="actual_closing" value="{{ round($vaultVal) }}">
                  <input type="hidden" name="profit_submitted_to_boss" id="profit_boss" value="{{ round($finPrf) }}">
                  <input type="hidden" name="money_in_circulation" value="{{ round($circVal) }}">
                  <input type="hidden" name="carried_forward" id="cycle_forward" value="{{ round($circVal) }}">
                  
                  @if($isAccountant)
                    <button class="btn btn-success btn-block px-4 shadow-sm font-weight-bold mt-2" type="submit" style="border-radius: 8px;">
                      <i class="fa fa-lock mr-1"></i> FINALIZE & LOCK LEDGER
                    </button>
                    <button type="button" class="btn btn-danger btn-block btn-sm mt-3" data-toggle="modal" data-target="#expenseModal">
                      <i class="fa fa-minus-circle mr-2"></i> Record Shift Expense
                    </button>
                  @endif
                </form>
              @else
                <div class="text-center py-3 bg-light rounded shadow-inner mb-3 position-relative border" style="border-color: #940000 !important; border-width: 2px !important;">
                    <div class="position-absolute" style="top: 10px; right: 10px;">
                        <button class="btn btn-outline-danger btn-sm undo-close-day-btn" data-ledger-id="{{ $ledger->id }}" style="border-radius: 20px; font-size: 0.7rem;">
                            <i class="fa fa-unlock"></i> Reopen
                        </button>
                    </div>
                    <i class="fa fa-shield fa-2x mb-2" style="color: #940000;"></i>
                    <h5 class="font-weight-bold" style="color: #940000;">SHIFT CLOSED</h5>
                    <p class="small text-muted mb-2">Data stored securely for {{ \Carbon\Carbon::parse($ledger->ledger_date)->format('M d, Y') }}.</p>
                    <div class="row mt-2 border-top pt-2 mx-1 text-dark" style="font-size: 0.75rem;">
                       <div class="col-4 border-right px-1">
                          Profit:<br>
                          <strong style="color: #940000;">{{ number_format($finalProfit) }}</strong>
                          <br><small class="text-muted" style="font-size:0.55rem;">
                             @if($stockProfit > $finalProfit)
                                ({{ number_format($stockProfit) }} gen - {{ number_format($stockProfit - $finalProfit) }} exp)
                             @else
                                (net)
                             @endif
                          </small>
                       </div>
                       <div class="col-4 border-right px-1">
                          Vault:<br>
                          <strong style="color: #940000;">{{ number_format($ledger->actual_closing_cash) }}</strong>
                          <br><small class="text-muted" style="font-size:0.55rem;">({{ number_format($ledger->opening_cash) }} bf + {{ number_format($totalRevenueToday) }} today)</small>
                       </div>
                       <div class="col-4 px-1">
                          Float:<br>
                          <strong style="color: #940000;">{{ number_format($ledger->carried_forward) }}</strong>
                          <br><small class="text-muted" style="font-size:0.55rem;">({{ number_format($ledger->opening_cash) }} bf + {{ number_format($moneyInCirculation ?? ($ledger->carried_forward - $ledger->opening_cash)) }} circ)</small>
                       </div>
                    </div>
                </div>
              @endif

              {{-- EXPENSES LOG (Mini version in Column 2) --}}
              @if($expenses->count() > 0 || $pettyCashIssues->count() > 0)
                <div class="mt-4 pt-3 border-top">
                  <h6 class="small font-weight-bold mb-2"><i class="fa fa-list text-danger"></i> Recent Outflows</h6>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size: 0.7rem;">
                      <tbody>
                        @foreach($expenses->take(5) as $exp)
                        <tr>
                          <td>{{ $exp->category }}</td>
                          <td class="text-right font-weight-bold">
                            TSh {{ number_format($exp->amount) }}
                            @if($ledger->status === 'open')
                            <button class="btn btn-link text-danger p-0 ml-1 delete-expense-btn" data-id="{{ $exp->id }}" title="Delete Expense">
                              <i class="fa fa-times"></i>
                            </button>
                            @endif
                          </td>
                        </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              @endif
          </div>
        @else
          <div class="tile shadow-sm h-100 d-flex align-items-center justify-content-center text-center bg-light border-dashed">
             <div>
                <i class="fa fa-clock-o fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Awaiting Verification</h5>
                <p class="small">Verify the handover in the left panel to unlock the settlement form.</p>
             </div>
          </div>
        @endif
      </div>
    </div>
  @endif
@elseif(!$isManagementRole)
    {{-- The status alert section for Counter Staff --}}
    <div class="row">
      <div class="col-md-12">
        <div class="tile">
          <h3 class="tile-title"><i class="fa fa-handshake-o"></i> My Handover to Accountant</h3>
          <div class="tile-body">
            @if($todayHandover)
            <div class="alert {{ $todayHandover->status === 'verified' ? 'alert-success' : ($todayHandover->status === 'disputed' ? 'alert-danger' : 'alert-info') }}">
              <h4>Handover {{ ucfirst($todayHandover->status) }}</h4>
              <p>You submitted your daily physical and digital collections on {{ $todayHandover->created_at->format('h:i A') }}.</p>
              <hr>
              <div class="row">
                <div class="col-md-6">
                  <strong>Total Amount:</strong> TSh {{ number_format($todayHandover->amount, 0) }}<br>
                  @if($todayHandover->payment_breakdown)
                    <ul class="mb-0 mt-2">
                      @foreach($todayHandover->payment_breakdown as $method => $amount)
                        @if($amount > 0)
                          <li><strong>{{ strtoupper(str_replace('_', ' ', $method)) }}:</strong> TSh {{ number_format($amount, 0) }}</li>
                        @endif
                      @endforeach
                    </ul>
                  @endif
                </div>
                <div class="col-md-6">
                  <strong>Recipient:</strong> Accountant<br>
                  @if($todayHandover->notes)
                    <strong>Notes:</strong> {{ $todayHandover->notes }}<br>
                  @endif
                </div>
              </div>

              {{-- STAFF SHORTAGE SUMMARY FOR COUNTER --}}
              @php
                $hasShortagesForCounter = false;
                foreach($waiters as $data) {
                    if ($data['difference'] < 0) { $hasShortagesForCounter = true; break; }
                }
              @endphp

              @if($hasShortagesForCounter)
                <div class="mt-4 pt-3 border-top">
                  <h6 class="text-danger font-weight-bold mb-3"><i class="fa fa-exclamation-triangle"></i> STAFF SHORTAGE SUMMARY</h6>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered bg-white shadow-sm">
                      <thead class="bg-light">
                        <tr>
                          <th>Staff Name</th>
                          <th class="text-right">Expected</th>
                          <th class="text-right">Submitted</th>
                          <th class="text-right text-danger">Shortage</th>
                          <th>Explanation / Notes</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($waiters as $data)
                          @if($data['difference'] < 0)
                          @php
                            $auditNotes = json_decode($data['reconciliation']->notes ?? '{}', true);
                            $waiterNote = $auditNotes['waiter_note'] ?? 'No explanation provided';
                          @endphp
                          <tr class="text-danger">
                            <td class="font-weight-bold">{{ $data['waiter']->full_name }}</td>
                            <td class="text-right text-muted">TSh {{ number_format($data['expected_amount'], 0) }}</td>
                            <td class="text-right text-muted">TSh {{ number_format($data['submitted_amount'], 0) }}</td>
                            <td class="text-right font-weight-bold">- TSh {{ number_format(abs($data['difference']), 0) }}</td>
                            <td class="font-italic small">
                               <i class="fa fa-quote-left text-muted"></i> {{ $waiterNote }}
                            </td>
                          </tr>
                          @endif
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              @endif

              @if($todayHandover->status === 'pending')
                <hr>
                <div class="text-right">
                  <button class="btn btn-outline-danger btn-sm reset-handover-btn" data-date="{{ $date }}">
                    <i class="fa fa-undo"></i> Reset Handover & Redo Reconciliation
                  </button>
                  <p class="small text-muted mt-2 mb-0 font-italic">Clicking this will cancel your submission and allow you to adjust the waiter rows again.</p>
                </div>
              @endif
            </div>
            @else
            <div class="alert alert-secondary">
                <i class="fa fa-info-circle"></i> No handover was recorded for this date.
            </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endif

@if(!$accountant && Route::currentRouteName() !== 'accountant.counter.reconciliation')
  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-handshake-o"></i> My Handover to Accountant</h3>
        <div class="tile-body">
          <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i> No active accountant found. You cannot handover money until an accountant is registered by the owner.
          </div>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- COUNTER STAFF TWO-STEP WORKFLOW: Verify → Close --}}
@if(!$isAccountant && $todayHandover)
<div class="row mt-4">
  <div class="col-md-12">

    @php 
      $isVerified = ($todayHandover->status === 'verified');
      $headerColor = $isVerified ? '#28a745' : '#ffc107';
      $headerBg = $isVerified ? '#d4edda' : '#fff3cd';
      $statusIcon = $isVerified ? 'fa-check-circle' : 'fa-clock-o';
      $statusLabel = $isVerified ? 'COLLECTION VERIFIED' : 'AWAITING VERIFICATION';
      $statusColor = $isVerified ? 'success' : 'warning';
    @endphp

    {{-- ═══════════════════════════════════════════ --}}
    {{-- UNIFIED RECONCILIATION CARD                 --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div class="tile shadow-sm" style="border-radius: 12px; border-top: 5px solid {{ $headerColor }};">
      <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:48px;height:48px;border-radius:50%;background:{{ $headerBg }};display:flex;align-items:center;justify-content:center;">
          <i class="fa fa-money fa-2x text-{{ $statusColor }}"></i>
        </div>
        <div>
          <h4 class="mb-0 font-weight-bold">Daily Financial Reconciliation</h4>
          <small class="text-muted">
            @if(!$isVerified)
              Confirm you have physically counted and consolidated all cash from your waiters before closing.
            @else
              Accountant has confirmed your collections. You can now finalize and close your day.
            @endif
          </small>
        </div>
        <span class="badge badge-{{ $statusColor }} ml-auto px-3 py-2" style="font-size:0.8rem;">
          <i class="fa {{ $statusIcon }} mr-1"></i> {{ $statusLabel }}
        </span>
      </div>

      {{-- COLLECTION DATA (BREAKDOWN) --}}
      <div class="row text-center my-3 py-3 bg-light rounded border mx-0">
        <div class="col-4 border-right">
          <small class="text-muted text-uppercase d-block" style="font-size:0.65rem;">Total Submitted</small>
          <strong class="h5 {{ $isVerified ? 'text-success' : 'text-primary' }}">TSh {{ number_format($todayHandover->amount, 0) }}</strong>
          @if($todayHandover->payment_breakdown)
            <div class="mt-2 pt-2 border-top">
              @foreach($todayHandover->payment_breakdown as $method => $amt)
                @if($amt > 0)
                  <div class="d-flex justify-content-between px-3" style="font-size: 0.7rem;">
                    <span class="text-uppercase text-muted">{{ str_replace('_', ' ', $method) }}</span>
                    <span class="font-weight-bold">TSh {{ number_format($amt, 0) }}</span>
                  </div>
                @endif
              @endforeach
            </div>
          @endif
        </div>
        <div class="col-4 border-right">
          <small class="text-muted text-uppercase d-block" style="font-size:0.65rem;">Submitted At</small>
          <strong class="h6">{{ $todayHandover->created_at->format('M d, h:i A') }}</strong>
          @if($todayHandover->notes)
            <div class="mt-2 pt-2 border-top text-left px-2">
              <small class="text-muted d-block text-uppercase" style="font-size: 0.6rem;">Handover Note:</small>
              <p class="small mb-0 font-italic">{{ $todayHandover->notes }}</p>
            </div>
          @endif
        </div>
        <div class="col-4">
          <small class="text-muted text-uppercase d-block" style="font-size:0.65rem;">Accounting Status</small>
          @if($isVerified)
            <div class="text-success font-weight-bold mt-1">
              <i class="fa fa-check-circle mr-1"></i> VERIFIED
            </div>
            <small class="text-muted d-block mt-1" style="font-size:0.65rem;">
              Confirmed at {{ $todayHandover->verified_at ? $todayHandover->verified_at->format('h:i A') : '--' }}
            </small>
          @else
            <div class="text-warning font-weight-bold mt-1">
              <i class="fa fa-clock-o mr-1"></i> PENDING
            </div>
            <small class="text-muted d-block mt-1" style="font-size:0.65rem;">
              Awaiting confirmation
            </small>
          @endif
        </div>
      </div>

      {{-- ACTIONS FOOTER --}}
      <div class="pt-3 border-top mt-2">
        {{-- PENDING STATE ACTIONS --}}
        @if(!$isVerified)
          <div class="text-center py-2">
            @if($isAccountant)
              <button class="btn btn-success btn-lg px-5 shadow verify-handover-btn font-weight-bold" data-id="{{ $todayHandover->id }}" style="border-radius:8px;">
                <i class="fa fa-check-circle mr-2"></i> Verify &amp; Confirm Receipt
              </button>
            @else
              <div class="py-2">
                 <div class="spinner-border text-primary spinner-border-sm mb-2" role="status">
                    <span class="sr-only">Loading...</span>
                 </div>
                 <h6 class="text-primary font-weight-bold">Awaiting Verification by Accountant</h6>
                 <p class="text-muted small mb-0">Your handover has been submitted. Click "Refresh" once the accountant confirms receipt.</p>
                 
                 {{-- Shortage Summary for Counter Staff --}}
                 @php
                    $shortWaiters = [];
                    foreach($waiters as $data) {
                        if ($data['difference'] < 0) {
                            $shortWaiters[] = [
                                'name' => $data['waiter']->full_name,
                                'amount' => abs($data['difference'])
                            ];
                        }
                    }
                 @endphp
                 
                 @if(!empty($shortWaiters))
                  <div class="mt-4 p-2 bg-white border rounded shadow-sm mx-auto" style="max-width: 450px;">
                      <h6 class="text-danger font-weight-bold small mb-2 text-center border-bottom pb-1">
                          <i class="fa fa-exclamation-triangle"></i> ATTENTION: STAFF SHORTAGES
                      </h6>
                      <div class="d-flex flex-wrap justify-content-center">
                          @foreach($shortWaiters as $sw)
                              <span class="badge m-1 py-2 px-3" style="border: 1px solid #dc3545; color: #dc3545; background: #fff5f5; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">
                                  <i class="fa fa-user mr-1"></i> {{ $sw['name'] }}: 
                                  <span class="ml-1">-TSh {{ number_format($sw['amount'], 0) }}</span>
                              </span>
                          @endforeach
                      </div>
                      <p class="small text-muted mt-2 mb-0 px-2">Please ensure you have received written explanations for these shortages before closing.</p>
                  </div>
                 @endif
              </div>
              <div class="mt-2">
                 <button class="btn btn-outline-warning btn-sm" onclick="location.reload();">
                    <i class="fa fa-refresh mr-1"></i> Refresh Status
                 </button>
              </div>
            @endif
          </div>

        {{-- VERIFIED STATE ACTIONS --}}
        @else
          {{-- Summary Mini-Stats --}}
          @if($isAccountant)
            {{-- Summary Mini-Stats (Accountant Only) --}}
            <div class="row text-center mb-4 mt-2">
              <div class="col-4">
                <small class="text-muted d-block text-uppercase" style="font-size:0.6rem;">Profit</small>
                <span class="h6 font-weight-bold text-success">TSh {{ number_format($stockProfit, 0) }}</span>
              </div>
              <div class="col-4">
                <small class="text-muted d-block text-uppercase" style="font-size:0.6rem;">Vault Cash</small>
                <span class="h6 font-weight-bold">TSh {{ number_format($totalBusinessValue, 0) }}</span>
              </div>
              <div class="col-4">
                <small class="text-muted d-block text-uppercase" style="font-size:0.6rem;">New Float</small>
                <span class="h6 font-weight-bold text-info">TSh {{ number_format($rolloverFloat, 0) }}</span>
              </div>
            </div>
          @endif

          @if($isAccountant)
            @if($ledger && $ledger->status === 'open')
              <div class="row">
                <div class="col-6">
                  <button class="btn btn-danger btn-block font-weight-bold" data-toggle="modal" data-target="#counterExpenseModal" style="border-radius:8px;">
                    <i class="fa fa-minus-circle mr-2"></i> Add Expense
                  </button>
                </div>
                <div class="col-6">
                  <form action="{{ route('accountant.daily-master-sheet.close') }}" method="POST">
                    @csrf
                    <input type="hidden" name="ledger_id" value="{{ $ledger->id }}">
                    <input type="hidden" name="actual_closing_cash" value="{{ round($totalBusinessValue) }}">
                    <input type="hidden" name="profit_submitted_to_boss" value="{{ round($finalProfit) }}">
                    <input type="hidden" name="money_in_circulation" value="{{ round($moneyInCirculation) }}">
                    <input type="hidden" name="carried_forward" value="{{ round($rolloverFloat) }}">
                    <button class="btn btn-success btn-block font-weight-bold" type="submit" style="border-radius:8px;" onclick="return confirm('Archive today\'s values and sign out?')">
                      <i class="fa fa-lock mr-2"></i> Close Day
                    </button>
                  </form>
                </div>
              </div>
            @else
              <div class="alert alert-success py-2 mb-0 d-flex justify-content-between align-items-center" style="border-radius:8px;">
                <div>
                  <i class="fa fa-lock mr-2"></i> <strong>Day Closed.</strong> Ledger is finalized.
                </div>
              </div>
            @endif
          @endif

          <div class="mt-3 text-right">
            @if($isAccountant && (!$ledger || $ledger->status !== 'closed'))
              <button class="btn btn-link text-danger btn-sm undo-verify-btn p-0" data-id="{{ $todayHandover->id }}" style="font-size:0.7rem; text-decoration: none;">
                <i class="fa fa-undo mr-1"></i> Undo Accounting Verification
              </button>
            @endif

            @if($isAccountant && ($ledger && $ledger->status === 'closed'))
                <button class="btn btn-outline-danger btn-sm undo-close-day-btn" data-ledger-id="{{ $ledger->id }}" style="border-radius:20px;font-size:0.7rem;">
                  <i class="fa fa-unlock"></i> Reopen
                </button>
            @endif
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endif

<!-- Counter Expense Modal -->
@if($isAccountant && $ledger)
<div class="modal fade" id="counterExpenseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:12px;">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-minus-circle mr-2"></i> Log Shift Expense</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="counterExpenseForm">
        @csrf
        <input type="hidden" name="ledger_id" value="{{ $ledger->id }}">
        <div class="modal-body p-4">
          <div class="form-group mb-3">
            <label class="font-weight-bold">CATEGORY</label>
            <input type="text" name="category" class="form-control" list="counterExpenseCategories" placeholder="Select or type..." required>
            <datalist id="counterExpenseCategories">
              <option value="Restocking / Procurement">
              <option value="Transport / Fare">
              <option value="Staff Meals/Allowances">
              <option value="Cleaning & Maintenance">
              <option value="Miscellaneous">
            </datalist>
          </div>
          <div class="form-group mb-3">
            <label class="font-weight-bold">DESCRIPTION</label>
            <input type="text" name="description" class="form-control" placeholder="What was it for?" required>
          </div>
          <div class="form-group mb-3">
            <label class="font-weight-bold">AMOUNT (TSh)</label>
            <input type="number" name="amount" class="form-control form-control-lg font-weight-bold" placeholder="0" required min="1">
          </div>
          <div class="form-group">
            <label class="font-weight-bold small text-muted">DEDUCT FROM:</label>
            <select name="fund_source" class="form-control font-weight-bold border-danger" required>
              <option value="circulation" selected>Working Float (Circulation)</option>
              <option value="profit">Daily Earnings (Profit)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-4 font-weight-bold" id="submitCounterExpenseBtn">Record Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

<!-- Settle Shortage Modal -->
<div class="modal fade" id="settleShortageModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:12px;">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-money mr-2"></i> Settle Waiter Shortage</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="settleShortageForm">
        @csrf
        <input type="hidden" name="reconciliation_id" id="settle_reconciliation_id">
        <div class="modal-body p-4">
          <p>Record payment for shortage from <strong id="settle_waiter_name" class="text-danger"></strong>.</p>
          <div class="alert alert-light border p-2 mb-3">
             <small class="text-muted text-uppercase d-block">Current Shortage:</small>
             <strong class="h4 mb-0 text-danger">TSh <span id="settle_shortage_display">0</span></strong>
          </div>
          
          <div class="form-group mb-3">
            <label class="font-weight-bold">AMOUNT PAID (TSh)</label>
            <input type="number" name="amount" id="settle_amount" class="form-control form-control-lg font-weight-bold text-success" required>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">NOTES</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning font-weight-bold">Confirm Settlement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Orders Modal -->
<div class="modal fade" id="ordersModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Orders for <span id="modal-waiter-name"></span></h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="orders-content">
          <div class="text-center">
            <i class="fa fa-spinner fa-spin fa-3x"></i>
            <p>Loading orders...</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@if($isAccountant && isset($accountantLedger))
<!-- Quick Expense Modal for Accountant -->
<div class="modal fade" id="expenseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:12px;">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-minus-circle mr-2"></i> Log Shift Expense</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="quickExpenseForm">
        @csrf
        <input type="hidden" name="ledger_id" value="{{ $accountantLedger->id }}">
        <div class="modal-body p-4">
          <div class="form-group mb-3">
            <label class="font-weight-bold">CATEGORY</label>
            <input type="text" name="category" class="form-control" list="recExpenseCategories" placeholder="Select or type..." required>
            <datalist id="recExpenseCategories">
              <option value="Restocking / Procurement">
              <option value="Transport / Fare">
              <option value="Staff Meals/Allowances">
              <option value="Cleaning & Maintenance">
              <option value="Miscellaneous">
            </datalist>
          </div>
          <div class="form-group mb-3">
            <label class="font-weight-bold">DESCRIPTION</label>
            <input type="text" name="description" class="form-control" placeholder="What was it for?" required>
          </div>
          <div class="form-group mb-3">
            <label class="font-weight-bold">AMOUNT (TSh)</label>
            <input type="number" name="amount" class="form-control form-control-lg font-weight-bold" placeholder="0" required min="1">
          </div>
          <div class="form-group">
            <label class="font-weight-bold small text-muted">DEDUCT FROM:</label>
            <select name="fund_source" class="form-control font-weight-bold border-danger" required>
              <option value="circulation" selected>Working Float (Circulation)</option>
              <option value="profit">Daily Earnings (Profit)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-4 font-weight-bold" id="submitQuickExpenseBtn">Record Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@if($bar_shift)
<!-- Close Shift Modal -->
<div class="modal fade" id="closeShiftModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-lock"></i> Close Counter Shift</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form action="{{ route('bar.counter.close-shift', $bar_shift->id) }}" method="POST">
        @csrf
        <div class="modal-body p-4">
          <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> Closing your shift will finalize all your recorded sales for today. Please count your physical cash carefully.
          </div>
          
          <div class="bg-light p-3 rounded mb-4 border">
            <div class="row">
              <div class="col-6 text-center border-right">
                <small class="text-muted d-block font-weight-bold">Expected Cash</small>
                <h4 class="mb-0">TSh {{ number_format($bar_shift->expected_cash + $bar_shift->opening_cash) }}</h4>
                <small class="text-muted">(Incl. Opening Cash)</small>
              </div>
              <div class="col-6 text-center">
                <small class="text-muted d-block font-weight-bold">Digital Sales</small>
                <h4 class="mb-0">TSh {{ number_format($bar_shift->digital_revenue) }}</h4>
                <small class="text-muted">(Via Mobile/Bank)</small>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Total Physical Cash in Drawer (TSh)</label>
            <input type="number" name="actual_cash" class="form-control form-control-lg font-weight-bold text-primary" 
                   placeholder="Enter total cash counted" required min="0" value="{{ $bar_shift->expected_cash + $bar_shift->opening_cash }}">
            <small class="form-text text-muted">Enter the total amount of physical cash currently in the counter drawer.</small>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Closing Notes (Optional)</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Any discrepancies or notes about the shift..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
          <button type="button" class="btn btn-light btn-lg flex-fill" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-lg flex-fill font-weight-bold">CONFIRM & CLOSE SHIFT</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
  // Initialize DataTables
  if ($('#waiters-table').length > 0) {
    const table = $('#waiters-table').DataTable({
      "pageLength": 25,
      "responsive": true,
      "language": {
        "search": "_INPUT_",
        "searchPlaceholder": "Search Waiter..."
      }
    });

    // Custom Status Filter
    $('#status-filter').on('change', function() {
      const statusValue = $(this).val();
      if (statusValue) {
        // Regex exactly matches the selected status (case-insensitive) in the 12th column (Status)
        table.column(11).search('^' + statusValue + '$', true, false).draw();
      } else {
        // Clear filter
        table.column(11).search('').draw();
      }
    });
  }
  
  // View orders button
  $(document).on('click', '.view-orders-btn', function() {
    const waiterId = $(this).data('waiter-id');
    const waiterName = $(this).data('waiter-name');
    const date = '{{ $date }}';
    const isCounterOnlyView = '{{ Route::currentRouteName() }}' === 'bar.counter.reconciliation';
    
    $('#modal-waiter-name').text(waiterName);
    $('#orders-content').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Loading orders...</p></div>');
    $('#ordersModal').modal('show');
    
    $.ajax({
      url: '{{ Route::currentRouteName() === "accountant.counter.reconciliation" ? route("accountant.counter.reconciliation.waiter-orders", ":id") : route("bar.counter.reconciliation.waiter-orders", ":id") }}'.replace(':id', waiterId),
      method: 'GET',
      data: { date: date },
      success: function(response) {
        if (response.success && response.orders.length > 0) {
          let html = '<div class="table-responsive"><table class="table table-sm">';
          html += '<thead><tr><th>Order #</th><th>Time</th><th>Platform</th><th>Bar Items (Drinks)</th><th>Bar Amount</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead><tbody>';
          
          response.orders.forEach(function(order) {
            // Calculate bar amount (from items - drinks)
            let barAmount = 0;
            if (order.items && order.items.length > 0) {
              barAmount = order.items.reduce(function(sum, item) {
                return sum + (parseFloat(item.total_price) || 0);
              }, 0);
            }
            
            // Calculate food amount (from kitchen_order_items)
            let foodAmount = 0;
            if (!isCounterOnlyView && order.kitchen_order_items && order.kitchen_order_items.length > 0) {
              foodAmount = order.kitchen_order_items.reduce(function(sum, item) {
                return sum + (parseFloat(item.total_price) || 0);
              }, 0);
            }
            
            html += '<tr>';
            html += '<td><strong>' + order.order_number + '</strong></td>';
            html += '<td>' + new Date(order.created_at).toLocaleTimeString() + '</td>';
            html += '<td>';
            if (order.order_source) {
              const source = order.order_source.toLowerCase();
              let badgeClass = 'secondary';
              let displayText = order.order_source;
              if (source === 'mobile') { badgeClass = 'info'; displayText = 'Mobile'; }
              else if (source === 'web') { badgeClass = 'primary'; displayText = 'Web'; }
              else if (source === 'kiosk') { badgeClass = 'warning'; displayText = 'Kiosk'; }
              html += '<span class="badge badge-' + badgeClass + '">' + displayText + '</span>';
            } else {
              html += '<span class="text-muted">-</span>';
            }
            html += '</td>';
            html += '<td>';
            if (order.items && order.items.length > 0) {
              order.items.forEach(function(item) {
                let label = '';
                if ((item.sell_type || 'unit') === 'tot') {
                  const cat = (item.product_variant?.product?.category || '').toLowerCase();
                  let pName = 'Tot';
                  if (cat.includes('wine')) pName = 'Glass';
                  else if (cat.includes('spirit') || cat.includes('whiskey') || cat.includes('vodka') || cat.includes('gin')) pName = 'Shot';
                  
                  let plural = pName === 'Glass' ? 'Glasses' : (pName + 's');
                  label = (item.quantity > 1 ? plural : pName) + ' of ';
                }
                
                const pName = item.product_variant?.display_name || item.product_variant?.name || item.product_variant?.product?.name || 'N/A';
                html += '<span class="badge badge-primary">' + item.quantity + 'x ' + label + pName + '</span> ';
              });
            } else { html += '<span class="text-muted">-</span>'; }
            html += '</td>';
            html += '<td><strong>TSh ' + barAmount.toLocaleString() + '</strong></td>';
            html += '<td><strong>TSh ' + barAmount.toLocaleString() + '</strong></td>';
            html += '<td>';
            if (order.order_payments && order.order_payments.length > 0) {
              // Iterate through all payments if the NEW system is used
              order.order_payments.forEach(function(payment, idx) {
                if (idx > 0) html += '<hr class="my-1">';
                
                const method = payment.payment_method || 'N/A';
                const provider = (payment.mobile_money_number || 'MOBILE').toLowerCase();
                let displayLabel = method.toUpperCase();
                let badgeClass = 'secondary';
                
                if (method === 'cash') {
                  displayLabel = 'CASH';
                  badgeClass = 'warning';
                } else {
                  badgeClass = 'success';
                  if (provider.includes('mpesa')) displayLabel = 'M-PESA';
                  else if (provider.includes('mixx')) displayLabel = 'MIXX BY YAS';
                  else if (provider.includes('halo')) displayLabel = 'HALOPESA';
                  else if (provider.includes('tigo')) displayLabel = 'TIGO PESA';
                  else if (provider.includes('airtel')) displayLabel = 'AIRTEL MONEY';
                  else if (provider.includes('nmb')) displayLabel = 'NMB BANK';
                  else if (provider.includes('crdb')) displayLabel = 'CRDB BANK';
                  else if (provider.includes('kcb')) displayLabel = 'KCB BANK';
                }
                
                html += '<span class="badge badge-' + badgeClass + '">' + displayLabel + '</span>';
                html += '<div style="font-size: 0.8rem;" class="mt-1">';
                html += '<strong>TSh ' + parseFloat(payment.amount).toLocaleString() + '</strong>';
                if (payment.transaction_reference) {
                   html += '<br><small class="text-muted"><i class="fa fa-hashtag"></i> Ref: ' + payment.transaction_reference + '</small>';
                }
                html += '</div>';
              });
            } else if (order.payment_method) {
              // Fallback for OLD system using order fields
              const method = order.payment_method;
              const providerName = (order.mobile_money_number || 'MOBILE').toLowerCase();
              let displayProvider = method.toUpperCase();
              let badgeClass = method === 'cash' ? 'warning' : 'success';
              
              if (method === 'mobile_money' || method === 'bank') {
                if (providerName.includes('mpesa')) displayProvider = 'M-PESA';
                else if (providerName.includes('mixx')) displayProvider = 'MIXX BY YAS';
                else if (providerName.includes('halo')) displayProvider = 'HALOPESA';
                else if (providerName.includes('tigo')) displayProvider = 'TIGO PESA';
                else if (providerName.includes('airtel')) displayProvider = 'AIRTEL MONEY';
                else if (providerName.includes('nmb')) displayProvider = 'NMB BANK';
                else if (providerName.includes('crdb')) displayProvider = 'CRDB BANK';
                else if (providerName.includes('kcb')) displayProvider = 'KCB BANK';
              }
              
              html += '<span class="badge badge-' + badgeClass + '">' + displayProvider + '</span>';
              if (order.transaction_reference) {
                html += '<br><small class="text-muted" style="font-size: 0.8rem; margin-top: 3px; display: block;"><i class="fa fa-hashtag"></i> Ref: ' + order.transaction_reference + '</small>';
              }
            } else {
              html += '<span class="badge badge-secondary">Not Set</span>';
            }
            html += '</td>';
            html += '<td>';
            const totalPaid = (order.order_payments && order.order_payments.length > 0)
              ? order.order_payments.reduce(function(sum, p) { return sum + (parseFloat(p.amount) || 0); }, 0)
              : (parseFloat(order.paid_amount) || 0);
            const orderTotal = barAmount || 0;
            const isFullyPaid = totalPaid >= orderTotal - 0.01 && totalPaid > 0;
            const isPartialPaid = totalPaid > 0 && totalPaid < orderTotal - 0.01;

            if (isFullyPaid) {
              html += '<span class="badge badge-success">Paid</span>';
              if (order.paid_by_waiter && order.paid_by_waiter.full_name) {
                html += '<br><small class="text-muted">By ' + order.paid_by_waiter.full_name + '</small>';
              }
            } else if (isPartialPaid) {
              const outstanding = orderTotal - totalPaid;
              html += '<span class="badge badge-warning">Partial</span>';
              html += '<br><small class="text-danger font-weight-bold">TSh ' + outstanding.toLocaleString() + ' outstanding</small>';
            } else {
              html += '<span class="badge badge-danger">Unpaid</span>';
            }
            html += '</td>';
            html += '</tr>';
          });
          html += '</tbody></table></div>';
          $('#orders-content').html(html);
        } else {
          $('#orders-content').html('<div class="alert alert-info">No orders found.</div>');
        }
      },
      error: function(xhr) {
        console.error('Error loading orders:', xhr);
        const errorMsg = xhr.responseJSON?.error || xhr.statusText || 'Error loading orders';
        $('#orders-content').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' + errorMsg + '</div>');
      }
    });
  });
  
  // Verify reconciliation button
  $(document).on('click', '.verify-btn', function() {
    const reconciliationId = $(this).data('reconciliation-id');
    const btn = $(this);
    Swal.fire({
      title: 'Verify Reconciliation?',
      text: 'Are you sure you want to verify this reconciliation?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, Verify',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying...');
        $.ajax({
          url: '{{ Route::currentRouteName() === "accountant.counter.reconciliation" ? route("accountant.counter.verify-reconciliation", ":id") : route("bar.counter.verify-reconciliation", ":id") }}'.replace(':id', reconciliationId),
          method: 'POST',
          data: { _token: '{{ csrf_token() }}' },
          success: function(response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Verified!', text: 'Reconciliation verified successfully.', timer: 2000, timerProgressBar: true }).then(() => { location.reload(); });
            }
          },
          error: function(xhr) {
            const error = xhr.responseJSON?.error || 'Failed to verify reconciliation';
            Swal.fire({ icon: 'error', title: 'Error', text: error });
            btn.prop('disabled', false).html('<i class="fa fa-check"></i> Verify');
          }
        });
      }
    });
  });
  
  // Mark all orders as paid
  $(document).on('click', '.mark-all-paid-btn', function() {
    const waiterId = $(this).data('waiter-id');
    const date = $(this).data('date');
    const totalAmount = parseFloat($(this).data('total-amount'));
    const recordedAmount = parseFloat($(this).data('recorded-amount')) || 0;
    const submittedAmount = parseFloat($(this).data('submitted-amount')) || 0;
    const difference = parseFloat($(this).data('difference')) || 0;
    const waiterName = $(this).data('waiter-name') || 'this waiter';
    const breakdown = $(this).data('breakdown') || {};
    const btn = $(this);
    
    let digitalTotal = Object.values(breakdown).reduce((a, b) => a + (parseFloat(b) || 0), 0);
    let expectedCash = Math.max(0, totalAmount - digitalTotal);
    
    let platformHtml = '<div class="row px-2">';
    // Add Cash field first (Pre-filled with whatever is required to reach Expected Total)
    platformHtml += `
      <div class="col-md-12 px-1 mb-2">
        <label class="small font-weight-bold mb-1">PHYSICAL CASH COLLECTION</label>
        <div class="input-group input-group-sm">
          <div class="input-group-prepend"><span class="input-group-text">TSh</span></div>
          <input type="number" class="form-control platform-input" data-platform="cash" value="${expectedCash}" placeholder="0">
        </div>
        <small class="text-muted" style="font-size: 11px;">Expected cash to balance the shift.</small>
      </div>
    `;
    
    // Add Digital platforms (Locked - waitered already recorded these)
    Object.keys(breakdown).forEach(platform => {
      platformHtml += `
        <div class="col-md-6 px-1 mb-2">
          <label class="small font-weight-bold mb-1 text-muted"><i class="fa fa-lock"></i> ${platform.toUpperCase()}</label>
          <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text bg-light">TSh</span></div>
            <input type="number" class="form-control platform-input text-muted" style="background-color: #f8f9fa;" data-platform="${platform}" value="${breakdown[platform]}" readonly>
          </div>
        </div>
      `;
    });
    platformHtml += '</div>';

    const remainingAmount = totalAmount - submittedAmount;
    const defaultSubmitAmount = submittedAmount > 0 ? Math.max(0, remainingAmount) : (recordedAmount > 0 ? recordedAmount : totalAmount);
    
    let initialDiff = expectedCash + digitalTotal - totalAmount;
    let differenceHtml = initialDiff > 0 ? `<span class="text-success">+TSh ${Math.abs(initialDiff).toLocaleString()}</span>` : (initialDiff < 0 ? `<span class="text-danger">TSh ${initialDiff.toLocaleString()}</span>` : `<span class="text-muted">TSh 0</span>`);
    
    Swal.fire({
      title: 'Submit Payment',
      width: '600px',
      html: `
        <div class="text-left">
          <p class="mb-2">Record actual collections for <strong>${waiterName}</strong>. <br><small class="text-info"><i class="fa fa-info-circle"></i> Note: All 'Served' orders will be automatically marked as 'Paid' upon submission.</small></p>
          <div class="alert alert-light border p-2 mb-3">
            <div class="row small"><div class="col-6">Expected:</div><div class="col-6 text-right"><strong>TSh ${totalAmount.toLocaleString()}</strong></div></div>
            ${recordedAmount > 0 ? `<div class="row small mt-1"><div class="col-6">System Recorded:</div><div class="col-6 text-right text-info"><strong>TSh ${recordedAmount.toLocaleString()}</strong></div></div>` : ''}
            <div class="row small mt-1"><div class="col-6 font-weight-bold">Live Difference:</div><div class="col-6 text-right" id="dynamic-difference"><strong>${differenceHtml}</strong></div></div>
          </div>
          
          <div id="platform-breakdown-container">
            ${platformHtml}
          </div>
          
          <hr class="my-3">
          
          <div class="form-group mb-0">
            <label class="font-weight-bold">Total Amount to Submit:</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">TSh</span></div>
              <input type="number" id="payment-amount" class="form-control font-weight-bold text-primary" value="${defaultSubmitAmount > 0 ? defaultSubmitAmount : ''}" readonly>
            </div>
            <small class="text-muted">This is automatically summed from the individual platform fields above.</small>
          </div>
          
          <hr class="my-3">
          
          <div class="form-group mb-0">
            <label class="font-weight-bold mb-1">Reason for Shortage / Notes</label>
            <textarea id="waiter-notes" class="form-control" rows="2" placeholder="Required if submitting less than Expected..."></textarea>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Submit Payment',
      didOpen: () => {
        const updateTotal = () => {
          let total = 0;
          $('.platform-input').each(function() {
            total += parseFloat($(this).val()) || 0;
          });
          $('#payment-amount').val(total);
          
          let currentDiff = total - totalAmount;
          let newDiffHtml = currentDiff > 0 ? `<span class="text-success">+TSh ${Math.abs(currentDiff).toLocaleString()}</span>` : (currentDiff < 0 ? `<span class="text-danger">TSh ${currentDiff.toLocaleString()}</span>` : `<span class="text-muted">TSh 0</span>`);
          $('#dynamic-difference').html(`<strong>${newDiffHtml}</strong>`);
          
          if(total < totalAmount && total > 0) {
              $('#waiter-notes').attr('placeholder', '(REQUIRED) Explain why collection is short...').addClass('border-warning');
          } else {
              $('#waiter-notes').attr('placeholder', 'Optional notes...').removeClass('border-warning');
          }
        };
        $('.platform-input').on('input', updateTotal);
        updateTotal(); // Run initially
      },
      preConfirm: () => {
        const amount = parseFloat(document.getElementById('payment-amount').value);
        const notes = document.getElementById('waiter-notes').value.trim();
        
        if (!amount || amount <= 0) { Swal.showValidationMessage('Enter a valid amount'); return false; }
        
        if (amount < totalAmount && notes === '') {
            Swal.showValidationMessage('Shortage detected: Please write a reason in the Notes field');
            return false;
        }
        
        const finalBreakdown = {};
        $('.platform-input').each(function() {
          const platform = $(this).data('platform');
          finalBreakdown[platform] = parseFloat($(this).val()) || 0;
        });
        
        return { amount: amount, breakdown: finalBreakdown, notes: notes };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');
        $.ajax({
          url: '{{ route("bar.counter.mark-all-paid") }}',
          method: 'POST',
          data: { 
            _token: '{{ csrf_token() }}', 
            waiter_id: waiterId, 
            date: date, 
            submitted_amount: result.value.amount,
            breakdown: result.value.breakdown,
            notes: result.value.notes
          },
          success: function(response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Success!', text: 'Reconciliation submitted.', timer: 2000 }).then(() => { location.reload(); });
            }
          },
          error: function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.error || 'Failed' });
            btn.prop('disabled', false).html('<i class="fa fa-hand-holding-usd"></i> Reconcile');
          }
        });
      }
    });
  });

  
  // Reset reconciliation button
  $(document).on('click', '.reset-btn', function() {
    const reconciliationId = $(this).data('reconciliation-id');
    const btn = $(this);
    Swal.fire({
      title: 'Reset Reconciliation?',
      text: 'This will reopen the staff row so you can adjust the submitted amount. Continue?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, Reset',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Resetting...');
        $.ajax({
          url: '{{ route("bar.counter.reset-reconciliation", ":id") }}'.replace(':id', reconciliationId),
          method: 'POST',
          data: { _token: '{{ csrf_token() }}' },
          success: function(response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Reset!', text: 'Row reopened.', timer: 2000 }).then(() => { location.reload(); });
            }
          },
          error: function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.error || 'Failed to reset' });
            btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Reset');
          }
        });
      }
    });
  });

  // Auto-calculate handover total
  $('.handover-input').on('input', function() {
    let total = 0;
    $('.handover-input').each(function() {
      total += parseFloat($(this).val()) || 0;
    });
    $('#handover-total').text('TSh ' + total.toLocaleString());
  });
  
  // Verify Handover Button (Accountant)
  $(document).on('click', '.verify-handover-btn', function() {
    const handoverId = $(this).data('id');
    const btn = $(this);
    
    Swal.fire({
      title: 'Confirm Money Receipt?',
      text: 'By verifying, you confirm you have received the physical and digital funds. This will consolidate all verified money into your Cash Vault for the Daily Master Sheet.',
      icon: 'info',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      confirmButtonText: 'Yes, I Received it',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing Vault...');
        $.ajax({
          url: '{{ route("accountant.counter.handover.verify", ":id") }}'.replace(':id', handoverId),
          method: 'POST',
          data: { _token: '{{ csrf_token() }}' },
          success: function(response) {
            if (response.success) {
              Swal.fire({ 
                icon: 'success', 
                title: 'Funds Consolidated!', 
                text: response.message,
                confirmButtonText: 'Great'
              }).then(() => { location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: response.error || 'Failed to verify' });
              btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Confirm Receipt & Consolidate All Funds as Cash');
            }
          },
          error: function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Server error occurred.' });
            btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Confirm Receipt & Consolidate All Funds as Cash');
          }
        });
      }
    });
  });

  // Trigger calculation on load
  $('.handover-input').first().trigger('input');

  // Reset Handover Button
  $(document).on('click', '.reset-handover-btn', function() {
    const date = $(this).data('date');
    const btn = $(this);
    Swal.fire({
      title: 'Reset Entire Handover?',
      text: 'This will cancel your handover and allow you to adjust each waiter row again. Continue?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Yes, Reset Everything'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Resetting...');
        $.ajax({
          url: '{{ route("bar.counter.reset-handover") }}',
          method: 'POST',
          data: { _token: '{{ csrf_token() }}', date: date },
          success: function(response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Reset!', text: 'The day has been re-opened.', timer: 2000 }).then(() => { location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: response.error || 'Failed to reset' });
              btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Reset Handover');
            }
          },
          error: function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Server error' });
            btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Reset Handover');
          }
        });
      }
    });
  });

  // Handover Validation & Confirmation
  $('form[action="{{ route("bar.counter.handover") }}"]').on('submit', function(e) {
      e.preventDefault();
      const form = this;
      const btn = $(form).find('button[type="submit"]');
      const totalDeclaration = parseFloat($('#handover-total').text().replace(/[^0-9.-]+/g,"")) || 0;
      const systemTotal = {{ isset($overallTotalHandover) ? $overallTotalHandover : 0 }};
      const notes = $('#handover-notes').val().trim();
      
      if (totalDeclaration < systemTotal && notes === '') {
          $('#notes-required').show();
          $('#handover-notes').addClass('is-invalid').focus();
          
          Swal.fire({
              icon: 'warning',
              title: 'Shortage Detected',
              text: 'Since your declaration is less than the calculated amount, you must provide a reason in the Notes field.',
              confirmButtonColor: '#ff9800'
          });
          return false;
      }
      
      Swal.fire({
          title: 'Finalize Handover?',
          text: "You are about to lock this shift and send TSh " + totalDeclaration.toLocaleString() + " to the Accountant. Are you sure?",
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#28a745',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Yes, Submit Handover',
          cancelButtonText: 'Review Again'
      }).then((result) => {
          if (result.isConfirmed) {
              btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');
              form.submit();
          }
      });
  });

  // Live monitor handover input changes to show/hide required hint
  $('.handover-input').on('input', function() {
      const systemTotal = {{ isset($overallTotalHandover) ? $overallTotalHandover : 0 }};
      setTimeout(function() {
          const currentTotal = parseFloat($('#handover-total').text().replace(/[^0-9.-]+/g,"")) || 0;
          if (currentTotal < systemTotal) {
              $('#notes-required').fadeIn();
              $('label:has(#notes-required)').addClass('text-danger');
          } else {
              $('#notes-required').fadeOut();
              $('label:has(#notes-required)').removeClass('text-danger');
              $('#handover-notes').removeClass('is-invalid');
          }
      }, 100);
  });

  // Quick Expense Submission
  $('#quickExpenseForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $('#submitQuickExpenseBtn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
    $.ajax({
      url: "{{ route('accountant.daily-master-sheet.expense') }}",
      method: "POST",
      data: $(this).serialize(),
      success: function(response) {
        if(response.success) { 
            Swal.fire({ icon: 'success', title: 'Expense Recorded!', text: 'The deduction has been added to the master ledger.', timer: 1500 }).then(() => { location.reload(); });
        } else { 
           Swal.fire('Error', response.error || 'Failed to log expense', 'error');
           $btn.prop('disabled', false).html('Record Expense'); 
        }
      },
      error: function(xhr) { 
        let errorMsg = xhr.responseJSON?.error || 'Connection error.';
        Swal.fire('Deduction Failed', errorMsg, 'error');
        $btn.prop('disabled', false).html('Record Expense'); 
      }
    });
  });

  // Counter Staff Expense Form Submission
  $('#counterExpenseForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $('#submitCounterExpenseBtn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
    $.ajax({
      url: "{{ route('accountant.daily-master-sheet.expense') }}",
      method: "POST",
      data: $(this).serialize(),
      success: function(response) {
        if (response.success) {
          Swal.fire({ 
            icon: 'success', 
            title: 'Expense Recorded!', 
            text: 'The expense has been deducted from the ledger.', 
            timer: 1500 
          }).then(() => { 
            location.reload(); 
          });
        } else {
          Swal.fire('Error', response.error || 'Failed to log expense', 'error');
          $btn.prop('disabled', false).html('Record Expense');
        }
      },
      error: function(xhr) {
        let errorMsg = xhr.responseJSON?.error || 'Connection error.';
        Swal.fire('Deduction Failed', errorMsg, 'error');
        $btn.prop('disabled', false).html('Record Expense');
      }
    });
  });

  // Undo Verification Handler
  $(document).on('click', '.undo-verify-btn', function() {
    const handoverId = $(this).data('id');
    const btn = $(this);
    Swal.fire({
      title: 'Undo Verification?',
      text: 'This will remove the funds from your cash vault and set the handover back to pending. Continue?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Yes, Undo Verify',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Undoing...');
        $.ajax({
          url: '{{ route("accountant.counter.handover.undo-verify", ":id") }}'.replace(':id', handoverId),
          method: 'POST',
          data: { _token: '{{ csrf_token() }}' },
          success: function(response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Undone!', text: 'Verification reversed successfully.', timer: 2000 }).then(() => { location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: response.error || 'Failed to undo' });
              btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Undo Verification');
            }
          },
          error: function(xhr) {
             let errorMsg = xhr.responseJSON?.error || 'Server error';
             Swal.fire({ icon: 'error', title: 'Error', text: errorMsg });
             btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Undo Verification');
          }
        });
      }
    });
  });

  // Undo Close Day Handler
  $(document).on('click', '.undo-close-day-btn', function() {
    const ledgerId = $(this).data('ledger-id');
    const btn = $(this);
    Swal.fire({
      title: 'Reopen Day?',
      text: 'This will unlock the ledger and allow you to add expenses or modify the daily totals. Proceed?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Yes, Reopen Day',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
        $.ajax({
          url: "{{ route('accountant.daily-master-sheet.undo-close') }}",
          method: 'POST',
          data: { _token: '{{ csrf_token() }}', ledger_id: ledgerId },
          success: function(response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Day Reopened!', text: response.message, timer: 2000 }).then(() => { location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: response.error || 'Failed to reopen day' });
              btn.prop('disabled', false).html('<i class="fa fa-unlock"></i> Reopen Day');
            }
          },
          error: function(xhr) {
             let errorMsg = xhr.responseJSON?.error || 'Server error';
             Swal.fire({ icon: 'error', title: 'Error', text: errorMsg });
             btn.prop('disabled', false).html('<i class="fa fa-unlock"></i> Reopen Day');
          }
        });
      }
    });
  });

  // Delete Expense Handler
  $(document).on('click', '.delete-expense-btn', function(e) {
    e.preventDefault();
    const expId = $(this).data('id');
    const btn = $(this);
    Swal.fire({
      title: 'Delete Expense?',
      text: 'This will remove the expense and recalculate the shift ledger.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Yes, Delete',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
          url: '{{ route("accountant.daily-master-sheet.delete-expense", ":id") }}'.replace(':id', expId),
          method: 'POST',
          data: { _token: '{{ csrf_token() }}' },
          success: function(response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Expense removed.', timer: 1500 }).then(() => { location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: response.error || 'Failed to delete expense' });
              btn.prop('disabled', false).html('<i class="fa fa-times"></i>');
            }
          },
          error: function(xhr) {
             let errorMsg = xhr.responseJSON?.error || 'Server error';
             Swal.fire({ icon: 'error', title: 'Error', text: errorMsg });
             btn.prop('disabled', false).html('<i class="fa fa-times"></i>');
          }
        });
      }
    });
  });

  // Handle Settle Shortage Click
  $(document).on('click', '.settle-shortage-btn', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    const shortage = $(this).data('shortage');
    
    $('#settle_reconciliation_id').val(id);
    $('#settle_waiter_name').text(name);
    $('#settle_shortage_display').text(Number(shortage).toLocaleString());
    $('#settle_amount').val(shortage);
    $('#settleShortageModal').modal('show');
  });

  // Submit Shortage Settlement
  $('#settleShortageForm').on('submit', function(e) {
    e.preventDefault();
    const submitBtn = $(this).find('button[type="submit"]');
    submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

    $.ajax({
      url: '{{ route("accountant.counter.settle-shortage") }}',
      method: 'POST',
      data: $(this).serialize(),
      success: function(response) {
        if (response.success) {
          Swal.fire('Success!', response.message, 'success').then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Error', response.error || 'Failed to settle shortage', 'error');
          submitBtn.prop('disabled', false).text('Confirm Settlement');
        }
      },
      error: function(xhr) {
        submitBtn.prop('disabled', false).text('Confirm Settlement');
        const error = xhr.responseJSON?.error || 'Failed to settle shortage';
        Swal.fire('Error', error, 'error');
      }
    });

  });
});
</script>

@endpush


