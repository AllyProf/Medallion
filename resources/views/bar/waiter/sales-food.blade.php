@extends('layouts.dashboard')

@section('title', 'My Daily Food Sales')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-cutlery"></i> My Food Sales</h1>
    <p>View your kitchen-only sales and submit collection to Chef</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bar.waiter.dashboard') }}">Waiter Dashboard</a></li>
    <li class="breadcrumb-item">Food Sales</li>
  </ul>
</div>

<!-- Date Selector -->
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <form method="GET" action="{{ route('bar.waiter.food-sales') }}" class="form-inline">
        <div class="form-group mr-3">
          <label for="date" class="mr-2">Select Date:</label>
          <input type="date" name="date" id="date" class="form-control" value="{{ $date }}" required>
        </div>
        <button type="submit" class="btn btn-warning">
          <i class="fa fa-search"></i> View Food Sales
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Sales Summary Cards -->
<div class="row">
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon shadow-sm" style="border-radius: 12px;">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4 class="text-uppercase small font-weight-bold text-muted">FOOD SALES</h4>
        <p><b>TSh {{ number_format($totalSales, 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon shadow-sm" style="border-radius: 12px;">
      <i class="icon fa fa-shopping-cart fa-3x"></i>
      <div class="info">
        <h4 class="text-uppercase small font-weight-bold text-muted">FOOD ORDERS</h4>
        <p><b>{{ $totalOrders }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small secondary coloured-icon shadow-sm" style="border-radius: 12px;">
      <i class="icon fa fa-bank fa-3x"></i>
      <div class="info">
        <h4 class="text-uppercase small font-weight-bold text-muted">CASH PORTION</h4>
        <p><b>TSh {{ number_format($cashCollected, 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small success coloured-icon shadow-sm" style="border-radius: 12px;">
      <i class="icon fa fa-mobile fa-3x"></i>
      <div class="info">
        <h4 class="text-uppercase small font-weight-bold text-muted">DIGITAL PORTION</h4>
        <p><b>TSh {{ number_format($mobileMoneyCollected, 0) }}</b></p>
      </div>
    </div>
  </div>
</div>

<!-- Reconciliation Status -->
@if($reconciliation)
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile shadow-sm border-{{ $reconciliation->status === 'verified' ? 'success' : 'info' }}" style="border-radius:12px; border-left: 5px solid #ff9800;">
      <h3 class="tile-title"><i class="fa fa-info-circle"></i> Kitchen Reconciliation Status</h3>
      <div class="tile-body">
        <div class="row items-center text-center">
          <div class="col-md-3 border-right">
            <small class="text-muted d-block text-uppercase font-weight-bold small">Current Status</small>
            @if($reconciliation->status === 'reconciled' || $reconciliation->status === 'verified')
              <span class="badge badge-success px-3 py-2">VERIFIED BY CHEF</span>
            @elseif($reconciliation->status === 'submitted')
              <span class="badge badge-info px-3 py-2">SENT TO CHEF</span>
            @else
              <span class="badge badge-warning px-3 py-2">PENDING</span>
            @endif
          </div>
          <div class="col-md-3 border-right">
            <small class="text-muted d-block text-uppercase font-weight-bold small">Food Expected</small>
            <strong>TSh {{ number_format($reconciliation->expected_amount, 0) }}</strong>
          </div>
          <div class="col-md-3 border-right">
            <small class="text-muted d-block text-uppercase font-weight-bold small">Food Submitted</small>
            <strong>TSh {{ number_format($reconciliation->submitted_amount, 0) }}</strong>
          </div>
          <div class="col-md-3">
            <small class="text-muted d-block text-uppercase font-weight-bold small">Difference</small>
            <strong class="{{ $reconciliation->difference >= 0 ? 'text-success' : 'text-danger' }}">
              {{ $reconciliation->difference >= 0 ? '+' : '' }}TSh {{ number_format($reconciliation->difference, 0) }}
            </strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

<!-- Orders List -->
<div class="row">
  <div class="col-md-12">
    <div class="tile shadow-sm" style="border-radius: 12px;">
      <h3 class="tile-title">Kitchen Orders for {{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</h3>
      <div class="tile-body">
        @if($orders->count() > 0)
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead class="bg-light text-center">
                <tr>
                  <th>Order #</th>
                  <th>Kitchen Items</th>
                  <th>Table</th>
                  <th class="text-right">Food Shared Value</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach($orders as $order)
                  @php 
                    $foodTotal = $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price');
                    if($foodTotal <= 0) continue;
                  @endphp
                  <tr>
                    <td class="text-center font-weight-bold">{{ $order->order_number }}</td>
                    <td>
                      @foreach($order->kitchenOrderItems as $item)
                        <span class="badge badge-info mb-1">{{ $item->quantity }}x {{ $item->food_name }}</span>
                      @endforeach
                    </td>
                    <td class="text-center">{{ $order->table ? $order->table->table_number : 'Kiosk' }}</td>
                    <td class="text-right font-weight-bold text-warning">TSh {{ number_format($foodTotal) }}</td>
                    <td class="text-center">
                      @if($order->payment_status === 'paid')
                        <span class="badge badge-success">Paid</span>
                      @else
                        <span class="badge badge-warning">Unpaid</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr class="bg-light font-weight-bold">
                  <td colspan="3" class="text-right">Total Food Collection:</td>
                  <td class="text-right text-warning h5 mb-0">TSh {{ number_format($totalSales, 0) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        @else
          <div class="alert alert-info border-info text-center py-4">
            <i class="fa fa-info-circle fa-2x mb-3 text-warning"></i><br>
            <h5>No Kitchen orders found for this date.</h5>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- Submit Reconciliation -->
@if($totalSales > 0 && (!$reconciliation || $reconciliation->status === 'pending'))
<div class="row">
  <div class="col-md-12">
    <div class="tile shadow border-warning" style="border-radius: 12px;">
      <h3 class="tile-title"><i class="fa fa-paper-plane-o"></i> Submit Food Collection to Chef</h3>
      <div class="tile-body">
        <form id="submit-food-reconciliation-form">
          @csrf
          <input type="hidden" name="date" value="{{ $date }}">
          <div class="row">
            <div class="col-md-6 border-right">
              <div class="form-group">
                <label class="font-weight-bold">EXPECTED FOOD CASH</label>
                <div class="input-group input-group-lg">
                   <div class="input-group-prepend"><span class="input-group-text bg-light border-0">TSh</span></div>
                   <input type="text" class="form-control border-0 bg-white font-weight-bold" value="{{ number_format($expectedAmount, 0) }}" readonly>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">ACTUAL CASH REMITTING *</label>
                <div class="input-group input-group-lg">
                  <div class="input-group-prepend"><span class="input-group-text bg-light">TSh</span></div>
                  <input type="number" name="submitted_amount" id="submitted_amount" class="form-control font-weight-bold text-warning" 
                         step="0.01" min="0" value="{{ $expectedAmount }}" required>
                </div>
              </div>
            </div>
          </div>
          <div class="form-group mt-3">
            <label class="font-weight-bold">Kitchen Handover Notes</label>
            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Explain any food dockets missing or extra cash..."></textarea>
          </div>
          <div class="form-group text-right">
            <button type="submit" class="btn btn-warning btn-lg px-5 shadow-sm rounded-pill font-weight-bold">
              <i class="fa fa-check-circle mr-2"></i> Submit to Kitchen
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
  $('#submit-food-reconciliation-form').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    
    Swal.fire({
      title: 'Submit Food Money?',
      text: "You are about to remit your kitchen collection to the Chef.",
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#ff9800',
      confirmButtonText: 'Yes, Submit'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: '{{ route("bar.waiter.food-submit") }}',
          method: 'POST',
          data: formData,
          success: function(response) {
            Swal.fire('Submitted!', response.message, 'success').then(() => {
              location.reload();
            });
          },
          error: function(xhr) {
             Swal.fire('Error', xhr.responseJSON.error, 'error');
          }
        });
      }
    });
  });
});
</script>
@endpush
