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
  <!-- Daily Performance Line Chart (left col) -->
  <div class="col-md-7">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-area-chart"></i> Daily Financial Performance</h3>
      <div class="tile-body">
        <div style="position: relative; height: 400px; width: 100%;">
          <canvas id="dailyPerformanceChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Expense Distribution Pie Chart (right col) -->
  <div class="col-md-5">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-pie-chart"></i> Expense Distribution</h3>
      <div class="tile-body">
        @php $hasExpenses = !empty($expenseByCategory) && array_sum(array_values($expenseByCategory)) > 0; @endphp
        @if($hasExpenses)
        <div style="position: relative; height: 280px; width: 100%;">
          <canvas id="expenseDistributionChart"></canvas>
        </div>
        <div style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;" id="expenseLegend"></div>
        @else
        <div style="text-align: center; padding: 80px 20px; color: #aaa;">
          <i class="fa fa-pie-chart fa-3x" style="margin-bottom: 15px; display: block; color: #ddd;"></i>
          <p style="font-size: 14px;">No expense records for this period.</p>
          <small>Tracked from Petty Cash issues.</small>
        </div>
        @endif
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
                        label: 'Expenses',
                        data: dailyPerformanceData.map(d => d.expenses),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#dc3545',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 8,
                        type: 'line',
                        order: 5
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


    // 2. Expense Distribution Pie Chart
    const expenseData = @json($expenseByCategory);
    const ctxExp = document.getElementById('expenseDistributionChart');
    if (ctxExp && Object.keys(expenseData).length > 0) {
        const labels = Object.keys(expenseData);
        const values = Object.values(expenseData);
        const total  = values.reduce((a, b) => a + b, 0);

        const palette = [
            '#dc3545','#fd7e14','#ffc107','#28a745','#17a2b8',
            '#6610f2','#e83e8c','#20c997','#6f42c1','#007bff'
        ];
        const bgColors = labels.map((_, i) => palette[i % palette.length]);

        new Chart(ctxExp, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors.map(c => c + 'dd'),
                    borderColor: bgColors,
                    borderWidth: 2,
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ` TSh ${Math.round(ctx.parsed).toLocaleString()} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Custom legend
        const legend = document.getElementById('expenseLegend');
        if (legend) {
            labels.forEach((label, i) => {
                const pct = total > 0 ? ((values[i] / total) * 100).toFixed(1) : 0;
                const item = document.createElement('div');
                item.style.cssText = 'display:flex;align-items:center;gap:5px;background:#f8f9fa;padding:4px 10px;border-radius:20px;font-size:11px;';
                item.innerHTML = `<span style="width:10px;height:10px;border-radius:50%;background:${bgColors[i]};display:inline-block;flex-shrink:0;"></span><strong>${label}</strong>: TSh ${Math.round(values[i]).toLocaleString()} <span style="color:#888;">(${pct}%)</span>`;
                legend.appendChild(item);
            });
        }
    }

});
</script>
@endpush
