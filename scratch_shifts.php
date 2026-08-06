<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shifts = \App\Models\BarShift::with(['staff'])
    ->where('created_at', '>=', '2026-05-20 00:00:00')
    ->orderBy('created_at', 'asc')
    ->get();

$data = [];
foreach ($shifts as $shift) {
    // Get orders placed during this shift
    $ordersCount = \App\Models\BarOrder::where('bar_shift_id', $shift->id)->count();
    $totalSales = \App\Models\BarOrder::where('bar_shift_id', $shift->id)->sum('total_amount');
    
    $data[] = [
        'id' => $shift->id,
        'status' => $shift->status,
        'created_at' => $shift->created_at->format('Y-m-d H:i:s'),
        'opened_at' => $shift->opened_at ? $shift->opened_at->format('Y-m-d H:i:s') : null,
        'closed_at' => $shift->closed_at ? $shift->closed_at->format('Y-m-d H:i:s') : null,
        'opening_cash' => $shift->opening_cash,
        'expected_cash' => $shift->expected_cash,
        'actual_cash' => $shift->actual_cash,
        'staff_name' => $shift->staff->full_name ?? 'N/A',
        'orders_count' => $ordersCount,
        'total_sales' => $totalSales,
        'notes' => $shift->notes
    ];
}

echo json_encode($data, JSON_PRETTY_PRINT);
