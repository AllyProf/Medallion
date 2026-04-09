@extends('layouts.dashboard')

@section('title', 'Business Trends & Profitability')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-line-chart"></i> Business Trends & Profitability</h1>
    <p>Analyze high-level performance trends and profit margins</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i> Dashboard</li>
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Business Trends</li>
  </ul>
</div>

<!-- Header Controls -->
<div style="background: #fff; padding: 15px 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
    <h2 style="font-size: 20px; font-weight: 700; color: #1a233a; margin: 0;">Financial Analytics</h2>
    <div class="btn-group">
        <a href="?period=7days" class="btn btn-outline-secondary {{ $period == '7days' ? 'active' : '' }}">Last 7 Days</a>
        <a href="?period=30days" class="btn btn-outline-secondary {{ $period == '30days' ? 'active' : '' }}">Last 30 Days</a>
        <a href="?period=month" class="btn btn-outline-secondary {{ $period == 'month' ? 'active' : '' }}">This Month</a>
    </div>
</div>

<div class="row">
  <!-- Trend Line Chart -->
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-area-chart"></i> Daily Financial Performance</h3>
      <div class="tile-body">
        <div style="position: relative; height: 400px; width: 100%;">
          <canvas id="dailyPerformanceChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
   <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-bar-chart"></i> Historical Financial Comparison</h3>
      <div class="tile-body">
        <div style="position: relative; height: 350px; width: 100%;">
          <canvas id="historicalComparisonChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Inject Dynamic PHP arrays
    const dailyPerformanceData = @json($dailyPerformance);
    const totalRevenue = {{ floatval($totalRevenue) }};
    const totalCogs = {{ floatval($totalCogs) }};
    const totalExpenses = {{ floatval($totalExpenses) }};
    const totalNetProfit = {{ floatval($totalNetProfit) }};
    const historicalData = @json($historical);

    // 1. Daily Financial Performance Chart
    const ctxDaily = document.getElementById('dailyPerformanceChart');
    if (ctxDaily && dailyPerformanceData.length > 0) {
        const maxProfitIndex = dailyPerformanceData.reduce((bestIndex, d, i, arr) => d.total_profit > arr[bestIndex].total_profit ? i : bestIndex, 0);

        new Chart(ctxDaily, {
            type: 'line',
            data: {
                labels: dailyPerformanceData.map(d => d.label),
                datasets: [
                    {
                        label: 'Target Profit Goal',
                        data: dailyPerformanceData.map(() => 50000),
                        borderColor: '#ffc107',
                        borderDash: [5, 5],
                        borderWidth: 2,
                        fill: false,
                        pointRadius: 0,
                        type: 'line',
                        order: 1
                    },
                    {
                        label: 'Total Potential (Revenue)',
                        data: dailyPerformanceData.map(d => d.revenue),
                        borderColor: '#adb5bd',
                        borderDash: [3, 3],
                        borderWidth: 1.5,
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        type: 'line',
                        order: 2
                    },
                    {
                        label: 'Drinks Profit',
                        data: dailyPerformanceData.map(d => d.bar_profit),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: dailyPerformanceData.map((d, i) => i === maxProfitIndex ? '#ffd700' : '#fff'),
                        pointBorderColor: '#007bff',
                        pointBorderWidth: 2,
                        pointRadius: dailyPerformanceData.map((d, i) => i === maxProfitIndex ? 7 : 4),
                        pointHoverRadius: 8,
                        type: 'line',
                        order: 4
                    },
                    {
                        label: 'Food Profit',
                        data: dailyPerformanceData.map(d => d.food_profit),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: dailyPerformanceData.map((d, i) => i === maxProfitIndex ? '#ffd700' : '#fff'),
                        pointBorderColor: dailyPerformanceData.map((d, i) => i === maxProfitIndex ? '#ffc107' : '#28a745'),
                        pointBorderWidth: 2,
                        pointRadius: dailyPerformanceData.map((d, i) => i === maxProfitIndex ? 7 : 4),
                        pointHoverRadius: 8,
                        type: 'line',
                        order: 3
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
                            font: { size: 10 }
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
    }



    // 3. Historical Comparison Bar Chart
    const ctxHist = document.getElementById('historicalComparisonChart');
    if (ctxHist && historicalData.length > 0) {
        historicalData.reverse(); // Reverse to read oldest to newest left to right
        new Chart(ctxHist, {
            type: 'bar',
            data: {
                labels: historicalData.map(h => h.label),
                datasets: [
                    {
                        label: 'Expenses Used (TSh)',
                        data: historicalData.map(h => h.expenses),
                        type: 'line',
                        borderColor: '#e53935',
                        backgroundColor: 'rgba(229, 57, 53, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Drinks Profit Generated (TSh)',
                        data: historicalData.map(h => h.bar_profit),
                        backgroundColor: '#1a237e',
                        borderRadius: 4
                    },
                    {
                        label: 'Food Profit Generated (TSh)',
                        data: historicalData.map(h => h.food_profit),
                        backgroundColor: '#00897b',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ctx.dataset.label + ': TSh ' + Math.round(ctx.parsed.y).toLocaleString(); }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
