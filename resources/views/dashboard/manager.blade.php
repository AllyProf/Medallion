@extends('layouts.dashboard')

@section('title', 'Manager Dashboard')

@push('styles')
<style>
  /* ── Trend chart ── */
  #revenueTrendChart { max-height: 250px; }

  /* ── Top products bar ── */
  .product-bar-row { margin-bottom: 12px; }
  .product-bar-label { font-size: 14px; font-weight: 500; color: #2c3e50; margin-bottom: 6px; display: flex; justify-content: space-between; }
  .product-bar-track { height: 10px; background: #eaecf4; border-radius: 10px; }
  .product-bar-fill  { height: 10px; background: #009688; border-radius: 10px; transition: width 1s ease; }

  /* ── KPI Widgets ── */
  .widget-small {
    height: 100px;
    margin-bottom: 20px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  }
  .widget-small .icon {
    width: 65px;
    line-height: 100px;
    font-size: 35px;
  }
  .widget-small .info {
    padding: 10px 15px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .widget-small .info h4 {
    text-transform: uppercase;
    font-size: 13px;
    margin-bottom: 5px;
    font-weight: 600;
  }
  .widget-small .info p {
    margin-bottom: 1px;
    font-size: 18px;
  }
  .widget-small .info small {
    display: block;
    margin-top: 2px;
  }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-tachometer"></i> Manager Dashboard</h1>
    <p>Welcome back, <strong>{{ $staff->full_name }}</strong> &mdash; {{ now()->format('l, F j, Y') }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Manager Dashboard</li>
  </ul>
</div>

{{-- ═══════════════════════════════════════╗
     ROW 1 – KPI Cards                     ║
══════════════════════════════════════════--}}
@php
  $monthBarRev = $monthRevenue - $foodMonthRevenue;
@endphp

<div class="row">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon"><i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Today Revenue</h4>
        <p><b>TSh {{ number_format($todayRevenue) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon"><i class="icon fa fa-line-chart fa-3x"></i>
      <div class="info">
        <h4>Month {{ now()->format('M Y') }}</h4>
        <p><b>TSh {{ number_format($monthRevenue) }}</b></p>
        <small class="text-white" style="opacity:.85;">Bar: {{ number_format($monthBarRev) }} | Food: {{ number_format($foodMonthRevenue) }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon"><i class="icon fa fa-exchange fa-3x"></i>
      <div class="info">
        <h4>Pending Transfers</h4>
        <p><b>{{ number_format($pendingTransfers) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon"><i class="icon fa fa-shopping-bag fa-3x"></i>
      <div class="info">
        <h4>Month Purchases</h4>
        <p><b>TSh {{ number_format($monthlyPurchaseCost) }}</b></p>
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════╗
     ROW 1.5 – Monthly Targets Progress     ║
══════════════════════════════════════════--}}
<div class="row">
  <div class="col-md-6 mb-4">
    <div class="tile pb-2">
      <div class="d-flex justify-content-between">
        <h6 class="text-muted small font-weight-bold"><i class="fa fa-glass mr-1"></i> BAR MONTHLY GOAL</h6>
        <span class="badge badge-primary">{{ $barTargetProgress }}%</span>
      </div>
      <div class="product-bar-track mt-2">
        <div class="product-bar-fill bg-info" style="width: {{ $barTargetProgress }}%; background-color: #36b9cc !important;"></div>
      </div>
      <div class="d-flex justify-content-between mt-1 tiny text-muted">
        <span>TSh {{ number_format($monthRevenue) }}</span>
        <span>Target: TSh {{ number_format($barMonthlyTarget) }}</span>
      </div>
    </div>
  </div>
  <div class="col-md-6 mb-4">
    <div class="tile pb-2">
      <div class="d-flex justify-content-between">
        <h6 class="text-muted small font-weight-bold"><i class="fa fa-cutlery mr-1"></i> FOOD MONTHLY GOAL</h6>
        <span class="badge badge-warning">{{ $foodTargetProgress }}%</span>
      </div>
      <div class="product-bar-track mt-2">
        <div class="product-bar-fill bg-warning" style="width: {{ $foodTargetProgress }}%; background-color: #f6c23e !important;"></div>
      </div>
      <div class="d-flex justify-content-between mt-1 tiny text-muted">
        <span>TSh {{ number_format($foodMonthRevenue) }}</span>
        <span>Target: TSh {{ number_format($foodMonthlyTarget) }}</span>
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
      <h3 class="tile-title"><i class="fa fa-area-chart"></i> Revenue – Last 7 Days (Bar + Food)</h3>
      <div class="tile-body">
        <canvas id="revenueTrendChart" style="max-height: 300px;"></canvas>
        @if(collect($revenueTrend)->isEmpty())
          <div class="empty-state"><i class="fa fa-bar-chart"></i> No revenue data yet</div>
        @endif
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
            @foreach($categoryDistribution->take(8) as $cat)
              @php $isFood = ($cat->type ?? 'drink') === 'food'; @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1" style="border:none; border-bottom:1px solid #f0f0f0;">
                <span class="text-truncate" style="max-width: 60%;">
                  <i class="fa fa-circle mr-2" style="font-size:8px; color:{{ $isFood ? '#e65100' : '#1a237e' }};"></i>
                  {{ $isFood ? '🍽' : '🍺' }} {{ $cat->category ?? 'Unknown' }}
                </span>
                <span class="badge badge-pill" style="background:{{ $isFood ? '#ff9800' : '#1a237e' }}; color:#fff;">TSh {{ number_format($cat->total_revenue) }}</span>
              </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted text-center mt-3">No sales data this month.</p>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 3 – Top Products & Top Waiters                   ║
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
                $name = $tp->product_full_name ?? 'Unknown Product';
                $pct = round(($tp->total_sold / $maxSold) * 100);
                $isFood = ($tp->type ?? 'drink') === 'food';
              @endphp
              <div class="col-md-6 mb-3">
                <div class="product-bar-label">
                  <span class="text-truncate pr-3">
                    @if($isFood)
                      <span title="Food Item">🍽</span>
                    @else
                      <span title="Drink">🍺</span>
                    @endif
                    {{ $name }}
                  </span>
                  <span style="color: {{ $isFood ? '#e65100' : '#b71c1c' }}; font-weight: bold;">{{ number_format($tp->total_sold) }} <small>{{ $isFood ? 'pcs' : 'btls' }}</small></span>
                </div>
                <div class="product-bar-track">
                  <div class="product-bar-fill" style="width: {{ $pct }}%; background: {{ $isFood ? '#ff9800' : '#009688' }};"></div>
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

  {{-- Top Waiters --}}
  <div class="col-md-4 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-users"></i> Top Waiters This Month</h3>
      <div class="tile-body">
        @if(isset($topWaiters) && $topWaiters->count() > 0)
          <ul class="list-group list-group-flush">
            @foreach($topWaiters->take(6) as $tw)
              <li class="list-group-item px-0 d-flex justify-content-between align-items-center" style="font-size:13px;">
                <div>
                  <div class="font-weight-bold">{{ $tw['waiter']->full_name }}</div>
                  <small class="text-muted">{{ $tw['orders_count'] }} orders</small>
                </div>
                <div class="text-right">
                  <div class="text-success font-weight-bold">TSh {{ number_format($tw['total_revenue']) }}</div>
                  <div class="text-muted" style="font-size:11px;">
                    Bar: {{ number_format($tw['bar_revenue']) }} | Food: {{ number_format($tw['food_revenue']) }}
                  </div>
                </div>
              </li>
            @endforeach
          </ul>
        @else
          <div class="empty-state"><i class="fa fa-user-o"></i> No waiter data this month</div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 4 – Quick Actions                                ║
═══════════════════════════════════════════════════════--}}
<div class="row mt-2">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-bolt"></i> Quick Links</h3>
      <div class="tile-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <a href="{{ route('bar.stock-transfers.index') }}" class="btn btn-outline-primary btn-block p-3 text-center">
              <i class="fa fa-truck fa-2x mb-2"></i><br>TRANSFERS
              @if($pendingTransfers > 0)
                <span class="badge badge-primary ml-1">{{ $pendingTransfers }}</span>
              @endif
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="{{ route('bar.stock-receipts.index') }}" class="btn btn-outline-success btn-block p-3 text-center">
              <i class="fa fa-inbox fa-3x mb-2"></i><br>STOCK RECEIPTS
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="{{ route('bar.beverage-inventory.warehouse-stock') }}" class="btn btn-outline-info btn-block p-3 text-center">
              <i class="fa fa-archive fa-2x mb-2"></i><br>WAREHOUSE
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="{{ route('bar.counter.counter-stock') }}" class="btn btn-outline-info btn-block p-3 text-center" style="border-color: #009688; color: #009688;">
              <i class="fa fa-glass fa-2x mb-2"></i><br>COUNTER STOCK
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="{{ route('reports.stock-transfers') }}" class="btn btn-outline-secondary btn-block p-3 text-center">
              <i class="fa fa-file-text-o fa-2x mb-2"></i><br>TRANSFER REPORT
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="{{ route('accountant.reconciliations') }}" class="btn btn-outline-dark btn-block p-3 text-center">
              <i class="fa fa-money fa-2x mb-2"></i><br>RECONCILIATIONS
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="{{ route('reports.waiter-trends') }}" class="btn btn-outline-warning btn-block p-3 text-center">
              <i class="fa fa-users fa-2x mb-2"></i><br>WAITER TRENDS
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="{{ route('manager.qr-codes.index') }}" class="btn btn-outline-primary btn-block p-3 text-center" style="border-color: #940000; color: #940000;">
              <i class="fa fa-qrcode fa-2x mb-2"></i><br>QR PORTAL
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function() {
  @php
    $trendJson = collect($revenueTrend)->map(function($r) {
        return [
            'date'         => $r->date ?? null,
            'revenue'      => $r->revenue ?? 0,
            'bar_revenue'  => $r->bar_revenue ?? 0,
            'food_revenue' => $r->food_revenue ?? 0,
            'orders'       => $r->orders ?? 0,
        ];
    })->values()->toArray();
  @endphp
  const trendData = @json($trendJson);

  const allDays = [], allBarRev = [], allFoodRev = [], allOrders = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const dateStr = d.toISOString().slice(0, 10);
    allDays.push(d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
    const matching = trendData.find(r => r.date === dateStr);
    allBarRev.push(matching ? parseFloat(matching.bar_revenue) : 0);
    allFoodRev.push(matching ? parseFloat(matching.food_revenue) : 0);
    allOrders.push(matching ? parseInt(matching.orders) : 0);
  }

  const ctx = document.getElementById('revenueTrendChart');
  if (!ctx) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: allDays,
      datasets: [
        {
          label: 'Drinks (TSh)',
          data: allBarRev,
          backgroundColor: 'rgba(30, 136, 229, 0.8)',
          borderColor: '#1565C0',
          borderWidth: 1,
          borderRadius: 4,
          stack: 'revenue',
          yAxisID: 'y',
        },
        {
          label: 'Food (TSh)',
          data: allFoodRev,
          backgroundColor: 'rgba(230, 81, 0, 0.8)',
          borderColor: '#bf360c',
          borderWidth: 1,
          borderRadius: 4,
          stack: 'revenue',
          yAxisID: 'y',
        },
        {
          type: 'line',
          label: 'Orders',
          data: allOrders,
          borderColor: '#7B1FA2',
          backgroundColor: 'rgba(123, 31, 162, 0.1)',
          borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: '#7B1FA2',
          tension: 0.4,
          fill: true,
          yAxisID: 'y1',
        }
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              if (ctx.datasetIndex === 2) return ' ' + ctx.parsed.y + ' orders';
              return ' TSh ' + ctx.parsed.y.toLocaleString();
            }
          }
        }
      },
      scales: {
        x: { stacked: true },
        y: {
          type: 'linear', position: 'left', stacked: true,
          ticks: { callback: v => 'TSh ' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v), font: { size: 10 } },
          grid: { color: 'rgba(0,0,0,0.04)' }
        },
        y1: {
          type: 'linear', position: 'right',
          ticks: { font: { size: 10 } },
          grid: { drawOnChartArea: false }
        }
      },
      animation: {
        duration: 1000,
        easing: 'easeOutQuart'
      },
      hover: {
        mode: 'index',
        intersect: false,
        includeInvisible: true
      }
    }
  });

  // ── Category Distribution Chart ──
  const distData = @json($categoryDistribution);
  const distCtx = document.getElementById('categoryDistributionChart');
  
  if (distCtx && distData && distData.length > 0) {
    const labels = distData.map(d => d.category || 'Uncategorized');
    const data = distData.map(d => parseInt(d.total_sold));
    const bgColors = [
      'rgba(26, 35, 126, 0.8)',   // Indigo
      'rgba(0, 150, 136, 0.8)',   // Teal
      'rgba(233, 30, 99, 0.8)',   // Pink
      'rgba(255, 152, 0, 0.8)',   // Orange
      'rgba(76, 175, 80, 0.8)',   // Green
      'rgba(156, 39, 176, 0.8)',  // Purple
      'rgba(3, 169, 244, 0.8)'    // Light Blue
    ];

    new Chart(distCtx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: bgColors,
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } },
          tooltip: {
            callbacks: {
              label: function(ctx) { return ' ' + ctx.parsed + ' items sold'; }
            }
          }
        },
        cutout: '70%',
        animation: {
          duration: 1200,
          easing: 'easeOutQuart'
        }
      }
    });
  }
})();
</script>
@endpush
