@extends('layouts.dashboard')

@section('title', 'Kitchen Master Sheet History')

@push('styles')
<style>
  .excel-table { font-size: 0.9rem; width: 100% !important; margin-bottom: 0 !important; }
  .excel-table th { background: #212529 !important; color: white !important; font-size: 0.75rem; vertical-align: middle !important; padding: 12px 8px !important; }
  .excel-table td { vertical-align: middle !important; padding: 10px 12px !important; }
  .excel-table tr.main-row { cursor: pointer; transition: background 0.2s; }
  .excel-table tr.main-row:hover { background-color: #f1f3f5 !important; }
  .excel-table tr.main-row[aria-expanded="true"] { background-color: #e7f3ff !important; border-bottom: none !important; }
  
  .money-column { text-align: right; font-family: 'Courier New', Courier, monospace; }
  .status-badge { font-size: 0.65rem; padding: 2px 5px; border-radius: 3px; font-weight: bold; text-transform: uppercase; border: 1px solid #28a745; color: #28a745; }
  .badge-pending { border-color: #ffc107; color: #856404; }
  .badge-none { border-color: #6c757d; color: #6c757d; }

  .detail-row { background-color: #fcfcfc !important; }
  .detail-container { padding: 20px 40px; border-left: 5px solid #940000; box-shadow: inset 0 3px 6px rgba(0,0,0,0.08); background: #fdfdfd; }
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
@endpush

@section('content')
<div class="app-title d-print-none">
  <div>
    <h1><i class="fa fa-cutlery"></i> Kitchen Master History</h1>
    <p>Historical record of kitchen reconciliations and staff handovers.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Accountant</li>
    <li class="breadcrumb-item active">Kitchen History</li>
  </ul>
</div>

<div class="tile d-print-none mb-3 py-2">
  <form method="GET" action="{{ route('accountant.food-master-sheet.history') }}" class="row align-items-center">
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
       <a href="{{ route('accountant.food-master-sheet.history') }}" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-refresh"></i> Reset</a>
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
              <th colspan="3" class="text-center">DAILY COLLECTIONS</th>
              <th class="text-right">EXPECTED</th>
              <th class="text-right">EXPENSES</th>
              <th class="text-right text-success">NET TO SAFE</th>
              <th rowspan="2" class="text-center d-print-none">ACTIONS</th>
            </tr>
            <tr>
              <th class="text-right">CASH</th>
              <th class="text-right">DIGITAL</th>
              <th class="text-right">TOTAL</th>
              <th class="text-right">POS TOTAL</th>
              <th class="text-right">KITCHEN</th>
              <th class="text-right text-success">FINAL CASH</th>
            </tr>
          </thead>
          <tbody>
            @if($handovers->count() > 0)
              @foreach($handovers as $h)
                @php
                    $isShort = $h->shortage_total > 0;
                @endphp
                {{-- MAIN ROW --}}
                <tr class="main-row" data-toggle="collapse" data-target="#details-{{ $h->id }}">
                  <td class="text-center text-muted"><i class="fa fa-chevron-down"></i></td>
                  <td class="font-weight-bold text-primary">{{ \Carbon\Carbon::parse($h->handover_date)->format('d M, Y') }}</td>
                  <td class="text-center">
                    @if($h->is_boss_received)
                        <span class="status-badge"><i class="fa fa-check-circle"></i> RECEIVED</span>
                    @else
                        <span class="status-badge badge-pending">PENDING</span>
                    @endif
                  </td>
                  <td class="money-column">{{ number_format($h->amount) }}</td>
                  <td class="money-column">{{ number_format($h->digital_total) }}</td>
                  <td class="money-column font-weight-bold">{{ number_format($h->amount + $h->digital_total) }}</td>
                  <td class="money-column font-weight-bold bg-light">{{ number_format($h->expected_sales) }}</td>
                  <td class="money-column text-danger">({{ number_format($h->expenses_total) }})</td>
                  <td class="money-column text-success font-weight-bold" style="background: #fdfdfd">
                    {{ number_format($h->net_to_safe) }}
                  </td>
                  <td class="text-center d-print-none py-1">
                      <a href="{{ route('accountant.food.reconciliation', ['date' => \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d')]) }}" class="btn btn-outline-info btn-sm px-2 pt-0 pb-0 shadow-sm">
                        <i class="fa fa-eye"></i> View
                      </a>
                  </td>
                </tr>

                {{-- COLLAPSIBLE DETAIL ROW --}}
                <tr id="details-{{ $h->id }}" class="collapse detail-row">
                  <td colspan="10">
                    <div class="detail-container">
                      <div class="row">
                         {{-- EXPENSES --}}
                         <div class="col-md-6 border-right">
                           <h6 class="text-danger"><i class="fa fa-minus-circle"></i> KITCHEN EXPENDITURES</h6>
                           <table class="table table-sm nested-table mt-2">
                             <thead>
                               <tr>
                                 <th>Description</th>
                                 <th class="text-right">Amount</th>
                               </tr>
                             </thead>
                             <tbody>
                               @forelse($h->expense_list as $ex)
                                 <tr>
                                   <td>{{ $ex['description'] ?? 'Manual Expense' }}</td>
                                   <td class="text-right font-weight-bold text-danger">TSh {{ number_format($ex['amount'] ?? 0) }}</td>
                                 </tr>
                               @empty
                               @endforelse

                               {{-- FOOD PETTY CASH --}}
                               @foreach($h->food_petty_cash_list as $pc)
                                 <tr>
                                   <td><i class="fa fa-shopping-basket text-muted"></i> Petty Cash: {{ $pc->purpose }} <small class="text-muted">({{ $pc->recipient->full_name ?? 'Staff' }})</small></td>
                                   <td class="text-right font-weight-bold text-primary">TSh {{ number_format($pc->amount) }}</td>
                                 </tr>
                               @endforeach

                               @if(count($h->expense_list) == 0 && count($h->food_petty_cash_list) == 0)
                                 <tr><td colspan="2" class="text-center italic text-muted text-small">No expenses or petty cash logged.</td></tr>
                               @endif
                               <tr class="bg-light">
                                 <th class="text-right">Total Expenses:</th>
                                 <th class="text-right text-danger">TSh {{ number_format($h->expenses_total) }}</th>
                               </tr>
                             </tbody>
                           </table>

                           @if(count($h->shortage_list) > 0)
                            <div class="mt-4 border-top pt-3">
                               <h6 class="text-danger font-weight-bold" style="font-size:0.8rem;"><i class="fa fa-exclamation-triangle"></i> STAFF SHORTAGES</h6>
                               <table class="table table-sm nested-table mt-2">
                                 <thead><tr><th>Staff Member</th><th class="text-right">Shortage</th></tr></thead>
                                 <tbody>
                                   @foreach($h->shortage_list as $short)
                                   <tr class="text-danger">
                                      <td>{{ $short['waiter_name'] }}</td>
                                      <td class="text-right font-weight-bold">TSh {{ number_format($short['amount']) }}</td>
                                   </tr>
                                   @endforeach
                                 </tbody>
                               </table>
                            </div>
                           @endif
                         </div>

                         {{-- SUMMARY --}}
                         <div class="col-md-6">
                            <h6 class="text-success"><i class="fa fa-info-circle"></i> SHIFT SUMMARY</h6>
                            <div class="mt-3">
                               @if(isset($h->payment_breakdown['opening_cash']) && $h->payment_breakdown['opening_cash'] > 0)
                               <p class="mb-1 d-flex justify-content-between text-muted italic">
                                  <span>Yesterday's Rollover (Bf):</span>
                                  <span>TSh {{ number_format($h->payment_breakdown['opening_cash']) }}</span>
                               </p>
                               @endif
                               <p class="mb-1 d-flex justify-content-between text-dark">
                                  <span>Today's Food Revenue:</span>
                                  <span class="font-weight-bold">TSh {{ number_format($h->total_collection - ($h->payment_breakdown['opening_cash'] ?? 0)) }}</span>
                               </p>
                               <p class="mb-1 d-flex justify-content-between border-bottom pb-1">
                                  <span>Kitchen Expenses Paid:</span>
                                  <span class="text-danger">(-) TSh {{ number_format($h->expenses_total) }}</span>
                               </p>
                               <p class="mb-3 d-flex justify-content-between h6">
                                  <span>Net Cash to Safe:</span>
                                  <span class="font-weight-bold text-success">TSh {{ number_format($h->net_to_safe) }}</span>
                               </p>
                               
                               <div class="alert alert-secondary py-2" style="font-size:0.85rem; border-left: 5px solid #6c757d;">
                                  <strong>Shift Audit:</strong><br>
                                  Total food orders matched at <strong>TSh {{ number_format($h->expected_sales) }}</strong>. 
                                  @if($isShort)
                                    A total shortage of <strong>TSh {{ number_format($h->shortage_total) }}</strong> was identified and attributed to staff.
                                  @else
                                    Shift was 100% balanced with no shortages.
                                  @endif

                                  <div class="mt-2 text-dark font-weight-bold">
                                    @if($h->is_boss_received)
                                        <i class="fa fa-check-circle text-success"></i> This profit has been received and confirmed by the Boss.
                                    @elseif($h->boss_receipt_status === 'pending')
                                        <i class="fa fa-clock-o text-warning"></i> Profit is currently PENDING Boss confirmation.
                                    @else
                                        <i class="fa fa-briefcase"></i> Cash is currently in the kitchen drawer.
                                        <button data-id="{{ $h->id }}" data-amount="{{ $h->net_to_safe }}" class="btn btn-sm btn-primary ml-2 submit-kitchen-profit-btn">
                                           <i class="fa fa-handshake-o"></i> Submit to Boss Now
                                        </button>
                                    @endif
                                  </div>
                               </div>
                               
                               <div class="text-right">
                                  <a href="{{ route('accountant.food.reconciliation', ['date' => \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d')]) }}" class="btn btn-primary btn-sm rounded-pill">
                                    <i class="fa fa-external-link"></i> Full Audit View
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
              <tr><td colspan="10" class="text-center py-5 text-muted italic">No kitchen history found matching your search.</td></tr>
            @endif
          </tbody>
        </table>
      </div>
      
      @if($handovers->hasPages())
      <div class="mt-3 d-print-none d-flex justify-content-center pb-3">
        {{ $handovers->appends(request()->query())->links('pagination::bootstrap-4') }}
      </div>
      @endif
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
$(document).on('click', '.submit-kitchen-profit-btn', function() {
    const btn = $(this);
    const handoverId = btn.data('id');
    const amount = btn.data('amount');

    Swal.fire({
        title: 'Submit Kitchen Profit?',
        text: "Submit TSh " + parseInt(amount).toLocaleString() + " to the Boss and mark it as pending?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#940000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Send to Boss!'
    }).then((result) => {
        if (result.isConfirmed) {
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');
            
            $.ajax({
                url: "{{ route('accountant.food-master-sheet.profit-handover.submit') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    handover_id: handoverId,
                    amount: amount
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Sent!', response.message, 'success');
                        setTimeout(() => { location.reload(); }, 1500);
                    } else {
                        Swal.fire('Error', response.error || "Submission failed", 'error');
                        btn.prop('disabled', false).html('<i class="fa fa-handshake-o"></i> Submit to Boss Now');
                    }
                },
                error: function() {
                    Swal.fire('Failed', "Submission failed. Network or server error.", 'error');
                    btn.prop('disabled', false).html('<i class="fa fa-handshake-o"></i> Submit to Boss Now');
                }
            });
        }
    });
});
</script>
@endsection
