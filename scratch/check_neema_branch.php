<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Staff;
use App\Models\BarOrder;

$s = Staff::find(39);
echo "Neema Branch: [" . ($s->location_branch ?? '') . "]\n";

$o = BarOrder::with('table')->find(19);
echo "Order 19 Table Location: [" . ($o->table->location ?? 'N/A') . "]\n";

// Check session values by listing some other orders to see if they have location branches
echo "Recent orders and their table locations:\n";
$recent = BarOrder::with('table')->latest()->limit(5)->get();
foreach($recent as $ro) {
    echo "  - Order ID: {$ro->id} | Table Location: " . ($ro->table->location ?? 'N/A') . "\n";
}
