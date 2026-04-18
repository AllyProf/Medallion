<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Combined Receipt - {{ $orders->pluck('order_number')->implode(', ') }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 2px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            @page { margin: 0.2cm; size: 80mm auto; }
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #000;
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }
        .center { text-align: center; }
        .biz-name { font-size: 18px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
        .biz-sub { font-size: 10px; margin-top: 1px; font-weight: 700; }
        .receipt-title { font-size: 18px; font-weight: 900; letter-spacing: 1px; line-height: 1.1; margin-top: 4px;}
        .section-label { font-size: 11px; font-weight: 900; letter-spacing: 0.5px; margin: 8px 0 4px; text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: 2px;}
        .divider-solid { border: none; border-top: 2px solid #000; margin: 5px 0; }
        .divider-dash { border: none; border-top: 2px dashed #000; margin: 5px 0; }
        .meta-box { border: 1px solid #000; padding: 4px 6px; margin-bottom: 6px; }
        .meta { font-size: 11px; margin: 1px 0; font-weight: 700; }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 6px;
            margin: 0 0 4px 0;
            padding-bottom: 3px;
            border-bottom: 1px dashed #000;
            font-size: 13px;
            font-weight: 800;
        }
        .item-name { flex: 1; padding-right: 4px; color: #000; }
        .item-price { white-space: nowrap; font-size: 12px; font-weight: 900; }
        .total-row { display: flex; justify-content: space-between; font-size: 14px; margin: 2px 0; font-weight: 900; }
        .grand {
            font-size: 17px;
            font-weight: 900;
            border-top: 2px double #000;
            border-bottom: 2px solid #000;
            padding: 4px 0;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }
        .footer { text-align: center; font-size: 10px; font-weight: 700; margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000; line-height: 1.3; }
        .no-print { text-align: center; margin-top: 10px; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; margin: 3px; font-size: 13px; }
        .order-group { border: 1px solid #ddd; padding: 4px; margin-bottom: 8px; }
        .payment-box { border: 2px solid #000; padding: 6px; margin-top: 8px; font-size: 11px; background: #fff; }
        .pay-row { display: flex; justify-content: space-between; margin: 2px 0; font-weight: 900; font-size: 12px; }
        .pay-status { margin-top: 6px; border-top: 2px dashed #000; padding-top: 5px; text-align: center; font-size: 13px; font-weight: 900; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <div class="center">
        <div class="biz-name">MEDALLION RESTAURANT</div>
        <div class="receipt-title">COMBINED BILL</div>
        <div style="font-size: 11px; margin-top:2px;">Orders: {{ $orders->pluck('order_number')->implode(', ') }}</div>
    </div>

    <hr class="divider-solid">

    <div class="meta-box">
        <div class="meta"><strong>Print Date:</strong> {{ now()->format('d/m/Y H:i') }}</div>
        @if($orders->first()->waiter)<div class="meta"><strong>Served by:</strong> {{ $orders->first()->waiter->full_name }}</div>@endif
    </div>

    @php
        $grandTotal = 0;
        $combinedDrinks = [];
        $combinedFood = [];
    @endphp

    @foreach($orders as $order)
        @php $grandTotal += $order->total_amount; @endphp
        
        {{-- Aggregate Drinks --}}
        @foreach($order->items as $item)
            @php
                $variantName = $item->productVariant->display_name ?? 'Item';
                if (isset($combinedDrinks[$variantName])) {
                    $combinedDrinks[$variantName]['qty'] += $item->quantity;
                    $combinedDrinks[$variantName]['total'] += $item->total_price;
                } else {
                    $combinedDrinks[$variantName] = [
                        'qty' => $item->quantity,
                        'total' => $item->total_price
                    ];
                }
            @endphp
        @endforeach

        {{-- Aggregate Food --}}
        @foreach($order->kitchenOrderItems->where('status','!=','cancelled') as $item)
            @php
                $foodName = $item->food_item_name . ($item->variant_name ? ' ('.$item->variant_name.')' : '');
                if (isset($combinedFood[$foodName])) {
                    $combinedFood[$foodName]['qty'] += $item->quantity;
                    $combinedFood[$foodName]['total'] += $item->total_price;
                } else {
                    $combinedFood[$foodName] = [
                        'qty' => $item->quantity,
                        'total' => $item->total_price
                    ];
                }
            @endphp
        @endforeach
    @endforeach

    @if(count($combinedDrinks) > 0)
        <div class="section-label">DRINKS SUMMARY</div>
        @foreach($combinedDrinks as $name => $data)
            <div class="item-row">
                <span class="item-name">{{ $data['qty'] }}x {{ $name }}</span>
                <span class="item-price">{{ number_format($data['total'], 0) }}</span>
            </div>
        @endforeach
    @endif

    @if(count($combinedFood) > 0)
        <div class="section-label">FOOD SUMMARY</div>
        @foreach($combinedFood as $name => $data)
            <div class="item-row">
                <span class="item-name">{{ $data['qty'] }}x {{ $name }}</span>
                <span class="item-price">{{ number_format($data['total'], 0) }}</span>
            </div>
        @endforeach
    @endif

    <hr class="divider-dash">

    <div class="total-row grand">
        <span>GRAND TOTAL:</span>
        <span>TSh {{ number_format($grandTotal, 0) }}</span>
    </div>

    <div class="center" style="margin-top: 10px;">
        <div style="font-size: 10px; font-weight: 900; text-decoration: underline;">ORDER BREAKDOWN</div>
        @foreach($orders as $order)
            <div style="font-size: 11px; display: flex; justify-content: space-between; margin-top: 2px;">
                <span>#{{ $order->order_number }} ({{ $order->created_at->format('H:i') }})</span>
                <span>TSh {{ number_format($order->total_amount, 0) }}</span>
            </div>
        @endforeach
    </div>

    @php
        $allPaid = $orders->every(fn($o) => $o->payment_status === 'paid');
    @endphp

    <div class="payment-box">
        <div class="center" style="font-weight:900;font-size:12px;border-bottom:2px solid #000;margin-bottom:4px;padding-bottom:2px;letter-spacing:0.5px;">PAYMENT</div>
        @if($allPaid)
            @php
                $aggregatedPayments = [];
                foreach($orders as $order) {
                    if (isset($order->orderPayments) && $order->orderPayments->count() > 0) {
                        foreach($order->orderPayments as $p) {
                            $method = strtoupper(str_replace('_', ' ', $p->payment_method ?? 'PAID'));
                            if (!isset($aggregatedPayments[$method])) {
                                $aggregatedPayments[$method] = 0;
                            }
                            $aggregatedPayments[$method] += (float)$p->amount;
                        }
                    } else {
                        $method = strtoupper(str_replace('_', ' ', $order->payment_method ?? 'PAID'));
                        if (!isset($aggregatedPayments[$method])) {
                            $aggregatedPayments[$method] = 0;
                        }
                        $aggregatedPayments[$method] += (float)($order->paid_amount ?: $order->total_amount);
                    }
                }
            @endphp
            @foreach($aggregatedPayments as $method => $amount)
                <div class="pay-row">
                    <span>{{ $method }}</span>
                    <span><strong>TSh {{ number_format($amount, 0) }}</strong></span>
                </div>
            @endforeach
            <div class="pay-status">PAID</div>
        @else
            <div class="pay-row"><span>CASH:</span><span><strong>AVAILABLE</strong></span></div>
            <div class="pay-row"><span>M-PESA:</span><span><strong>36645568</strong></span></div>
            <div class="pay-row"><span>CRDB:</span><span><strong>11007342</strong></span></div>
            <div class="pay-row"><span>MIXX BY YAS:</span><span><strong>17788036</strong></span></div>
            <div class="pay-status">PENDING PAYMENT</div>
        @endif
    </div>

    <div class="footer">
        <strong>Thank you for choosing us!</strong><br>
        Powered By EmCa Tech LTD<br>
        www.emca.tech
    </div>

    <div class="no-print">
        <button class="btn" onclick="window.print()">Print Receipt</button>
        <button class="btn" style="background:#6c757d" onclick="window.close()">Close</button>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
