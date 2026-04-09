@extends('layouts.dashboard')

@section('title', 'Comparative Sales Velocity')

@push('styles')
<style>
    .velocity-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 25px;
        margin-bottom: 30px;
    }
    .velocity-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 20px;
        margin-bottom: 20px;
    }
    .velocity-title {
        font-size: 24px;
        font-weight: 700;
        color: #1a233a;
        margin: 0;
    }
    .btn-group-custom .btn {
        background: #fff;
        border: 1px solid #e0e0e0;
        color: #495057;
        font-size: 13px;
        font-weight: 600;
        padding: 6px 12px;
    }
    .btn-group-custom .btn.active {
        color: #c62828;
        border-bottom: 2px solid #c62828;
    }
    
    .section-subtitle {
        font-size: 11px;
        font-weight: 700;
        color: #8792a1;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }
    .section-desc {
        font-size: 13px;
        color: #5c6873;
        margin-bottom: 20px;
    }
    
    .leaderboard-column {
        border-left: 1px solid #f0f0f0;
        padding-left: 25px;
    }
    
    .leaderboard-item {
        margin-bottom: 30px;
    }
    .leaderboard-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: white;
        font-weight: bold;
        font-size: 14px;
        flex-shrink: 0;
    }
    .icon-gold { color: #ffca28; font-size: 20px; }
    .icon-gray { background-color: #607d8b; }
    .icon-red { background-color: #ef5350; }
    
    .leaderboard-name {
        font-size: 15px;
        font-weight: 700;
        color: #212529;
        margin-bottom: 0;
    }
    .leaderboard-orders {
        font-size: 12px;
        color: #8792a1;
    }
    .leaderboard-revenue {
        font-size: 16px;
        font-weight: 700;
        color: #800000;
        text-align: right;
        margin-bottom: 0;
    }
    .leaderboard-share {
        font-size: 12px;
        color: #00c853;
        text-align: right;
    }
    
    .custom-progress-bg {
        height: 6px;
        background: #f0f0f0;
        border-radius: 0;
        margin-top: 8px;
        width: 100%;
        position: relative;
    }
    .custom-progress-fill {
        height: 100%;
        position: absolute;
        left: 0;
        top: 0;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 0;
        color: #8792a1;
    }
    
    @php
        // Fixed rank colors mapped
        $rankColors = [
            0 => ['chart' => '#00a884', 'bg' => 'rgba(0, 168, 132, 0.1)', 'fill' => 'background-color: #4caf50;'],
            1 => ['chart' => '#2196f3', 'bg' => 'transparent', 'fill' => 'background-color: #00bcd4;'],
            2 => ['chart' => '#ff9800', 'bg' => 'transparent', 'fill' => 'background-color: #00bcd4;']
        ];
    @endphp
</style>
@endpush

@section('content')
<div class="velocity-card">
    <div class="velocity-header">
        <h2 class="velocity-title">Comparative Sales Velocity</h2>
        <div class="btn-group btn-group-custom">
            <a href="?period=7days" class="btn {{ $period == '7days' ? 'active' : '' }}">Last 7 Days</a>
            <a href="?period=30days" class="btn {{ $period == '30days' ? 'active' : '' }}">Last 30 Days</a>
            <a href="?period=month" class="btn {{ $period == 'month' ? 'active' : '' }}">This Month</a>
        </div>
    </div>
    
    @if(count($waiterData) == 0)
    <div class="empty-state">
        <i class="fa fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
        <h4>No Waiter Data Found</h4>
        <p>There are no completed orders assigned to waiters within this period.</p>
    </div>
    @else
    <div class="row">
        <!-- Left Side: Chart -->
        <div class="col-md-8 pr-4">
            <div class="section-subtitle">DAILY REVENUE CONTRIBUTION</div>
            <div class="section-desc">Top {{ count($waiterData) }} Performing Staff (Comparative Flow)</div>
            
            <div style="position: relative; height: 400px; width: 100%;">
                <canvas id="waiterVelocityChart"></canvas>
            </div>
        </div>
        
        <!-- Right Side: Leaderboard -->
        <div class="col-md-4 leaderboard-column">
            <div class="text-center mb-4">
                <div class="section-subtitle">EFFICIENCY LEADERBOARD</div>
            </div>
            
            @foreach($waiterData as $index => $w)
            <div class="leaderboard-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        @if($index == 0)
                            <div class="mr-3"><i class="fa fa-trophy icon-gold"></i></div>
                        @elseif($index == 1)
                            <div class="leaderboard-icon icon-gray mr-3">2</div>
                        @else
                            <div class="leaderboard-icon icon-red mr-3">{{ $index + 1 }}</div>
                        @endif
                        
                        <div>
                            <p class="leaderboard-name">{{ $w['name'] }}</p>
                            <span class="leaderboard-orders">{{ number_format($w['orders']) }} distinct orders</span>
                        </div>
                    </div>
                    <div>
                        <p class="leaderboard-revenue">TSh {{ number_format($w['revenue']) }}</p>
                        <div style="font-size: 11px; text-align: right; color: #5c6873; margin-top: 2px;">
                            <span style="color: #1a237e; font-weight: 600;">Drinks: TSh {{ number_format($w['bar_revenue'] ?? 0) }}</span><br>
                            <span style="color: #00897b; font-weight: 600;">Food: TSh {{ number_format($w['food_revenue'] ?? 0) }}</span>
                        </div>
                        <div class="leaderboard-share mt-1" style="font-weight: 600;">{{ $w['share'] }}% Total Share</div>
                    </div>
                </div>
                <div class="custom-progress-bg">
                    <div class="custom-progress-fill" style="width: {{ min(100, $w['share']) }}%; {{ $rankColors[$index % 3]['fill'] }}"></div>
                </div>
            </div>
            @endforeach
            
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Inject dynamic PHP data safely
    const chartLabels = @json($labels);
    const dynamicWaiters = @json($waiterData);
    const rankColors = @json($rankColors);

    // Build the Chart JS dataset array dynamically
    const chartDatasets = dynamicWaiters.map((w, index) => {
        let colorProfile = rankColors[index % 3];
        return {
            label: w.name,
            data: w.daily_flow,
            borderColor: colorProfile.chart,
            backgroundColor: colorProfile.bg,
            borderWidth: 3,
            pointBackgroundColor: colorProfile.chart,
            pointRadius: 6,
            tension: 0.4,
            fill: index === 0 // only fill the top performer's curve
        };
    });

    const ctxVelocity = document.getElementById('waiterVelocityChart');
    if (ctxVelocity && chartDatasets.length > 0) {
        new Chart(ctxVelocity, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: chartDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { 
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { weight: 'bold', size: 12 },
                            color: '#5c6873',
                            padding: 20
                        }
                    },
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
                            callback: function(value) {
                                if(value === 0) return 'TSh 0';
                                return 'TSh ' + (value/1000) + 'k';
                            },
                            color: '#8792a1',
                            font: { size: 11 }
                        },
                        grid: {
                            color: '#f5f5f5',
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            color: '#8792a1',
                            font: { size: 12 }
                        },
                        grid: {
                            display: false,
                            drawBorder: true,
                            borderColor: '#e0e0e0'
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
