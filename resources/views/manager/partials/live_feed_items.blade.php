@foreach($liveFeed as $order)
@php
    $timeAgo = $order->created_at->diffForHumans();
    $statusClass = 'badge-secondary';
    if($order->status == 'pending') $statusClass = 'badge-warning';
    if($order->status == 'preparing') $statusClass = 'badge-info';
    if($order->status == 'ready') $statusClass = 'badge-primary';
    if($order->status == 'served') $statusClass = 'badge-success';
@endphp
<div class="list-group-item list-group-item-action border-0 mb-2 py-3 shadow-sm" style="border-radius: 12px; transition: transform 0.2s;">
    <div class="d-flex w-100 justify-content-between align-items-center">
        <div>
            <h6 class="mb-1 font-weight-bold" style="font-size: 1.1rem;">#{{ $order->order_number }} - {{ $order->customer_name ?: ($order->table ? 'Table '.$order->table->table_number : 'Walk-in') }}</h6>
            <small class="text-muted"><i class="fa fa-clock-o mr-1"></i> {{ $timeAgo }} · By <strong>{{ $order->waiter->full_name }}</strong></small>
        </div>
        <div class="text-right">
            <div class="badge {{ $statusClass }} px-3 py-2 mb-1" style="border-radius: 20px;">{{ strtoupper($order->status) }}</div>
            <div class="font-weight-bold text-dark" style="font-size: 1.1rem;">TSh {{ number_format($order->total_amount) }}</div>
        </div>
    </div>
    <div class="mt-2 text-muted small">
        @php
            $items = $order->items->pluck('productVariant.display_name')->concat($order->kitchenOrderItems->pluck('food_item_name'))->take(3);
        @endphp
        <i class="fa fa-shopping-basket mr-1"></i> {{ $items->implode(', ') }}{{ ($order->items->count() + $order->kitchenOrderItems->count()) > 3 ? ' ...' : '' }}
    </div>
</div>
@endforeach
@if($liveFeed->isEmpty())
<div class="text-center py-5">
    <i class="fa fa-coffee fa-3x text-muted mb-3"></i>
    <p class="text-muted">No orders placed yet today.</p>
</div>
@endif
