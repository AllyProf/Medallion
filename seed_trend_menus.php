<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MenuItem;

$reportsMenu = MenuItem::where('slug', 'reports')->first();

if ($reportsMenu) {
    MenuItem::firstOrCreate([
        'slug' => 'business-trends',
    ], [
        'name' => 'Business Trends',
        'parent_id' => $reportsMenu->id,
        'route' => 'reports.business-trends',
        'icon' => 'fa-line-chart',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    MenuItem::firstOrCreate([
        'slug' => 'waiter-trends',
    ], [
        'name' => 'Waiter Trends',
        'parent_id' => $reportsMenu->id,
        'route' => 'reports.waiter-trends',
        'icon' => 'fa-users',
        'sort_order' => 4,
        'is_active' => true,
    ]);
    echo "Menus added successfully.\n";
} else {
    echo "Reports menu not found.\n";
}
