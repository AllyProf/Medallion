@extends('layouts.dashboard')

@section('content')
<style>
    /* REPORT COLORS AND THEME - MOSHI STYLE */
    :root {
        --report-orange: #d35400;
        --report-border: #e67e22;
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
    .report-header-center img { height: 50px; margin-bottom: 5px; }
    .report-header-center h1 { font-size: 2.3rem; font-weight: 800; color: var(--report-orange); margin: 0; text-transform: uppercase; }
    .biz-contact-info { font-size: 0.9rem; color: #555; margin-top: 5px; } 

    .operations-title { color: var(--report-orange); font-weight: 700; font-size: 1.25rem; margin-top: 10px; }
    .orange-divider { height: 3px; background: var(--report-orange); margin: 15px 0; border: none; }

    .report-sub-meta { display: flex; justify-content: flex-end; font-size: 0.78rem; color: #777; gap: 15px; margin-bottom: 8px; }

    .title-area { position: relative; text-align: center; margin: 25px 0; }
    .main-report-title { font-size: 1.6rem; font-weight: 800; text-transform: uppercase; display: inline-block; border-bottom: 2px solid #555; padding-bottom: 3px; }
    .official-stamp { position: absolute; right: 28%; top: -8px; border: 4px solid #27ae60; color: #27ae60; padding: 3px 12px; font-weight: 900; font-size: 1.3rem; transform: rotate(-10deg); border-radius: 8px; opacity: 0.8; text-transform: uppercase; pointer-events: none; }

    .btn-print { background: #e67e22; color: #fff; padding: 10px 25px; border-radius: 6px; border: none; font-weight: 700; }
    .btn-print:hover { background: #d35400; color: #fff; }

    .report-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
    .stats-card-title { font-size: 0.95rem; font-weight: 800; color: var(--report-orange); text-transform: uppercase; border-bottom: 2px solid var(--report-orange); padding-bottom: 5px; margin-bottom: 10px; }
    .stats-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 0.88rem; }

    .audit-table { width: 100%; border-collapse: collapse; border: 1.5px solid #333; }
    .audit-table th { background: #f8f9fa; border: 1px solid #333; padding: 10px 6px; font-weight: 800; font-size: 0.72rem; text-transform: uppercase; text-align: center; }

    .category-row { background: #fdf2e9; font-weight: 800; text-transform: uppercase; font-size: 0.82rem; }
    .category-row td { padding: 8px 12px; border: 1px solid #333; }

    .audit-table td { border: 1px solid #333; padding: 10px 6px; font-size: 0.82rem; text-align: center; }
    .audit-table td.text-left { text-align: left; font-weight: 700; color: #1a1a1a; padding-left: 12px; }

    .qty-bold { font-weight: 800; font-size: 1.1rem; color: #222; }
    .text-muted-row { color: #888; }
    .uom-badge { color: #d35400; font-weight: 800; font-size: 0.8rem; }

    /* VERIFICATION - ON SCREEN ONLY */
    .verified-row { background-color: #f0fff4 !important; }
    .verified-row .item-name-text { text-decoration: line-through; opacity: 0.5; }

    @media print {
        @page { size: portrait; margin: 0.5cm; }
        .app-header, .app-sidebar, .d-print-none, .breadcrumb { display: none !important; }
        .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .report-page { padding: 0; }
        .audit-table { border: 2px solid #000; font-size: 0.70rem; }
        .audit-table th, .audit-table td { border: 1.5px solid #000; padding: 4px !important; font-size: 0.75rem !important; }
        .category-row td { padding: 4px !important; font-size: 0.8rem !important; }
        .report-header-center h1 { font-size: 1.8rem; }
        .report-stats-grid { gap: 15px; margin-bottom: 10px; }
        .stats-row { padding: 2px 0; font-size: 0.75rem; }
        .qty-bold { font-size: 0.85rem; }
        .item-name-text { font-size: 0.75rem; }
        tbody tr { page-break-inside: avoid; }
        .mt-5.pt-5.row { margin-top: 2rem !important; padding-top: 1rem !important; }
    }
</style>

<div class="report-page mt-3">
    
    <div class="report-header-center">
        <img src="https://ui-avatars.com/api/?name={{ urlencode($businessName) }}&background=d35400&color=fff&size=80" alt="Logo">
        <h1>{{ $businessName }}</h1>
        <div class="biz-contact-info">
            {{ $owner->city }} | Mobile: {{ $owner->phone }} | Email: {{ $owner->email }}
        </div>
        <div class="operations-title">{{ strtoupper($location) }} Operations Report</div>
        <hr class="orange-divider">
    </div>

    <div class="report-sub-meta">
        <span>Staff: {{ $staff ? $staff->full_name : 'Accountant' }}</span>
        <span>| Report #: STOCK-{{ strtoupper($location[0]) }}-{{ date('Ymd') }}-{{ strtoupper(substr(uniqid(), -4)) }}</span>
    </div>

    <div class="title-area">
        <h2 class="main-report-title">Shift Stock Sheet</h2>
        <div class="official-stamp">Official</div>
    </div>

    <div class="text-center mb-4 d-print-none">
        <button onclick="window.print()" class="btn btn-print shadow-sm"><i class="fa fa-print"></i> Print Report / PDF</button>
        <div class="mt-2 text-success" style="font-weight:600; font-size:0.85rem;"><i class="fa fa-leaf"></i> Tip: Select "Print on both sides" in your printer dialogue to save paper!</div>
    </div>

    @php
        $filteredItems = $location == 'warehouse' 
                          ? $stockData->filter(fn($r) => $r['warehouse_qty'] > 0)
                          : $stockData->filter(fn($r) => $r['counter_qty'] > 0 || $r['open_tots'] > 0);
    @endphp

    <div class="report-stats-grid">
        <div>
            <div class="stats-card-title">Report Information</div>
            <div class="stats-row"><strong>Report Date:</strong> <span>{{ date('d M Y') }}</span></div>
            <div class="stats-row"><strong>Audit Location:</strong> <span>{{ ucfirst($location) }}</span></div>
            <div class="stats-row"><strong>System Certification:</strong> <span>MauzoLink Audit Tool</span></div>
        </div>
        <div>
            <div class="stats-card-title">Report Summary</div>
            <div class="stats-row"><strong>Total Variants Tracked:</strong> <span>{{ $filteredItems->count() }} Items</span></div>
            <div class="stats-row"><strong>Low Stock Alerts:</strong> <span>{{ $stockData->where('status', 'Low Stock')->count() }} Tracks</span></div>
            <div class="stats-row"><strong>Report Status:</strong> <span>Finalized Snapshot</span></div>
        </div>
    </div>

    <div class="stats-card-title mb-2">1. {{ strtoupper($location) }} Physical Inventory Status</div>
    
    <table class="audit-table">
        <thead>
            <tr>
                <th class="d-print-none" style="width: 35px; background: #eee;">TIC</th>
                <th style="width: 35px;">#</th>
                <th class="text-left">Drink Item Name</th>
                <th style="width: 70px;">UOM</th>
                <th style="width: 110px;">Packaging</th>
                @if($location == 'counter')
                    <th style="width: 85px;">Stock In</th>
                    <th style="width: 85px;">Sold</th>
                @endif
                <th style="width: 130px;">
                    Qty ({{ $location == 'warehouse' ? 'Pkgs' : 'Units' }})
                </th>
                <th style="width: 130px;">
                    Closing Bal ({{ $location == 'warehouse' ? 'Pkgs' : 'Units' }})
                </th>
            </tr>
        </thead>
        <tbody>
            @php 
                $categories = $filteredItems->groupBy('category');
                $globalCount = 1;
            @endphp

            @foreach($categories as $categoryName => $items)
                <tr class="category-row">
                    <td class="d-print-none"></td>
                    <td colspan="{{ $location == 'counter' ? '8' : '6' }}">
                        {{ $categoryName }}
                    </td>
                </tr>

                @foreach($items as $item)
                    @php
                        $qty = $location == 'warehouse' ? (float)$item['warehouse_qty'] : (float)$item['counter_qty'];
                        $pkgs = 0;
                        if($item['items_per_pkg'] > 1) {
                            $pkgs = $qty / $item['items_per_pkg'];
                        } else {
                            $pkgs = $qty;
                        }
                        
                        $unitLabel = $item['unit'];
                        if(in_array(strtolower($unitLabel), ['ml', 'l', 'tot', 'cl'])) {
                            $unitLabel = 'Bottle';
                        }
                    @endphp
                    <tr class="audit-row">
                        <td class="d-print-none text-center" style="background: #fafafa;">
                            <input type="checkbox" class="verify-check" 
                                   data-key="{{ $location }}_{{ $item['item_id'] }}" 
                                   onchange="tickRow(this)">
                        </td>
                        <td class="text-muted-row">{{ $globalCount++ }}</td>
                        <td class="text-left">
                            <span class="item-name-text">{{ $item['item_name'] }}</span>
                        </td>
                        <td><span class="uom-badge">{{ $item['measurement'] }}</span></td>
                        <td><span class="text-muted small">{{ $item['packaging'] }} ({{ $item['items_per_pkg'] }})</span></td>
                        
                        @if($location == 'counter')
                            <td class="text-success font-weight-bold" style="background:#f4fbf7;">
                                {{ floatval($item['received_today']) > 0 ? floatval($item['received_today']) . ' ' . $unitLabel : '0 ' . $unitLabel }}
                            </td>
                            <td class="text-danger font-weight-bold" style="background:#fdf5f5;">
                                {{ floatval($item['sold_today']) > 0 ? floatval($item['sold_today']) . ' ' . $unitLabel : '0 ' . $unitLabel }}
                            </td>
                        @endif
                        
                        {{-- DISPLAY QTY BY LOCATION --}}
                        <td class="qty-bold">
                            @if($location == 'warehouse')
                                {{ $pkgs > 0 ? (int)$pkgs . ' ' . $item['packaging'] . ($pkgs > 1 ? 's' : '') : '-' }}
                            @else
                                @php
                                    $unitLabel = $item['unit'];
                                    if(in_array(strtolower($unitLabel), ['ml', 'l', 'tot', 'cl'])) {
                                        $unitLabel = 'Bottle';
                                    }
                                    $qtyDisplay = $qty > 0 ? (int)$qty . ' ' . $unitLabel . ($qty > 1 ? 's' : '') : '';
                                    if(isset($item['open_tots']) && $item['open_tots'] > 0) {
                                        $qtyDisplay .= ($qty > 0 ? ' + ' : '') . $item['open_tots'] . ' Tots';
                                    }
                                    if($qtyDisplay === '') $qtyDisplay = '-';
                                @endphp
                                {{ $qtyDisplay }}
                            @endif
                        </td>
                        
                        <td class="qty-bold" style="background:#f9f9f9; border-left:2.5px solid #000;">
                            @if($location == 'warehouse')
                                {{ $pkgs > 0 ? (int)$pkgs . ' ' . $item['packaging'] . ($pkgs > 1 ? 's' : '') : '-' }}
                            @else
                                @php
                                    $unitLabel = $item['unit'];
                                    if(in_array(strtolower($unitLabel), ['ml', 'l', 'tot', 'cl'])) {
                                        $unitLabel = 'Bottle';
                                    }
                                    $qtyDisplay = $qty > 0 ? (int)$qty . ' ' . $unitLabel . ($qty > 1 ? 's' : '') : '';
                                    if(isset($item['open_tots']) && $item['open_tots'] > 0) {
                                        $qtyDisplay .= ($qty > 0 ? ' + ' : '') . $item['open_tots'] . ' Tots';
                                    }
                                    if($qtyDisplay === '') $qtyDisplay = '-';
                                @endphp
                                {{ $qtyDisplay }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    {{-- DUAL SIGNATURE AREAS --}}
    <div class="mt-5 pt-5 row">
        <div class="col-md-6 border-top pt-2">
            <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">
                {{ $location == 'warehouse' ? 'Stock Keeper' : 'Counter Staff' }} Name & Signature
            </small>
            <div class="mt-2 font-weight-bold" style="font-size:1.1rem; color: #d35400;">
                {{ $stockKeeper }}
            </div>
            <div class="mt-2 text-muted">_______________________________________</div>
        </div>
        <div class="col-md-6 border-top pt-2 text-right">
            <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">Accountant Name & Signature</small>
            <div class="mt-2 font-weight-bold" style="font-size:1.1rem; color: #d35400;">{{ $accountant }}</div>
            <div class="mt-2 text-muted">_______________________________________</div>
        </div>
    </div>
    
    <div class="text-center mt-4 small text-muted italic">
        Date Generated: {{ date('d M Y, H:i') }} | Certified Audit Snapshot
    </div>

</div>

<div class="d-print-none mt-4 text-center pb-5">
    <button onclick="clearTicks()" class="btn btn-sm btn-outline-danger mr-2"><i class="fa fa-undo"></i> Reset All Audit Ticks</button>
</div>

<script>
    function tickRow(cb) {
        const row = cb.closest('tr');
        if(cb.checked) { row.classList.add('verified-row'); } 
        else { row.classList.remove('verified-row'); }
        localStorage.setItem('audit_tick_' + cb.dataset.key, cb.checked ? '1' : '0');
    }
    function clearTicks() {
        if(confirm('Reset all audit ticks?')) {
            document.querySelectorAll('.verify-check').forEach(cb => { cb.checked = false; tickRow(cb); localStorage.removeItem('audit_tick_' + cb.dataset.key); });
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.verify-check').forEach(cb => {
            if(localStorage.getItem('audit_tick_' + cb.dataset.key) === '1') { cb.checked = true; tickRow(cb); }
        });
    });
</script>
@endsection
