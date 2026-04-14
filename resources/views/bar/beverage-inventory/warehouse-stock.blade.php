@extends('layouts.dashboard')

@section('title', 'Warehouse Stock')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-archive"></i> Warehouse Stock</h1>
    <p>View and manage all stock available in warehouse</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bar.beverage-inventory.index') }}">Beverage Inventory</a></li>
    <li class="breadcrumb-item">Warehouse Stock</li>
  </ul>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-4">
      <div class="widget-small primary coloured-icon">
        <i class="icon fa fa-archive fa-3x"></i>
        <div class="info">
          <h4>Total Items</h4>
          <p><b>{{ number_format($totalWarehouseStock) }} btl</b></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="widget-small info coloured-icon">
        <i class="icon fa fa-cubes fa-3x"></i>
        <div class="info">
          <h4>Variants</h4>
          <p><b>{{ $warehouseStock->count() }} Variants</b></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="widget-small danger coloured-icon">
        <i class="icon fa fa-exclamation-triangle fa-3x"></i>
        <div class="info">
          <h4>Low Stock</h4>
          <p><b>{{ $warehouseStock->where('is_low_stock', true)->count() }}</b></p>
        </div>
      </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="tile-title">Warehouse Inventory</h3>
                <div class="d-flex align-items-center">
                    <div class="btn-group mr-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary active" id="view-table" onclick="toggleView('table')" title="Table View">
                            <i class="fa fa-table"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="view-card" onclick="toggleView('card')" title="Card View">
                            <i class="fa fa-th-large"></i>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary active" id="tab-all" onclick="switchTab('all')">All Items</button>
                        <button type="button" class="btn btn-outline-danger" id="tab-low" onclick="switchTab('low')">Low Stock</button>
                    </div>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="form-group">
                        <input type="text" id="warehouseSearch" class="form-control" placeholder="Search product or brand...">
                    </div>
                </div>
                <div class="col-md-8">
                    <ul class="nav nav-pills wh-category-tabs" id="categoryTabs">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-category="all">All Categories</a>
                        </li>
                        @foreach($categories as $cat)
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-category="{{ \Str::slug($cat) }}">{{ ucfirst($cat) }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="tile-body">
                <!-- Table View -->
                <div class="table-responsive" id="tableView">
                    <table class="table table-hover table-bordered" id="warehouseTable">
                        <thead>
                            <tr style="background: #f4f4f4;">
                                <th>Product Details</th>
                                <th>Category</th>
                                <th class="text-center">Stock Level</th>
                                <th class="text-center">Packages</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($warehouseStock as $item)
                            <tr class="warehouse-row" 
                                data-category="{{ \Str::slug($item['category']) }}" 
                                data-is-low="{{ $item['is_low_stock'] ? 'true' : 'false' }}"
                                data-search="{{ strtolower($item['display_title'] . ' ' . $item['product_name']) }}">
                                <td>
                                    <strong>{{ $item['display_title'] }}</strong><br>
                                    <small class="text-muted">{{ $item['variant'] }}</small>
                                </td>
                                <td>{{ $item['category'] }}</td>
                                <td class="text-center">
                                    <span class="badge badge-{{ $item['is_low_stock'] ? 'danger' : 'success' }}" style="font-size: 14px; padding: 5px 10px;">
                                        {{ number_format($item['quantity']) }} {{ $item['unit'] }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <strong>{!! $item['packages'] !!}</strong>
                                </td>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Card View -->
                <div id="cardView" style="display: none;">
                    <div class="row">
                        @foreach($warehouseStock as $item)
                        <div class="col-md-4 mb-3 warehouse-card" 
                             data-category="{{ \Str::slug($item['category']) }}" 
                             data-is-low="{{ $item['is_low_stock'] ? 'true' : 'false' }}"
                             data-search="{{ strtolower($item['display_title'] . ' ' . $item['product_name']) }}">
                            <div class="card h-100 {{ $item['is_low_stock'] ? 'border-danger' : 'border-light' }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="font-weight-bold mb-1">{{ $item['display_title'] }}</h6>
                                        <span class="badge badge-{{ $item['is_low_stock'] ? 'danger' : 'success' }}">
                                            {{ number_format($item['quantity']) }} {{ $item['unit'] }}
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-2">{{ $item['variant'] }} • {{ $item['category'] }}</p>
                                    <div class="bg-light p-2 rounded mb-2">
                                        <div class="d-flex justify-content-between small">
                                            <span>Pkg Type:</span>
                                            <strong>{{ $item['packaging_type'] }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span>Inventory Breakdown:</span>
                                            <strong>{!! $item['packages'] !!}</strong>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<style>
    .wh-category-tabs .nav-link {
        font-weight: 600;
        color: #666;
        margin-right: 5px;
    }
    .wh-category-tabs .nav-link.active {
        background-color: #009688;
        color: #fff;
    }
</style>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Live Search
    $('#warehouseSearch').on('keyup', function() {
        filterTable();
    });

    // Category Tabs
    $('#categoryTabs .nav-link').on('click', function(e) {
        e.preventDefault();
        $('#categoryTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        filterTable();
    });
});

function switchTab(tab) {
    $('#tab-all, #tab-low').removeClass('active btn-primary btn-danger').addClass('btn-outline-primary btn-outline-danger');
    if (tab === 'all') {
        $('#tab-all').removeClass('btn-outline-primary').addClass('active btn-primary');
    } else {
        $('#tab-low').removeClass('btn-outline-danger').addClass('active btn-danger');
    }
    filterTable();
}

function toggleView(view) {
    $('#view-table, #view-card').removeClass('active');
    if (view === 'table') {
        $('#view-table').addClass('active');
        $('#tableView').fadeIn();
        $('#cardView').hide();
    } else {
        $('#view-card').addClass('active');
        $('#cardView').fadeIn();
        $('#tableView').hide();
    }
}

function filterTable() {
    const searchTerm = $('#warehouseSearch').val().toLowerCase();
    const activeCategory = $('#categoryTabs .nav-link.active').data('category');
    const showLowStock = $('#tab-low').hasClass('active');

    $('.warehouse-row, .warehouse-card').each(function() {
        const item = $(this);
        const text = item.data('search');
        const category = item.data('category');
        const isLow = item.data('is-low') === 'true';

        const matchesSearch = text.includes(searchTerm);
        const matchesCategory = activeCategory === 'all' || category === activeCategory;
        const matchesStock = !showLowStock || isLow;

        if (matchesSearch && matchesCategory && matchesStock) {
            item.show();
        } else {
            item.hide();
        }
    });
}


</script>
@endsection
