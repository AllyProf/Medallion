<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Docket - {{ $order->order_number }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 5px; }
            .no-print { display: none !important; }
            @page { margin: 0.3cm; size: 80mm auto; }
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 8px;
            background: #fff;
            color: #000;
            font-size: 13px;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .brand-name {
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 1px;
            line-height: 1.2;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .docket-title {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 2px;
            line-height: 1.1;
        }
        .order-num {
            font-size: 20px;
            font-weight: bold;
            margin-top: 4px;
            border: 2px solid #000;
            display: inline-block;
            padding: 2px 12px;
        }
        .table-banner {
            font-size: 22px;
            font-weight: 900;
            background: #000;
            color: #fff;
            text-align: center;
            padding: 4px;
            margin: 6px 0;
            letter-spacing: 3px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 4px;
            line-height: 1.35;
            font-weight: 700;
            gap: 8px;
            white-space: nowrap;
        }
        .meta-box {
            border: 1px solid #000;
            padding: 5px 6px;
            margin-bottom: 6px;
            background: #fff;
        }
        .meta-row.single {
            justify-content: flex-start;
            white-space: normal;
            word-break: break-word;
        }
        .divider { border: none; border-top: 2px dashed #000; margin: 6px 0; }
        .item-row {
            margin-bottom: 10px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 6px;
        }
        .item-main {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 17px;
            font-weight: 900;
        }
        .item-main .name { flex: 1; padding-right: 6px; }
        .item-main .price { white-space: nowrap; font-size: 14px; }
        .item-variant {
            font-size:14px;
            font-weight: bold;
            margin-left: 18px;
            color: #222;
        }
        .item-note {
            font-size: 12px;
            font-style: italic;
            margin-left: 18px;
            margin-top: 3px;
            border-left: 3px solid #000;
            padding-left: 5px;
            color: #333;
        }
        .total-section {
            border-top: 3px double #000;
            padding-top: 6px;
            margin-top: 4px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 900;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px dashed #000;
            line-height: 1.4;
        }
        .powered-by {
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
        }
        .no-print {
            text-align: center;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            margin: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand-name">MEDALLION RESTAURANT</div>
        <div class="docket-title">KITCHEN DOCKET</div>
        <div class="order-num"># {{ $order->order_number }}</div>
    </div>

    @if($order->table)
        <div class="table-banner">TABLE {{ $order->table->table_number }}</div>
    @endif

    <div class="meta-box">
        <div class="meta-row">
            <span><strong>Time:</strong> {{ $order->created_at->format('H:i') }}</span>
            <span><strong>Date:</strong> {{ $order->created_at->format('d M Y') }}</span>
        </div>
        <div class="meta-row single">
            <span><strong>Waiter:</strong> {{ $order->waiter->full_name ?? 'N/A' }}</span>
        </div>
    </div>

    <hr class="divider">

    @php $foodTotal = 0; @endphp
    @foreach($order->kitchenOrderItems->where('status', '!=', 'cancelled') as $item)
        @php $foodTotal += $item->total_price; @endphp
        <div class="item-row">
            <div class="item-main">
                <span class="name">{{ $item->quantity }}x {{ $item->food_item_name }}</span>
                <span class="price">TSh {{ number_format($item->total_price, 0) }}</span>
            </div>
            @if($item->variant_name)
                <div class="item-variant">▸ {{ $item->variant_name }}</div>
            @endif
            @if($item->special_instructions)
                <div class="item-note">Note: {{ $item->special_instructions }}</div>
            @endif
        </div>
    @endforeach

    <div class="total-section">
        <div class="total-row">
            <span>FOOD TOTAL:</span>
            <span>TSh {{ number_format($foodTotal, 0) }}</span>
        </div>
    </div>

    @if($order->notes && str_contains($order->notes, 'ORDER NOTES:'))
        <hr class="divider">
        <div style="font-size:12px; border: 1px solid #000; padding: 4px;">
            <strong>General Notes:</strong><br>
            {{ trim(explode('ORDER NOTES:', $order->notes)[1] ?? '') }}
        </div>
    @endif

    <div class="footer">
        <strong>*** END OF DOCKET ***</strong><br>
        Printed: {{ now()->format('H:i:s') }}
        <div class="powered-by">Powered By EmCa Tech-www.emca.tech</div>
    </div>

    <div class="no-print">
        <button class="btn" onclick="window.print()">Print Docket</button>
        <button class="btn" style="background:#6c757d" onclick="window.close()">Close</button>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
