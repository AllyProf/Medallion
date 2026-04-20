<?php

use App\Models\BarOrder;

$orderNumbers = ['ORD-207', 'ORD-201', 'ORD-199', 'ORD-193', 'ORD-185', 'ORD-183', 'ORD-182', 'ORD-173', 'ORD-169', 'ORD-168'];
$orders = BarOrder::whereIn('order_number', $orderNumbers)->get();

echo "--- ORDER SHIFT ANALYSIS ---\n";
foreach ($orders as $o) {
    echo "  #{$o->order_number} | Shift ID: " . ($o->bar_shift_id ?? 'NULL') . " | Date: " . $o->created_at->format('Y-m-d') . " | Total: " . number_format($o->total_amount) . "\n";
}
