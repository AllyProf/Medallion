@extends('layouts.dashboard')

@section('title', 'Counter Dashboard')

@section('content')
<style>
    :root {
        --brand: #940000;
        --brand-dark: #6b0000;
        --brand-light: rgba(148,0,0,0.08);
    }
    /* POS Styling */
    .product-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #eee;
    }
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(148,0,0,0.18);
        border-color: var(--brand);
    }
    .product-card .card-body {
        padding: 10px;
    }
    .product-card .product-title {
        font-size: 0.9rem;
        font-weight: bold;
        margin-bottom: 5px;
        height: 40px;
        overflow: hidden;
    }
    .product-card .product-price {
        color: var(--brand);
        font-weight: bold;
    }
    .product-card .stock-badge {
        font-size: 0.7rem;
    }
    .cart-tile {
        height: calc(100vh - 220px);
        min-height: 600px;
        display: flex;
        flex-direction: column;
    }
    #cart-items-container {
        flex-grow: 1;
        overflow-y: auto;
    }
    .sell-type-btn.active {
        background-color: var(--brand) !important;
        color: white !important;
        border-color: var(--brand) !important;
    }
    .payment-method-btn.active {
        background-color: #28a745 !important;
        color: white !important;
    }

    /* Animation for POS transition */
    #pos-section, #dashboard-content {
        transition: opacity 0.3s ease;
    }

    /* Category pills — brand colour when active */
    .category-pill {
        cursor: pointer;
        margin-right: 5px;
        margin-bottom: 10px;
        transition: all 0.2s ease;
    }
    .category-pill.active,
    .category-pill.badge-primary {
        background-color: var(--brand) !important;
        border-color: var(--brand) !important;
        color: #fff !important;
    }

    /* Search bar accent */
    .input-group-text.bg-primary {
        background-color: var(--brand) !important;
        border-color: var(--brand) !important;
    }

    /* POS modal styling */
    .modal-content {
        border-radius: 15px;
        box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        background-color: #fff;
    }
    .modal-header {
        border-radius: 15px 15px 0 0;
        background-color: var(--brand) !important;
        padding: 1.5rem;
    }
    .modal-body {
        background-color: #fff;
        color: #333;
    }
    .modal-footer {
        background-color: #f8f9fa;
        border-radius: 0 0 15px 15px;
    }
    
    /* Responsive grid tweaks */
    @media (max-width: 768px) {
        .product-card .product-title { font-size: 0.8rem; height: 35px; }
        .product-card .card-body { padding: 8px; }
    }

    /* Text-primary override for brand */
    .text-primary { color: var(--brand) !important; }
    .border-primary { border-color: var(--brand) !important; }
    .btn-primary { background-color: var(--brand) !important; border-color: var(--brand) !important; }
    .btn-primary:hover { background-color: var(--brand-dark) !important; border-color: var(--brand-dark) !important; }
    .btn-outline-primary { color: var(--brand) !important; border-color: var(--brand) !important; }
    .btn-outline-primary:hover, .btn-outline-primary.active { background-color: var(--brand) !important; color: #fff !important; }

    /* Food card top accent */
    .border-info.product-card:hover { border-color: var(--brand) !important; }

    /* POS header pill / badge-primary */
    .badge-primary { background-color: var(--brand) !important; }

    /* Complete Payment button */
    #btn-place-order-final { background-color: var(--brand) !important; border-color: var(--brand) !important; }
    #btn-place-order-final:hover { background-color: var(--brand-dark) !important; }

    /* Uniform Widgets */
    .widget-small {
        height: 100px !important;
        margin-bottom: 20px;
    }
    .widget-small .info {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding-left: 15px !important;
    }
    .widget-small .info h4 {
        font-size: 0.85rem;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .widget-small .info p {
        margin-bottom: 0;
    }
    /* Unified Heights & Spaced Grid */
    .pos-product-tile, .cart-tile {
        height: calc(100vh - 220px);
        min-height: 620px;
        display: flex;
        flex-direction: column;
    }
    .pos-grid-container {
        flex-grow: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 15px !important;
        scroll-behavior: smooth;
    }
    
    #cart-items-container {
        flex-grow: 1;
        overflow-y: auto;
        padding: 10px;
        scroll-behavior: smooth;
    }
    
    .pos-grid-container::-webkit-scrollbar, #cart-items-container::-webkit-scrollbar {
        width: 6px;
    }
    .pos-grid-container::-webkit-scrollbar-track, #cart-items-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .pos-grid-container::-webkit-scrollbar-thumb, #cart-items-container::-webkit-scrollbar-thumb {
        background: #940000;
        border-radius: 10px;
    }
    .pos-grid-container::-webkit-scrollbar-thumb:hover, #cart-items-container::-webkit-scrollbar-thumb:hover {
        background: #6b0000;
    }
    
    .product-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        transition: none;
    }
    .product-card.low-stock-border {
        border: 1px solid #fec107 !important;
    }
    .product-card .img-header {
        background: #f8f9fa;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px 8px 0 0;
    }
    .product-card .add-to-order-btn {
        color: #940000;
        font-weight: bold;
        text-decoration: none;
        display: block;
        text-align: center;
        padding: 4px 0;
        border-top: 1px solid #eee;
        font-size: 0.85rem;
    }
    .category-pill {
        border-radius: 4px !important;
        font-size: 0.75rem;
        padding: 5px 10px !important;
        font-weight: normal;
    }
    .category-pill.active {
        background-color: #940000 !important;
    }
    .category-pill:not(.active) {
        background-color: #6c757d !important;
    }
</style>

<div class="app-title">
  <div>
    <h1><i class="fa fa-tachometer"></i> Counter Dashboard</h1>
    <p>Welcome back, {{ session('staff_name') }}!</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item">Counter Dashboard</li>
  </ul>
</div>

<!-- DASHBOARD MAIN CONTENT -->
<div id="dashboard-content">
    <!-- Statistics Cards -->
    <div class="row">
      <div class="col-md-3">
        <a href="{{ route('bar.stock-transfers.available') }}" style="text-decoration: none;">
            <div class="widget-small primary coloured-icon">
              <i class="icon fa fa-archive fa-3x"></i>
              <div class="info">
                <h4>Warehouse Stock</h4>
                <p><b>{{ $warehouseStockItems ?? 0 }} items</b></p>
              </div>
            </div>
        </a>
      </div>
      <div class="col-md-3">
        <a href="{{ route('bar.counter.counter-stock') }}" style="text-decoration: none;">
            <div class="widget-small info coloured-icon">
              <i class="icon fa fa-cubes fa-3x"></i>
              <div class="info">
                <h4>Counter Stock Items</h4>
                <p><b>{{ $counterStockItems }}</b></p>
                @if($lowStockItems > 0)
                  <small class="text-warning">{{ $lowStockItems }} low stock</small>
                @endif
              </div>
            </div>
        </a>
      </div>
      <div class="col-md-3">
        <div class="widget-small warning coloured-icon">
          <i class="icon fa fa-exchange fa-3x"></i>
          <div class="info">
            <h4>Pending Transfers</h4>
            <p><b>{{ $pendingTransfers }}</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="widget-small danger coloured-icon">
          <i class="icon fa fa-exclamation-triangle fa-3x"></i>
          <div class="info">
            <h4>Low Stock Items</h4>
            <p><b>{{ $lowStockItems }}</b></p>
            <small>Need attention</small>
          </div>
        </div>
      </div>
    </div>
    
    @if($bar_shift)
    <!-- Active Shift Summary -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile p-3 mb-4 shadow-sm" style="background: linear-gradient(135deg, #fff 0%, #f1f8f7 100%); border: 2px solid #009688; border-radius: 12px; position: relative; overflow: hidden;">
                <!-- Decorative background icon -->
                <i class="fa fa-clock-o" style="position: absolute; right: -20px; top: -20px; font-size: 15rem; color: rgba(0, 150, 136, 0.04); transform: rotate(-15deg);"></i>
                
                <div class="d-flex justify-content-between align-items-center position-relative">
                    <div>
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge badge-primary mr-2 px-3 py-2 shadow-sm font-weight-bold" style="font-size: 0.9rem;">SHIFT: #{{ $bar_shift->formatted_id }}</span>
                            <h5 class="mb-0 text-dark font-weight-bold"><i class="fa fa-user-circle-o mr-1"></i> {{ $staff->full_name ?? 'Counter Staff' }}</h5>
                        </div>
                        <p class="mb-0 text-muted">
                            <i class="fa fa-calendar-check-o mr-1"></i> Opened on <strong>{{ $bar_shift->opened_at->format('M d, Y') }}</strong> at <strong>{{ $bar_shift->opened_at->format('H:i:s') }}</strong>
                        </p>
                    </div>
                    <div class="text-right d-flex align-items-center">
                        <div class="timer-display shadow-sm mr-3" style="background: #222; color: #00ff88; padding: 10px 20px; border-radius: 8px; font-family: monospace; font-size: 1.8rem; tracking: 2px; border: 2px solid #444;">
                            <span id="shift-timer">00:00:00</span>
                        </div>
                        <span class="badge badge-success px-3 py-2 text-uppercase font-weight-bold shadow-sm" style="letter-spacing: 1px;"><i class="fa fa-circle mr-1 blink"></i> ACTIVE</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="row">
      <div class="col-md-12">
        <div class="tile">
          <h3 class="tile-title">Quick Actions</h3>
          <div class="tile-body">
            <div class="row">
              <div class="col-md-3 mb-3">
                <button type="button" class="btn btn-primary btn-block btn-lg" id="btn-pos-mode">
                  <i class="fa fa-shopping-cart fa-2x"></i><br>
                  Place New Order (POS)
                </button>
              </div>
              <div class="col-md-3 mb-3">
                <a href="{{ route('bar.counter.waiter-orders') }}" class="btn btn-info btn-block btn-lg">
                  <i class="fa fa-list-alt fa-2x"></i><br>
                  Waiter Orders
                  @if($pendingOrders > 0)
                    <span class="badge badge-danger">{{ $pendingOrders }}</span>
                  @endif
                </a>
              </div>
              <div class="col-md-3 mb-3">
                <a href="{{ route('bar.counter.shift-history') }}" class="btn btn-secondary btn-block btn-lg">
                  <i class="fa fa-history fa-2x"></i><br>
                  Shift History
                </a>
              </div>
              <div class="col-md-3 mb-3">
                <a href="{{ route('bar.counter.counter-stock') }}" class="btn btn-success btn-block btn-lg">
                  <i class="fa fa-cubes fa-2x"></i><br>
                  Counter Stock
                </a>
              </div>
              <div class="col-md-3 mb-3">
                <a href="{{ route('bar.stock-transfers.available') }}" class="btn btn-warning btn-block btn-lg text-white">
                  <i class="fa fa-archive fa-2x"></i><br>
                  Warehouse Stock
                </a>
              </div>
              @if($bar_shift)
              <div class="col-md-3 mb-3">
                <a href="{{ route('bar.counter.reconciliation') }}" class="btn btn-danger btn-block btn-lg">
                  <i class="fa fa-lock fa-2x"></i><br>
                  Close Shift
                </a>
              </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="row">
        <!-- Recent Orders -->
        <div class="col-md-6">
            <div class="tile h-100">
                <h3 class="tile-title">Recent Orders</h3>
                @if($recentOrders->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Waiter/Staff</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders as $order)
                                <tr>
                                    <td><span class="font-weight-bold">#{{ $order->order_number }}</span></td>
                                    <td>
                                        @if($order->order_source === 'counter')
                                            <span class="text-info font-weight-bold">Counter:</span> {{ $order->waiter->full_name ?? 'Staff' }}
                                        @else
                                            {{ $order->waiter->full_name ?? 'Counter' }}
                                        @endif
                                    </td>
                                    <td>TSh {{ number_format($order->total_amount) }}</td>
                                    <td>
                                        @if($order->status == 'pending')
                                            <span class="badge badge-warning">Pending</span>
                                        @elseif($order->status == 'served')
                                            <span class="badge badge-success">Served</span>
                                        @elseif($order->status == 'cancelled')
                                            <span class="badge badge-danger">Cancelled</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($order->status) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            {{-- View is always available --}}
                                            <button class="btn btn-sm btn-secondary view-order-details mr-1 mb-1"
                                                data-order-id="{{ $order->id }}" title="View Details">
                                                <i class="fa fa-eye"></i>
                                            </button>

                                            @if($order->status === 'pending' && $order->payment_status !== 'paid')
                                                <button class="btn btn-sm btn-primary btn-add-items mr-1 mb-1"
                                                    data-table-id="{{ $order->table_id }}"
                                                    data-order-id="{{ $order->id }}"
                                                    data-order-num="{{ $order->order_number }}"
                                                    title="Add More Items">
                                                    <i class="fa fa-plus"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info btn-mark-served mr-1 mb-1"
                                                    data-order-id="{{ $order->id }}"
                                                    title="Mark as Served (deducts stock)">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                            @elseif($order->status === 'served' && $order->payment_status !== 'paid')
                                                <button class="btn btn-sm btn-success btn-pay-order font-weight-bold mr-1 mb-1"
                                                    data-order-id="{{ $order->id }}"
                                                    data-total="{{ $order->total_amount }}"
                                                    title="Collect Payment">
                                                    <i class="fa fa-money"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $recentOrders->links() }}
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fa fa-shopping-basket fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No orders found for today.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Low Stock -->
        <div class="col-md-6">
            <div class="tile">
                <h3 class="tile-title">Low Stock Alerts</h3>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Warehouse</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowStockItemsList as $item)
                            <tr>
                                <td>{{ $item['product_name'] }}</td>
                                <td class="text-danger font-weight-bold">{{ $item['counter_qty'] }} {{ strtolower($item['unit'] ?? 'btl') }}</td>
                                <td>{{ $item['warehouse_qty'] }} {{ strtolower($item['unit'] ?? 'btl') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- POS SECTION (Hidden by default) -->
<div id="pos-section" style="display: none;">
  <div class="row mb-3">
    <div class="col-md-12">
      <div class="tile py-2">
        <div class="tile-title-w-btn mb-0">
          <div class="d-flex align-items-center">
              <h3 class="title mb-0"><i class="fa fa-shopping-cart"></i> Counter POC</h3>
              <div id="pos-mode-indicator" class="alert alert-warning py-1 px-2 mb-0 ml-3" style="display: none;">
                  <small><i class="fa fa-plus-circle"></i> Appending to Order #<span id="pos-appending-order-num"></span></small>
              </div>
          </div>
          <p class="mb-0">
              <button class="btn btn-secondary" id="btn-back-to-dashboard">
                  <i class="fa fa-arrow-left"></i> Dashboard
              </button>
          </p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Product Selection Column -->
    <div class="col-md-8">
      <div class="tile pos-product-tile">
        <!-- Search and Categories -->
        <div class="row mb-3">
          <div class="col-md-12">
            <div class="input-group mb-3 border rounded overflow-hidden">
              <div class="input-group-prepend">
                <span class="input-group-text bg-dark border-0 text-white" style="background: #940000 !important;"><i class="fa fa-search"></i></span>
              </div>
              <input type="text" class="form-control border-0" id="product-search" placeholder="Search drinks or food items...">
            </div>
            
            <div class="d-flex flex-wrap mb-3" id="category-filters">
                <span class="badge badge-primary p-2 category-pill active" data-category="all" style="background: #222; border: 1px solid #444;">All Items</span>
                @php 
                    $uniqueCats = collect($variants)->pluck('category')->unique()->filter()->values(); 
                    
                    $colors = ['#ffb822', '#17a2b8', '#6f42c1', '#28a745', '#fd7e14', '#007bff'];
                    $icons = ['fa-glass', 'fa-coffee', 'fa-lemon-o', 'fa-beer', 'fa-flask', 'fa-tint'];

                    $catStyleMap = [];
                    foreach($uniqueCats as $i => $cat) {
                        $catStyleMap[$cat] = [
                            'color' => $colors[$i % count($colors)],
                            'icon' => $icons[$i % count($icons)]
                        ];
                    }
                @endphp
                @foreach($uniqueCats as $cat)
                    <span class="badge badge-secondary p-2 category-pill" data-category="cat-{{ \Illuminate\Support\Str::slug($cat) }}">
                        {{ $cat }}
                    </span>
                @endforeach
            </div>
          </div>
        </div>

        <!-- Product Grid -->
        <div class="row pos-grid-container px-2" id="pos-items-grid">
            <!-- Drinks -->
            @foreach($variants as $v)
            @php
                $isLow = $v['quantity'] <= 5;
            @endphp
            <div class="col-md-4 col-sm-6 mb-4 pos-item cat-drinks cat-{{ \Illuminate\Support\Str::slug($v['category']) }}" 
                 data-id="{{ $v['id'] }}" 
                 data-name="{{ $v['product_name'] }}" 
                 data-variant="{{ $v['variant'] }}"
                 data-price="{{ $v['selling_price'] }}"
                 data-price-tot="{{ $v['selling_price_per_tot'] }}"
                 data-can-tot="{{ $v['can_sell_in_tots'] ? 'true' : 'false' }}"
                 data-portion-label="{{ $v['portion_label'] }}"
                 data-quantity="{{ $v['quantity'] }}"
                 data-unit="{{ $v['unit'] ?: 'btl' }}"
                 data-total-tots="{{ $v['total_tots'] }}"
                 data-open-tots="{{ $v['open_tots'] }}"
                 data-type="drink">
                <div class="card product-card shadow-sm {{ $isLow ? 'low-stock-border' : '' }}">
                    @if($isLow)
                    <div style="position: absolute; top: 10px; right: 10px; z-index: 5;">
                        <span class="badge badge-warning text-dark font-weight-bold py-1 px-2 mb-0" style="font-size: 10px; border-radius: 4px;">LOW STOCK</span>
                    </div>
                    @endif

                    <div class="img-header">
                         @if($v['product_image'])
                            <img src="{{ asset('storage/' . $v['product_image']) }}" style="height: 40px; object-fit: contain;">
                         @else
                            <i class="fa {{ $catStyleMap[$v['category']]['icon'] ?? 'fa-glass' }} fa-2x opacity-25" style="color: #666;"></i>
                         @endif
                    </div>

                    <div class="card-body p-2 d-flex flex-column">
                        <div class="font-weight-bold text-dark mb-0" style="font-size: 0.95rem; line-height: 1.1; height: 1.1rem; overflow: hidden;">
                            {{ $v['product_name'] }}
                        </div>
                        <div class="small text-muted mb-2" style="font-size: 0.75rem;">
                            @if($v['variant']) ({{ $v['variant'] }}) @else &nbsp; @endif
                        </div>
                        
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted" style="font-size: 0.75rem;">Available:</span>
                            <strong class="text-dark" style="font-size: 0.75rem;">
                                {{ number_format($v['quantity']) }} {{ $v['unit'] ?: 'btl' }}s
                                @if(($v['open_tots'] ?? 0) > 0)
                                    <span class="text-primary">+ {{ $v['open_tots'] }} {{ $v['portion_label'] }}s</span>
                                @endif
                            </strong>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted" style="font-size: 0.75rem;">Bottle Price:</span>
                            <strong style="color: #940000; font-size: 0.85rem;">TSh {{ number_format($v['selling_price']) }}</strong>
                        </div>

                        <div class="mt-auto">
                            <a href="javascript:void(0)" class="add-to-order-btn btn-add-default">
                                <i class="fa fa-plus-circle"></i> Add to Order
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach            <!-- Food items removed from counter dashboard -->
h
        </div>
      </div>
    </div>

    <!-- Right Sidebar: Cart -->
    <div class="col-md-4">
      <div class="tile cart-tile shadow-sm">
        <h3 class="tile-title border-bottom pb-2"><i class="fa fa-shopping-basket text-primary"></i> Order List</h3>
        
        <div id="cart-items-container">
          <div class="text-center p-5 text-muted" id="empty-cart-msg">
            <i class="fa fa-shopping-cart fa-4x mb-3 opacity-50"></i>
            <h5>Empty Order</h5>
            <p>Select items from the left to start</p>
          </div>
          <table class="table table-sm table-striped" id="cart-table" style="display: none;">
            <thead>
              <tr class="bg-light">
                <th>Item</th>
                <th width="80">Qty</th>
                <th class="text-right">Total</th>
                <th width="30"></th>
              </tr>
            </thead>
            <tbody id="cart-tbody">
              <!-- Cart items row -->
            </tbody>
          </table>
        </div>
        
        <div class="cart-bottom-fixed border-top pt-3">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Subtotal</span>
            <span id="cart-subtotal" class="font-weight-bold">TSh 0</span>
          </div>
          <div class="d-flex justify-content-between mb-3">
            <h4 class="mb-0">Payable Amount</h4>
            <h4 id="cart-total" class="text-primary mb-0 font-weight-bold">TSh 0</h4>
          </div>
          
          <div class="bg-light p-2 rounded mb-3 border">
              <div class="form-group mb-2">
                <label class="small font-weight-bold text-muted mb-1 text-uppercase">Table Selection</label>
                <select class="form-control select2" id="order-table" style="width: 100%;">
                  <option value="">-- Walk-in / Generic --</option>
                  @foreach($tables as $table)
                    <option value="{{ $table['id'] }}">Table {{ $table['table_number'] }} ({{ $table['location'] }}) - {{ $table['status'] }}</option>
                  @endforeach
                </select>
              </div>
              
              <div class="row no-gutters">
                  <div class="col-md-6 pr-md-1 mb-2 mb-md-0">
                      <div class="input-group input-group-sm">
                          <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-user"></i></span></div>
                          <input type="text" id="pos-customer-name" class="form-control" placeholder="Guest Name">
                      </div>
                  </div>
                  <div class="col-md-6 pl-md-1">
                      <div class="input-group input-group-sm">
                          <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-phone"></i></span></div>
                          <input type="text" id="pos-customer-phone" class="form-control" placeholder="Guest Phone">
                      </div>
                  </div>
              </div>
          </div>
          <input type="hidden" id="pos-existing-order-id" value="">
          
          <div class="row mt-3">
              <div class="col-10 pr-1">
                  <button class="btn btn-primary btn-block btn-lg font-weight-bold shadow-sm" id="btn-place-only" disabled>
                    <i class="fa fa-save"></i> <span class="text-uppercase">Place Order (Store)</span>
                  </button>
              </div>
              <div class="col-2 pl-1">
                  <button class="btn btn-outline-danger btn-block btn-lg" id="btn-clear-cart" title="Clear All Items">
                    <i class="fa fa-trash"></i>
                  </button>
              </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa fa-plus-circle"></i> Add to Order</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" id="modal-item-id">
        <input type="hidden" id="modal-item-type">
        <input type="hidden" id="modal-item-price">
        <input type="hidden" id="modal-item-price-tot">
        <input type="hidden" id="modal-item-name">
        <input type="hidden" id="modal-item-variant">
        <input type="hidden" id="modal-portion-label">
        <input type="hidden" id="modal-item-qty-total">
        <input type="hidden" id="modal-item-open-tots">
        <input type="hidden" id="modal-item-unit-label">

        <div class="text-center mb-1">
          <h3 id="modal-display-name" class="font-weight-bold text-dark mb-0"></h3>
          <div id="modal-display-stock" class="text-muted small font-weight-bold mb-3"></div>
          <h4 id="modal-display-price" class="text-primary"></h4>
        </div>

        <div id="sell-type-group" class="mb-4" style="display: none;">
          <label class="font-weight-bold">Selling Option</label>
          <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
            <label class="btn btn-outline-info flex-fill p-3 active">
              <input type="radio" name="sell_type" value="unit" checked> 
              <i class="fa fa-shopping-basket fa-2x mb-2 d-block"></i> <span id="modal-unit-text">Full Bottle</span>
            </label>
            <label class="btn btn-outline-info flex-fill p-3" id="label-sell-tot">
              <input type="radio" name="sell_type" value="tot"> 
              <i id="modal-portion-icon" class="fa fa-glass fa-2x mb-2 d-block"></i> <span id="modal-portion-text">A Glass</span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label class="font-weight-bold">Quantity</label>
          <div class="input-group input-group-lg">
            <div class="input-group-prepend">
              <button class="btn btn-dark px-4" type="button" id="btn-qty-minus"><i class="fa fa-minus"></i></button>
            </div>
            <input type="number" class="form-control text-center font-weight-bold" id="modal-quantity" value="1" min="1">
            <div class="input-group-append">
              <button class="btn btn-dark px-4" type="button" id="btn-qty-plus"><i class="fa fa-plus"></i></button>
            </div>
          </div>
        </div>

        <div id="food-notes-group" class="mt-3" style="display: none;">
          <label class="font-weight-bold">Preparation Notes</label>
          <textarea class="form-control" id="modal-notes" rows="2" placeholder="e.g., Spicy, No onions..."></textarea>
        </div>
      </div>
      <div class="modal-footer border-0 p-4">
        <button type="button" class="btn btn-light btn-lg flex-fill" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-lg flex-fill font-weight-bold" id="btn-add-to-cart-confirm">ADD TO ORDER</button>
      </div>
    </div>
  </div>
</div>

<!-- Checkout / Payment Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-credit-card"></i> Process Payment</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body p-4">
        <div class="bg-light p-3 rounded mb-4 text-center border">
          <small class="text-muted d-block text-uppercase font-weight-bold">Total Amount Due</small>
          <h2 class="mb-0 text-dark font-weight-bold" id="checkout-total-display">TSh 0</h2>
          <input type="hidden" id="checkout-order-id" value="">
        </div>

        <div class="form-group">
          <label class="font-weight-bold">Select Payment Mode</label>
          <div class="btn-group btn-group-toggle d-flex flex-wrap" data-toggle="buttons">
            <label class="btn btn-outline-success flex-fill active p-3">
              <input type="radio" name="payment_method" value="cash" checked>
              <i class="fa fa-money fa-2x mb-2 d-block"></i> CASH
            </label>
            <label class="btn btn-outline-info flex-fill p-3">
              <input type="radio" name="payment_method" value="mobile_money">
              <i class="fa fa-mobile fa-3x mb-2 d-block"></i> MOBILE MONEY
            </label>
            <label class="btn btn-outline-primary flex-fill p-3">
              <input type="radio" name="payment_method" value="bank">
              <i class="fa fa-university fa-2x mb-2 d-block"></i> BANK
            </label>
            <label class="btn btn-outline-dark flex-fill p-3">
              <input type="radio" name="payment_method" value="card">
              <i class="fa fa-credit-card fa-2x mb-2 d-block"></i> CARD
            </label>
          </div>
        </div>

        {{-- Mobile Money --}}
        <div id="mobile-money-details" style="display: none;" class="mt-3 p-3 bg-light border-info border rounded">
          <div class="form-group">
            <label class="font-weight-bold small">MM Provider</label>
            <select class="form-control" id="mobile-money-provider">
              <option value="Tigo Pesa">Tigo Pesa</option>
              <option value="M-Pesa">M-Pesa</option>
              <option value="Airtel Money">Airtel Money</option>
              <option value="HaloPesa">HaloPesa</option>
              <option value="MIXX BY YAS">MIXX BY YAS</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold small">Transaction Reference / Receipt #</label>
            <input type="text" id="mobile-money-ref" class="form-control" placeholder="Enter Reference ID">
          </div>
        </div>

        {{-- Bank Transfer --}}
        <div id="bank-details" style="display: none;" class="mt-3 p-3 bg-light border-primary border rounded">
          <div class="form-group">
            <label class="font-weight-bold small">Bank Name</label>
            <select class="form-control" id="bank-provider">
              <option value="CRDB Bank">CRDB Bank</option>
              <option value="NMB Bank">NMB Bank</option>
              <option value="NBC Bank">NBC Bank</option>
              <option value="Stanbic Bank">Stanbic Bank</option>
              <option value="Equity Bank">Equity Bank</option>
              <option value="Absa Bank">Absa Bank</option>
              <option value="DTB Bank">DTB Bank</option>
              <option value="KCB Bank">KCB Bank</option>
              <option value="Exim Bank">Exim Bank</option>
              <option value="Azania Bank">Azania Bank</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold small">Bank Slip / Reference #</label>
            <input type="text" id="bank-ref" class="form-control" placeholder="Enter bank slip or reference number">
          </div>
        </div>

        {{-- Card Payment --}}
        <div id="card-details" style="display: none;" class="mt-3 p-3 bg-light border-dark border rounded">
          <div class="form-group">
            <label class="font-weight-bold small">Card Type</label>
            <select class="form-control" id="card-provider">
              <option value="Visa">Visa</option>
              <option value="Mastercard">Mastercard</option>
              <option value="Amex">American Express</option>
              <option value="UnionPay">UnionPay</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold small">Card Approval Code</label>
            <input type="text" id="card-ref" class="form-control" placeholder="Enter approval / authorization code">
          </div>
        </div>

      </div>
      <div class="modal-footer border-0 p-4 pt-0">
          <button type="button" class="btn btn-success btn-lg btn-block font-weight-bold py-3 shadow-sm" id="btn-place-order-final">
              <i class="fa fa-check-circle"></i> COMPLETE & PROCESS PAYMENT
          </button>
      </div>
    </div>
  </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" role="dialog" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg border-0" role="document">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="orderDetailsModalLabel">Order Details View</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-0" id="orderDetailsContent">
        <div class="text-center p-5">
          <i class="fa fa-spinner fa-spin fa-3x text-primary mb-3"></i>
          <p class="h5">Loading order details...</p>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@if($bar_shift)
<!-- Close Shift Modal -->
<div class="modal fade" id="closeShiftModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-lock"></i> Close Counter Shift</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form action="{{ route('bar.counter.close-shift', $bar_shift->id) }}" method="POST">
        @csrf
        <div class="modal-body p-4">
          <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> Closing your shift will finalize all your recorded sales for today. Please count your physical cash carefully.
          </div>
          
          <div class="bg-light p-3 rounded mb-4 border">
            <div class="row">
              <div class="col-6 text-center border-right">
                <small class="text-muted d-block font-weight-bold">Expected Cash</small>
                <h4 class="mb-0">TSh {{ number_format($bar_shift->expected_cash + $bar_shift->opening_cash) }}</h4>
                <small class="text-muted">(Incl. Opening Cash)</small>
              </div>
              <div class="col-6 text-center">
                <small class="text-muted d-block font-weight-bold">Digital Sales</small>
                <h4 class="mb-0">TSh {{ number_format($bar_shift->digital_revenue) }}</h4>
                <small class="text-muted">(Via Mobile/Bank)</small>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Total Physical Cash in Drawer (TSh)</label>
            <input type="number" name="actual_cash" class="form-control form-control-lg font-weight-bold text-primary" 
                   placeholder="Enter total cash counted" required min="0" value="{{ $bar_shift->expected_cash + $bar_shift->opening_cash }}">
            <small class="form-text text-muted">Enter the total amount of physical cash currently in the counter drawer.</small>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Closing Notes (Optional)</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Any discrepancies or notes about the shift..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
          <button type="button" class="btn btn-light btn-lg flex-fill" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-lg flex-fill font-weight-bold">CONFIRM & CLOSE SHIFT</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let cart = [];
    
    // --- UI NAVIGATION ---
    $('#btn-pos-mode').on('click', function() {
        $('#dashboard-content').fadeOut(300, function() {
            $('#pos-section').fadeIn(300);
            renderCart();
        });
    });

    $('#btn-back-to-dashboard').on('click', function() {
        $('#pos-section').fadeOut(300, function() {
            $('#dashboard-content').fadeIn(300);
            // Reset POS state
            $('#pos-existing-order-id').val('');
            $('#pos-mode-indicator').hide();
            $('#order-table').val('').trigger('change');
            $('#pos-customer-name').val('');
            $('#pos-customer-phone').val('');
            cart = [];
            renderCart();
        });
    });

    // --- PRODUCT SEARCH & FILTER ---
    $('#product-search').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        $('.pos-item').each(function() {
            let name = $(this).data('name').toLowerCase();
            let variant = ($(this).data('variant') || '').toLowerCase();
            if (name.includes(val) || variant.includes(val)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('.category-pill').on('click', function() {
        $('.category-pill').removeClass('active badge-primary').addClass('badge-secondary');
        $(this).addClass('active badge-primary').removeClass('badge-secondary');
        
        let cat = $(this).data('category');
        if (cat === 'all') {
            $('.pos-item').fadeIn(200);
        } else {
            $('.pos-item').hide();
            $('.pos-item.' + cat).fadeIn(200);
        }
    });

    // --- CART ACTIONS ---
    $('.pos-item').on('click', function(e) {
        const item = $(this).data();
        const isShotBtn = $(e.target).closest('.btn-add-shots').length > 0;
        const isBottleBtn = $(e.target).closest('.btn-add-bottle').length > 0;

        $('#modal-item-id').val(item.id);
        $('#modal-item-type').val(item.type);
        $('#modal-item-price').val(item.price);
        $('#modal-item-price-tot').val((item.canTot == 'true' || item.canTot === true) ? item.priceTot : '');
        $('#modal-item-name').val(item.name);
        $('#modal-item-variant').val(item.variant || '');
        $('#modal-portion-label').val(item.portionLabel || 'Tot');
        $('#modal-item-qty-total').val(item.quantity);
        $('#modal-item-open-tots').val(item.openTots || 0);
        $('#modal-item-unit-label').val(item.unit || 'btl');
        $('#addItemModal').data('total-tots', item.totalTots || 1);
        
        if (item.type === 'food') {
            $('#modal-display-stock').html('<i class="fa fa-fire"></i> Status: <span class="text-success">READY</span>');
            $('#modal-display-price').html('TSh ' + parseInt(item.price).toLocaleString());
        } else {
            let stockMsg = '<i class="fa fa-database"></i> ' + item.quantity + ' ' + (item.unit || 'btl') + 's';
            if ((item.openTots || 0) > 0) {
                stockMsg += ' + ' + item.openTots + ' ' + (item.portionLabel || 'Tot') + 's';
            }
            stockMsg += ' currently in stock';
            $('#modal-display-stock').html(stockMsg);
            
            // Dynamic icons for portions
            const portionLabel = item.portionLabel || 'Tot';
            const portionIcon = (portionLabel === 'Glass') ? 'fa-glass' : 'fa-flask';
            $('#modal-portion-icon').attr('class', 'fa ' + portionIcon + ' fa-2x mb-2 d-block');
            $('#modal-portion-text').text('A ' + portionLabel);
            $('#modal-unit-text').text('Full ' + (item.unit === 'btl' ? 'Bottle' : 'Piece'));
            
            // Determine starting price display
            let currentPrice = item.price;
            let priceLabel = 'per ' + (item.unit === 'btl' ? 'Bottle' : 'Piece');
            if (isShotBtn) {
                currentPrice = item.priceTot;
                priceLabel = 'a ' + (item.portionLabel || 'Tot');
            }
            $('#modal-display-price').html('TSh ' + parseInt(currentPrice).toLocaleString() + ' <small class="text-muted">' + priceLabel + '</small>');
        }
        
        // Sync Guest info from sidebar if not already set in checkout
        $('#checkout-customer-name').val($('#pos-customer-name').val());
        $('#checkout-customer-phone').val($('#pos-customer-phone').val());
        
        $('#modal-quantity').val(1);
        $('#modal-notes').val('');
        
        const canSellTot = (item.canTot == 'true' || item.canTot === true);
        
        if (item.type === 'drink' && canSellTot) {
            $('#sell-type-group').show();
            $('#label-sell-tot').show();
            $('#modal-portion-text').text(item.portionLabel + 's');
            
            // Pre-select based on button
            const targetType = isShotBtn ? 'tot' : 'unit';
            $(`input[name="sell_type"][value="${targetType}"]`).prop('checked', true).change().parent().addClass('active').siblings().removeClass('active');
            
        } else if (item.type === 'drink') {
            $('#sell-type-group').show();
            $('#label-sell-tot').hide();
            $('input[name="sell_type"][value="unit"]').prop('checked', true).parent().addClass('active').siblings().removeClass('active');
        } else {
            $('#sell-type-group').hide();
        }
        
        if (item.type === 'food') {
            $('#food-notes-group').show();
        } else {
            $('#food-notes-group').hide();
        }
        
        $('#addItemModal').modal('show');
    });

    // Quantity buttons in modal
    $('#btn-qty-minus').on('click', function() {
        let q = parseInt($('#modal-quantity').val());
        if (q > 1) $('#modal-quantity').val(q - 1);
    });
    $('#btn-qty-plus').on('click', function() {
        let q = parseInt($('#modal-quantity').val());
        $('#modal-quantity').val(q + 1);
    });

    // Handle Sell Type selection styling
    $('input[name="sell_type"]').on('change', function() {
        $(this).parent().addClass('active').siblings().removeClass('active');
        const isTot = $(this).val() === 'tot';
        const price = isTot ? $('#modal-item-price-tot').val() : $('#modal-item-price').val();
        
        const portion = $('#modal-portion-label').val();
        const baseUnit = $('#modal-item-unit-label').val() === 'btl' ? 'Bottle' : 'Piece';
        const qtyBottles = parseFloat($('#modal-item-qty-total').val()) || 0;
        const openTots = parseFloat($('#modal-item-open-tots').val()) || 0;
        const totalTotsPerBottle = parseFloat($('#addItemModal').data('total-tots')) || 1;
        
        if (isTot) {
            const availTots = Math.floor(qtyBottles * totalTotsPerBottle) + openTots;
            $('#modal-display-stock').html('<i class="fa fa-glass"></i> ' + availTots.toLocaleString() + ' ' + portion + 's available in stock');
        } else {
            let stockMsg = '<i class="fa fa-database"></i> ' + qtyBottles.toLocaleString() + ' ' + baseUnit + 's';
            if (openTots > 0) {
                stockMsg += ' + ' + openTots + ' ' + portion + 's';
            }
            stockMsg += ' currently in stock';
            $('#modal-display-stock').html(stockMsg);
        }
        
        const label = isTot ? 'a ' + portion : 'per ' + baseUnit;
        $('#modal-display-price').html('TSh ' + parseInt(price).toLocaleString() + ' <small class="text-muted">' + label + '</small>');
    });

    // Add to Cart Confirm
    $('#btn-add-to-cart-confirm').on('click', function() {
        const id = $('#modal-item-id').val();
        const type = $('#modal-item-type').val();
        const sellType = type === 'drink' ? $('input[name="sell_type"]:checked').val() : 'unit';
        const price = sellType === 'tot' ? $('#modal-item-price-tot').val() : $('#modal-item-price').val();
        const name = $('#modal-item-name').val();
        const variant = $('#modal-item-variant').val();
        const qty = parseInt($('#modal-quantity').val());
        const notes = $('#modal-notes').val();
        
        // Check if item already in cart with same sell type
        const existingIndex = cart.findIndex(i => 
            (type === 'food' ? i.food_item_id == id : i.variant_id == id) && i.sell_type === sellType && i.notes === notes
        );
        
        if (existingIndex > -1) {
            cart[existingIndex].quantity += qty;
        } else {
            const cartItem = {
                product_name: name,
                variant_name: variant,
                portion_label: $('#modal-portion-label').val(),
                quantity: qty,
                price: parseFloat(price),
                sell_type: sellType,
                notes: notes
            };
            
            if (type === 'food') {
                cartItem.food_item_id = id;
            } else {
                cartItem.variant_id = id;
            }
            
            cart.push(cartItem);
        }
        
        renderCart();
        $('#addItemModal').modal('hide');
        showToast('success', name + ' added successfully', 'Cart Updated');
    });

    function renderCart() {
        const tbody = $('#cart-tbody');
        tbody.empty();
        
        if (cart.length === 0) {
            $('#empty-cart-msg').show();
            $('#cart-table').hide();
            $('#btn-checkout').prop('disabled', true);
            $('#cart-subtotal, #cart-total').text('TSh 0');
            return;
        }
        
        $('#empty-cart-msg').hide();
        $('#cart-table').show();
        $('#btn-checkout').prop('disabled', false);
        $('#btn-place-only').prop('disabled', false);
        
        let total = 0;
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            let row = `<tr>
                <td>
                    <div class="font-weight-bold">${item.product_name}</div>
                    <small class="text-muted">${item.variant_name} ${item.sell_type === 'tot' ? '(' + item.portion_label + ')' : ''}</small>
                    ${item.notes ? '<br><small class="text-info"><i>' + item.notes + '</i></small>' : ''}
                </td>
                <td class="align-middle">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control text-center cart-qty-input" value="${item.quantity}" data-index="${index}" readonly>
                    </div>
                </td>
                <td class="text-right align-middle">TSh ${itemTotal.toLocaleString()}</td>
                <td class="text-right align-middle">
                    <button class="btn btn-sm btn-link text-danger btn-remove-cart" data-index="${index}"><i class="fa fa-times"></i></button>
                </td>
            </tr>`;
            tbody.append(row);
        });
        
        $('#cart-subtotal, #cart-total, #checkout-total-display').text('TSh ' + total.toLocaleString());
    }

    $(document).on('click', '.btn-remove-cart', function() {
        const idx = $(this).data('index');
        cart.splice(idx, 1);
        renderCart();
    });

    $('#btn-clear-cart').on('click', function() {
        if (confirm('Clear all items from order?')) {
            cart = [];
            renderCart();
        }
    });

    // --- CHECKOUT & PAYMENT ---
    $('#btn-checkout').on('click', function() {
        $('#checkout-order-id').val($('#pos-existing-order-id').val());
        $('#checkoutModal').modal('show');
    });

    $('#order-table').on('change', function() {
        if ($(this).val()) {
            $('#place-only-wrapper').slideDown();
        } else {
            $('#place-only-wrapper').slideUp();
        }
    });

    $('#btn-place-only').on('click', function() {
        if (cart.length === 0) return;
        
        const btn = $(this);
        const existingOrderId = $('#pos-existing-order-id').val();
        
        const originalBtnText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> SAVING...');
        
        const orderData = {
            items: cart,
            table_id: $('#order-table').val(),
            customer_name: $('#pos-customer-name').val(),
            customer_phone: $('#pos-customer-phone').val(),
            order_notes: $('#checkout-notes').val(), // Added missing notes field
            existing_order_id: existingOrderId,
            _token: '{{ csrf_token() }}'
        };

        $.ajax({
            url: '{{ route("bar.counter.create-order") }}',
            method: 'POST',
            data: orderData,
            success: function(response) {
                if (response.success) {
                    const savedOrderId = response.order.id;
                    // Store order id so PAY NOW pays against this order
                    $('#pos-existing-order-id').val(savedOrderId);
                    $('#checkout-order-id').val(savedOrderId);
                    $('#checkout-total-display').text(btn.closest('.col-md-4').find('#cart-total').text());
                    btn.prop('disabled', false).html(originalBtnText);
                    // Highlight PAY NOW to guide the counter staff
                    $('#btn-checkout').removeClass('btn-outline-success').addClass('btn-success').html('<i class="fa fa-money"></i> PAY NOW');
                    showToast('success', 'Order stored! Redirecting to orders...', 'Saved');
                    
                    setTimeout(() => {
                        window.location.href = "{{ route('bar.counter.waiter-orders') }}";
                    }, 1200);
                }
            },
            error: function(err) {
                btn.prop('disabled', false).html(originalBtnText);
                let errMsg = "Unknown error";
                if (err.responseJSON) {
                    errMsg = err.responseJSON.error || err.responseJSON.message || "Request failed";
                    if (err.responseJSON.errors) {
                        const firstErr = Object.values(err.responseJSON.errors)[0][0];
                        errMsg = firstErr || errMsg;
                    }
                }
                showAlert('error', errMsg, 'Failed');
            }
        });
    });

    $('input[name="payment_method"]').on('change', function() {
        const val = $(this).val();
        $('#mobile-money-details, #bank-details, #card-details').slideUp();
        if (val === 'mobile_money') {
            $('#mobile-money-details').slideDown();
        } else if (val === 'bank') {
            $('#bank-details').slideDown();
        } else if (val === 'card') {
            $('#card-details').slideDown();
        }
    });

    $('#btn-place-order-final, #btn-pay-later-final').on('click', function() {
        const isPayLater = $(this).attr('id') === 'btn-pay-later-final';
        const btn = $(this);
        const method = $('input[name="payment_method"]:checked').val() || 'cash';
        
        if (cart.length === 0 && !$('#checkout-order-id').val()) {
            showAlert('warning', 'Your order list is empty!');
            return;
        }

        const existingOrderId = $('#checkout-order-id').val();
        
        if (method === 'mobile_money' && !$('#mobile-money-ref').val() && !isPayLater) {
            showAlert('warning', 'Please enter reference number for mobile money');
            return;
        }
        if (method === 'bank' && !$('#bank-ref').val() && !isPayLater) {
            showAlert('warning', 'Please enter Bank Slip / Reference number');
            return;
        }
        if (method === 'card' && !$('#card-ref').val() && !isPayLater) {
            showAlert('warning', 'Please enter the Card Approval Code');
            return;
        }

        const originalBtnText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> PROCESSING...');
        
        if (existingOrderId) {
            if (isPayLater) {
                $('#checkoutModal').modal('hide');
                btn.prop('disabled', false).html(originalBtnText);
                return;
            }
            // RECORD PAYMENT FOR EXISTING ORDER
            $.ajax({
                url: '{{ url("bar/counter/record-payment") }}/' + existingOrderId,
                method: 'POST',
                data: {
                    payment_method: method,
                    mobile_money_number: method === 'mobile_money' ? $('#mobile-money-provider').val()
                                      : method === 'bank' ? $('#bank-provider').val()
                                      : method === 'card' ? $('#card-provider').val()
                                      : null,
                    transaction_reference: method === 'mobile_money' ? $('#mobile-money-ref').val()
                                        : method === 'bank' ? $('#bank-ref').val()
                                        : method === 'card' ? $('#card-ref').val()
                                        : null,
                    _token: '{{ csrf_token() }}'
                },
                success: function(payResponse) {
                    $('#checkoutModal').modal('hide');
                    showToast('success', 'Order payment recorded.', 'Success!');
                    setTimeout(() => {
                        window.location.href = "{{ route('bar.counter.waiter-orders') }}";
                    }, 1200);
                },
                error: function(err) {
                    btn.prop('disabled', false).html(originalBtnText);
                    let errMsg = "Payment failed";
                    if (err.responseJSON) {
                        errMsg = err.responseJSON.error || err.responseJSON.message || errMsg;
                    }
                    showAlert('error', errMsg, 'Error');
                }
            });
        } else {
            // NEW ORDER FLOW
            const orderData = {
                items: cart,
                table_id: $('#order-table').val(),
                customer_name: $('#checkout-customer-name').val() || $('#pos-customer-name').val(),
                customer_phone: $('#checkout-customer-phone').val() || $('#pos-customer-phone').val(),
                _token: '{{ csrf_token() }}'
            };

            $.ajax({
                url: '{{ route("bar.counter.create-order") }}',
                method: 'POST',
                data: orderData,
                success: function(response) {
                    if (response.success) {
                        const orderId = response.order.id;
                        
                        if (isPayLater) {
                            $('#checkoutModal').modal('hide');
                            cart = [];
                            renderCart();
                            showToast('success', 'Order placed successfully.', 'Success!');
                            setTimeout(() => {
                                window.location.href = "{{ route('bar.counter.waiter-orders') }}";
                            }, 1200);
                            return;
                        }

                        // Record Payment immediately
                        $.ajax({
                            url: '{{ url("bar/counter/record-payment") }}/' + orderId,
                            method: 'POST',
                            data: {
                                payment_method: method,
                                mobile_money_number: method === 'mobile_money' ? $('#mobile-money-provider').val()
                                                  : method === 'bank' ? $('#bank-provider').val()
                                                  : method === 'card' ? $('#card-provider').val()
                                                  : null,
                                transaction_reference: method === 'mobile_money' ? $('#mobile-money-ref').val()
                                                    : method === 'bank' ? $('#bank-ref').val()
                                                    : method === 'card' ? $('#card-ref').val()
                                                    : null,
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(payResponse) {
                                $('#checkoutModal').modal('hide');
                                cart = [];
                                renderCart();
                                showToast('success', 'Order completed successfully.', 'Success!');
                                setTimeout(() => {
                                    window.location.href = "{{ route('bar.counter.waiter-orders') }}";
                                }, 1200);
                            },
                            error: function(err) {
                                btn.prop('disabled', false).html(originalBtnText);
                                showAlert('error', 'Order created but payment failed: ' + (err.responseJSON ? err.responseJSON.error : 'Unknown error'), 'Alert');
                            }
                        });
                    } else {
                        btn.prop('disabled', false).html(originalBtnText);
                        showAlert('error', response.error || 'Failed to create order', 'Error');
                    }
                },
                error: function(err) {
                    btn.prop('disabled', false).html(originalBtnText);
                    showAlert('error', err.responseJSON ? err.responseJSON.error : 'Order creation failed', 'Error');
                }
            });
        }
    });

    // --- ORDER ACTIONS (MARK SERVED / PAY / CANCEL / VIEW / ADD) ---
    $(document).on('click', '.btn-mark-served', function() {
        const orderId = $(this).data('order-id');
        Swal.fire({
            title: 'Mark as Served?',
            text: "This will deduct items from counter stock.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, mark served'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("bar.counter.update-order-status", ":id") }}'.replace(':id', orderId),
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', status: 'served' },
                    success: function(response) {
                        showToast('success', response.message || 'Order status updated successfully.', 'Updated');
                        setTimeout(function() {
                            location.reload();
                        }, 1200);
                    },
                    error: function(xhr) {
                        showAlert('error', xhr.responseJSON.error || "Failed to update status", 'Error');
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-pay-order', function() {
        const orderId = $(this).data('order-id');
        const total = $(this).data('total');
        
        $('#checkout-order-id').val(orderId);
        $('#checkout-total-display').text('TSh ' + parseInt(total).toLocaleString());
        $('#checkoutModal').modal('show');
    });

    $(document).on('click', '.btn-cancel-order', function() {
        const orderId = $(this).data('order-id');
        const orderNum = $(this).data('order-number');
        
        Swal.fire({
            title: 'Cancel Order?',
            text: `Are you sure you want to cancel order ${orderNum}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Cancel it!',
            input: 'text',
            inputPlaceholder: 'Reason for cancellation (optional)',
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("bar.counter.cancel-order", ":id") }}'.replace(':id', orderId),
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        reason: result.value
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message, 'Order Cancelled');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        showAlert('error', xhr.responseJSON.error || "Failed to cancel order", 'Error');
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-add-items', function() {
        const tableId = $(this).data('table-id');
        const orderId = $(this).data('order-id');
        const orderNum = $(this).data('order-num');
        
        if (tableId) {
            $('#order-table').val(tableId).trigger('change');
        }
        
        $('#pos-existing-order-id').val(orderId || '');
        if (orderId) {
            $('#pos-appending-order-num').text(orderNum);
            $('#pos-mode-indicator').show();
        } else {
            $('#pos-mode-indicator').hide();
        }
        
        $('#btn-pos-mode').trigger('click');
        showToast('info', 'Add items to order #' + orderNum, 'POS Mode');
    });

    $(document).on('click', '.view-order-details', function() {
        const orderId = $(this).data('order-id');
        $('#orderDetailsContent').html('<div class="text-center p-5"><i class="fa fa-spinner fa-spin fa-3x text-primary mb-3"></i><p class="h5">Loading order details...</p></div>');
        $('#orderDetailsModal').modal('show');

        $.ajax({
            url: '{{ route("bar.orders.details", ":id") }}'.replace(':id', orderId),
            method: 'GET',
            success: function(response) {
                const order = response.order;
                let itemsHtml = '';
                
                // Counter staff only sees Drinks/Beverages — food goes to kitchen
                if (order.items && order.items.length > 0) {
                    itemsHtml += `<h6 class="text-primary font-weight-bold"><i class="fa fa-glass"></i> Drinks / Beverages</h6>
                                  <table class="table table-sm table-striped">
                                    <thead class="bg-light"><tr><th>Item</th><th class="text-center">Qty</th><th class="text-right">Total</th></tr></thead><tbody>`;
                    order.items.forEach(item => {
                        itemsHtml += `<tr><td>${item.product_name} <small class="text-muted">(${item.variant})</small></td><td class="text-center">${item.quantity}</td><td class="text-right">TSh ${parseInt(item.total_price).toLocaleString()}</td></tr>`;
                    });
                    itemsHtml += '</tbody></table>';
                } else {
                    itemsHtml = `<div class="alert alert-info"><i class="fa fa-info-circle"></i> No drink items for this order.</div>`;
                }

                // Show drinks-only total (sum of order.items)
                const drinksTotal = order.items ? order.items.reduce((sum, i) => sum + parseFloat(i.total_price || 0), 0) : 0;

                // Strip food/added item notes — counter doesn't need that detail
                let cleanNotes = '';
                if (order.notes) {
                    cleanNotes = order.notes
                        .split(' | ')
                        .filter(p => !p.trim().startsWith('FOOD ITEMS:') && !p.trim().startsWith('ADDED ITEMS:'))
                        .join(' | ')
                        .trim();
                }

                const content = `
                    <div class="p-2">
                        <div class="row mb-3 pb-3 border-bottom no-gutters">
                            <div class="col-6">
                                <span class="d-block small text-muted text-uppercase">Order Details</span>
                                <h4 class="mb-1 font-weight-bold text-primary">#${order.order_number}</h4>
                                <div class="d-flex flex-wrap">
                                    <span class="badge badge-primary mr-1 mb-1 shadow-sm"><i class="fa fa-table"></i> ${order.table ? 'Table ' + order.table.table_number : 'Walk-in'}</span>
                                    <span class="badge badge-info mr-1 mb-1 shadow-sm"><i class="fa fa-user-circle"></i> ${order.waiter ? order.waiter.full_name : 'Counter Staff'}</span>
                                    ${order.customer_name ? `<span class="badge badge-dark mb-1 shadow-sm"><i class="fa fa-id-card"></i> Guest: ${order.customer_name}</span>` : ''}
                                </div>
                            </div>
                            <div class="col-6 text-right">
                                <span class="d-block small text-muted text-uppercase">Status &amp; Contact</span>
                                <p class="mb-1 font-weight-bold small">${order.created_at}</p>
                                <div class="mb-1">
                                    <span class="badge badge-${order.status === 'served' ? 'success' : 'warning'} px-2">${order.status.toUpperCase()}</span>
                                    <span class="badge badge-${order.payment_status === 'paid' ? 'success' : 'danger'} px-2">${order.payment_status.toUpperCase()}</span>
                                </div>
                                ${order.customer_phone ? `<span class="text-info small font-weight-bold d-block"><i class="fa fa-phone"></i> ${order.customer_phone}</span>` : ''}
                            </div>
                        </div>
                        
                        ${itemsHtml}
                        
                        <div class="mt-4 p-3 bg-light rounded text-right border">
                            <span class="d-block small text-muted text-uppercase">Drinks Total</span>
                            <h3 class="text-primary font-weight-bold mb-0">TSh ${parseInt(drinksTotal).toLocaleString()}</h3>
                        </div>
                        
                        ${cleanNotes ? `<div class="mt-3 p-3 bg-light border border-info rounded-sm small shadow-sm">
                            <strong class="text-info"><i class="fa fa-sticky-note"></i> Notes:</strong><br>${cleanNotes.replace(/\|/g, '<br>')}
                        </div>` : ''}
                    </div>
                `;
                $('#orderDetailsContent').html(content);
            },
            error: function() {
                $('#orderDetailsContent').html('<div class="alert alert-danger m-3">Failed to load order details.</div>');
            }
        });
    });

    // --- SHIFT TIMER ---
    @if($bar_shift)
    function updateShiftTimer() {
        const openedAt = {{ $bar_shift->opened_at->timestamp }} * 1000;
        const now = new Date().getTime();
        const diff = Math.floor((now - openedAt) / 1000);

        if (diff < 0) return;

        const hours = Math.floor(diff / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        const seconds = diff % 60;

        const display = 
            String(hours).padStart(2, '0') + ':' + 
            String(minutes).padStart(2, '0') + ':' + 
            String(seconds).padStart(2, '0');

        $('#shift-timer').text(display);
    }

    setInterval(updateShiftTimer, 1000);
    updateShiftTimer();
    @endif
});
</script>
<style>
    .blink { animation: blinker 2s linear infinite; }
    @keyframes blinker { 50% { opacity: 0.3; } }
</style>
@endpush
