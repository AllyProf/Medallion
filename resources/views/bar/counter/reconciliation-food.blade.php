@extends('layouts.dashboard')

@section('title', 'Food & Kitchen Reconciliation')

@push('styles')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<style>
  #food-waiters-table { border-collapse: collapse !important; border-radius: 8px; overflow: hidden; }
  #food-waiters-table th, #food-waiters-table td { vertical-align: middle; white-space: nowrap; }
  .audit-col-bg { background-color: rgba(0, 150, 136, 0.03); }
  .widget-small { margin-bottom: 15px; border-radius: 12px; }
  .badge { font-weight: 600; padding: 5px 8px; }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-cutlery"></i> Food & Kitchen Reconciliation</h1>
    <p>Verify waiter food sales and Chef physical handover.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item">Accountant</li>
    <li class="breadcrumb-item">Food Reconciliation</li>
  </ul>
</div>

<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <form method="GET" action="{{ route('accountant.food.reconciliation') }}" class="form-inline">
        <div class="form-group mr-3">
          <label for="date" class="mr-2">Select Date:</label>
          <input type="date" name="date" id="date" class="form-control" value="{{ $date }}" required>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-search"></i> View Food Sales
        </button>
      </form>
    </div>
  </div>
</div>

@if(!empty($foodShiftClosedForToday))
<div class="row mb-3">
  <div class="col-md-12">
    <div class="alert alert-info border-info shadow-sm mb-0">
      <i class="fa fa-hourglass-half"></i>
      Food reconciliation for today is already verified and closed. Waiting for next shift day.
    </div>
  </div>
</div>
@endif

{{-- Summary Stats --}}
<div class="row mb-4">
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon shadow-sm">
      <i class="icon fa fa-shopping-basket fa-3x"></i>
      <div class="info">
        <h4 class="text-uppercase text-muted small">Total Food Sales</h4>
        <p><b>TSh {{ number_format($totalFoodSalesToday) }}</b></p>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="widget-small info coloured-icon shadow-sm">
      <i class="icon fa fa-handshake-o fa-3x"></i>
      <div class="info">
        <h4 class="text-uppercase text-muted small">Chef Handover</h4>
        @if($chefHandover)
          <p><b>TSh {{ number_format($chefHandover->amount) }}</b></p>
          <span class="badge badge-success">Submitted</span>
        @else
          <p><b>Awaiting</b></p>
          <span class="badge badge-warning">Pending Physical Submission</span>
        @endif
      </div>
    </div>
  </div>

  <div class="col-md-4">
    @php 
      $diff = $chefHandover ? ($chefHandover->amount - $totalFoodSalesToday) : 0;
    @endphp
    <div class="widget-small {{ $diff >= 0 ? 'success' : 'danger' }} coloured-icon shadow-sm">
      <i class="icon fa fa-balance-scale fa-3x"></i>
      <div class="info">
        <h4 class="text-uppercase text-muted small">Food Difference</h4>
        <p><b>TSh {{ number_format($diff) }}</b></p>
        <small class="text-muted">Handover vs System Total</small>
      </div>
    </div>
  </div>
</div>

<div class="row">
  {{-- Waiter List (Left) --}}
  <div class="col-md-7">
    <div class="tile">
      <h3 class="tile-title">Waiters Food Sales Breakdown</h3>
      <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped" id="food-waiters-table">
          <thead>
            <tr>
              <th>Staff</th>
              <th>Food Orders</th>
              <th>Food Sales</th>
              <th class="audit-col-bg">Expected</th>
              <th class="audit-col-bg">Reconciled</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($waiters as $data)
            <tr>
              <td>
                <strong>{{ $data['waiter']->full_name }}</strong><br>
                <small class="text-muted">{{ $data['waiter']->role->name ?? 'Staff' }}</small>
              </td>
              <td class="text-center"><span class="badge badge-info">{{ $data['food_orders_count'] }}</span></td>
              <td>TSh {{ number_format($data['food_sales']) }}</td>
              <td class="audit-col-bg"><strong>TSh {{ number_format($data['food_sales']) }}</strong></td>
              <td class="audit-col-bg">
                @if($data['reconciliation'])
                  <strong class="text-success">TSh {{ number_format($data['reconciliation']->submitted_amount) }}</strong>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                @if($data['reconciliation'])
                  @if($data['reconciliation']->status == 'reconciled')
                    <span class="badge badge-success"><i class="fa fa-check-circle"></i> Balanced</span>
                  @elseif($data['reconciliation']->status == 'partial' || $data['reconciliation']->status == 'shortage')
                    <span class="badge badge-danger"><i class="fa fa-minus-circle"></i> Shortage</span>
                  @else
                    <span class="badge badge-warning text-white"><i class="fa fa-clock"></i> Pending</span>
                  @endif
                @else
                  <span class="badge badge-warning text-white"><i class="fa fa-clock"></i> Pending</span>
                @endif
              </td>
              <td>
                <button class="btn btn-sm btn-info view-food-orders-btn" 
                        data-waiter-id="{{ $data['waiter']->id }}"
                        data-waiter-name="{{ $data['waiter']->full_name }}">
                  <i class="fa fa-eye"></i> Orders
                </button>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted">
                @if(!empty($foodShiftClosedForToday))
                  Today's food shift is closed. Waiting for the next shift day.
                @else
                  No waiter food records found for this date.
                @endif
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Chef Handover (Right - col-5) --}}
  <div class="col-md-5">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-user-circle"></i> Chef Handover</h3>
      
      @if(!$chefHandover)
        <p class="text-muted small mb-2"><i class="fa fa-info-circle"></i> Enter digital receipts to deduct from cash.</p>
        
        <form id="chef-handover-form" action="{{ route('accountant.chef.handover.submit') }}" method="POST">
          @csrf
          <input type="hidden" name="date" value="{{ $date }}">
          <input type="hidden" name="total_expected" id="total_expected_val" value="{{ $totalFoodSalesToday }}">
          
          <div class="row no-gutters mb-2">
            <div class="col-12 mb-2">
                <label class="small font-weight-bold mb-1">Chef in Charge</label>
                <select class="form-control form-control-sm" name="chef_id" required>
                @if(isset($chefs) && $chefs->count() > 0)
                    @foreach($chefs as $c)
                    <option value="{{ $c->id }}" {{ ($chef && $chef->id == $c->id) ? 'selected' : '' }}>{{ $c->full_name }}</option>
                    @endforeach
                @else
                    <option value="">No Active Chef</option>
                @endif
                </select>
            </div>
          </div>

          <div class="row no-gutters mb-2 border-bottom pb-2 bg-light p-2 rounded">
            <div class="col-4 pr-1">
                <label class="small font-weight-bold mb-1 text-muted">Opening (Bf)</label>
                <input type="number" name="opening_cash" id="opening_cash_input" class="form-control form-control-sm font-weight-bold" value="{{ $openingCash ?? 0 }}" oninput="updateCalculations()">
            </div>
            <div class="col-4 pr-1">
                <label class="small font-weight-bold mb-1 text-success">Today's Target</label>
                <input type="number" id="expected_cash_display_val" class="form-control form-control-sm font-weight-bold" value="{{ $totalFoodSalesToday }}" readonly>
            </div>
            <div class="col-4">
                <label class="small font-weight-bold mb-1 text-primary">Actual Cash</label>
                <input type="number" name="cash_amount" id="actual_cash_input" class="form-control form-control-sm font-weight-bold" required value="{{ $totalFoodSalesToday + ($openingCash ?? 0) }}">
            </div>
            <div id="cash_diff_alert" class="col-12 mt-1 small"></div>
          </div>

          <div class="mb-2">
            <label class="small font-weight-bold mb-1 text-muted">Digital Receipts</label>
            <div class="row no-gutters">
                @foreach(['mpesa' => 'M-PESA', 'tigopesa' => 'TIGO', 'airtelmoney' => 'AIRTEL', 'halopesa' => 'HALO', 'crdb' => 'CRDB', 'nmb' => 'NMB'] as $key => $label)
                    <div class="col-4 p-1">
                        <label class="small mb-0" style="font-size: 0.65rem;">{{ $label }}</label>
                        <input type="number" name="platform_amounts[{{ $key }}]" class="form-control form-control-sm digital-input" placeholder="0" min="0">
                    </div>
                @endforeach
            </div>
          </div>
          
          <div class="d-flex justify-content-between align-items-center mb-2 px-1">
              <span class="small font-weight-bold text-info">Digital Total:</span>
              <div style="width: 120px;">
                  <input type="number" id="total_digital_display" class="form-control form-control-sm text-right font-weight-bold text-info" value="0" readonly>
              </div>
          </div>

          <!-- Waiter Shortage Attribution (Multi-Waiter Support) -->
          <div class="p-2 border rounded border-danger mb-2" id="shortage_section" style="display:none; background: #fff5f5;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="text-danger font-weight-bold small mb-0" style="font-size: 0.7rem;"><i class="fa fa-user-times"></i> Assign Shortage to Waiter(s)</label>
                <button type="button" class="btn btn-xs btn-outline-danger" onclick="addShortageRow()"><i class="fa fa-plus"></i></button>
            </div>
            <div id="shortage-rows-container">
                <div class="row no-gutters mb-1 shortage-row">
                    <div class="col-7 pr-1">
                        <select class="form-control form-control-sm border-danger" name="shortages[0][waiter_id]">
                            <option value="">-- Waiter --</option>
                            @foreach($waiters as $data)
                            <option value="{{ $data['waiter']->id }}">{{ $data['waiter']->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <input type="number" name="shortages[0][amount]" class="form-control form-control-sm font-weight-bold text-danger border-danger assigned-shortage-amount" placeholder="Amount" oninput="validateShortageSum()">
                    </div>
                </div>
            </div>
            <div id="shortage_sum_warning" class="small text-danger mt-1 font-italic" style="display:none; font-size: 0.65rem;">
                <i class="fa fa-exclamation-triangle"></i> Total assigned doesn't match the gap!
            </div>
          </div>

          {{-- Dynamic Expenses Section --}}
          <div class="mb-2 p-2 bg-light rounded border border-danger">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="small font-weight-bold mb-0 text-danger"><i class="fa fa-shopping-cart"></i> Kitchen Expenses</label>
                <button type="button" class="btn btn-xs btn-danger" onclick="addExpenseRow()"><i class="fa fa-plus"></i></button>
            </div>
            <div id="expense-rows-container">
                <div class="row no-gutters mb-1 expense-row">
                    <div class="col-4 pr-1">
                        <input type="number" name="expenses[0][amount]" class="form-control form-control-sm expense-amount" placeholder="Amount" min="0" oninput="updateCalculations()">
                    </div>
                    <div class="col-8">
                        <input type="text" name="expenses[0][description]" class="form-control form-control-sm" placeholder="Description (e.g. Gas)">
                    </div>
                </div>
            </div>
          </div>

          <div class="form-group mb-2">
            <textarea name="notes" class="form-control form-control-sm" rows="1" placeholder="Notes..."></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-sm shadow-sm" id="chef-submit-btn" {{ (!isset($chefs) || $chefs->count() == 0) ? 'disabled' : '' }}>
            <i class="fa fa-save"></i> Save Handover
          </button>
        </form>
      @else
        <div class="text-center py-3 bg-light rounded border border-success">
          <i class="fa fa-check-circle fa-2x text-success mb-2"></i>
          <h5 class="text-success font-weight-bold mb-3">Handover Finalized</h5>
          
          <div class="px-3 text-left">
            <div class="d-flex justify-content-between small border-bottom mb-1 pb-1">
              <span>Physical Cash:</span>
              <strong>TSh {{ number_format($chefHandover->payment_breakdown['cash'] ?? 0) }}</strong>
            </div>

            @php 
              $digitalPlatforms = ['mpesa', 'tigopesa', 'airtelmoney', 'halopesa', 'crdb', 'nmb'];
              $digitalTotal = 0;
              foreach($digitalPlatforms as $p) {
                  $digitalTotal += ($chefHandover->payment_breakdown[$p] ?? 0);
              }
              $expenseTotal = 0;
              // Assuming expenses are handled separately or we need to sum them from a list
              // For the sake of the summary, we can show total digital and cash.
              $cashReceived = $chefHandover->payment_breakdown['cash'] ?? 0;
            @endphp
            
            @foreach($digitalPlatforms as $platform)
              @if(isset($chefHandover->payment_breakdown[$platform]) && $chefHandover->payment_breakdown[$platform] > 0)
                <div class="d-flex justify-content-between small text-info mb-0" style="font-size: 0.7rem;">
                  <span><i class="fa fa-caret-right"></i> {{ strtoupper($platform) }}:</span>
                  <span>TSh {{ number_format($chefHandover->payment_breakdown[$platform]) }}</span>
                </div>
              @endif
            @endforeach

            <div class="d-flex justify-content-between border-top font-weight-bold pt-1 mt-1 text-primary">
              <span>Final Net Cash:</span>
              <span>TSh {{ number_format($cashReceived) }}</span>
            </div>

            <div class="d-flex justify-content-between small text-muted">
              <span>Digital Total:</span>
              <span>TSh {{ number_format($digitalTotal) }}</span>
            </div>

            {{-- MULTI-WAITER SHORTAGE SUMMARY --}}
            @if(isset($chefHandover->payment_breakdown['attributed_shortages']) && count($chefHandover->payment_breakdown['attributed_shortages']) > 0)
              <div class="mt-3 p-2 bg-white border border-danger rounded small">
                <strong class="text-danger small" style="font-size: 0.65rem;"><i class="fa fa-warning"></i> Waiter Shortages:</strong>
                @foreach($chefHandover->payment_breakdown['attributed_shortages'] as $short)
                  <div class="d-flex justify-content-between border-bottom-dotted mb-1">
                    <span>{{ $short['waiter_name'] }}:</span>
                    <strong class="text-danger">TSh {{ number_format($short['amount']) }}</strong>
                  </div>
                @endforeach
              </div>
            @endif

            {{-- ITEMIZED EXPENSES SUMMARY --}}
            @if(isset($chefHandover->payment_breakdown['attributed_expenses']) && count($chefHandover->payment_breakdown['attributed_expenses']) > 0)
              <div class="mt-2 p-2 bg-white border border-dark rounded small">
                <strong class="text-dark small" style="font-size: 0.65rem;"><i class="fa fa-shopping-cart"></i> Kitchen Expenses:</strong>
                @foreach($chefHandover->payment_breakdown['attributed_expenses'] as $exp)
                  <div class="d-flex justify-content-between border-bottom-dotted mb-1">
                    <span>{{ $exp['description'] }}:</span>
                    <strong>TSh {{ number_format($exp['amount']) }}</strong>
                  </div>
                @endforeach
              </div>
            @endif

            @if($chefHandover->notes)
              <div class="mt-2 small text-muted italic">
                <strong>Notes:</strong> {{ $chefHandover->notes }}
              </div>
            @endif
          </div>
          
          <div class="mt-3 text-center px-2">
            <form id="reset-handover-form" action="{{ route('accountant.chef.handover.reset', $chefHandover->id) }}" method="POST">
                @csrf
                <button type="button" class="btn btn-sm btn-block btn-outline-danger" onclick="confirmResetHandover()">
                    <i class="fa fa-undo"></i> Reset This Handover
                </button>
            </form>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Food Orders Modal --}}
<div class="modal fade" id="foodOrdersModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title font-weight-bold text-dark">Food Orders Hub: <span id="modal-waiter-name"></span></h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body p-0" id="food-orders-content">
        <div class="text-center p-5"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i></div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
  
  // View Food Orders (AJAX)
  $('.view-food-orders-btn').click(function() {
    const waiterId = $(this).data('waiter-id');
    const waiterName = $(this).data('waiter-name');
    $('#modal-waiter-name').text(waiterName);
    $('#food-orders-content').html('<div class="text-center p-5"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i> Syncing records...</div>');
    $('#foodOrdersModal').modal('show');
    
    $.get('{{ route("accountant.food.reconciliation.waiter-orders", ":id") }}'.replace(':id', waiterId), { date: '{{ $date }}' }, function(response) {
      if(response.success) {
        let html = `<div class="p-3"><table class="table table-sm table-striped">
          <thead class="text-muted small uppercase">
            <tr><th>Order Ref</th><th>Items Description</th><th class="text-right">Price</th></tr>
          </thead><tbody>`;
          
        let renderedOrders = 0;
        response.orders.forEach(order => {
          let foodTotal = 0;
          let itemsHtml = '<div class="small">';
          order.kitchen_order_items.forEach(item => {
            foodTotal += parseFloat(item.total_price);
            itemsHtml += `<div class="mb-1"><strong>${item.food_item_name}</strong> <span class="text-muted">x${item.quantity}</span></div>`;
          });
          itemsHtml += '</div>';

          if (foodTotal <= 0) {
            return;
          }

          renderedOrders += 1;
          html += `<tr>
            <td class="font-weight-bold">#${order.order_number}<br><small class="text-muted">${new Date(order.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</small></td>
            <td>${itemsHtml}</td>
            <td class="text-right font-weight-bold">TSh ${foodTotal.toLocaleString()}</td>
          </tr>`;
        });
        if (renderedOrders === 0) {
          html += `<tr><td colspan="3" class="text-center text-muted py-4">No active food orders found for this waiter.</td></tr>`;
        }
        html += '</tbody></table></div>';
        $('#food-orders-content').html(html);
      }
    });
  });

  // SWEETALERT RESET CONFIRMATION
  window.confirmResetHandover = function() {
    Swal.fire({
      title: 'Reset Handover?',
      text: "This will delete the record and clear any attributed waiter shortages. This action cannot be undone!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, Reset Now',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        $('#reset-handover-form').submit();
      }
    });
  }

  // DYNAMIC EXPENSES
  let expenseIndex = 1;
  window.addExpenseRow = function() {
    let row = `
      <div class="row no-gutters mb-1 expense-row">
        <div class="col-4 pr-1">
          <input type="number" name="expenses[${expenseIndex}][amount]" class="form-control form-control-sm expense-amount" placeholder="Amount" min="0">
        </div>
        <div class="col-7">
          <input type="text" name="expenses[${expenseIndex}][description]" class="form-control form-control-sm" placeholder="Description">
        </div>
        <div class="col-1 text-center">
          <button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="$(this).closest('.expense-row').remove(); updateCalculations();"><i class="fa fa-times-circle"></i></button>
        </div>
      </div>
    `;
    $('#expense-rows-container').append(row);
    expenseIndex++;
  }

  // DYNAMIC SHORTAGES
  let shortageIndex = 1;
  window.addShortageRow = function() {
    let row = `
      <div class="row no-gutters mb-1 shortage-row">
        <div class="col-7 pr-1">
          <select class="form-control form-control-sm border-danger" name="shortages[${shortageIndex}][waiter_id]">
            <option value="">-- Waiter --</option>
            @foreach($waiters as $data)
            <option value="{{ $data['waiter']->id }}">{{ $data['waiter']->full_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-4">
          <input type="number" name="shortages[${shortageIndex}][amount]" class="form-control form-control-sm font-weight-bold text-danger border-danger assigned-shortage-amount" placeholder="Amount" oninput="validateShortageSum()">
        </div>
        <div class="col-1 text-center">
          <button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="$(this).closest('.shortage-row').remove(); validateShortageSum();"><i class="fa fa-times-circle"></i></button>
        </div>
      </div>
    `;
    $('#shortage-rows-container').append(row);
    shortageIndex++;
  }

  // VALIDATE SHORTAGE SUM
  window.validateShortageSum = function() {
    let salesTarget = parseFloat($('#total_expected_val').val()) || 0;
    let digitalTotal = 0; $('.digital-input').each(function() { digitalTotal += parseFloat($(this).val()) || 0; });
    let actualCash = parseFloat($('#actual_cash_input').val()) || 0;
    
    // Shortage = Target Minus Physical Cash Brought
    // Expenses don't fix the shortage
    let totalGap = (salesTarget - digitalTotal) - actualCash;
    
    // If no shortage, enable save button and hide warnings
    if (totalGap <= 0) {
      $('#shortage_sum_warning').hide();
      $('#chef-submit-btn').prop('disabled', false);
      return true;
    }

    let assignedTotal = 0;
    $('.assigned-shortage-amount').each(function() {
      assignedTotal += parseFloat($(this).val()) || 0;
    });

    if (Math.abs(assignedTotal - totalGap) > 1) { // 1 TSh tolerance
      $('#shortage_sum_warning').show().html(`<i class="fa fa-warning"></i> Assign exactly TSh ${totalGap.toLocaleString()} to enable Save.`);
      $('#chef-submit-btn').prop('disabled', true);
      return false;
    } else {
      $('#shortage_sum_warning').hide();
      $('#chef-submit-btn').prop('disabled', false);
      return true;
    }
  }

  // ACCOUNTING DEDUCTION LOGIC
  window.updateCalculations = function() {
    let openingCash = parseFloat($('#opening_cash_input').val()) || 0;
    let salesTarget = parseFloat($('#total_expected_val').val()) || 0;
    
    // 1. Sum Digital
    let digitalTotal = 0;
    $('.digital-input').each(function() {
      digitalTotal += parseFloat($(this).val()) || 0;
    });
    $('#total_digital_display').val(digitalTotal);

    // Target Cash Flow = (Opening + Sales) - Digital
    let grossCashTarget = (openingCash + salesTarget) - digitalTotal;

    // 2. Sum Expenses
    let expenseTotal = 0;
    $('.expense-amount').each(function() {
       expenseTotal += parseFloat($(this).val()) || 0;
    });
    
    // 3. Actual Cash
    let actualCash = parseFloat($('#actual_cash_input').val()) || 0;

    // 4. Shortage Logic
    let shortage = grossCashTarget - actualCash;
    
    // 6. FINAL NET TO SAFE (Current drawer physical cash - expenses)
    let netToSafe = actualCash - expenseTotal;
    
    // 7. TOTAL NET COLLECTION
    let totalCollection = (actualCash + digitalTotal) - expenseTotal;
    
    $('#expected_cash_display_val').val(grossCashTarget);
    
    // 8. Visual Waterfall Summary
    let waterfallHtml = `
        <div class="mt-2 text-muted" style="font-size: 0.7rem; border-top: 1px dashed #ccc; pt-1">
            <div class="d-flex justify-content-between text-muted"><span>Yesterday's Rollover (Bf):</span> <span>TSh ${openingCash.toLocaleString()}</span></div>
            <div class="d-flex justify-content-between"><span>Today's Food Sales:</span> <span>TSh ${salesTarget.toLocaleString()}</span></div>
            <div class="d-flex justify-content-between text-info border-bottom pb-1"><span>(-) Digital Payments:</span> <span>TSh ${digitalTotal.toLocaleString()}</span></div>
            <div class="d-flex justify-content-between font-weight-bold pt-1"><span>Target Cash Flow:</span> <span class="text-dark">TSh ${grossCashTarget.toLocaleString()}</span></div>
            
            <div class="mt-2 text-center p-1 bg-white border rounded">
                <div class="d-flex justify-content-between text-muted border-bottom mb-1 pb-1"><span><b>PHYSICAL CASH:</b></span> <span class="text-dark">TSh ${actualCash.toLocaleString()}</span></div>
                <div class="d-flex justify-content-between text-danger"><span>Missing From Goal:</span> <span>TSh ${shortage > 0 ? shortage.toLocaleString() : 0}</span></div>
                <div class="d-flex justify-content-between text-muted"><span>Kitchen Expenses:</span> <span>TSh ${expenseTotal.toLocaleString()}</span></div>
                <div class="d-flex justify-content-between font-weight-bold text-success border-top mt-1 pt-1" style="font-size: 0.8rem;">
                    <span>REMAINING IN DRAWER:</span> 
                    <span>TSh ${netToSafe.toLocaleString()}</span>
                </div>
            </div>

            <div class="mt-2 p-1 bg-light border-top">
               <div class="d-flex justify-content-between font-weight-bold text-info" style="font-size: 0.75rem;">
                  <span>TOTAL COLLECTION:</span>
                  <span>TSh ${totalCollection.toLocaleString()}</span>
               </div>
            </div>
        </div>
    `;

    let statusHtml = '';
    if (shortage > 0) {
      $('#shortage_section').show();
      statusHtml = `<div class="p-1 mb-1 bg-danger text-white rounded text-center small"><i class="fa fa-warning"></i> ${shortage.toLocaleString()} MISSING (Assign Below)</div>`;
      validateShortageSum();
    } else {
      $('#shortage_section').hide();
      statusHtml = shortage < 0 ? 
        `<div class="p-1 mb-1 bg-info text-white rounded text-center small">OVERAGE: TSh ${Math.abs(shortage).toLocaleString()}</div>` :
        '<div class="p-1 mb-1 bg-success text-white rounded text-center small">CASH BALANCED</div>';
    }

    $('#cash_diff_alert').html(statusHtml + waterfallHtml);
  }

  $(document).on('input', '.digital-input, #actual_cash_input, .expense-amount', updateCalculations);
  
  // Initialize on load
  updateCalculations();
});
</script>
@endpush
