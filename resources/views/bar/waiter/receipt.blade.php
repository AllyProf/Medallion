<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $order->order_number }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 4px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            @page { margin: 0.2cm; size: 80mm auto; }
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            max-width: 320px;
            margin: 0 auto;
            padding: 10px;
            font-size: 15px;
            font-weight: 600;
            color: #000;
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }
        .center { text-align: center; }
        .biz-name { font-size: 20px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
        .biz-sub { font-size: 12px; margin-top: 2px; font-weight: 700; }
        .receipt-title { font-size: 28px; font-weight: 900; letter-spacing: 2px; line-height: 1.1; }
        .order-num {
            font-size: 22px;
            font-weight: 900;
            margin-top: 4px;
            border: 3px solid #000;
            display: inline-block;
            padding: 4px 14px;
        }
        .divider-solid { border: none; border-top: 3px solid #000; margin: 8px 0; }
        .divider-dash { border: none; border-top: 3px dashed #000; margin: 8px 0; }
        .meta-box { border: 1px solid #000; padding: 6px 8px; margin-bottom: 8px; }
        .meta { font-size: 15px; margin: 2px 0; font-weight: 800; }
        .section-label { font-size: 13px; font-weight: 900; letter-spacing: 1px; margin: 8px 0 4px; text-transform: uppercase; }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin: 0 0 10px 0;
            padding-bottom: 7px;
            border-bottom: 2px dashed #000;
            font-size: 18px;
            font-weight: 900;
        }
        .item-name { flex: 1; padding-right: 4px; color: #000; }
        .item-price { white-space: nowrap; font-size: 16px; font-weight: 900; }
        .total-row { display: flex; justify-content: space-between; font-size: 18px; margin: 3px 0; font-weight: 900; }
        .grand { font-size: 20px; font-weight: 900; border-top: 3px double #000; padding-top: 6px; margin-top: 6px; }
        .payment-box { border: 2px dashed #000; padding: 6px; margin-top: 10px; font-size: 13px; }
        .pay-row { display: flex; justify-content: space-between; margin: 2px 0; font-weight: 700; }
        .footer { text-align: center; font-size: 12px; font-weight: 700; margin-top: 10px; padding-top: 7px; border-top: 2px dashed #000; line-height: 1.45; }
        .no-print { text-align: center; margin-top: 15px; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; margin: 3px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="center">
        <div class="biz-name">{{ auth()->user()->business_name ?? 'RESTAURANT' }}</div>
        <div class="receipt-title">ORDER RECEIPT</div>
        <div class="order-num"># {{ $order->order_number }}</div>
    </div>

    <hr class="divider-solid">

    <div class="meta-box">
        <div class="meta"><strong>Date:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</div>
        @if($order->table)<div class="meta"><strong>Table:</strong> {{ $order->table->table_number }}</div>@endif
        @if($order->customer_name)<div class="meta"><strong>Customer:</strong> {{ $order->customer_name }}</div>@endif
        @if($order->waiter)<div class="meta"><strong>Served by:</strong> {{ $order->waiter->full_name }}</div>@endif
    </div>

    <hr class="divider-dash">

    @php
        $drinksTotal = 0;
        $foodTotal = 0;
    @endphp

    @if($order->items->count())
    <div class="section-label">Drinks</div>
    @endif
    @foreach($order->items as $item)
        @php $drinksTotal += $item->total_price; @endphp
        <div class="item-row">
            <span class="item-name">{{ $item->quantity }}x {{ $item->productVariant->display_name ?? 'Item' }}</span>
            <span class="item-price">{{ number_format($item->total_price, 0) }}</span>
        </div>
    @endforeach

    @if($order->kitchenOrderItems->where('status','!=','cancelled')->count())
    <div class="section-label">Food</div>
    @endif
    @foreach($order->kitchenOrderItems->where('status','!=','cancelled') as $item)
        @php $foodTotal += $item->total_price; @endphp
        <div class="item-row">
            <span class="item-name">{{ $item->quantity }}x {{ $item->food_item_name }}{{ $item->variant_name ? ' ('.$item->variant_name.')' : '' }}</span>
            <span class="item-price">{{ number_format($item->total_price, 0) }}</span>
        </div>
    @endforeach

    <hr class="divider-dash">

    @php $grandTotal = $drinksTotal + $foodTotal; @endphp
    @if($drinksTotal > 0 && $foodTotal > 0)
        <div class="total-row"><span>Drinks:</span><span>TSh {{ number_format($drinksTotal, 0) }}</span></div>
        <div class="total-row"><span>Food:</span><span>TSh {{ number_format($foodTotal, 0) }}</span></div>
    @endif
    <div class="total-row grand">
        <span>TOTAL:</span>
        <span>TSh {{ number_format($grandTotal, 0) }}</span>
    </div>

    <div class="payment-box">
        <div class="center" style="font-weight:bold;border-bottom:1px solid #000;margin-bottom:3px;">PAYMENT</div>
        <div class="pay-row"><span>M-PESA:</span><span><strong>36645568</strong></span></div>
        <div class="pay-row"><span>CRDB:</span><span><strong>11007342</strong></span></div>
        <div class="pay-row"><span>MIXX BY YAS:</span><span><strong>17788036</strong></span></div>
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
