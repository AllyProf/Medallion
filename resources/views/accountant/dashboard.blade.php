@extends('layouts.dashboard')

@section('title', 'Accountant Dashboard')

@push('styles')
<style>
  /* ── Trend chart ── */
  #revenueTrendChart { max-height: 250px; }

  /* ── Metric Cards Uniformity ── */
  .stats-row .col-md { display: flex; }
  .widget-small { 
    width: 100%; 
    min-height: 70px; 
    align-items: center; 
    border-radius: 6px; 
    box-shadow: 0 2px 6px rgba(0,0,0,0.05); 
    transition: transform 0.2s;
    border: 1px solid rgba(0,0,0,0.05);
    margin-bottom: 15px;
  }
  .widget-small:hover { transform: translateY(-2px); }
  .widget-small .info { padding: 8px 15px; }
  .widget-small .info h4 { text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; font-weight: 700; margin-bottom: 2px; opacity: 0.7; }
  .widget-small .info p { font-size: 15px; margin-bottom: 0; }
  .widget-small .icon { width: 50px; height: 100%; display: flex; align-items: center; justify-content: center; border-radius: 6px 0 0 6px; font-size: 20px; }

  /* ── Top products bar ── */
  .product-bar-row { margin-bottom: 12px; }
  .product-bar-label { font-size: 14px; font-weight: 500; color: #2c3e50; margin-bottom: 6px; display: flex; justify-content: space-between; }
  .product-bar-track { height: 10px; background: #eaecf4; border-radius: 10px; }
  .product-bar-fill  { height: 10px; background: #009688; border-radius: 10px; transition: width 1s ease; }

  /* ── Empty states ── */
  .empty-state { text-align: center; padding: 30px 0; color: #90a4ae; }
  .empty-state i { font-size: 36px; display: block; margin-bottom: 8px; }
</style>
@endpush

@section('content')
<div class="app-title">
  <div class="d-flex align-items-center">
    <div>
      <h1><i class="fa fa-calculator"></i> Accountant Dashboard</h1>
      <p>Financial Overview & Reconciliation Management</p>
    </div>
    <div class="ml-4">
      <a href="{{ route('manager.live-sales') }}" class="btn btn-primary shadow-sm" style="background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%); border: none; border-radius: 20px; padding: 10px 20px; font-weight: 700; letter-spacing: 0.5px;">
        <i class="fa fa-bolt pulse mr-2"></i> LIVE SALES PULSE
      </a>
    </div>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item">Accountant</li>
  </ul>
</div>

<!-- Date Selector -->
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <form method="GET" action="{{ route('accountant.dashboard') }}" class="form-inline">
        <div class="form-group mr-3">
          <label for="date" class="mr-2">Today's Date:</label>
          <input type="date" name="date" id="date" class="form-control" value="{{ $date }}" required>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-search"></i> Update
        </button>
      </form>
    </div>
  </div>
</div>

<div class="row mb-4 stats-row">
  <div class="col-md">
    <div class="widget-small primary coloured-icon h-100 text-white"><i class="icon fa fa-money fa-2x"></i>
      <div class="info" style="color: white !important;">
        <h4 style="color: white !important; opacity: 0.9;">Today Revenue</h4>
        <p><b>TSh {{ number_format($todayRevenue) }}</b></p>
        <div style="font-size: 10px; opacity: 0.8; margin-top: 4px; color: white !important;">Bar: {{ number_format($todayBarVerified) }} | Food: {{ number_format($todayFoodVerified) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md">
    <div class="widget-small info coloured-icon h-100"><i class="icon fa fa-cutlery fa-2x"></i>
      <div class="info">
        <h4>Today Kitchen Sales</h4>
        <p><b>TSh {{ number_format($todayFoodSales) }}</b></p>
        <div style="font-size: 10px; opacity: 0; margin-top: 4px;">-</div> {{-- Hidden balancer --}}
      </div>
    </div>
  </div>
  <div class="col-md">
    <div class="widget-small success coloured-icon h-100 text-dark"><i class="icon fa fa-bank fa-2x"></i>
      <div class="info" style="color: #000 !important;">
        <h4 style="color: #000 !important; opacity: 0.9;">Cash Collected</h4>
        <p><b style="color: #000 !important;">TSh {{ number_format($todayCash) }}</b></p>
        <div style="font-size: 10px; opacity: 0; margin-top: 4px;">-</div> {{-- Hidden balancer --}}
      </div>
    </div>
  </div>
  <div class="col-md">
    <div class="widget-small danger coloured-icon h-100"><i class="icon fa fa-shopping-cart fa-2x"></i>
      <div class="info">
        <h4>Today Expenses</h4>
        <p><b>TSh {{ number_format($todayExpenses ?? 0) }}</b></p>
        <div style="font-size: 10px; opacity: 0; margin-top: 4px;">-</div> {{-- Hidden balancer --}}
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════
     ROW 2 – Revenue Trend Chart  |  Category Distribution ║
     ═══════════════════════════════════════════════════--}}
<div class="row">
  <div class="col-md-8 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-line-chart"></i> Daily Profit Performance: Drinks vs Food</h3>
      <div class="tile-body">
        <div style="position: relative; height: 300px; width: 100%;">
          <canvas id="revenueTrendChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-pie-chart"></i> Category Sales</h3>
      <div class="tile-body text-center">
        <div style="position: relative; height: 200px; width: 100%; display: flex; justify-content: center; align-items: center; margin-bottom: 15px;">
          <canvas id="categoryDistributionChart"></canvas>
        </div>
        @if(isset($categoryDistribution) && $categoryDistribution->count() > 0)
          <ul class="list-group list-group-flush text-left" style="font-size: 13px;">
            @foreach($categoryDistribution->take(6) as $cat)
              <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1" style="border:none; border-bottom:1px solid #f0f0f0;">
                <span class="text-truncate" style="max-width: 60%;"><i class="fa fa-circle mr-2" style="font-size:8px; color:#1a237e;"></i>{{ $cat['category'] ?? 'Uncategorized' }}</span>
                <span class="badge badge-primary badge-pill">TSh {{ number_format($cat['total_revenue']) }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 3 – Top Products & Top Waiters                      ║
     ═══════════════════════════════════════════════════════--}}
<div class="row">
  <div class="col-md-8 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-star"></i> Top Products This Month</h3>
      <div class="tile-body">
        @if($topProducts->count() > 0)
          @php $maxSold = $topProducts->max('total_sold') ?: 1; @endphp
          <div class="row">
            @foreach($topProducts as $tp)
              @php
                $pct = round(($tp['total_sold'] / $maxSold) * 100);
              @endphp
              <div class="col-md-6 mb-3">
                <div class="product-bar-label">
                  <span class="text-truncate pr-3">{{ $tp['name'] }}</span>
                  <span style="color: #b71c1c; font-weight: bold;">{{ number_format($tp['total_sold']) }} <small>sold</small></span>
                </div>
                <div class="product-bar-track">
                  <div class="product-bar-fill" style="width: {{ $pct }}%"></div>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="empty-state"><i class="fa fa-star-o"></i> No sales data this month</div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-md-4 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-users"></i> Top Waiters This Month</h3>
      <div class="tile-body">
        @if($topWaiters->count() > 0)
          <ul class="list-group list-group-flush">
            @foreach($topWaiters->take(5) as $tw)
              <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <div>
                  <div class="font-weight-bold">{{ $tw['waiter']->full_name }}</div>
                  <small class="text-muted">{{ $tw['orders_count'] }} successful orders</small>
                </div>
                <div class="text-right">
                  <div class="text-success font-weight-bold">TSh {{ number_format($tw['total_revenue']) }}</div>
                  <div class="small text-muted" style="font-size: 11px;">
                    Bar: {{ number_format($tw['bar_revenue']) }} | Food: {{ number_format($tw['food_revenue']) }}
                  </div>
                </div>
              </li>
            @endforeach
          </ul>
        @else
          <div class="empty-state"><i class="fa fa-user-o"></i> No waiter data</div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row mt-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Quick Actions</h3>
      <div class="tile-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <a href="{{ route('manager.live-sales') }}" class="btn btn-primary btn-block p-3 text-center shadow-sm" style="background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%); border: none;">
              <i class="fa fa-bolt fa-2x mb-2 pulse"></i><br>
              LIVE SALES PULSE
              <span class="badge badge-light ml-1" style="color: #00d2ff;">REAL-TIME</span>
            </a>
          </div>

          <div class="col-md-4 mb-3">
            <a href="{{ route('accountant.fund-issuance') }}" class="btn btn-outline-warning btn-block p-3">
              <i class="fa fa-money fa-2x mb-2"></i><br>
              ISSUE PETTY CASH
            </a>
          </div>
          <div class="col-md-4 mb-3">
            <a href="{{ route('bar.counter.counter-stock') }}" class="btn btn-outline-primary btn-block p-3" style="border-color: #6c757d; color: #6c757d;">
              <i class="fa fa-glass fa-2x mb-2"></i><br>
              COUNTER STOCK
            </a>
          </div>
          <div class="col-md-4 mb-3">
            <a href="{{ route('bar.beverage-inventory.warehouse-stock') }}" class="btn btn-outline-success btn-block p-3">
              <i class="fa fa-archive fa-2x mb-2"></i><br>
              WAREHOUSE STOCK
            </a>
          </div>
          <div class="col-md-4 mb-3">
            <a href="{{ route('reports.stock-receipts') }}" class="btn btn-outline-dark btn-block p-3">
              <i class="fa fa-file-text-o fa-2x mb-2"></i><br>
              STOCK RECEIPTS
            </a>
          </div>
          <div class="col-md-4 mb-3">
            <a href="{{ route('reports.stock-transfers') }}" class="btn btn-outline-secondary btn-block p-3">
              <i class="fa fa-truck fa-2x mb-2"></i><br>
              TRANSFER REPORTS
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function() {
  const rawData = @json($revenueByDay);
  // Show last 30 completed days (exclude today which is always incomplete)
  const trendData = rawData; // Include all days including today

  const ctx = document.getElementById('revenueTrendChart');
  if (!ctx) return;

    let cumulative = 0;
    const waterfallData = trendData.map(d => {
      const start = cumulative;
      cumulative += d.total_profit;
      return [start, cumulative];
    });

    const barColors = [
      '#ef5350', '#66bb6a', '#29b6f6', '#ffca28', '#ffa726', '#7e57c2', '#26a69a'
    ];

    const maxProfitIndex = trendData.reduce((bestIndex, d, i, arr) => d.total_profit > arr[bestIndex].total_profit ? i : bestIndex, 0);

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: trendData.map(d => d.date),
        datasets: [
          {
            label: 'Drinks Profit',
            data: trendData.map(d => d.bar_profit),
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: trendData.map((d, i) => i === maxProfitIndex ? '#ffd700' : '#fff'),
            pointBorderColor: '#007bff',
            pointBorderWidth: 2,
            pointRadius: trendData.map((d, i) => i === maxProfitIndex ? 7 : 4),
            pointHoverRadius: 8,
            type: 'line'
          },
          {
            label: 'Food Profit',
            data: trendData.map(d => d.food_profit),
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: trendData.map((d, i) => i === maxProfitIndex ? '#ffd700' : '#fff'),
            pointBorderColor: trendData.map((d, i) => i === maxProfitIndex ? '#ffc107' : '#28a745'),
            pointBorderWidth: 2,
            pointRadius: trendData.map((d, i) => i === maxProfitIndex ? 7 : 4),
            pointHoverRadius: 8,
            type: 'line'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: function(ctx) { return ctx.dataset.label + ': TSh ' + Math.round(ctx.parsed.y).toLocaleString(); }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: v => 'TSh ' + (v >= 1000 ? (v/1000).toFixed(0) + 'K' : v),
              font: { size: 11 }
            },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { 
            ticks: { font: { size: 11 } }, 
            grid: { display: false } 
          }
        }
      }
    });


  // ── Category Distribution Chart ──
  const distData = @json($categoryDistribution);
  const distCtx = document.getElementById('categoryDistributionChart');
  
  if (distCtx && distData && distData.length > 0) {
    new Chart(distCtx, {
      type: 'doughnut',
      data: {
        labels: distData.map(d => d.category || 'Uncategorized'),
        datasets: [{
          data: distData.map(d => parseFloat(d.total_revenue)),
          backgroundColor: [
            'rgba(26, 35, 126, 0.8)', 'rgba(0, 150, 136, 0.8)', 'rgba(233, 30, 99, 0.8)',
            'rgba(255, 152, 0, 0.8)', 'rgba(76, 175, 80, 0.8)', 'rgba(156, 39, 176, 0.8)'
          ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          cutout: '70%'
        }
      }
    });
  }
})();
</script>
@endpush

@endsection

