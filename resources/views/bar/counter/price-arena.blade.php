@extends('layouts.dashboard')

@section('content')
@php
    $showBuying = in_array($priceMode, ['buying', 'both'], true);
    $showSelling = in_array($priceMode, ['selling', 'both'], true);
@endphp
<style>
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
    .orange-divider {
        height: 0;
        background: transparent;
        margin: 15px 0;
        border: none;
        border-top: 3px solid #d35400;
    }
    .report-sub-meta { display: flex; justify-content: flex-end; font-size: 0.78rem; color: #777; gap: 15px; margin-bottom: 8px; }
    .title-area { position: relative; text-align: center; margin: 25px 0; }
    .main-report-title { font-size: 1.6rem; font-weight: 800; text-transform: uppercase; display: inline-block; border-bottom: 2px solid #555; padding-bottom: 3px; }
    .official-stamp { position: absolute; right: 22%; top: -8px; border: 4px solid #27ae60; color: #27ae60; padding: 3px 12px; font-weight: 900; font-size: 1.3rem; transform: rotate(-10deg); border-radius: 8px; opacity: 0.8; text-transform: uppercase; pointer-events: none; }
    .btn-print { background: #e67e22; color: #fff; padding: 10px 25px; border-radius: 6px; border: none; font-weight: 700; }
    .btn-print:hover { background: #d35400; color: #fff; }
    .arena-toolbar { background: #fff7f0; border: 1.5px solid var(--report-border); border-radius: 8px; padding: 14px 16px; margin-bottom: 22px; }
    .arena-toolbar label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--report-orange); margin-bottom: 4px; display: block; }
    .arena-toolbar .form-control { border: 1px solid #d5d5d5; font-size: 0.85rem; height: 36px; }
    .arena-totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 0 0 22px; }
    .arena-total-card { border: 1.5px solid #333; border-top: 3px solid var(--report-orange); padding: 10px 12px; background: #fff; }
    .arena-total-card .label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #777; }
    .arena-total-card .value { font-size: 1.15rem; font-weight: 800; color: #1a1a1a; margin-top: 2px; }
    .report-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
    .stats-card-title { font-size: 0.95rem; font-weight: 800; color: var(--report-orange); text-transform: uppercase; border-bottom: 2px solid var(--report-orange); padding-bottom: 5px; margin-bottom: 10px; }
    .stats-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 0.88rem; }
    .audit-table { width: 100%; border-collapse: collapse; border: 1.5px solid #333; }
    .audit-table th { background: #f8f9fa; border: 1px solid #333; padding: 10px 6px; font-weight: 800; font-size: 0.72rem; text-transform: uppercase; text-align: center; }
    .category-row { background: #fdf2e9; font-weight: 800; text-transform: uppercase; font-size: 0.82rem; }
    .category-row td { padding: 8px 12px; border: 1px solid #333; }
    .audit-table td { border: 1px solid #333; padding: 10px 6px; font-size: 0.82rem; text-align: center; }
    .audit-table td.text-left { text-align: left; font-weight: 700; color: #1a1a1a; padding-left: 12px; }
    .qty-bold { font-weight: 800; font-size: 1.05rem; color: #222; }
    .text-muted-row { color: #888; }
    .uom-badge { color: #d35400; font-weight: 800; font-size: 0.8rem; }
    .price-col { background: #fffaf5; font-weight: 700; }
    .value-col { background: #f4fbf7; font-weight: 800; }
    .sell-value-col { background: #f0f7ff; font-weight: 800; }
    .totals-row td { background: #2c3e50 !important; color: #fff !important; font-weight: 800; }
    .category-subtotal td { background: #efe6dc !important; font-weight: 800; }
    @media print {
        @page { size: portrait; margin: 0.5cm; }
        .app-header, .app-sidebar, .d-print-none, .breadcrumb { display: none !important; }
        .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .report-page {
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .orange-divider {
            border: none !important;
            border-top: 3px solid #d35400 !important;
            background: transparent !important;
            height: 0 !important;
            margin: 10px 0 14px !important;
            display: block !important;
            visibility: visible !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .operations-title { color: #d35400 !important; }
        .audit-table th, .audit-table td { border: 1.5px solid #000; padding: 4px !important; font-size: 0.75rem !important; }
        tbody tr { page-break-inside: avoid; }
    }
</style>

<div class="report-page mt-3">
    <div class="report-header-center">
        <h1>{{ $businessName }}</h1>
        <div class="biz-contact-info">
            {{ $owner->city }} | Mobile: {{ $owner->phone }} | Email: {{ $owner->email }}
        </div>
        <div class="operations-title">{{ $location == 'warehouse' ? 'WAREHOUSE PRICE' : 'COUNTOR PRICE' }}</div>
        <hr class="orange-divider">
    </div>

    @php
        $sheetDate = $sheetDate ?? now()->format('Y-m-d');
        $sheetDateLabel = $sheetDateLabel ?? \Carbon\Carbon::parse($sheetDate)->format('d M Y');
        $isToday = $isToday ?? ($sheetDate === now()->format('Y-m-d'));
    @endphp

    <div class="report-sub-meta">
        <span>Staff: {{ $staff ? $staff->full_name : 'Accountant' }}</span>
        <span>| Sheet Date: {{ $sheetDateLabel }}</span>
        <span>| Report #: PRICE-{{ strtoupper($location[0]) }}-{{ str_replace('-', '', $sheetDate) }}-{{ strtoupper(substr(uniqid(), -4)) }}</span>
    </div>

    <div class="title-area">
        <h2 class="main-report-title">{{ $location == 'warehouse' ? 'Warehouse Price' : 'Countor Price' }}</h2>
    </div>

    <div class="arena-toolbar d-print-none">
        <form method="GET" action="{{ route('bar.price-arena', $location) }}" class="row align-items-end">
            <div class="col-md-2 col-sm-6 form-group mb-2 mb-md-0">
                <label for="sheet-date" title="Sheet Date"><i class="fa fa-calendar"></i></label>
                <input type="date" id="sheet-date" name="date" value="{{ $sheetDate }}" max="{{ now()->format('Y-m-d') }}" class="form-control" onchange="this.form.submit()">
            </div>
            <div class="col-md-2 col-sm-6 form-group mb-2 mb-md-0">
                <label>Location</label>
                <select name="location_switch" class="form-control" onchange="window.location.href=this.value">
                    <option value="{{ route('bar.price-arena', ['warehouse', 'price_mode' => $priceMode, 'category' => $categoryFilter, 'date' => $sheetDate]) }}" {{ $location === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                    <option value="{{ route('bar.price-arena', ['counter', 'price_mode' => $priceMode, 'category' => $categoryFilter, 'date' => $sheetDate]) }}" {{ $location === 'counter' ? 'selected' : '' }}>Counter</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-6 form-group mb-2 mb-md-0">
                <label>Price View</label>
                <select name="price_mode" class="form-control" onchange="this.form.submit()">
                    <option value="buying" {{ $priceMode === 'buying' ? 'selected' : '' }}>Buying Price Only</option>
                    <option value="selling" {{ $priceMode === 'selling' ? 'selected' : '' }}>Selling Price Only</option>
                    <option value="both" {{ $priceMode === 'both' ? 'selected' : '' }}>Buying + Selling</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-6 form-group mb-2 mb-md-0">
                <label>Category</label>
                <select name="category" class="form-control" onchange="this.form.submit()">
                    <option value="all" {{ $categoryFilter === 'all' ? 'selected' : '' }}>All Categories</option>
                    @foreach($availableCategories as $cat)
                        <option value="{{ $cat }}" {{ strcasecmp((string)$categoryFilter, (string)$cat) === 0 ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-sm-12 form-group mb-0 text-md-right">
                <button type="button" onclick="window.print()" class="btn btn-print btn-sm"><i class="fa fa-print"></i> Print</button>
            </div>
        </form>
    </div>

    <div class="report-stats-grid">
        <div>
            <div class="stats-card-title">Report Information</div>
            <div class="stats-row"><strong>Report Date:</strong> <span>{{ $sheetDateLabel }}{{ $isToday ? '' : ' (historical)' }}</span></div>
            <div class="stats-row"><strong>Location:</strong> <span>{{ ucfirst($location) }}</span></div>
            <div class="stats-row"><strong>System Certification:</strong> <span>MauzoLink Audit Tool</span></div>
        </div>
        <div>
            <div class="stats-card-title">Price Summary</div>
            <div class="stats-row"><strong>Items Priced:</strong> <span>{{ $priceItems->count() }} Items</span></div>
            <div class="stats-row"><strong>Price Mode:</strong> <span>{{ ucfirst($priceMode) }}</span></div>
            <div class="stats-row"><strong>Category:</strong> <span>{{ $categoryFilter === 'all' ? 'All Categories' : $categoryFilter }}</span></div>
        </div>
    </div>

    <div class="arena-totals">
        <div class="arena-total-card">
            <div class="label">Items</div>
            <div class="value">{{ number_format($priceItems->count()) }}</div>
        </div>
        <div class="arena-total-card">
            <div class="label">Total Qty</div>
            <div class="value">{{ number_format($priceTotals['qty']) }}</div>
        </div>
        @if($showBuying)
        <div class="arena-total-card">
            <div class="label">Total Buying Value</div>
            <div class="value text-danger">TSh {{ number_format($priceTotals['buying_value'], 0) }}</div>
        </div>
        @endif
        @if($showSelling)
        <div class="arena-total-card">
            <div class="label">Total Selling Value</div>
            <div class="value text-primary">TSh {{ number_format($priceTotals['selling_value'], 0) }}</div>
        </div>
        @endif
    </div>

    <div class="stats-card-title mb-2">{{ strtoupper($location) }} Buying / Selling Valuation</div>

    <table class="audit-table">
        <thead>
            <tr>
                <th style="width: 35px;">#</th>
                <th class="text-left">Drink Item Name</th>
                <th style="width: 70px;">UOM</th>
                <th style="width: 90px;">Qty</th>
                @if($showBuying)
                    <th style="width: 110px;">Buying Price</th>
                    <th style="width: 120px;">Buying Value</th>
                @endif
                @if($showSelling)
                    <th style="width: 110px;">Selling Price</th>
                    <th style="width: 120px;">Selling Value</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                $priceCategories = $priceItems->groupBy('category');
                $globalCount = 1;
                $colspan = 4 + ($showBuying ? 2 : 0) + ($showSelling ? 2 : 0);
            @endphp

            @forelse($priceCategories as $categoryName => $items)
                <tr class="category-row">
                    <td colspan="{{ $colspan }}">{{ $categoryName }}</td>
                </tr>
                @php $catBuying = 0; $catSelling = 0; $catQty = 0; @endphp
                @foreach($items as $item)
                    @php
                        $qty = $location == 'warehouse' ? (float)$item['warehouse_qty'] : (float)$item['counter_qty'];
                        $buy = (float)$item['buying_price'];
                        $sell = (float)$item['selling_price'];
                        $buyValue = $qty * $buy;
                        $sellValue = $qty * $sell;
                        $catBuying += $buyValue;
                        $catSelling += $sellValue;
                        $catQty += $qty;
                        $unitLabel = $item['unit'];
                        if (in_array(strtolower((string)$unitLabel), ['ml', 'l', 'tot', 'cl'])) {
                            $unitLabel = 'Bottle';
                        }
                    @endphp
                    <tr>
                        <td class="text-muted-row">{{ $globalCount++ }}</td>
                        <td class="text-left">{{ $item['item_name'] }}</td>
                        <td>
                            @php
                                $sizeUnit = strtolower(trim((string)($item['size_unit'] ?? 'ml')));
                                if (in_array($sizeUnit, ['l', 'ltr', 'litre', 'liter', 'litres', 'liters'], true)) {
                                    $sizeLabel = 'litre';
                                } elseif ($sizeUnit === '') {
                                    $sizeLabel = 'ml';
                                } else {
                                    $sizeLabel = $sizeUnit;
                                }
                                $meas = trim((string)($item['measurement'] ?? ''));
                            @endphp
                            <span class="uom-badge">
                                @if($meas !== '')
                                    {{ $meas }} {{ $sizeLabel }}
                                @else
                                    {{ $sizeLabel }}
                                @endif
                            </span>
                        </td>
                        <td class="qty-bold">{{ number_format($qty) }} <small class="text-muted">{{ $unitLabel }}</small></td>
                        @if($showBuying)
                            <td class="price-col">TSh {{ number_format($buy, 0) }}</td>
                            <td class="value-col">TSh {{ number_format($buyValue, 0) }}</td>
                        @endif
                        @if($showSelling)
                            <td class="price-col">TSh {{ number_format($sell, 0) }}</td>
                            <td class="sell-value-col">TSh {{ number_format($sellValue, 0) }}</td>
                        @endif
                    </tr>
                @endforeach
                <tr class="category-subtotal">
                    <td></td>
                    <td class="text-left" colspan="2">{{ $categoryName }} Subtotal</td>
                    <td>{{ number_format($catQty) }}</td>
                    @if($showBuying)
                        <td></td>
                        <td>TSh {{ number_format($catBuying, 0) }}</td>
                    @endif
                    @if($showSelling)
                        <td></td>
                        <td>TSh {{ number_format($catSelling, 0) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="text-muted py-4">No stock items found for this filter.</td>
                </tr>
            @endforelse

            @if($priceItems->count() > 0)
                <tr class="totals-row">
                    <td></td>
                    <td class="text-left" colspan="2">GRAND TOTAL</td>
                    <td>{{ number_format($priceTotals['qty']) }}</td>
                    @if($showBuying)
                        <td></td>
                        <td>TSh {{ number_format($priceTotals['buying_value'], 0) }}</td>
                    @endif
                    @if($showSelling)
                        <td></td>
                        <td>TSh {{ number_format($priceTotals['selling_value'], 0) }}</td>
                    @endif
                </tr>
            @endif
        </tbody>
    </table>

    <div class="mt-5 pt-4 row">
        <div class="col-6 border-top pt-3">
            <div class="font-weight-bold text-uppercase" style="letter-spacing:0.5px; color: #2c3e50; font-size: 0.95rem;">
                Saini ya Mmiliki wa Restaurant
            </div>
            <div class="mt-2 font-weight-bold" style="font-size: 1.05rem; color: #d35400;">
                CAESSAR SHAYO
            </div>
            <div class="mt-2 text-muted">_______________________________________</div>
            <div class="mt-3 font-weight-bold" style="font-size: 0.88rem; color: #444;">
                Tarehe: <span style="border-bottom: 1px dotted #555; padding-bottom: 2px; display: inline-block; min-width: 160px;">{{ $sheetDateLabel }}</span>
            </div>
            <div class="mt-2 font-weight-bold" style="font-size: 0.88rem; color: #444;">
                Mbele ya: <span style="border-bottom: 1px dotted #555; padding-bottom: 2px; display: inline-block; min-width: 160px;">_______________________</span>
            </div>
            <div class="mt-3 font-weight-bold text-uppercase" style="font-size: 0.95rem; color: #d35400; letter-spacing: 0.5px;">
                Wakili
            </div>
        </div>

        <div class="col-6 border-top pt-3 text-right">
            <div class="font-weight-bold text-uppercase" style="letter-spacing:0.5px; color: #2c3e50; font-size: 0.95rem;">
                Saini ya Mwendesha Biashara
            </div>
            <div class="mt-2 text-muted">_______________________________________</div>
            <div class="mt-4 pt-3 font-weight-bold text-uppercase" style="font-size: 0.95rem; color: #d35400; letter-spacing: 1px;">
                SAINI & MHURI
            </div>
        </div>
    </div>

    <div class="text-center mt-4 small text-muted italic">
        Sheet Date: {{ $sheetDateLabel }} | Date Generated: {{ $generatedAt }} | Certified {{ $location == 'warehouse' ? 'Warehouse Price' : 'Countor Price' }} Snapshot
    </div>
</div>
@endsection
