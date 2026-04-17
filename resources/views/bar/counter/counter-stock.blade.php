@extends('layouts.dashboard')

@section('title', 'Counter Stock')

@section('content')
@php $unitLabel = 'btl'; @endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-cubes"></i> Counter Stock</h1>
    <p>View current counter inventory in detail</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bar.counter.dashboard') }}">Counter Dashboard</a></li>
    <li class="breadcrumb-item">Counter Stock</li>
  </ul>
</div>

<!-- Statistics -->
<div class="row">
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>Total Items</h4>
        <p><b>{{ count($variants) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-exclamation-triangle fa-3x"></i>
      <div class="info">
        <h4>Low Stock Items</h4>
        <p><b>{{ $variants->where('is_low_stock', true)->count() }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile shadow-sm border-0" style="border-radius: 15px;">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="tile-title mb-0">Counter Inventory</h3>
        <div class="d-flex align-items-center">
            <!-- VIEW TOGGLE -->
            <div class="btn-group mr-3" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary active view-btn" data-view="grid">
                    <i class="fa fa-th"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary view-btn" data-view="list">
                    <i class="fa fa-list"></i>
                </button>
            </div>
          <a href="{{ route('bar.stock-transfers.available') }}" class="btn btn-primary mr-2 shadow-sm font-weight-bold">
            <i class="fa fa-plus-circle"></i> Request Transfer
          </a>
          <a href="{{ route('bar.counter.dashboard') }}" class="btn btn-secondary shadow-sm">
            <i class="fa fa-arrow-left"></i> Back
          </a>
        </div>
      </div>

      <!-- SEARCH & QUICK FILTERS -->
      <div class="row mb-4">
          <div class="col-md-3">
              <div class="form-group">
                  <label class="control-label font-weight-bold">Search Products</label>
                  <div class="input-group">
                      <div class="input-group-prepend">
                          <span class="input-group-text"><i class="fa fa-search"></i></span>
                      </div>
                      <input type="text" id="inventorySearch" class="form-control" placeholder="Search by name...">
                  </div>
              </div>
          </div>
          <div class="col-md-9">
              <label class="control-label font-weight-bold">Quick Filters (Categories & Brands)</label>
              <div class="category-tabs-wrapper">
                  <div class="d-flex align-items-center overflow-auto no-scrollbar py-1" id="categoryContainer">
                      <button class="btn btn-sm btn-outline-primary active filter-pill mr-1 mb-1" data-filter="all" data-filter-type="category">
                          ALL ITEMS
                      </button>
                      @foreach($categories as $label)
                          <button class="btn btn-sm btn-outline-primary filter-pill mr-1 mb-1" data-filter="{{ Str::slug($label) }}" data-filter-type="category">
                              {{ strtoupper($label) }}
                          </button>
                      @endforeach
                  </div>
              </div>
          </div>
      </div>

      <hr class="mb-4">

      <div class="tile-body">
        @if(count($variants) > 0)
          
          <!-- INVENTORY GRID -->
          <div class="row mt-2" id="inventoryGrid">
            @foreach($variants as $variant)
            @php
              $catSlug     = \Illuminate\Support\Str::slug($variant['category']);
              $brandSlug   = \Illuminate\Support\Str::slug($variant['brand']);
              $qty         = $variant['quantity'];
              $ipp         = $variant['items_per_package'] ?? 1;
              $searchName = strtolower($variant['product_name'] . ' ' . $variant['variant_name'] . ' ' . $variant['brand'] . ' ' . $variant['category']);
              $unitLabel = strtolower($variant['unit'] ?? 'btl');
              $pkgLabel  = strtolower($variant['packaging'] ?? 'pkg');
              $crates      = $ipp > 1 ? floor($qty / $ipp) : 0;
              $extraBottles= $ipp > 1 ? ($qty % $ipp) : $qty;
              $statusColor = $qty <= 0 ? 'danger' : ($variant['is_low_stock'] ? 'warning' : 'success');
              $displayTitle = !empty($variant['variant_name']) ? $variant['variant_name'] : $variant['product_name'];
            @endphp
            <div class="col-md-4 mb-4 product-card-wrapper" 
                 data-category="{{ $catSlug }}"
                  data-brand="{{ $brandSlug }}"
                  data-name="{{ $searchName }}"
                  data-item-id="{{ $variant['id'] }}"
                  data-qty="{{ $qty }}"
                  data-threshold="{{ $variant['counter_alert_threshold'] }}">
              
                  <div class="tile p-3 h-100 mb-0 shadow-sm border inventory-item-card transition-all" 
                       id="card-{{ $variant['id'] }}"
                       style="border-radius: 15px; border-width: 2px !important;">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                      <div class="flex-grow-1 pr-2">
                          <h6 class="font-weight-bold text-primary mb-1 line-clamp-1" title="{{ $displayTitle }}">{{ $displayTitle }}</h6>
                          @php
                              $b = strtolower($variant['brand'] ?? '');
                              $c = strtolower($variant['category'] ?? '');
                              $displayBrand = $variant['brand'];
                              if ($b && stripos($b, 'bonite') !== false) {
                                  $displayBrand = trim(str_ireplace(['bonite', '(', ')', 'coca-cola'], '', $b));
                                  if (empty($displayBrand)) $displayBrand = 'Sodas';
                                  else $displayBrand = ucwords($displayBrand);
                              }
                              // Avoid "Water • Water" or "Drinking Water • Water"
                              $isDuplicate = ($b === $c || str_contains($b, $c) || str_contains($c, $b));
                          @endphp
                          <p class="text-muted smallest mb-0">
                              @if(!$isDuplicate) {{ $displayBrand }} • @endif {{ $variant['category'] }}
                          </p>
                      </div>
                      @php
                          $vM = $variant['variant'] ?? '';
                          $vU = $variant['measurement_unit'] ?? '';
                          $fullMeasure = $vM;
                          if (!empty($vM) && !preg_match('/[a-zA-Z]/', $vM)) {
                              $fullMeasure = $vM . ($vU ?: '');
                          }
                      @endphp
                      <span class="badge badge-secondary px-2 py-1 smallest">{{ $fullMeasure }}</span>
                  </div>

                  <div class="bg-light p-2 rounded mb-3">
                      <div class="d-flex justify-content-between small mb-1">
                          <span class="text-muted">Stock Level:</span>
                          @php
                             $u = strtolower($unitLabel);
                             if (in_array($u, ['ml', 'cl', 'l'])) $u = 'btl';
                             $finalUnit = $qty == 1 ? $u : ($u . 's');
                             
                             $openStr = '';
                             $totCapacity = $variant['total_tots_capacity'] ?? 0;
                             $pName = 'Tot';
                             $cat = strtolower($variant['raw_category'] ?? '');
                             if (str_contains($cat, 'wine')) $pName = 'Glass';
                             elseif (str_contains($cat, 'spirit') || str_contains($cat, 'whiskey') || str_contains($cat, 'vodka') || str_contains($cat, 'gin')) $pName = 'Shot';
                             $pNamePlural = \Illuminate\Support\Str::plural($pName);

                             if (($variant['open_tots'] ?? 0) > 0) {
                                 $openStr = ' <span class="text-info ml-1">+ ' . $variant['open_tots'] . ' ' . ($variant['open_tots'] > 1 ? $pNamePlural : $pName) . '</span>';
                             }

                             $totalAvailableTots = ($qty * $totCapacity) + ($variant['open_tots'] ?? 0);
                          @endphp
                          <div class="text-right">
                              <strong class="text-{{ $statusColor }} font-weight-bold d-block">{{ number_format($qty) }} {{ $finalUnit }}{!! $openStr !!}</strong>
                              @if($variant['can_sell_in_tots'] && $totCapacity > 0)
                                  <small class="text-muted font-italic" style="font-size: 0.85em;">
                                      ≈ {{ number_format($totalAvailableTots) }} total {{ strtolower($totalAvailableTots == 1 ? $pName : $pNamePlural) }}
                                  </small>
                              @endif
                          </div>
                      </div>
                      {{-- Packages hidden for counter bottles focus --}}
                  </div>

                  <div class="row no-gutters mb-3 text-center bg-white rounded border py-2 shadow-xs">
                      <div class="col-6 border-right">
                          <div class="smallest text-muted">Bottle Price</div>
                          <div class="font-weight-bold text-dark">Tsh {{ number_format($variant['selling_price']) }}</div>
                      </div>
                      <div class="col-6">
                          <div class="smallest text-muted">Glass/Tot</div>
                          @if($variant['can_sell_in_tots'])
                              <div class="font-weight-bold text-info">Tsh {{ number_format($variant['selling_price_per_tot']) }}</div>
                          @else
                              <div class="smallest italic text-muted">N/A</div>
                          @endif
                      </div>
                  </div>

                  <div class="d-flex justify-content-between align-items-center mb-0 mt-auto">
                    @if($variant['product_image'])
                        <img src="{{ asset('storage/' . $variant['product_image']) }}" class="rounded shadow-xs" style="width: 42px; height: 42px; object-fit: contain; background: #fff;" onerror="this.style.display='none'">
                    @else
                        <div class="rounded bg-light shadow-xs d-flex align-items-center justify-content-center text-muted" style="width: 42px; height: 42px;">
                            <i class="fa fa-cubes opacity-50"></i>
                        </div>
                    @endif
                    <button class="btn btn-sm btn-outline-primary btn-set-threshold" 
                            title="Set Low Stock Alert"
                            onclick="openThresholdModal({{ $variant['id'] }}, '{{ addslashes($displayTitle) }}', '{{ $unitLabel }}')">
                        <i class="fa fa-bell-o"></i> Alert
                    </button>
                  </div>
                  <!-- Threshold indicator -->
                  <div class="threshold-info mt-1 text-right" id="threshold-info-{{ $variant['id'] }}" style="font-size:9px; color:#e65100; font-weight:bold;"></div>
              </div>
            </div>
            @endforeach
          </div>

          <!-- INVENTORY LIST (Hidden by default) -->
          <div id="inventoryList" class="table-responsive d-none mt-2">
              <table class="table table-hover table-bordered shadow-sm" style="border-radius: 10px; overflow: hidden;">
                  <thead class="bg-light">
                      <tr>
                          <th>Product Name</th>
                          <th>Brand / Category</th>
                          <th>Measure</th>
                          <th>Counter Stock</th>
                          <th>Selling Price</th>
                          <th class="text-center">Status</th>
                          <th class="text-center">Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      @foreach($variants as $variant)
                      @php
                        $catSlug = \Illuminate\Support\Str::slug($variant['category']);
                        $brandSlug = \Illuminate\Support\Str::slug($variant['brand']);
                        $displayTitle = !empty($variant['variant_name']) ? $variant['variant_name'] : $variant['product_name'];
                        $searchName = strtolower($variant['product_name'] . ' ' . $variant['variant_name'] . ' ' . $variant['brand'] . ' ' . $variant['category']);
                        $unitLabel = strtolower($variant['unit'] ?? 'btl');
                        $pkgLabel  = strtolower($variant['packaging'] ?? 'pkg');
                      @endphp
                      <tr class="product-card-wrapper" 
                          id="row-{{ $variant['id'] }}"
                          data-item-id="{{ $variant['id'] }}"
                          data-category="{{ $catSlug }}" 
                          data-brand="{{ $brandSlug }}"
                          data-name="{{ $searchName }}"
                          data-qty="{{ $variant['quantity'] }}"
                          data-threshold="{{ $variant['counter_alert_threshold'] }}">
                          <td><strong class="text-primary">{{ $displayTitle }}</strong><br><small class="text-muted">{{ $variant['brand'] }}</small></td>
                          <td>
                            <strong>{{ $variant['brand'] }}</strong><br>
                            <span class="badge badge-light border smallest text-muted">{{ $variant['category'] }}</span>
                          </td>
                          <td><span class="badge badge-secondary">{{ $variant['variant'] }}{{ (preg_match('/[a-zA-Z]/', ($variant['variant'] ?? '')) ? '' : ($variant['measurement_unit'] ?? '')) }}</span></td>
                          <td>
                              @php
                                 $u = strtolower($unitLabel);
                                 if (in_array($u, ['ml', 'cl', 'l'])) $u = 'btl';
                                 $finalUnit = $variant['quantity'] == 1 ? $u : ($u . 's');
                                 
                                 $totCapacity = $variant['total_tots_capacity'] ?? 0;
                                 $pName = 'Tot';
                                 $cat = strtolower($variant['raw_category'] ?? '');
                                 if (str_contains($cat, 'wine')) $pName = 'Glass';
                                 elseif (str_contains($cat, 'spirit') || str_contains($cat, 'whiskey') || str_contains($cat, 'vodka') || str_contains($cat, 'gin')) $pName = 'Shot';
                                 $pNamePlural = \Illuminate\Support\Str::plural($pName);

                                 $openStr = '';
                                 if (($variant['open_tots'] ?? 0) > 0) {
                                     $openStr = '<br><span class="text-info smallest font-weight-bold">+ ' . $variant['open_tots'] . ' ' . ($variant['open_tots'] > 1 ? $pNamePlural : $pName) . ' open</span>';
                                 }
                                 
                                 $totalAvailableTots = ($variant['quantity'] * $totCapacity) + ($variant['open_tots'] ?? 0);
                              @endphp
                              <strong class="text-{{ $variant['quantity'] < 10 ? 'warning' : 'dark' }}">{{ number_format($variant['quantity']) }} {{ $finalUnit }}</strong>{!! $openStr !!}
                              @if($variant['can_sell_in_tots'] && $totCapacity > 0)
                                  <div class="smallest text-muted font-italic">Total: {{ number_format($totalAvailableTots) }} {{ strtolower($totalAvailableTots == 1 ? $pName : $pNamePlural) }}</div>
                              @endif
                              {{-- Packages hidden for counter bottles focus --}}
                          </td>
                          <td>
                            <div class="smallest font-weight-bold">Btl: TSh {{ number_format($variant['selling_price']) }}</div>
                            @if($variant['can_sell_in_tots'])
                              <div class="smallest text-info">Tot: TSh {{ number_format($variant['selling_price_per_tot']) }}</div>
                            @endif
                          </td>
                          <td>
                             <span class="badge badge-{{ $variant['quantity'] <= 0 ? 'danger' : ($variant['is_low_stock'] ? 'warning' : 'success') }}">
                                {{ $variant['quantity'] <= 0 ? 'OUT OF STOCK' : ($variant['is_low_stock'] ? 'LOW STOCK' : 'AVAILABLE') }}
                             </span>
                          </td>
                          <td class="text-center">
                              <button class="btn btn-sm btn-outline-info" onclick="openThresholdModal({{ $variant['id'] }}, '{{ addslashes($displayTitle) }}', '{{ $unitLabel }}')">
                                  <i class="fa fa-bell-o"></i> Alert
                              </button>
                          </td>
                      </tr>
                      @endforeach
                  </tbody>
              </table>
          </div>

          <!-- Total Bar -->
          {{-- Total Asset bar removed for counter confidentiality --}}

        @else
          <div class="alert alert-info py-4 text-center shadow-xs" style="border-radius: 15px;">
            <i class="fa fa-info-circle fa-2x mb-3"></i>
            <h4>No products currently in counter stock.</h4>
            <div class="mt-3">
              <a href="{{ route('bar.stock-transfers.available') }}" class="btn btn-primary shadow-sm font-weight-bold">
                <i class="fa fa-exchange"></i> Request stock from warehouse
              </a>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- Low Stock Threshold Modal -->
<div class="modal fade shadow" id="thresholdModal" tabindex="-1" role="dialog" aria-labelledby="stockAlertTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 overflow-hidden">
      <div class="modal-header bg-primary text-white py-3">
        <h5 class="modal-title h6 mb-0 text-white" id="stockAlertTitle">
            <i class="fa fa-bell-o mr-2"></i> CONFIGURE STOCK ALERT
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-4 bg-light">
        <div class="tile mb-0 p-3 shadow-sm border-0">
            <div class="mb-3 text-center border-bottom pb-2">
                <h6 id="threshold-product-name" class="font-weight-bold text-dark mb-1">Product Name</h6>
                <p class="small text-muted mb-0">Set visibility of low stock alerts for this item</p>
            </div>
            
            <div class="form-group mb-0">
                <label class="control-label font-weight-bold mb-2">Primary Alert Threshold</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-right-0"><i class="fa fa-tachometer text-primary"></i></span>
                  </div>
                  <input type="number" id="threshold-value" class="form-control font-weight-bold h5 mb-0" style="padding-left: 10px;" min="1" value="10">
                  <div class="input-group-append">
                    <span class="input-group-text bg-white border-left-0 font-weight-bold text-muted" id="threshold-unit-display">{{ $unitLabel ?: 'btl' }}s</span>
                  </div>
                </div>
                <small class="text-danger mt-2 d-block font-weight-bold">
                    <i class="fa fa-info-circle mr-1"></i> Notification will trigger when stock falls below this value.
                </small>
            </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0 px-4 pb-4 pt-0">
        <button type="button" class="btn btn-secondary shadow-sm font-weight-bold px-4" data-dismiss="modal">CANCEL</button>
        <button type="button" class="btn btn-primary shadow-sm font-weight-bold px-4" id="saveThresholdBtn">SAVE ALERT SETTINGS</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
    .font-weight-extra-bold { font-weight: 800; }
    .smallest { font-size: 11px; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .italic { font-style: italic; }
    .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    .shadow-xs { box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .inventory-item-card {
        border-radius: 15px;
        border: 2px solid transparent !important;
        background: #fff;
        display: flex;
        flex-direction: column;
    }

    .inventory-item-card.is-low-stock, tr.product-card-wrapper.is-low-stock td {
        background-color: #ffeb3b !important;
        border-color: #fdd835 !important;
        box-shadow: 0 10px 20px rgba(253, 216, 53, 0.2) !important;
    }
    
    .inventory-item-card.is-low-stock h6, 
    .inventory-item-card.is-low-stock p, 
    .inventory-item-card.is-low-stock span, 
    .inventory-item-card.is-low-stock strong, 
    .inventory-item-card.is-low-stock div,
    tr.product-card-wrapper.is-low-stock td {
        color: #333 !important;
    }

    .inventory-item-card.is-out-of-stock, tr.product-card-wrapper.is-out-of-stock td {
        background-color: #f44336 !important;
        border-color: #d32f2f !important;
        box-shadow: 0 10px 20px rgba(211, 47, 47, 0.2) !important;
    }

    .inventory-item-card.is-out-of-stock h6, 
    .inventory-item-card.is-out-of-stock p, 
    .inventory-item-card.is-out-of-stock span, 
    .inventory-item-card.is-out-of-stock strong, 
    .inventory-item-card.is-out-of-stock div,
    tr.product-card-wrapper.is-out-of-stock td {
        color: #fff !important;
    }
    
    .inventory-item-card.is-out-of-stock .bg-light,
    .inventory-item-card.is-out-of-stock .bg-white,
    .inventory-item-card.is-low-stock .bg-light,
    .inventory-item-card.is-low-stock .bg-white {
        background-color: transparent !important;
        border-color: rgba(255,255,255,0.2) !important;
    }
    
    .inventory-item-card.is-low-stock .bg-light,
    .inventory-item-card.is-low-stock .bg-white {
        border-color: rgba(0,0,0,0.1) !important;
    }
    
    .inventory-item-card.is-out-of-stock .text-muted,
    .inventory-item-card.is-low-stock .text-muted {
        opacity: 0.8 !important;
    }

    .inventory-item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
        border-color: #007bff22;
    }

    .transition-all { transition: all 0.3s ease; }
    
    .filter-pill {
        border-radius: 20px;
        padding: 6px 16px;
        font-weight: 600;
        font-size: 11px;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .filter-pill.active {
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transform: scale(1.05);
    }
    
    .filter-pill[data-filter-type="category"].active { background-color: #007bff; color: white !important; }
    .filter-pill[data-filter-type="brand"].active { background-color: #17a2b8; color: white !important; }

    .product-card-wrapper { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endpush

@section('scripts')
<script>
var _currentThresholdId = null;

function openThresholdModal(id, name, unit = 'btl') {
  _currentThresholdId = id;
  const wrapper = $(`.product-card-wrapper[data-item-id="${id}"]`).first();
  const saved = wrapper.attr('data-threshold') || 10;
  $('#threshold-product-name').text(name);
  $('#threshold-unit-display').text(unit + (unit.endsWith('s') ? '' : 's'));
  $('#threshold-value').val(saved);
  $('#thresholdModal').modal('show');
}

$(document).ready(function () {
    // 1. VIEW TOGGLE
    $('.view-btn').on('click', function() {
        const view = $(this).data('view');
        $('.view-btn').removeClass('active');
        $(this).addClass('active');

        if (view === 'grid') {
            $('#inventoryGrid').removeClass('d-none');
            $('#inventoryList').addClass('d-none');
        } else {
            $('#inventoryGrid').addClass('d-none');
            $('#inventoryList').removeClass('d-none');
        }
    });

    // 2. SEARCH & FILTER
    let activeCategory = 'all';
    let activeBrand = 'all';

    function applyFilters() {
        const searchTerm = $('#inventorySearch').val().toLowerCase();
        
        $('.product-card-wrapper').each(function() {
            const itemName = $(this).data('name');
            const itemCat = $(this).data('category');
            const itemBrand = $(this).data('brand');
            
            const matchesSearch = itemName.indexOf(searchTerm) > -1;
            const matchesCat = (activeCategory === 'all' || itemCat === activeCategory);
            const matchesBrand = (activeBrand === 'all' || itemBrand === activeBrand);
            
            if (matchesSearch && matchesCat && matchesBrand) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    $('#inventorySearch').on('input', applyFilters);
    
    $('.filter-pill').on('click', function() {
        const filter = $(this).data('filter');
        const type = $(this).data('filter-type');
        
        if (type === 'category') {
            activeCategory = filter;
            $('#categoryContainer .filter-pill[data-filter-type="category"]').removeClass('active');
        } else {
            activeBrand = filter;
            $('#categoryContainer .filter-pill[data-filter-type="brand"]').removeClass('active');
        }
        
        $(this).addClass('active');
        applyFilters();
    });

    // 3. THRESHOLD SAVE
    $('#saveThresholdBtn').on('click', function () {
        const btn = $(this);
        const val = parseInt($('#threshold-value').val(), 10);
        if (isNaN(val) || val < 1) return;
        
        const id = _currentThresholdId;
        
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> SAVING...');
        
        $.ajax({
            url: "{{ route('bar.counter.update-threshold') }}",
            method: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                id: id,
                threshold: val
            },
            success: function(response) {
                if (response.success) {
                    // Update data attribute
                    $(`.product-card-wrapper[data-item-id="${id}"]`).attr('data-threshold', response.threshold);
                    updateProductAlertState(id, response.threshold);
                    $('#thresholdModal').modal('hide');
                    $.notify({
                        title: "Success: ",
                        message: "Alert threshold updated successfully.",
                        icon: 'fa fa-check' 
                    },{
                        type: "success"
                    });
                } else {
                    alert(response.message || 'Error updating threshold');
                }
            },
            error: function() {
                alert('Network error. Failed to save threshold.');
            },
            complete: function() {
                btn.prop('disabled', false).text('SAVE ALERT SETTINGS');
            }
        });
    });

    // Core Highlighting Function
    function updateProductAlertState(id, threshold) {
        if (!id) return;
        
        // Find quantity from the master data attributes
        const wrapper = $(`.product-card-wrapper[data-item-id="${id}"]`).first();
        if (!wrapper.length) return;
        
        const qty = parseInt(wrapper.attr('data-qty'), 10);
        const limit = parseInt(threshold, 10);
        
        // Target BOTH the grid tile and the table row
        const gridCard = $(`#card-${id}`);
        const tableRow = $(`#row-${id}`);
        
        // Clear previous states
        gridCard.removeClass('is-low-stock is-out-of-stock shadow-sm');
        tableRow.removeClass('is-low-stock is-out-of-stock');
        
        if (qty <= 0) {
            gridCard.addClass('is-out-of-stock');
            tableRow.addClass('is-out-of-stock');
        } else if (qty < limit) {
            gridCard.addClass('is-low-stock');
            tableRow.addClass('is-low-stock');
        } else {
            gridCard.addClass('shadow-sm'); // Restore standard look if healthy
        }
        
        // Update the textual label (Only exists in Grid Card)
        $(`#threshold-info-${id}`).html(`<i class="fa fa-bell"></i> Alert < ${limit}`).show();
    }

    // 4. INITIALIZATION ON LOAD
    // Instead of looking for labels, we iterate THROUGH all unique item IDs on the page
    const uniqueItemIds = [];
    $('.product-card-wrapper').each(function() {
        const id = $(this).attr('data-item-id');
        if (id && uniqueItemIds.indexOf(id) === -1) {
            uniqueItemIds.push(id);
        }
    });

    uniqueItemIds.forEach(function(id) {
        const wrapper = $(`.product-card-wrapper[data-item-id="${id}"]`).first();
        const threshold = wrapper.attr('data-threshold') || 10;
        updateProductAlertState(id, threshold);
    });
});
</script>
@endsection
