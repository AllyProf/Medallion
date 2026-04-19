@extends('layouts.dashboard')

@section('title', 'Live Sales Monitor')

@push('styles')
<style>
    /* ── Live Tracking Animations ── */
    .live-pulse-dot {
        height: 10px; width: 10px; background-color: #ff0000; border-radius: 50%;
        display: inline-block; margin-right: 8px;
        box-shadow: 0 0 0 rgba(255, 0, 0, 0.4);
        animation: pulse-red 2s infinite;
    }
    @keyframes pulse-red {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(255, 0, 0, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
    }
    .live-shimmer {
        background: linear-gradient(90deg, #333 0%, #000 50%, #333 100%);
        background-size: 200% 100%; animation: shimmer 5s infinite linear;
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .text-white .live-shimmer {
        background: linear-gradient(90deg, #fff 0%, #eee 50%, #fff 100%);
        background-size: 200% 100%; -webkit-background-clip: text;
    }
    @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
    .status-badge-breathing { animation: breathe 3s infinite ease-in-out; }
    @keyframes breathe { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(0.98); } }
    .widget-small { transition: all 0.3s ease; border: none; }
    .widget-small:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    .refresh-bar-container { position: fixed; top: 50px; left: 0; width: 100%; height: 4px; z-index: 9999; }
    #refresh-progress { height: 100%; background: #ff0000; width: 100%; box-shadow: 0 0 10px rgba(255, 0, 0, 0.5); }
    .card-pulse-indicator { position: absolute; top: 10px; right: 15px; font-size: 0.6rem; font-weight: 700; opacity: 0.6; }
</style>
@endpush

@section('content')
<div class="refresh-bar-container">
    <div id="refresh-progress"></div>
</div>

<div class="app-title">
    <div>
        <h1>
            <span class="live-pulse-dot"></span>
            LIVE SALES PULSE
            <span class="badge badge-danger ml-2 status-badge-breathing" style="font-size: 0.7rem;">REAL-TIME</span>
        </h1>
        <p>
            @if($activeShift)
                Monitoring <strong>Shift #{{ $activeShift->id }}</strong> (Started {{ $activeShift->opened_at->format('H:i') }})
            @else
                Tracking <strong>General Session</strong> ({{ now()->format('l, F j') }})
            @endif
        </p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item" id="last-updated-text" style="font-weight: bold; color: #ff0000;">
            <i class="fa fa-refresh fa-spin mr-1"></i> SYNCED: JUST NOW
        </li>
    </ul>
</div>

<div class="row">
    <!-- Revenue Pulse -->
    <div class="col-md-3">
        <div class="widget-small primary coloured-icon position-relative">
            <div class="card-pulse-indicator"><i class="fa fa-circle text-white mr-1"></i> LIVE</div>
            <i class="icon fa fa-bolt fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Revenue' : 'Today Revenue' }}</h4>
                <p><b class="live-shimmer" id="total-revenue-text">TSh {{ number_format($totalRevenue) }}</b></p>
                <small>Cash: <span id="cash-revenue-text">{{ number_format($todayCash) }}</span> | Digital: <span id="digital-revenue-text">{{ number_format($todayDigital) }}</span></small>
            </div>
        </div>
    </div>

    <!-- Profit Pulse -->
    <div class="col-md-3">
        <div class="widget-small info coloured-icon position-relative" style="background-color: #28a745 !important;">
            <div class="card-pulse-indicator"><i class="fa fa-circle text-white mr-1"></i> LIVE</div>
            <i class="icon fa fa-line-chart fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Profit' : 'Est. Profit' }}</h4>
                <p><b class="live-shimmer" id="total-profit-text">TSh {{ number_format($shiftProfit) }}</b></p>
                <small class="text-white">Margin: Approx 40%+</small>
            </div>
        </div>
    </div>

    <!-- Circulation Pulse -->
    <div class="col-md-3">
        <div class="widget-small warning coloured-icon position-relative" style="background-color: #e67e22 !important;">
            <div class="card-pulse-indicator"><i class="fa fa-circle text-white mr-1"></i> LIVE</div>
            <i class="icon fa fa-exchange fa-3x"></i>
            <div class="info">
                <h4>In Circulation</h4>
                <p><b class="live-shimmer" id="total-circulation-text">TSh {{ number_format($moneyInCirculation) }}</b></p>
                <small>Opening + Rev - Exp</small>
            </div>
        </div>
    </div>

    <!-- Active Orders Pulse -->
    <div class="col-md-3">
        <div class="widget-small danger coloured-icon position-relative">
            <div class="card-pulse-indicator"><i class="fa fa-circle text-white mr-1"></i> LIVE</div>
            <i class="icon fa fa-shopping-cart fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Orders' : 'Today Orders' }}</h4>
                <p>
                    <b id="total-orders-count">{{ $totalOrders }}</b> <small>Total</small> | 
                    <b class="text-white" id="active-orders-count">{{ $activeOrders }}</b> <small>Live</small>
                </p>
                <small>Served: <span id="served-orders-count">{{ $servedOrders }}</span></small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Velocity Summary -->
    <div class="col-md-8">
        <div class="tile p-3 mb-4" style="min-height: 250px; display: flex; flex-direction: column;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-muted small font-weight-bold uppercase">{{ $activeShift ? 'SHIFT VELOCITY' : 'HOURLY VELOCITY' }}</h6>
                <span class="badge badge-primary">ORDERS PER HOUR</span>
            </div>
            <div class="velocity-chart-container flex-grow-1">
                <canvas id="velocityChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Mix -->
    <div class="col-md-4">
        <div class="tile p-3 mb-4" style="min-height: 250px; display: flex; flex-direction: column;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-muted small font-weight-bold uppercase">CATEGORY MIX</h6>
                <span class="badge badge-info">DRINKS vs FOOD</span>
            </div>
            <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                <div style="width: 180px; height: 180px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Live Activity Feed -->
    <div class="col-md-8 mb-4">
        <div class="tile">
            <h3 class="tile-title border-bottom pb-2">
                <i class="fa fa-flash text-warning mr-2"></i> Real-Time Sales Stream
            </h3>
            <div class="tile-body" id="live-feed-container" style="max-height: 600px; overflow-y: auto;">
                @include('manager.partials.live_feed_items', ['liveFeed' => $liveFeed])
            </div>
        </div>
    </div>

    <!-- Side Stats: Staff & Top Products -->
    <div class="col-md-4">
        <!-- Staff Performance -->
        <div class="tile mb-4">
            <h3 class="tile-title border-bottom pb-2"><i class="fa fa-users text-primary mr-2"></i> {{ $activeShift ? 'Shift Staff Pulse' : 'Active Staff Pulse' }}</h3>
            <div class="tile-body">
                <ul class="list-group list-group-flush" id="staff-pulse-container">
                    @include('manager.partials.staff_pulse_items', ['staffPulse' => $staffPulse])
                </ul>
            </div>
        </div>

        <!-- Today's Stars -->
        <div class="tile">
            <h3 class="tile-title border-bottom pb-2"><i class="fa fa-star text-warning mr-2"></i> {{ $activeShift ? 'Shift Top Items' : "Today's Hot Items" }}</h3>
            <div class="tile-body">
                <div class="mb-3">
                    <h6 class="text-muted small font-weight-bold mb-3">{{ $activeShift ? 'DRINKS (SHIFT)' : 'TOP DRINKS' }}</h6>
                    @foreach($topDrinks as $drink)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <span class="small font-weight-bold">{{ $drink->display_name }}</span>
                        <span class="badge badge-pill badge-primary">{{ $drink->total_qty }}</span>
                    </div>
                    @endforeach
                </div>
                <hr>
                <div>
                    <h6 class="text-muted small font-weight-bold mb-3">{{ $activeShift ? 'FOOD (SHIFT)' : 'TOP DISHES' }}</h6>
                    @foreach($topFood as $food)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <span class="small">{{ $food->food_item_name }}</span>
                        <span class="badge badge-pill badge-info">{{ $food->total_qty }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let velocityChart, categoryChart;
    const refreshInterval = 30000; // 30 seconds
    let lastRefresh = Date.now();

    function initChart() {
        const velCtx = document.getElementById('velocityChart').getContext('2d');
        velocityChart = new Chart(velCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Orders',
                    data: @json(array_values($hourlyData)),
                    borderColor: '#940000',
                    backgroundColor: 'rgba(148, 0, 0, 0.05)',
                    borderWidth: 3,
                    pointRadius: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                scales: {
                    x: { ticks: { color: '#666', font: { size: 10 } }, grid: { display: false } },
                    y: { ticks: { precision: 0 }, grid: { borderDash: [5, 5] } }
                }
            }
        });

        const catCtx = document.getElementById('categoryChart').getContext('2d');
        categoryChart = new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: ['Drinks', 'Food'],
                datasets: [{
                    data: [{{ $barRevenueShift }}, {{ $foodRevenueShift }}],
                    backgroundColor: ['#940000', '#17a2b8'],
                    hoverOffset: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { 
                        callbacks: {
                            label: function(context) {
                                return context.label + ': TSh ' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }

    function updateDashboard() {
        fetch('{{ route('manager.live-sales') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            // Update Revenue
            document.getElementById('total-revenue-text').innerText = 'TSh ' + data.revenue.total;
            document.getElementById('cash-revenue-text').innerText = data.revenue.cash;
            document.getElementById('digital-revenue-text').innerText = data.revenue.digital;
            document.getElementById('total-profit-text').innerText = 'TSh ' + data.revenue.profit;
            document.getElementById('total-circulation-text').innerText = 'TSh ' + data.revenue.circulation;

            // Update Pulse Counts
            document.getElementById('total-orders-count').innerText = data.pulse.total_orders;
            document.getElementById('active-orders-count').innerText = data.pulse.active_orders;
            document.getElementById('served-orders-count').innerText = data.pulse.served_orders;

            // Update Feed & Staff
            document.getElementById('live-feed-container').innerHTML = data.live_feed;
            document.getElementById('staff-pulse-container').innerHTML = data.staff_pulse;

            // Update Velocity Chart
            velocityChart.data.datasets[0].data = data.hourly_data;
            velocityChart.update('none');

            // Update Category Chart
            categoryChart.data.datasets[0].data = [data.category_mix.bar, data.category_mix.food];
            categoryChart.update('none');

            // Reset UI
            document.getElementById('last-updated-text').innerText = 'Synced: ' + new Date().toLocaleTimeString();
            lastRefresh = Date.now();
            updateProgressBar();
        })
        .catch(err => console.error('Dashboard sync error:', err));
    }

    function updateProgressBar() {
        const now = Date.now();
        const elapsed = now - lastRefresh;
        const remaining = Math.max(0, refreshInterval - elapsed);
        const percentage = (remaining / refreshInterval) * 100;
        document.getElementById('refresh-progress').style.width = percentage + '%';
        
        if (elapsed >= refreshInterval) {
            updateDashboard();
        } else {
            requestAnimationFrame(updateProgressBar);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initChart();
        updateProgressBar();
    });
</script>
@endpush
