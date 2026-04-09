<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $order->order_number }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 4px; }
            .no-print { display: none !important; }
            @page { margin: 0.2cm; size: 80mm auto; }
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 6px;
            font-size: 11px;
            color: #000;
        }
        .center { text-align: center; }
        .biz-name { font-size: 18px; font-weight: 900; letter-spacing: 1px; }
        .biz-sub { font-size: 10px; margin-top: 1px; }
        .divider-solid { border: none; border-top: 2px solid #000; margin: 4px 0; }
        .divider-dash { border: none; border-top: 1px dashed #000; margin: 4px 0; }
        .meta { font-size: 11px; margin: 2px 0; }
        .item-row { display: flex; justify-content: space-between; margin: 2px 0; font-size: 11px; }
        .item-name { flex: 1; padding-right: 4px; }
        .item-price { white-space: nowrap; }
        .total-row { display: flex; justify-content: space-between; font-size: 12px; margin: 2px 0; }
        .grand { font-size: 15px; font-weight: 900; border-top: 2px solid #000; padding-top: 3px; margin-top: 3px; }
        .payment-box { border: 1px dashed #000; padding: 4px; margin-top: 6px; font-size: 10px; }
        .pay-row { display: flex; justify-content: space-between; margin: 1px 0; }
        .footer { text-align: center; font-size: 10px; margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000; }
        .no-print { text-align: center; margin-top: 15px; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; margin: 3px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="center">
        <div class="biz-name">{{ auth()->user()->business_name ?? 'RESTAURANT' }}</div>
        <div class="biz-sub">QUALITY FOOD &amp; DRINKS</div>
    </div>

    <hr class="divider-solid">

    <div class="meta center"><strong>ORDER RECEIPT</strong></div>
    <div class="meta"><strong>#</strong> {{ $order->order_number }} &nbsp;&nbsp; {{ $order->created_at->format('d/m/Y H:i') }}</div>
    @if($order->table)<div class="meta"><strong>Table:</strong> {{ $order->table->table_number }}</div>@endif
    @if($order->customer_name)<div class="meta"><strong>Customer:</strong> {{ $order->customer_name }}</div>@endif
    @if($order->waiter)<div class="meta"><strong>Served by:</strong> {{ $order->waiter->full_name }}</div>@endif

    <hr class="divider-dash">

    @php
        $drinksTotal = 0;
        $foodTotal = 0;
    @endphp

    {{-- Drinks --}}
    @foreach($order->items as $item)
        @php $drinksTotal += $item->total_price; @endphp
        <div class="item-row">
            <span class="item-name">{{ $item->quantity }}x {{ $item->productVariant->display_name ?? 'Item' }}</span>
            <span class="item-price">{{ number_format($item->total_price, 0) }}</span>
        </div>
    @endforeach

    {{-- Food --}}
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
