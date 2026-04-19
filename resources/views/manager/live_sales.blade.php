@extends('layouts.dashboard')

@section('title', 'Live Sales Monitor')

@push('styles')
<style>
    .velocity-chart-container {
        height: 180px;
    }
    .refresh-bar-container {
        position: fixed;
        top: 50px;
        left: 0;
        width: 100%;
        height: 3px;
        z-index: 9999;
        background: transparent;
    }
    #refresh-progress {
        height: 100%;
        background: #940000;
        width: 100%;
        transition: width 1s linear;
    }
    .widget-small .info h4 {
        text-transform: uppercase;
        font-size: 12px;
        margin-bottom: 5px;
        font-weight: 600;
    }
    .widget-small .info p {
        margin-bottom: 0px;
        font-size: 18px;
    }
</style>
@endpush

@section('content')
<div class="refresh-bar-container">
    <div id="refresh-progress"></div>
</div>

<div class="app-title">
    <div>
        <h1><i class="fa fa-bolt"></i> {{ $activeShift ? 'Live Shift Pulse' : 'Daily Sales Monitor' }}</h1>
        <p>
            @if($activeShift)
                Monitoring <strong>Shift #{{ $activeShift->id }}</strong> (Started {{ $activeShift->opened_at->format('H:i') }})
            @else
                Real-time operational pulse for today, {{ now()->format('l, F j') }}
            @endif
        </p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
        <li class="breadcrumb-item"><a href="{{ route('manager.live-sales') }}">Live Monitor</a></li>
        <li class="breadcrumb-item" id="last-updated-text" style="font-weight: bold; color: #940000;">Synced: Just Now</li>
    </ul>
</div>

<div class="row">
    <!-- Revenue Pulse -->
    <div class="col-md-4">
        <div class="widget-small primary coloured-icon">
            <i class="icon fa fa-money fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Revenue' : 'Today Revenue' }}</h4>
                <p><b id="total-revenue-text">TSh {{ number_format($totalRevenue) }}</b></p>
                <small>Cash: <span id="cash-revenue-text">{{ number_format($todayCash) }}</span> | Digital: <span id="digital-revenue-text">{{ number_format($todayDigital) }}</span></small>
            </div>
        </div>
    </div>

    <!-- Active Orders Pulse -->
    <div class="col-md-4">
        <div class="widget-small info coloured-icon">
            <i class="icon fa fa-shopping-cart fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Orders' : 'Today Orders' }}</h4>
                <p>
                    <b id="total-orders-count">{{ $totalOrders }}</b> <small>Total</small> | 
                    <b class="text-primary" id="active-orders-count">{{ $activeOrders }}</b> <small>Live</small>
                </p>
                <small>Served: <span id="served-orders-count">{{ $servedOrders }}</span></small>
            </div>
        </div>
    </div>

    <!-- Velocity Summary -->
    <div class="col-md-4">
        <div class="tile p-2 mb-3" style="min-height: 100px; display: flex; flex-direction: column; justify-content: center;">
            <div class="d-flex justify-content-between align-items-center px-2">
                <h6 class="mb-0 text-muted small font-weight-bold uppercase">{{ $activeShift ? 'SHIFT VELOCITY' : 'HOURLY VELOCITY' }}</h6>
                <span class="badge badge-primary">LIVE</span>
            </div>
            <div class="velocity-chart-container mt-1">
                <canvas id="velocityChart"></canvas>
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
                    borderColor: '#940000',
                    backgroundColor: 'rgba(148, 0, 0, 0.05)',
                    borderWidth: 2,
                    pointRadius: 1,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                scales: {
                    x: { display: false },
                    y: { display: false }
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
@endpush
