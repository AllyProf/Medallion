@extends('layouts.dashboard')

@section('title', 'Live Sales Monitor')

@push('styles')
<style>
    :root {
        --live-accent: #00d2ff;
        --live-glow: rgba(0, 210, 255, 0.4);
    }
    .pulse-glow {
        box-shadow: 0 0 0 0 var(--live-glow);
        animation: pulse-animation 2s infinite;
        border-radius: 50%;
    }
    @keyframes pulse-animation {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 var(--live-glow); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(0, 210, 255, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 210, 255, 0); }
    }
    .live-card {
        border-radius: 15px;
        transition: all 0.3s ease;
        border: none;
        overflow: hidden;
    }
    .live-card:hover {
        transform: translateY(-5px);
    }
    .velocity-chart-container {
        height: 180px;
    }
    #refresh-timer {
        width: 100%;
        height: 3px;
        background: #eee;
        position: relative;
    }
    #refresh-progress {
        height: 100%;
        background: var(--live-accent);
        width: 100%;
        transition: width 1s linear;
    }
    .list-group-item {
        border-left: 4px solid transparent !important;
    }
    .list-group-item:hover {
        border-left: 4px solid var(--live-accent) !important;
        background-color: #f8f9fc;
    }
    .counter-value {
        font-weight: 800;
        letter-spacing: -1px;
    }
    .gradient-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
    .gradient-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); }
    .gradient-info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); }
</style>
@endpush

@section('content')
<div class="app-title mb-4">
    <div class="d-flex align-items-center">
        <div class="pulse-glow mr-3" style="width: 15px; height: 15px; background: var(--live-accent);"></div>
        <div>
            <h1 class="mb-0">Live Sales Dashboard</h1>
            <p class="text-muted small mb-0">Real-time business pulse for {{ now()->format('F j, Y') }}</p>
        </div>
    </div>
    <div class="text-right">
        <span class="badge badge-dark p-2 px-3" id="last-updated-text">Synced: Just Now</span>
        <div id="refresh-timer" class="mt-2" style="width: 150px; display: inline-block;">
            <div id="refresh-progress"></div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Revenue Pulse -->
    <div class="col-md-4">
        <div class="card live-card gradient-primary text-white shadow">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title text-uppercase opacity-75 mb-0" style="font-size: 0.9rem; letter-spacing: 1px;">Total Today</h5>
                    <i class="fa fa-money fa-2x opacity-50"></i>
                </div>
                <h2 class="counter-value mb-0" id="total-revenue-text">TSh {{ number_format($totalRevenue) }}</h2>
                <div class="mt-3 small">
                    <span class="mr-3"><i class="fa fa-circle text-white opacity-50 mr-1"></i> Cash: <strong id="cash-revenue-text">{{ number_format($todayCash) }}</strong></span>
                    <span><i class="fa fa-circle text-white opacity-50 mr-1"></i> Digital: <strong id="digital-revenue-text">{{ number_format($todayDigital) }}</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Orders Pulse -->
    <div class="col-md-4">
        <div class="card live-card bg-white shadow">
            <div class="card-body p-4 text-center">
                <h5 class="text-uppercase text-muted mb-4" style="font-size: 0.8rem; font-weight: 700;">Order Flow</h5>
                <div class="row align-items-center">
                    <div class="col-4 border-right">
                        <h3 class="mb-0 font-weight-bold" id="total-orders-count">{{ $totalOrders }}</h3>
                        <small class="text-muted text-uppercase" style="font-size: 0.6rem;">Total</small>
                    </div>
                    <div class="col-4 border-right">
                        <h3 class="mb-0 font-weight-bold text-info" id="active-orders-count">{{ $activeOrders }}</h3>
                        <small class="text-muted text-uppercase" style="font-size: 0.6rem;">Live</small>
                    </div>
                    <div class="col-4">
                        <h3 class="mb-0 font-weight-bold text-success" id="served-orders-count">{{ $servedOrders }}</h3>
                        <small class="text-muted text-uppercase" style="font-size: 0.6rem;">Served</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Efficiency / Velocity -->
    <div class="col-md-4">
        <div class="card live-card bg-dark text-white shadow h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Hourly Velocity</h6>
                    <span class="badge badge-info small">Today</span>
                </div>
                <div class="velocity-chart-container">
                    <canvas id="velocityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Live Activity Feed -->
    <div class="col-md-8 mb-4">
        <div class="tile h-100 mb-0 shadow-sm" style="border-radius: 15px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="tile-title mb-0"><i class="fa fa-bolt text-warning mr-2"></i> Live Sales Stream</h3>
                <span class="text-muted small">Latest items appearing instantly</span>
            </div>
            <div class="tile-body" id="live-feed-container" style="max-height: 600px; overflow-y: auto;">
                @include('manager.partials.live_feed_items', ['liveFeed' => $liveFeed])
            </div>
        </div>
    </div>

    <!-- Side Stats: Staff & Top Products -->
    <div class="col-md-4">
        <!-- Staff Performance -->
        <div class="tile shadow-sm mb-4" style="border-radius: 15px;">
            <h3 class="tile-title"><i class="fa fa-users text-primary mr-2"></i> Active Staff Pulse</h3>
            <div class="tile-body">
                <ul class="list-group list-group-flush" id="staff-pulse-container">
                    @include('manager.partials.staff_pulse_items', ['staffPulse' => $staffPulse])
                </ul>
            </div>
        </div>

        <!-- Today's Stars -->
        <div class="tile shadow-sm" style="border-radius: 15px; background: #fdfdfd;">
            <h3 class="tile-title"><i class="fa fa-star text-warning mr-2"></i> Today's Hot Items</h3>
            <div class="tile-body">
                <div class="mb-3">
                    <label class="badge badge-light px-3 py-2 w-100 text-left mb-2" style="font-size: 0.7rem;">TOP DRINKS</label>
                    @foreach($topDrinks as $drink)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                        <span class="text-dark small"><i class="fa fa-glass mr-2 text-muted"></i> {{ $drink->display_name }}</span>
                        <span class="badge badge-pill badge-primary">{{ $drink->total_qty }}</span>
                    </div>
                    @endforeach
                </div>
                <div>
                    <label class="badge badge-light px-3 py-2 w-100 text-left mb-2" style="font-size: 0.7rem;">TOP DISHES</label>
                    @foreach($topFood as $food)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                        <span class="text-dark small"><i class="fa fa-cutlery mr-2 text-muted"></i> {{ $food->food_item_name }}</span>
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
    let velocityChart;
    const refreshInterval = 30000; // 30 seconds
    let lastRefresh = Date.now();

    function initChart() {
        const ctx = document.getElementById('velocityChart').getContext('2d');
        velocityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Orders',
                    data: @json(array_values($hourlyData)),
                    borderColor: '#00d2ff',
                    backgroundColor: 'rgba(0, 210, 255, 0.1)',
                    borderWidth: 2,
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
                    x: { ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 } }, grid: { display: false } },
                    y: { display: false, grid: { display: false } }
                }
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

            // Update Pulse Counts
            document.getElementById('total-orders-count').innerText = data.pulse.total_orders;
            document.getElementById('active-orders-count').innerText = data.pulse.active_orders;
            document.getElementById('served-orders-count').innerText = data.pulse.served_orders;

            // Update Feed & Staff
            document.getElementById('live-feed-container').innerHTML = data.live_feed;
            document.getElementById('staff-pulse-container').innerHTML = data.staff_pulse;

            // Update Chart
            velocityChart.data.datasets[0].data = data.hourly_data;
            velocityChart.update('none');

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
@endsection
