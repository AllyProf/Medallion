@php 
    $activeShiftIds = isset($allOpenShiftIds) ? $allOpenShiftIds : (isset($activeShift) && $activeShift ? [$activeShift->id] : []);
    $hasShownActiveHeader = false;
    $hasShownHistoryHeader = false;
@endphp

@forelse($orders as $order)
    @php 
        $orderWaiterId = $order->waiter_id ?: 0;
        $orderStatus = $order->status;
    @endphp

    @if(count($activeShiftIds) > 0 && in_array($order->bar_shift_id, $activeShiftIds) && !$hasShownActiveHeader)
        <tr class="bg-light shift-header" data-shift-group="active">
            <td colspan="9" class="text-center py-2" style="background: #e1f5fe; border-bottom: 2px solid #b3e5fc;">
                <span class="badge badge-info px-3 py-2"><i class="fa fa-refresh fa-spin"></i> CURRENT ACTIVE SHIFT ORDERS</span>
            </td>
        </tr>
        @php $hasShownActiveHeader = true; @endphp
    @endif

    @if((count($activeShiftIds) === 0 || !in_array($order->bar_shift_id, $activeShiftIds)) && !$hasShownHistoryHeader)
        <tr class="bg-light shift-header" data-shift-group="history">
            <td colspan="9" class="text-center py-2" style="background: #f5f5f5; border-bottom: 2px solid #e0e0e0;">
                <span class="badge badge-secondary px-3 py-2"><i class="fa fa-history"></i> PREVIOUS SHIFTS / HISTORY</span>
            </td>
        </tr>
        @php $hasShownHistoryHeader = true; @endphp
    @endif

    <tr data-status="{{ $orderStatus }}" 
        data-waiter-id="{{ $orderWaiterId }}" 
        data-order-id="{{ $order->id }}" 
        data-waiter-name="{{ $order->waiter ? $order->waiter->full_name : '' }}" 
        class="order-data-row {{ $orderStatus === 'cancelled' ? 'order-row-cancelled' : '' }}">
        @php
            $counterTotal = (float) $order->items->sum('total_price');
            $displayCounterTotal = $counterTotal;
            if ($orderStatus === 'cancelled' && $displayCounterTotal <= 0 && !empty($order->notes)) {
                if (preg_match('/BAR VOID VALUE:\s*([0-9]+(?:\.[0-9]+)?)/i', $order->notes, $m)) {
                    $displayCounterTotal = (float) $m[1];
                }
            }
            $counterPaid = min((float) ($order->paid_amount ?? 0), $counterTotal);
        @endphp
        <td><strong>{{ $order->order_number }}</strong> @if($orderStatus === 'cancelled') <span class="badge badge-danger">CANCELLED</span> @endif</td>
        <td>
            @if($order->waiter)
                <i class="fa fa-user"></i> {{ $order->waiter->full_name }}
                @if($order->order_source === 'kiosk')
                    <small class="text-info d-block">(via Kiosk)</small>
                @endif
                <br>
                <small class="text-muted">{{ $order->waiter->staff_id }}</small>
            @else
                <span class="text-muted">N/A</span>
            @endif
        </td>
        <td>
            @if($order->order_source === 'kiosk')
                <span class="badge badge-info"><i class="fa fa-desktop"></i> Kiosk</span>
            @elseif($order->order_source === 'counter')
                <span class="badge badge-warning"><i class="fa fa-shopping-cart"></i> Counter</span>
            @elseif($order->waiter_id)
                <span class="badge badge-primary"><i class="fa fa-user"></i> Waiter</span>
            @else
                <span class="badge badge-secondary"><i class="fa fa-globe"></i> Web</span>
            @endif
        </td>
        <td>
            @php
                $groupedBarLines = $order->items
                    ->map(function ($item) {
                        if ($item->productVariant) {
                            $label = '';
                            if (($item->sell_type ?? 'unit') === 'tot') {
                                $cat = strtolower($item->productVariant->product->category ?? '');
                                $pName = 'Tot';
                                if (str_contains($cat, 'wine')) $pName = 'Glass';
                                elseif (str_contains($cat, 'spirit') || str_contains($cat, 'whiskey') || str_contains($cat, 'vodka') || str_contains($cat, 'gin')) $pName = 'Shot';
                                $label = ($item->quantity > 1 ? \Illuminate\Support\Str::plural($pName) : $pName) . ' of ';
                            }

                            $displayName = \App\Helpers\ProductHelper::generateDisplayName(
                                $item->productVariant->product->name ?? 'N/A',
                                ($item->productVariant->measurement ?? '') . ' - ' . ($item->productVariant->packaging ?? ''),
                                $item->productVariant->name
                            );
                            return [
                                'key' => (($item->sell_type ?? 'unit') . '|' . ($item->product_variant_id ?? '0')),
                                'name' => trim($label . $displayName),
                                'qty' => (int) $item->quantity,
                            ];
                        }

                        return [
                            'key' => 'unknown',
                            'name' => 'N/A',
                            'qty' => (int) ($item->quantity ?? 0),
                        ];
                    })
                    ->groupBy('key')
                    ->map(function ($rows) {
                        return [
                            'name' => $rows->first()['name'],
                            'qty' => $rows->sum('qty'),
                        ];
                    })
                    ->values();
            @endphp
            <ul class="list-unstyled mb-0">
                @forelse($groupedBarLines->take(3) as $line)
                <li>
                    <small>{{ $line['qty'] }}x {{ $line['name'] }}</small>
                </li>
                @empty
                <li><small class="text-muted">—</small></li>
                @endforelse
                @if($groupedBarLines->count() > 3)
                <li><small class="text-muted">+{{ $groupedBarLines->count() - 3 }} more</small></li>
                @endif
            </ul>
        </td>
        <td><strong>TSh {{ number_format($displayCounterTotal, 2) }}</strong></td>
        <td>
            <span class="badge badge-{{ $orderStatus === 'pending' ? 'warning' : ($orderStatus === 'served' ? 'success' : 'secondary') }}">
                {{ ucfirst($orderStatus) }}
            </span>
            @if($orderStatus === 'cancelled')
                @php $cancelSummary = $order->counterCancellationSummary(); @endphp
                @if($cancelSummary)
                    <br><small class="text-danger">Reason: {{ $cancelSummary }}</small>
                @else
                    <br><small class="text-muted">Cancelled</small>
                @endif
            @elseif($order->barLinesVoidAtCounterSummary())
                <br><small class="text-info">{{ $order->barLinesVoidAtCounterSummary() }}</small>
            @endif
        </td>
        <td>
            @if($order->payment_status === 'paid')
                <span class="badge badge-success">
                    <i class="fa fa-check"></i> Paid
                </span>
                @if($order->payment_method === 'mobile_money' && $order->mobile_money_number)
                    <br><span class="text-success small font-weight-bold">{{ $order->mobile_money_number }}</span>
                @elseif($order->payment_method && $order->payment_method !== 'cash')
                    <br><span class="text-info small font-weight-bold">{{ strtoupper(str_replace('_', ' ', $order->payment_method)) }}</span>
                @endif
                @if($order->paidByWaiter)
                    <br><small class="text-muted">Counter reconciled</small>
                @endif
            @elseif($order->payment_status === 'partial')
                <span class="badge badge-warning">
                    Partial: TSh {{ number_format($counterPaid, 2) }}
                </span>
                <br><small class="text-danger">TSh {{ number_format(max($counterTotal - $counterPaid, 0), 2) }} outstanding</small>
            @elseif($order->orderPayments && $order->orderPayments->count() > 0)
                @php $totalRecorded = $order->orderPayments->sum('amount'); @endphp
                @if($totalRecorded >= $counterTotal - 0.01)
                    <span class="badge badge-success"><i class="fa fa-check"></i> Paid</span>
                    <br><small class="text-muted">Awaiting counter verification</small>
                @else
                    <span class="badge badge-warning">Partial</span>
                    <br><small class="text-danger">TSh {{ number_format(max($counterTotal - $totalRecorded, 0), 2) }} outstanding</small>
                @endif
            @else
                <span class="badge badge-danger">Pending</span>
            @endif
        </td>
        <td>{{ $order->created_at->format('M d, Y H:i') }}</td>
        <td>
            <div class="">
                <button class="btn btn-sm btn-secondary view-order-btn mr-1 mb-1" data-order-id="{{ $order->id }}">
                    <i class="fa fa-eye"></i> View
                </button>

                @if($orderStatus === 'pending' && $order->payment_status !== 'paid')
                    <button class="btn btn-sm btn-info update-status-btn mr-1 mb-1"
                            data-order-id="{{ $order->id }}"
                            data-status="served">
                        <i class="fa fa-check"></i> Serve
                    </button>

                    <button class="btn btn-sm btn-danger update-status-btn mr-1 mb-1"
                            data-order-id="{{ $order->id }}"
                            data-status="cancelled">
                        <i class="fa fa-ban"></i> Cancel
                    </button>

                @elseif($orderStatus === 'served' && $order->payment_status !== 'paid')
                    <button class="btn btn-sm btn-success font-weight-bold pay-order-btn mr-1 mb-1"
                            data-order-id="{{ $order->id }}"
                            data-total="{{ $counterTotal }}">
                        <i class="fa fa-money"></i> PAY
                    </button>

                @elseif($order->payment_status === 'paid')
                    <button class="btn btn-sm btn-success" disabled style="opacity: 1;">
                        <i class="fa fa-check-circle"></i> Paid
                    </button>

                @elseif($orderStatus === 'cancelled')
                    <button class="btn btn-sm btn-secondary" disabled style="opacity: 1;">
                        <i class="fa fa-ban"></i> Cancelled
                    </button>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr id="no-orders-row">
        <td colspan="9" class="text-center">
            <p class="text-muted">No orders found</p>
        </td>
    </tr>
@endforelse

{{-- Pagination Links Partial --}}
@if($orders->hasPages())
<tr id="pagination-row">
    <td colspan="9" class="py-2">
        <ul class="pagination justify-content-center mb-0" id="orders-pagination">
            @if($orders->onFirstPage())
                <li class="page-item disabled"><span class="page-link">«</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $orders->previousPageUrl() }}" rel="prev">«</a></li>
            @endif

            @foreach($orders->getUrlRange(max(1, $orders->currentPage() - 2), min($orders->lastPage(), $orders->currentPage() + 2)) as $page => $url)
                @if($page == $orders->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                @endif
            @endforeach

            @if($orders->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $orders->nextPageUrl() }}" rel="next">»</a></li>
            @else
                <li class="page-item disabled"><span class="page-link">»</span></li>
            @endif
        </ul>
    </td>
</tr>
@endif
