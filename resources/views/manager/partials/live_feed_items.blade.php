@foreach($liveFeed as $order)
@php
    $timeAgo = $order->created_at->diffForHumans();
    $cancelled = $order->status === 'cancelled';
    $statusClass = 'badge-secondary';
    if($order->status == 'pending') $statusClass = 'badge-warning';
    if($order->status == 'preparing') $statusClass = 'badge-info';
    if($order->status == 'ready') $statusClass = 'badge-primary';
    if($order->status == 'served') $statusClass = 'badge-success';
    if($cancelled) $statusClass = 'badge-danger';

    $amount = (float) $order->total_amount;
    if ($cancelled && $amount <= 0 && !empty($order->notes) && preg_match('/BAR VOID VALUE:\s*([0-9]+(?:\.[0-9]+)?)/i', $order->notes, $voidMatch)) {
        $amount = (float) $voidMatch[1];
    }
    $summary = $cancelled ? $order->counterCancellationSummary() : null;
    $itemNames = collect($order->cancelledItemLabels())
        ->merge($order->items->map(function ($item) {
            return ((int) $item->quantity).'x '.($item->productVariant->display_name ?? 'Item');
        }))
        ->merge($order->kitchenOrderItems->map(function ($item) {
            return ((int) $item->quantity).'x '.($item->food_item_name ?: 'Food');
        }))
        ->filter()
        ->unique()
        ->values();
@endphp
<div class="list-group-item list-group-item-action border-0 mb-2 py-3 shadow-sm" style="border-radius: 12px; transition: transform 0.2s; {{ $cancelled ? 'border-left: 4px solid #dc3545 !important; background: #fff5f5;' : '' }}">
    <div class="d-flex w-100 justify-content-between align-items-center">
        <div>
            <h6 class="mb-1 font-weight-bold" style="font-size: 1.1rem;">#{{ $order->order_number }} - {{ $order->customer_name ?: ($order->table ? 'Table '.$order->table->table_number : 'Walk-in') }}</h6>
            <small class="text-muted"><i class="fa fa-clock-o mr-1"></i> {{ $timeAgo }} · By <strong>{{ $order->waiter->full_name ?? 'Staff' }}</strong></small>
        </div>
        <div class="text-right">
            <div class="badge {{ $statusClass }} px-3 py-2 mb-1" style="border-radius: 20px;">{{ strtoupper($order->status) }}</div>
            <div class="font-weight-bold {{ $cancelled ? 'text-danger' : 'text-dark' }}" style="font-size: 1.1rem;">TSh {{ number_format($amount) }}</div>
            @if($cancelled)
                <small class="text-danger d-block">Voided</small>
            @endif
        </div>
    </div>
    <div class="mt-2 text-muted small">
        <i class="fa fa-shopping-basket mr-1"></i>
        @if($itemNames->isNotEmpty())
            {{ $itemNames->take(4)->implode(', ') }}{{ $itemNames->count() > 4 ? ' ...' : '' }}
        @elseif($cancelled)
            Item name was not recorded
        @else
            —
        @endif
    </div>
    @if($summary)
        <div class="small text-danger mt-1">{{ $summary }}</div>
    @endif
</div>
@endforeach
@if($liveFeed->isEmpty())
<div class="text-center py-5">
    <i class="fa fa-coffee fa-3x text-muted mb-3"></i>
    <p class="text-muted">No orders placed yet today.</p>
</div>
@endif
