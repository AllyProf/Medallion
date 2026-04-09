@extends('layouts.dashboard')

@section('content')
<style>
    /* REPORT COLORS AND THEME - MOSHI STYLE */
    :root {
        --report-maroon: #940000;
        --report-border: #b00000;
        --report-text: #2c3e50;
    }

    .report-page {
        background: #fff;
        padding: 40px;
        color: var(--report-text);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
    }

    .report-header-center { text-align: center; margin-bottom: 25px; }
    .report-header-center img { height: 50px; margin-bottom: 5px; border: 1px solid #eee; padding: 2px; }
    .report-header-center h1 { font-size: 2.3rem; font-weight: 800; color: var(--report-maroon); margin: 0; text-transform: uppercase; }
    .biz-contact-info { font-size: 0.9rem; color: #555; margin-top: 5px; } 

    .operations-title { color: var(--report-maroon); font-weight: 700; font-size: 1.25rem; margin-top: 10px; }
    .orange-divider { height: 3px; background: var(--report-maroon); margin: 15px 0; border: none; }

    .report-sub-meta { display: flex; justify-content: flex-end; font-size: 0.78rem; color: #777; gap: 15px; margin-bottom: 8px; }

    .title-area { position: relative; text-align: center; margin: 25px 0; }
    .main-report-title { font-size: 1.6rem; font-weight: 800; text-transform: uppercase; display: inline-block; border-bottom: 2px solid #555; padding-bottom: 3px; }
    .official-stamp { position: absolute; right: 28%; top: -8px; border: 4px solid #27ae60; color: #27ae60; padding: 3px 12px; font-weight: 900; font-size: 1.3rem; transform: rotate(-10deg); border-radius: 8px; opacity: 0.8; text-transform: uppercase; pointer-events: none; }

    .btn-print { background: #e67e22; color: #fff; padding: 10px 25px; border-radius: 6px; border: none; font-weight: 700; }
    .btn-print:hover { background: #d35400; color: #fff; }

    .report-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
    .stats-card-title { font-size: 0.95rem; font-weight: 800; color: var(--report-maroon); text-transform: uppercase; border-bottom: 2px solid var(--report-maroon); padding-bottom: 5px; margin-bottom: 10px; }
    .stats-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 0.88rem; }

    .audit-table { width: 100%; border-collapse: collapse; border: 1.5px solid #333; }
    .audit-table th { background: #f8f9fa; border: 1px solid #333; padding: 10px 6px; font-weight: 800; font-size: 0.72rem; text-transform: uppercase; text-align: center; }

    .category-row { background: #fffcfc; font-weight: 800; text-transform: uppercase; font-size: 0.82rem; border-top: 2px solid var(--report-maroon); }
    .category-row td { padding: 8px 12px; border: 1px solid #333; }

    .audit-table td { border: 1px solid #333; padding: 10px 6px; font-size: 0.82rem; text-align: center; }
    .audit-table td.text-left { text-align: left; font-weight: 700; color: #1a1a1a; padding-left: 12px; }

    .qty-bold { font-weight: 800; font-size: 1.1rem; color: #222; }
    .text-muted-row { color: #888; }
    .uom-badge { color: var(--report-maroon); font-weight: 800; font-size: 0.8rem; }

    @media print {
        .app-header, .app-sidebar, .d-print-none, .breadcrumb, .main-footer { display: none !important; }
        .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .report-page { padding: 0; }
        .audit-table { border: 2px solid #000; }
        .audit-table th, .audit-table td { border: 1.5px solid #000; }
    }
</style>

<div class="report-page mt-3">
    
    <div class="report-header-center">
        <img src="https://ui-avatars.com/api/?name={{ urlencode($businessName) }}&background=940000&color=fff&size=80" alt="Logo">
        <h1>{{ $businessName }}</h1>
        <div class="biz-contact-info">
            {{ $owner->city }} | Mobile: {{ $owner->phone }} | Email: {{ $owner->email }}
        </div>
        <div class="operations-title">STOCK RECEPTION (GRN)</div>
        <hr class="orange-divider">
    </div>

    <div class="report-sub-meta">
        <span>Batch #: {{ $receiptNumber }}</span>
        <span>| Generated: {{ date('d M Y, H:i') }}</span>
    </div>

    <div class="title-area">
        <h2 class="main-report-title">Goods Received Note</h2>
        <div class="official-stamp">Received</div>
    </div>

    <div class="text-center mb-4 d-print-none">
        <button onclick="window.print()" class="btn btn-print shadow-sm mr-2"><i class="fa fa-print"></i> Print Receipt / PDF</button>
        <a href="{{ route('bar.stock-receipts.index') }}" class="btn btn-outline-secondary px-4 shadow-sm border rounded">Back to List</a>
    </div>

    <div class="report-stats-grid">
        <div>
            <div class="stats-card-title">Delivery Information</div>
            <div class="stats-row"><strong>Supplier:</strong> <span>{{ $supplier->company_name }}</span></div>
            <div class="stats-row"><strong>Receipt Date:</strong> <span>{{ \Carbon\Carbon::parse($receivedDate)->format('d M Y') }}</span></div>
            <div class="stats-row"><strong>Received By:</strong> <span>{{ $receivedBy->name ?? 'System' }}</span></div>
        </div>
        <div>
            <div class="stats-card-title">Financial Summary</div>
            <div class="stats-row"><strong>Items Count:</strong> <span>{{ $receipts->count() }} Products</span></div>
            <div class="stats-row"><strong>Total Net Cost:</strong> <strong>TSh {{ number_format($receipts->sum('final_buying_cost')) }}</strong></div>
            <div class="stats-row"><strong>Status:</strong> <span>Batch Verified</span></div>
        </div>
    </div>

    @php
        $receiptsByBrand = $receipts->groupBy(fn($r) => $r->productVariant->product->brand ?? 'General');
    @endphp

    <div class="stats-card-title mb-2">1. Delivered Items Breakdown</div>
    
    <table class="audit-table">
        <thead>
            <tr>
                <th style="width: 35px;">#</th>
                <th class="text-left">Drink Item Name</th>
                <th style="width: 70px;">UOM</th>
                <th style="width: 120px;">Packaging</th>
                <th style="width: 100px;">Qty (Pkgs)</th>
                <th style="width: 130px;">Unit Cost</th>
                <th style="width: 130px;">Total Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $count = 1; @endphp
            @foreach($receiptsByBrand as $brand => $items)
                <tr class="category-row">
                    <td colspan="7">Brand/Distributor: {{ strtoupper($brand) }}</td>
                </tr>
                @foreach($items as $item)
                @php
                    $pv = $item->productVariant;
                    $buyPrice = $item->buying_price_per_unit;
                    $totalUnits = $item->total_units;
                    $finalCost = $item->final_buying_cost;
                    $pkgLabel = $pv->packaging;
                    $itemsPerPkg = $pv->items_per_package;
                @endphp
                <tr>
                    <td class="text-muted-row">{{ $count++ }}</td>
                    <td class="text-left">
                        <strong>{{ $pv->name }}</strong>
                    </td>
                    <td><span class="uom-badge">{{ $pv->measurement }}</span></td>
                    <td><span class="text-muted small">{{ $pkgLabel }} ({{ $itemsPerPkg }})</span></td>
                    <td class="qty-bold">
                        {{ number_format($item->quantity_received, (fmod($item->quantity_received, 1) == 0 ? 0 : 1)) }} 
                        {{ $pkgLabel }}{{ $item->quantity_received > 1 ? 's' : '' }}
                    </td>
                    <td>TSh {{ number_format($buyPrice) }} / btl</td>
                    <td class="qty-bold">TSh {{ number_format($finalCost) }}</td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #eee; font-weight: 800;">
                <td colspan="6" class="text-right py-3 pr-4">SHIPMENT NET TOTAL</td>
                <td class="text-center py-3" style="font-size: 1.1rem; color: var(--report-maroon);">TSh {{ number_format($receipts->sum('final_buying_cost')) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($notes)
    <div class="mt-4 p-3 bg-light rounded" style="border-left: 5px solid var(--report-maroon);">
        <h6 class="font-weight-bold mb-1 small text-uppercase" style="letter-spacing: 1px;">Observations & Notes:</h6>
        <p class="mb-0 text-dark small italic" style="line-height: 1.5;">{{ $notes }}</p>
    </div>
    @endif

    {{-- DUAL SIGNATURE AREAS --}}
    <div class="mt-5 pt-5 row">
        <div class="col-6 border-top pt-2">
            <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">
                Stock Keeper Name & Signature
            </small>
            <div class="mt-2 font-weight-bold" style="font-size:1.1rem; color: #d35400;">
                {{ $stockKeeper }}
            </div>
            <div class="mt-2 text-muted">_______________________________________</div>
        </div>
        <div class="col-6 border-top pt-2 text-right">
            <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">Accountant Name & Signature</small>
            <div class="mt-2 font-weight-bold" style="font-size:1.1rem; color: #d35400;">{{ $accountant }}</div>
            <div class="mt-2 text-muted">_______________________________________</div>
        </div>
    </div>
    
    <div class="text-center mt-4 small text-muted italic">
        Certified Audit Snapshot | MauzoLink System Generated
    </div>
    
    <div class="text-center mt-2" style="font-size: 0.72rem; color: var(--report-maroon); font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">
        Powered By EmCa Techonologies LTD - www.emca.tech
    </div>

</div>

@if(request()->has('auto_print'))
<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 800);
    }
</script>
@endif

@endsection
